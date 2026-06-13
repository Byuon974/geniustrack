import { Controller } from '@hotwired/stimulus';

/*
 * Filtre la galerie de projets par catégorie de machine, côté client,
 * sans rechargement de page (les cartes sont déjà dans le DOM).
 * Préféré à un aller-retour serveur ici : la galerie est petite et statique.
 *
 * Accessibilité : on met à jour aria-pressed sur les chips et on masque
 * les cartes via l'attribut hidden (lu par les lecteurs d'écran).
 */
export default class extends Controller {
    static targets = ['chip', 'item'];

    filter(event) {
        const categorie = event.currentTarget.dataset.categorie;

        // État visuel + ARIA des chips.
        this.chipTargets.forEach((chip) => {
            const actif = chip === event.currentTarget;
            chip.classList.toggle('is-active', actif);
            chip.setAttribute('aria-pressed', actif ? 'true' : 'false');
        });

        // Affichage des cartes.
        this.itemTargets.forEach((item) => {
            const cats = (item.dataset.categories || '').split(' ');
            const visible = categorie === 'tous' || cats.includes(categorie);
            item.hidden = !visible;
        });
    }
}
