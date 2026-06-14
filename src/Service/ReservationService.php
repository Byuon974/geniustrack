<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\Reservation;
use App\Entity\SessionReservation;
use App\Enum\ProjetStatut;
use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Repository\SessionReservationRepository;
use App\Repository\ReservationRepository;
use App\Service\Exception\ReservationImpossibleException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Couche métier des réservations.
 *
 * Une réservation est une SESSION : un groupe occupe un créneau (date + durée)
 * pour y utiliser une ou plusieurs machines en parallèle. La session porte
 * l'effectif, le type et le statut ; chaque machine occupée est une occupation
 * (App\Entity\Reservation) rattachée à la session.
 *
 * Responsabilités :
 *  - faire respecter la capacité de 15 personnes par créneau (BF_3.9), comptée
 *    une seule fois par session, sans race condition ;
 *  - interdire la réservation d'une machine en maintenance ou hors service
 *    (BF_3.8) ;
 *  - limiter à 4 sessions de RÉALISATION par projet (benchmark) ; la
 *    préparation n'est pas plafonnée ;
 *  - garantir l'atomicité via une transaction et un verrou pessimiste.
 *
 * Le contrôleur ne connaît aucune de ces règles : il délègue ici.
 */
class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SessionReservationRepository $sessions,
        private readonly ReservationRepository $occupations,
    ) {
    }

    /**
     * Crée une session de réservation (un créneau, une à plusieurs machines)
     * pour un projet, en validant toutes les contraintes métier dans une
     * transaction atomique.
     *
     * @param list<Machine> $machines machines à occuper sur ce créneau (au moins une)
     * @throws ReservationImpossibleException si une règle métier est violée
     */
    public function creerSession(
        Projet $projet,
        ReservationType $type,
        \DateTimeImmutable $debut,
        int $nbPersonnes,
        int $dureeMinutes,
        array $machines,
    ): SessionReservation {
        $this->em->getConnection()->beginTransaction();
        try {
            $session = $this->persisterSessionVerrouillee(
                $projet, $type, $debut, $nbPersonnes, $dureeMinutes, $machines
            );
            $this->em->flush();
            $this->em->getConnection()->commit();

            return $session;
        } catch (\Throwable $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Crée plusieurs sessions dans UNE seule transaction atomique : soit toutes
     * les sessions du lot sont créées, soit aucune. Sert le panier, qui peut
     * composer plusieurs créneaux avant confirmation. Un échec sur une session
     * (machine prise, capacité dépassée) annule l'ensemble et rend la main avec
     * un état propre (pas de moitié de panier committée, pas de doublon en cas
     * de reconfirmation).
     *
     * @param list<array{projet: Projet, type: ReservationType, debut: \DateTimeImmutable, nbPersonnes: int, duree: int, machines: list<Machine>}> $lots
     * @return list<SessionReservation>
     * @throws ReservationImpossibleException
     */
    public function creerSessionsLot(array $lots): array
    {
        $this->em->getConnection()->beginTransaction();
        try {
            $creees = [];
            foreach ($lots as $lot) {
                $creees[] = $this->persisterSessionVerrouillee(
                    $lot['projet'], $lot['type'], $lot['debut'],
                    $lot['nbPersonnes'], $lot['duree'], $lot['machines']
                );
            }
            $this->em->flush();
            $this->em->getConnection()->commit();

            return $creees;
        } catch (\Throwable $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Cœur de la création d'une session, SANS gestion de transaction (l'appelant
     * ouvre et valide la transaction, et flushe une fois pour tout le lot).
     * Applique les règles, verrouille chaque machine, vérifie disponibilité et
     * capacité, puis persiste la session et ses occupations.
     *
     * @param list<Machine> $machines
     * @throws ReservationImpossibleException
     */
    private function persisterSessionVerrouillee(
        Projet $projet,
        ReservationType $type,
        \DateTimeImmutable $debut,
        int $nbPersonnes,
        int $dureeMinutes,
        array $machines,
    ): SessionReservation {
        if ([] === $machines) {
            throw new ReservationImpossibleException(
                'Une session doit comporter au moins une machine.'
            );
        }

        // Règle 0 : on ne réserve que sur un projet validé ou en cours (BF_3.3).
        // Portée dans le service (pas seulement le contrôleur) : la règle tient
        // quel que soit le point d'entrée. Défense en profondeur conforme à la
        // doctrine « le métier vit dans les services ».
        if (!\in_array($projet->getStatut(), [ProjetStatut::Valide, ProjetStatut::EnCours], true)) {
            throw new ReservationImpossibleException(
                'Ce projet doit être validé avant de pouvoir réserver un créneau.'
            );
        }

        // Règle 1 : quota de sessions de RÉALISATION par projet. La préparation
        // n'est pas plafonnée (RETEX : aucun système observé ne plafonne un
        // sous-type d'accompagnement ; le quota du benchmark vise les sessions
        // de fabrication). On exclut Annulee ET Reportee : une session
        // abandonnée ne consomme pas de quota. Le comptage porte sur les
        // sessions déjà rattachées au projet (les sessions du même lot le sont
        // par addSession avant flush), donc le quota est respecté à l'échelle
        // du lot.
        if (ReservationType::Realisation === $type) {
            $nbRealisations = $projet->getSessions()->filter(
                static fn (SessionReservation $s): bool => ReservationType::Realisation === $s->getType()
                    && ReservationStatut::Annulee !== $s->getStatut()
                    && ReservationStatut::Reportee !== $s->getStatut()
            )->count();

            if ($nbRealisations >= SessionReservation::MAX_SESSIONS_REALISATION) {
                throw new ReservationImpossibleException(sprintf(
                    'Ce projet a déjà atteint le maximum de %d sessions de réalisation.',
                    SessionReservation::MAX_SESSIONS_REALISATION
                ));
            }
        }

        $fin = $debut->modify(sprintf('+%d minutes', $dureeMinutes));

        // Règle 2 : chaque machine doit être réservable et libre sur le créneau.
        // On verrouille la MACHINE (ligne stable) avant de lire sa disponibilité
        // et la capacité du créneau : sans cela, deux sessions concurrentes sur
        // un créneau vide liraient toutes deux une somme de 0 et dépasseraient
        // la capacité. Le verrou sérialise les écritures par machine.
        foreach ($machines as $machine) {
            if (!$machine->estReservable()) {
                throw new ReservationImpossibleException(
                    sprintf('La machine « %s » est %s et ne peut pas être réservée.',
                        $machine->getNom(),
                        $machine->getEtat()->libelle()
                    )
                );
            }

            $this->em->lock($machine, LockMode::PESSIMISTIC_WRITE);

            if ($this->occupations->machineOccupeeSurCreneau($machine, $debut, $fin)) {
                throw new ReservationImpossibleException(
                    sprintf('La machine « %s » est déjà réservée sur ce créneau.', $machine->getNom())
                );
            }
        }

        // Règle 3 : capacité 15 personnes sur le créneau (BF_3.9), lecture
        // VERROUILLÉE. L'effectif de la session est compté une seule fois, quel
        // que soit le nombre de machines (le même groupe utilise plusieurs
        // machines en parallèle, il n'occupe pas N fois la capacité).
        $dejaPresents = $this->sessions->sommePersonnesSurCreneau($debut, $fin, verrouiller: true);
        if ($dejaPresents + $nbPersonnes > SessionReservation::CAPACITE_MAX_FABLAB) {
            $restant = max(0, SessionReservation::CAPACITE_MAX_FABLAB - $dejaPresents);
            throw new ReservationImpossibleException(sprintf(
                'Capacité dépassée : %d place(s) restante(s) sur ce créneau (max %d).',
                $restant,
                SessionReservation::CAPACITE_MAX_FABLAB
            ));
        }

        $session = (new SessionReservation())
            ->setType($type)
            ->definirCreneau($debut, $dureeMinutes)
            ->setNbPersonnes($nbPersonnes)
            ->setStatut(ReservationStatut::Planifiee);

        // Rattache la session au projet (synchronise la collection, pose le
        // projet) pour que la règle de quota voie les sessions du même lot.
        $projet->addSession($session);

        foreach ($machines as $machine) {
            $occupation = (new Reservation())->setMachine($machine);
            $session->addOccupation($occupation);
            $this->em->persist($occupation);
        }

        $this->em->persist($session);

        return $session;
    }

    /**
     * Reporte une session vers une nouvelle date (BF_3.12).
     *
     * Report = marquer l'ancienne session « reportée » puis en créer une
     * nouvelle (mêmes machines, même type, même effectif, même durée) à la
     * nouvelle date. La création passe par creerSession, donc toutes les règles
     * (capacité, machines libres, quota) s'appliquent à la nouvelle date. Si la
     * création échoue, l'ancienne session est rétablie intacte.
     *
     * Le report agit sur la session ENTIÈRE (toutes ses machines ensemble),
     * conformément au RETEX : ce qui est réservé sous une confirmation unique
     * se déplace en bloc, jamais par fraction.
     *
     * @return array{0: SessionReservation, 1: bool} [nouvelle session, report tardif]
     * @throws ReservationImpossibleException
     */
    public function reporter(SessionReservation $session, \DateTimeImmutable $nouvelleDate): array
    {
        if (ReservationStatut::Planifiee !== $session->getStatut()) {
            throw new ReservationImpossibleException(
                'Cette réservation ne peut pas être reportée (elle est déjà '.$session->getStatut()->value.').'
            );
        }

        // Le retard se calcule sur l'ANCIENNE date (celle qu'on abandonne).
        $delai = $session->getDateDebut()->diff(new \DateTimeImmutable());
        $reportTardif = !$delai->invert && $delai->days < 3;

        // On marque l'ancienne session « reportée » AVANT de créer la nouvelle,
        // pour qu'elle ne compte pas dans le quota pendant la création. En cas
        // d'échec, on restaure son statut.
        $statutInitial = $session->getStatut();
        $session->setStatut(ReservationStatut::Reportee);
        $this->em->flush();

        try {
            $nouvelle = $this->creerSession(
                $session->getProjet(),
                $session->getType(),
                $nouvelleDate,
                $session->getNbPersonnes(),
                $session->getDureeMinutes(),
                $session->getMachines(),
            );
        } catch (\Throwable $e) {
            $session->setStatut($statutInitial);
            $this->em->flush();
            throw $e;
        }

        return [$nouvelle, $reportTardif];
    }

    /**
     * Annule une session entière (toutes ses machines). Renvoie true si
     * l'annulation est tardive (< 3 jours), à traiter en sanction par
     * l'appelant (BF_6.2).
     *
     * @throws ReservationImpossibleException
     */
    public function annuler(SessionReservation $session): bool
    {
        // Garde d'état : seule une session planifiée peut être annulée. Sans
        // cela, annuler une session déjà annulée (double clic, requête rejouée)
        // repasserait par le calcul de délai et pourrait infliger une seconde
        // sanction pour le même créneau. On agit une seule fois.
        if (ReservationStatut::Planifiee !== $session->getStatut()) {
            throw new ReservationImpossibleException(
                'Cette réservation ne peut pas être annulée (elle est déjà '.$session->getStatut()->value.').'
            );
        }

        $session->setStatut(ReservationStatut::Annulee);
        $this->em->flush();

        $delai = $session->getDateDebut()->diff(new \DateTimeImmutable());

        return !$delai->invert && $delai->days < 3; // début dans moins de 3 jours
    }
}
