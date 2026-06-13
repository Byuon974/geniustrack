import { Controller } from '@hotwired/stimulus';

/*
 * Mode lisibilité (BNF_6.3, accessibilité).
 *
 * Dispositif fondé sur les leviers PROUVÉS par la recherche (espacement accru,
 * contraste renforcé, largeur de ligne réduite) plutôt que sur la seule police
 * OpenDyslexic, dont l'efficacité est contestée (RETEX : résultats contradictoires,
 * parfois défavorables). OpenDyslexic reste proposée en OPTION distincte, car
 * certains utilisateurs la préfèrent subjectivement.
 *
 * Deux réglages indépendants, persistés (préférence utilisateur durable) :
 *   - mode lisibilité   → classe .a11y-lisible sur <html>
 *   - police dyslexie   → classe .a11y-dyslexie sur <html>
 */
export default class extends Controller {
    static targets = ['toggleLisible', 'toggleDyslexie'];

    connect() {
        this.appliquer('a11y-lisible', this.lire('a11y-lisible'));
        this.appliquer('a11y-dyslexie', this.lire('a11y-dyslexie'));
        this.syncBoutons();
    }

    basculerLisible() {
        this.basculer('a11y-lisible');
    }

    basculerDyslexie() {
        this.basculer('a11y-dyslexie');
    }

    basculer(classe) {
        const actif = !document.documentElement.classList.contains(classe);
        this.appliquer(classe, actif);
        this.ecrire(classe, actif);
        this.syncBoutons();
    }

    appliquer(classe, actif) {
        document.documentElement.classList.toggle(classe, actif);
    }

    syncBoutons() {
        if (this.hasToggleLisibleTarget) {
            this.toggleLisibleTarget.setAttribute('aria-pressed',
                document.documentElement.classList.contains('a11y-lisible'));
        }
        if (this.hasToggleDyslexieTarget) {
            this.toggleDyslexieTarget.setAttribute('aria-pressed',
                document.documentElement.classList.contains('a11y-dyslexie'));
        }
    }

    // Persistance de la préférence (vraie app : localStorage légitime).
    lire(clef) {
        try { return localStorage.getItem(clef) === '1'; } catch { return false; }
    }
    ecrire(clef, actif) {
        try { localStorage.setItem(clef, actif ? '1' : '0'); } catch { /* indisponible */ }
    }
}
