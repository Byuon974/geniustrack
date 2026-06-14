import { Controller } from '@hotwired/stimulus';

/*
 * Stepper numerique reutilisable : deux boutons « moins » et « plus » encadrant
 * une valeur, pour saisir une petite quantite sans clavier. Pilote un champ reel
 * (souvent masque) qui porte la valeur soumise.
 *
 * Unifie la saisie de petits comptes a travers l'application (quantite d'une
 * demande, nombre de personnes d'un creneau...). RETEX (Nielsen Norman Group,
 * Learn UI Design) : pour un comptage de petites valeurs, le stepper demande
 * moins de manipulations qu'un menu ou qu'une saisie clavier, et un seul appui
 * couvre les cas courants.
 *
 * Cibles :
 *   - champ   : l'input reel (number, souvent masque) qui porte la valeur
 *   - valeur  : l'element d'affichage du compteur
 *
 * Valeurs :
 *   - min  (defaut 1)   borne basse
 *   - max  (defaut 99)  borne haute
 *   - pas  (defaut 1)   increment
 *
 * Les bornes sont reprises de l'input s'il porte min/max, sinon des valeurs.
 */
export default class extends Controller {
    static targets = ['champ', 'valeur'];

    static values = {
        min: { type: Number, default: 1 },
        max: { type: Number, default: 99 },
        pas: { type: Number, default: 1 },
    };

    connect() {
        // L'input reel fait foi : on lit ses bornes et sa valeur de depart.
        if (this.hasChampTarget) {
            const minAttr = this.champTarget.getAttribute('min');
            const maxAttr = this.champTarget.getAttribute('max');
            if (minAttr !== null) {
                this.minValue = Number(minAttr);
            }
            if (maxAttr !== null) {
                this.maxValue = Number(maxAttr);
            }
            const courant = Number(this.champTarget.value);
            this.courant = Number.isFinite(courant) && this.champTarget.value !== '' ? courant : this.minValue;
        } else {
            this.courant = this.minValue;
        }
        this.borner();
        this.refleter();
    }

    moins() {
        this.courant -= this.pasValue;
        this.borner();
        this.refleter();
    }

    plus() {
        this.courant += this.pasValue;
        this.borner();
        this.refleter();
    }

    borner() {
        if (this.courant < this.minValue) {
            this.courant = this.minValue;
        }
        if (this.courant > this.maxValue) {
            this.courant = this.maxValue;
        }
    }

    refleter() {
        if (this.hasValeurTarget) {
            this.valeurTarget.textContent = String(this.courant);
        }
        if (this.hasChampTarget) {
            this.champTarget.value = String(this.courant);
        }
    }
}
