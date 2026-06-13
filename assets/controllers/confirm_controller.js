import { Controller } from '@hotwired/stimulus';

/*
 * Demande confirmation avant la soumission d'un formulaire sensible, via une
 * modale maison (élément <dialog> natif : accessible, gère le focus et Échap),
 * stylée selon le design system et entièrement en français.
 *
 * Usage minimal :
 *   data-controller="confirm" data-confirm-message-value="Confirmer ?"
 *
 * Options :
 *   data-confirm-variante-value="succes|danger|neutre"  (couleur du bouton, défaut neutre)
 *   data-confirm-libelle-value="Valider"                 (libellé du bouton, défaut « Confirmer »)
 *   data-confirm-titre-value="Valider ce projet"         (titre de la modale, optionnel)
 *
 * Remplace window.confirm() : plus de popup système en anglais, hors charte.
 */
export default class extends Controller {
    static values = {
        message: String,
        variante: { type: String, default: 'neutre' },
        libelle: { type: String, default: 'Confirmer' },
        titre: { type: String, default: '' },
    };

    connect() {
        this.confirme = false;
        this.element.addEventListener('submit', this.onSubmit.bind(this));
    }

    onSubmit(event) {
        if (this.confirme) {
            this.confirme = false;
            return;
        }
        event.preventDefault();
        this.ouvrir();
    }

    ouvrir() {
        const dialog = document.createElement('dialog');
        dialog.className = 'modale-confirm';

        const classeBouton = {
            succes: 'btn btn--success',
            danger: 'btn btn--danger',
            neutre: 'btn btn--primary',
        }[this.varianteValue] || 'btn btn--primary';

        const titre = this.titreValue
            ? `<p class="modale-confirm__titre">${this.echapper(this.titreValue)}</p>`
            : '';

        dialog.innerHTML = `
            ${titre}
            <p class="modale-confirm__message">${this.echapper(this.messageValue || 'Confirmer cette action ?')}</p>
            <div class="modale-confirm__actions">
                <button type="button" class="btn btn--ghost" data-role="annuler">Annuler</button>
                <button type="button" class="${classeBouton}" data-role="confirmer">${this.echapper(this.libelleValue)}</button>
            </div>`;

        document.body.appendChild(dialog);
        dialog.showModal();

        const fermer = () => {
            dialog.close();
            dialog.remove();
        };

        dialog.querySelector('[data-role="annuler"]').addEventListener('click', fermer);
        dialog.querySelector('[data-role="confirmer"]').addEventListener('click', () => {
            fermer();
            this.confirme = true;
            if (this.element.requestSubmit) {
                this.element.requestSubmit();
            } else {
                this.element.submit();
            }
        });
        // Clic sur le fond (backdrop) ou touche Échap : on annule proprement.
        dialog.addEventListener('cancel', (e) => { e.preventDefault(); fermer(); });
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                fermer();
            }
        });
    }

    echapper(texte) {
        const div = document.createElement('div');
        div.textContent = texte;
        return div.innerHTML;
    }
}
