<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Projet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Centralise les notifications (BF_3.6, BF_4.4).
 *
 * Double canal : un EMAIL (asynchrone via Messenger) informe tout de suite, et
 * une NOTIFICATION in-app persistée garde une trace consultable dans le centre
 * de notifications, avec un compteur de non-lues. Un seul endroit sait formuler
 * et router : les controllers et le workflow appellent ces méthodes.
 *
 * La notification in-app n'est créée que lorsque le destinataire est un User
 * identifié (elle a besoin d'un compte pour s'afficher). L'alerte stock, qui
 * vise une simple adresse d'admin, reste un email seul.
 */
class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly string $expediteur = 'noreply@geniuslab.cci.re',
    ) {
    }

    /**
     * BF_3.6 : notifie l'étudiant d'un changement de statut de son projet.
     */
    public function statutProjet(Projet $projet): void
    {
        $etudiant = $projet->getEtudiant();

        $email = (new TemplatedEmail())
            ->from($this->expediteur)
            ->to($etudiant->getEmail())
            ->subject(sprintf('Votre projet « %s » : %s', $projet->getTitre(), $projet->getStatut()->libelle()))
            ->htmlTemplate('emails/statut_projet.html.twig')
            ->context(['projet' => $projet]);
        $this->mailer->send($email);

        $this->creer(
            $etudiant,
            'projet',
            sprintf('Votre projet « %s » est maintenant : %s.', $projet->getTitre(), $projet->getStatut()->libelle()),
            $projet->getId() ? '/projets/'.$projet->getId() : null,
        );
    }

    /**
     * Notifie le valideur (formateur/BDE) d'une nouvelle demande à traiter.
     */
    public function nouvelleDemande(Projet $projet, User $valideur): void
    {
        $email = (new TemplatedEmail())
            ->from($this->expediteur)
            ->to($valideur->getEmail())
            ->subject(sprintf('Nouvelle demande à valider : %s', $projet->getTitre()))
            ->htmlTemplate('emails/nouvelle_demande.html.twig')
            ->context(['projet' => $projet]);
        $this->mailer->send($email);

        $this->creer(
            $valideur,
            'projet',
            sprintf('Nouvelle demande à valider : « %s ».', $projet->getTitre()),
            $projet->getId() ? '/projets/'.$projet->getId() : null,
        );
    }

    /**
     * BF_4.4 : alerte l'admin qu'un article est sous le seuil.
     *
     * Email seul : le destinataire est une adresse d'admin, pas forcément un
     * compte identifié pour une notification in-app.
     *
     * @param string[] $articles noms des articles concernés
     */
    public function stockBas(string $emailAdmin, array $articles): void
    {
        $email = (new TemplatedEmail())
            ->from($this->expediteur)
            ->to($emailAdmin)
            ->subject('Alerte stock bas : GeniusLab')
            ->htmlTemplate('emails/stock_bas.html.twig')
            ->context(['articles' => $articles]);

        $this->mailer->send($email);
    }

    /**
     * Crée et persiste une notification in-app. Le flush appartient à l'appelant
     * (workflow/controller) qui flushe déjà sa transaction ; ici on persiste
     * pour rester groupé, mais on flushe par sécurité si rien d'autre ne le fait.
     */
    private function creer(User $destinataire, string $type, string $message, ?string $lien): void
    {
        $notification = new Notification($destinataire, $type, $message, $lien);
        $this->em->persist($notification);
        $this->em->flush();
    }
}
