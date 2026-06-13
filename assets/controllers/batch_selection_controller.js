import { Controller } from '@hotwired/stimulus';

/*
 * Sélection multiple et actions groupées sur une liste.
 *
 * Gère les cases à cocher par ligne, la case « tout sélectionner », et révèle
 * une barre d'actions tant qu'au moins une ligne est cochée. Les actions
 * elles-mêmes sont des formulaires classiques (POST) : ce contrôleur ne fait
 * que la sélection et la mise à jour des champs cachés qui portent les ids.
 *
 * Volontairement générique : réutilisable pour toute liste à actions groupées,
 * pas seulement les utilisateurs.
 */
export default class extends Controller {
    static targets = ['case', 'toutCocher', 'barre', 'compteur', 'ids'];

    connect() {
        this.rafraichir();
    }

    // Case « tout sélectionner » : propage son état à toutes les lignes visibles.
    basculerTout() {
        const coche = this.toutCocherTarget.checked;
        this.caseTargets.forEach((c) => {
            // On ne coche que les lignes visibles (compatibilité filtre éventuel).
            if (c.closest('tr')?.style.display !== 'none') {
                c.checked = coche;
            }
        });
        this.rafraichir();
    }

    // Une case de ligne a changé.
    basculerUn() {
        this.rafraichir();
    }

    // Met à jour le compteur, la visibilité de la barre et les ids transmis.
    rafraichir() {
        const cochees = this.caseTargets.filter((c) => c.checked);
        const ids = cochees.map((c) => c.value);

        if (this.hasCompteurTarget) {
            this.compteurTarget.textContent = ids.length;
        }
        if (this.hasBarreTarget) {
            this.barreTarget.hidden = ids.length === 0;
        }
        // Tous les champs cachés « ids » reçoivent la liste, séparée par des virgules.
        this.idsTargets.forEach((champ) => {
            champ.value = ids.join(',');
        });
        // État indéterminé de la case maîtresse si sélection partielle.
        if (this.hasToutCocherTarget) {
            const total = this.caseTargets.length;
            this.toutCocherTarget.indeterminate = ids.length > 0 && ids.length < total;
            this.toutCocherTarget.checked = ids.length === total && total > 0;
        }
    }
}
