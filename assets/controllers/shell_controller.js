import { Controller } from '@hotwired/stimulus';

/*
 * Pilote la sidebar.
 *  - En mobile (sous le point de rupture) : ouverture/fermeture en panneau
 *    coulissant, avec overlay et blocage du défilement du fond.
 *  - En desktop : repli de la sidebar (réduction), pour libérer de l'espace sur
 *    les écrans denses. L'état est mémorisé dans un cookie pour persister d'une
 *    page à l'autre (pas de localStorage, indisponible ici).
 */
export default class extends Controller {
    static targets = ['sidebar', 'overlay'];

    connect() {
        // Restaure l'état de repli desktop depuis le cookie.
        if (this.lireCookie('sidebar_repliee') === '1') {
            this.element.classList.add('sidebar-repliee');
        }
    }

    toggleSidebar() {
        // En desktop : on replie/déplie. En mobile : on ouvre/ferme le panneau.
        if (window.matchMedia('(min-width: 861px)').matches) {
            const repliee = this.element.classList.toggle('sidebar-repliee');
            this.ecrireCookie('sidebar_repliee', repliee ? '1' : '0');
        } else {
            const ouverte = this.sidebarTarget.classList.toggle('is-open');
            this.overlayTarget.classList.toggle('is-visible', ouverte);
            document.body.classList.toggle('no-scroll', ouverte);
        }
    }

    closeSidebar() {
        this.sidebarTarget.classList.remove('is-open');
        this.overlayTarget.classList.remove('is-visible');
        document.body.classList.remove('no-scroll');
    }

    lireCookie(nom) {
        const trouve = document.cookie.split('; ').find((c) => c.startsWith(nom + '='));
        return trouve ? trouve.split('=')[1] : null;
    }

    ecrireCookie(nom, valeur) {
        // Cookie de session (un an), limité au site, sans donnée sensible.
        document.cookie = `${nom}=${valeur}; path=/; max-age=31536000; SameSite=Lax`;
    }
}
