<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\Reservation;
use App\Enum\ProjetStatut;
use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Repository\ReservationRepository;
use App\Service\Exception\ReservationImpossibleException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Couche métier des réservations.
 *
 * Responsabilités :
 *  - faire respecter la capacité de 15 personnes (BF_3.9) sans race condition ;
 *  - interdire la réservation d'une machine en maintenance/HS (BF_3.8) ;
 *  - limiter à 4 sessions de réalisation par projet (benchmark) ;
 *  - garantir l'atomicité via une transaction + verrou pessimiste.
 *
 * Le controller ne connaît AUCUNE de ces règles : il délègue ici.
 */
class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservations,
    ) {
    }

    /**
     * Crée une session de réservation pour un projet, en validant toutes les
     * contraintes métier dans une transaction atomique.
     *
     * @throws ReservationImpossibleException si une règle métier est violée
     */
    public function creerSession(
        Projet $projet,
        Machine $machine,
        ReservationType $type,
        \DateTimeImmutable $debut,
        int $nbPersonnes,
        ?int $dureeMinutes = null,
    ): Reservation {
        // Règle 0 : on ne réserve que sur un projet validé ou en cours (BF_3.3).
        // Cette garde est portée ici, dans le service, et non plus seulement dans
        // le contrôleur : la règle métier doit tenir quel que soit le point
        // d'entrée (wizard, import, commande, futur appel), pas seulement par
        // l'écran. Défense en profondeur conforme à la doctrine « le métier vit
        // dans les services ».
        if (!\in_array($projet->getStatut(), [ProjetStatut::Valide, ProjetStatut::EnCours], true)) {
            throw new ReservationImpossibleException(
                'Ce projet doit être validé avant de pouvoir réserver un créneau.'
            );
        }

        // Règle 1 (hors transaction, vérif rapide) : machine réservable.
        if (!$machine->estReservable()) {
            throw new ReservationImpossibleException(
                sprintf('La machine « %s » est %s et ne peut pas être réservée.',
                    $machine->getNom(),
                    $machine->getEtat()->libelle()
                )
            );
        }

        // Règle 2 : quota de sessions de réalisation par projet.
        // On exclut Annulee ET Reportee : un créneau abandonné ne consomme pas
        // de session (sinon un report ferait dépasser le quota à tort).
        if ($type === ReservationType::Realisation) {
            $nbRealisations = $projet->getReservations()->filter(
                fn (Reservation $r) => $r->getType() === ReservationType::Realisation
                    && $r->getStatut() !== ReservationStatut::Annulee
                    && $r->getStatut() !== ReservationStatut::Reportee
            )->count();

            if ($nbRealisations >= Projet::MAX_SESSIONS_REALISATION) {
                throw new ReservationImpossibleException(sprintf(
                    'Ce projet a déjà atteint le maximum de %d sessions de réalisation.',
                    Projet::MAX_SESSIONS_REALISATION
                ));
            }
        }

        // Durée : celle choisie pour la session (créneau à durée variable), ou
        // à défaut la durée propre à la machine (compatibilité ascendante).
        $duree = $dureeMinutes ?? $machine->getDureeCreneauMinutes();
        $fin = $debut->modify(sprintf('+%d minutes', $duree));

        // Transaction atomique : verrou pessimiste sur la lecture de capacité,
        // puis insertion. Aucune réservation concurrente ne peut s'intercaler.
        $this->em->getConnection()->beginTransaction();
        try {
            // Verrou de sérialisation : on verrouille la MACHINE (ligne qui
            // existe toujours) avant de lire la capacité. Sans cela, deux
            // réservations simultanées sur un créneau VIDE liraient toutes deux
            // une somme de 0 (le FOR UPDATE sur un agrégat sans ligne ne
            // verrouille rien) et dépasseraient la capacité. En verrouillant la
            // machine, les transactions concurrentes sur ce créneau sont mises
            // en file et évaluent la capacité l'une après l'autre.
            $this->em->lock($machine, LockMode::PESSIMISTIC_WRITE);

            // Règle 3 : machine pas déjà occupée sur ce créneau.
            if ($this->reservations->machineOccupeeSurCreneau($machine, $debut, $fin)) {
                throw new ReservationImpossibleException(
                    'Cette machine est déjà réservée sur ce créneau.'
                );
            }

            // Règle 4 : capacité 15 personnes (BF_3.9), lecture VERROUILLÉE.
            $dejaPresents = $this->reservations->sommePersonnesSurCreneau($debut, $fin, verrouiller: true);
            if ($dejaPresents + $nbPersonnes > Reservation::CAPACITE_MAX_FABLAB) {
                $restant = max(0, Reservation::CAPACITE_MAX_FABLAB - $dejaPresents);
                throw new ReservationImpossibleException(sprintf(
                    'Capacité dépassée : %d place(s) restante(s) sur ce créneau (max %d).',
                    $restant,
                    Reservation::CAPACITE_MAX_FABLAB
                ));
            }

            $reservation = (new Reservation())
                ->setProjet($projet)
                ->setMachine($machine)
                ->setType($type)
                ->definirCreneau($debut, $duree)
                ->setNbPersonnesPrevues($nbPersonnes)
                ->setStatut(ReservationStatut::Planifiee);

            $this->em->persist($reservation);
            $this->em->flush();
            $this->em->getConnection()->commit();

            return $reservation;
        } catch (\Throwable $e) {
            $this->em->getConnection()->rollBack();
            throw $e;
        }
    }

    /**
     * Reporte une session vers une nouvelle date (BF_3.12).
     *
     * Le report = marquer l'ancien créneau « reporté » + créer un nouveau créneau
     * (même machine, même type, même effectif) à la nouvelle date. La création
     * passe par creerSession, donc toutes les règles (capacité, machine libre,
     * quota) s'appliquent à la nouvelle date. Si la création échoue, l'ancien
     * créneau reste intact (on ne le marque reporté qu'après succès).
     *
     * Renvoie le nouveau créneau. Le second retour (via le booléen) indique si
     * le report est tardif (< 3 jours), à traiter en sanction par l'appelant.
     *
     * @return array{0: Reservation, 1: bool} [nouveau créneau, report tardif]
     * @throws ReservationImpossibleException
     */
    public function reporter(Reservation $reservation, \DateTimeImmutable $nouvelleDate): array
    {
        // Garde d'état : seule une réservation planifiée peut être reportée.
        // Même raison que pour l'annulation : éviter d'agir deux fois sur un
        // créneau déjà clos (annulé, effectué) ou déjà reporté.
        if (ReservationStatut::Planifiee !== $reservation->getStatut()) {
            throw new ReservationImpossibleException(
                'Cette réservation ne peut pas être reportée (elle est déjà '.$reservation->getStatut()->value.').'
            );
        }

        // Le retard se calcule sur l'ANCIENNE date (celle qu'on abandonne tardivement).
        $delai = $reservation->getDateDebut()->diff(new \DateTimeImmutable());
        $reportTardif = !$delai->invert && $delai->days < 3;

        // On marque l'ancien créneau « reporté » AVANT de créer le nouveau, pour
        // qu'il ne soit pas compté dans le quota de réalisations pendant la
        // création. En cas d'échec, on restaure son statut (rien ne change).
        $statutInitial = $reservation->getStatut();
        $reservation->setStatut(ReservationStatut::Reportee);
        $this->em->flush();

        try {
            $nouveau = $this->creerSession(
                $reservation->getProjet(),
                $reservation->getMachine(),
                $reservation->getType(),
                $nouvelleDate,
                $reservation->getNbPersonnesPrevues(),
            );
        } catch (\Throwable $e) {
            // Échec : on rétablit l'ancien créneau tel qu'il était.
            $reservation->setStatut($statutInitial);
            $this->em->flush();
            throw $e;
        }

        return [$nouveau, $reportTardif];
    }

    /**
     * Annule une session. Renvoie true si l'annulation est tardive (< 3 jours),
     * à traiter en sanction par l'appelant (BF_6.2).
     */
    public function annuler(Reservation $reservation): bool
    {
        // Garde d'état : seule une réservation planifiée peut être annulée.
        // Sans cette vérification, annuler une réservation déjà annulée (double
        // clic, requête rejouée) repasserait par le calcul de délai et pourrait
        // infliger une seconde sanction pour le même créneau. On agit donc une
        // seule fois, depuis le seul état où l'annulation a un sens.
        if (ReservationStatut::Planifiee !== $reservation->getStatut()) {
            throw new ReservationImpossibleException(
                'Cette réservation ne peut pas être annulée (elle est déjà '.$reservation->getStatut()->value.').'
            );
        }

        $reservation->setStatut(ReservationStatut::Annulee);
        $this->em->flush();

        $delai = $reservation->getDateDebut()->diff(new \DateTimeImmutable());

        return !$delai->invert && $delai->days < 3; // début dans moins de 3 jours
    }
}
