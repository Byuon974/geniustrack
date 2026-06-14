import { Controller } from '@hotwired/stimulus';

/*
 * Rend un element (typiquement une alerte / un bandeau flash) fermable :
 * un clic sur le bouton de fermeture retire l'element du flux. Utilise pour
 * les messages de succes qui n'ont pas besoin de rester affiches en
 * permanence (RETEX : un bandeau de confirmation doit pouvoir etre ecarte).
 */
export default class extends Controller {
    fermer() {
        this.element.remove();
    }
}
