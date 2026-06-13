import { Controller } from '@hotwired/stimulus';

/*
 * Révèle un formulaire masqué (le motif de refus) et masque les boutons
 * d'action pendant la saisie, puis restaure l'état au clic sur Annuler.
 * Pattern de file de validation : le champ motif n'apparaît qu'au moment du
 * refus, pour garder la ligne dense le reste du temps.
 */
export default class extends Controller {
    static targets = ['zone', 'actions'];

    ouvrir() {
        this.zoneTarget.hidden = false;
        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = true;
        }
        const champ = this.zoneTarget.querySelector('input[type="text"]');
        if (champ) {
            champ.focus();
        }
    }

    fermer() {
        this.zoneTarget.hidden = true;
        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = false;
        }
    }
}
