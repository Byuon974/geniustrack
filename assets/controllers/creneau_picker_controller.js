import { Controller } from '@hotwired/stimulus';

/*
 * Selecteur de creneau multi-machines (page de reservation maison).
 *
 * Parcours : l'utilisateur choisit un jour et une duree -> les creneaux du jour
 * se chargent (nombre de machines libres par creneau). Il clique un creneau ->
 * les machines libres de ce creneau s'affichent en cases a cocher. Il coche une
 * ou plusieurs machines et ajoute au panier. Aucune saisie de date au clavier.
 *
 * Cibles :
 *   jour, duree            : les selects de filtre
 *   zoneCreneaux           : conteneur des pastilles de creneaux
 *   zoneMachines           : conteneur des cases a cocher machines
 *   champDebut, champDuree : champs caches soumis avec le formulaire
 *   boutonAjouter          : bouton d'ajout, masque tant qu'aucun creneau choisi
 *   form                   : le formulaire d'ajout
 */
export default class extends Controller {
    static targets = [
        'jour', 'duree', 'zoneCreneaux', 'zoneMachines',
        'champDebut', 'champDuree', 'boutonAjouter', 'form',
    ];

    static values = { url: String };

    connect() {
        this.rafraichirCreneaux();
    }

    /* Charge les creneaux du jour pour la duree choisie. */
    async rafraichirCreneaux() {
        const params = new URLSearchParams({
            fb_jour: this.hasJourTarget ? this.jourTarget.value : '',
            fb_duree: this.hasDureeTarget ? this.dureeTarget.value : '30',
        });

        // Tout changement de jour/duree invalide le creneau et les machines.
        this.champDebutTarget.value = '';
        this.zoneMachinesTarget.hidden = true;
        this.boutonAjouterTarget.hidden = true;

        this.zoneCreneauxTarget.setAttribute('aria-busy', 'true');
        try {
            const reponse = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            this.zoneCreneauxTarget.innerHTML = await reponse.text();
        } catch (e) {
            this.zoneCreneauxTarget.innerHTML = '<p class="cat">Impossible de charger les creneaux. Reessayez.</p>';
        } finally {
            this.zoneCreneauxTarget.removeAttribute('aria-busy');
        }
    }

    /* Clic sur un creneau libre : marque le choix et charge ses machines. */
    async choisirCreneau(event) {
        const bouton = event.target.closest('.fb-creneau--libre, .fb-creneau--occupe');
        if (!bouton) {
            return;
        }
        event.preventDefault();

        const debut = bouton.getAttribute('data-creneau');
        if (!debut) {
            return;
        }

        // Marque visuellement le creneau choisi.
        this.zoneCreneauxTarget.querySelectorAll('.fb-creneau--choisi')
            .forEach((el) => el.classList.remove('fb-creneau--choisi'));
        bouton.classList.add('fb-creneau--choisi');

        this.champDebutTarget.value = debut;
        this.champDureeTarget.value = this.hasDureeTarget ? this.dureeTarget.value : '30';

        // Charge les machines libres de ce creneau.
        const params = new URLSearchParams({
            fb_creneau: debut,
            fb_duree: this.hasDureeTarget ? this.dureeTarget.value : '30',
        });
        this.zoneMachinesTarget.hidden = false;
        this.zoneMachinesTarget.setAttribute('aria-busy', 'true');
        try {
            const reponse = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            this.zoneMachinesTarget.innerHTML = await reponse.text();
            this.boutonAjouterTarget.hidden = false;
        } catch (e) {
            this.zoneMachinesTarget.innerHTML = '<p class="cat">Impossible de charger les machines. Reessayez.</p>';
        } finally {
            this.zoneMachinesTarget.removeAttribute('aria-busy');
        }
    }
}
