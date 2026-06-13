import { Controller } from '@hotwired/stimulus';

/*
 * Menu déroulant simple (réglages d'accessibilité dans le header).
 * Ouvre et ferme un panneau, gère la fermeture au clic extérieur et à la touche
 * Échap, et tient à jour aria-expanded pour les lecteurs d'écran.
 */
export default class extends Controller {
    static targets = ['panneau'];

    connect() {
        this.fermerSiClicExterieur = this.fermerSiClicExterieur.bind(this);
        this.fermerSiEchap = this.fermerSiEchap.bind(this);
    }

    basculer(event) {
        const bouton = event.currentTarget;
        const ouvert = !this.panneauTarget.hidden;
        if (ouvert) {
            this.fermer(bouton);
        } else {
            this.ouvrir(bouton);
        }
    }

    ouvrir(bouton) {
        this.panneauTarget.hidden = false;
        bouton.setAttribute('aria-expanded', 'true');
        document.addEventListener('click', this.fermerSiClicExterieur);
        document.addEventListener('keydown', this.fermerSiEchap);
    }

    fermer(bouton) {
        this.panneauTarget.hidden = true;
        if (bouton) {
            bouton.setAttribute('aria-expanded', 'false');
        } else {
            const b = this.element.querySelector('[aria-haspopup]');
            if (b) {
                b.setAttribute('aria-expanded', 'false');
            }
        }
        document.removeEventListener('click', this.fermerSiClicExterieur);
        document.removeEventListener('keydown', this.fermerSiEchap);
    }

    fermerSiClicExterieur(event) {
        if (!this.element.contains(event.target)) {
            this.fermer(null);
        }
    }

    fermerSiEchap(event) {
        if (event.key === 'Escape') {
            this.fermer(null);
        }
    }

    disconnect() {
        document.removeEventListener('click', this.fermerSiClicExterieur);
        document.removeEventListener('keydown', this.fermerSiEchap);
    }
}
