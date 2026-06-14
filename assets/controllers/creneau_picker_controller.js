import { Controller } from '@hotwired/stimulus';

/*
 * Selecteur de creneau multi-machines (page de reservation maison).
 *
 * Parcours sans saisie clavier de date :
 *   1. Un calendrier mensuel inline affiche, par jour, une pastille de densite
 *      (libre / charge / complet) chargee en JSON depuis le serveur. Les jours
 *      passes ou indisponibles sont desactives.
 *   2. L'utilisateur clique un jour -> les creneaux du jour se chargent
 *      (fragment HTML, nombre de machines libres par creneau).
 *   3. Il clique un creneau -> les machines libres s'affichent en cases a
 *      cocher dans le panneau de droite.
 *   4. Il regle le nombre de personnes via un stepper - N + puis ajoute.
 *
 * Cibles :
 *   duree                  : select de duree (global)
 *   calendrier             : conteneur du calendrier mensuel
 *   moisLabel              : libelle du mois courant
 *   jourLabel              : libelle du jour selectionne (au-dessus des creneaux)
 *   zoneCreneaux           : conteneur des creneaux du jour
 *   panneauDroit           : conteneur bascule panier <-> machines
 *   zoneMachines           : conteneur des cases a cocher machines
 *   champDebut, champDuree : champs caches soumis avec le formulaire
 *   champJour              : champ cache du jour choisi (Y-m-d)
 *   champPersonnes         : champ cache du nombre de personnes
 *   valeurPersonnes        : affichage du compteur de personnes
 *   boutonAjouter          : bouton d'ajout
 *   form                   : le formulaire d'ajout
 */
export default class extends Controller {
    static targets = [
        'duree', 'calendrier', 'moisLabel', 'jourLabel',
        'zoneCreneaux', 'zoneMachines',
        'champDebut', 'champDuree', 'champJour', 'champPersonnes',
        'valeurPersonnes', 'boutonAjouter', 'form',
    ];

    static values = {
        url: String,
        jourInitial: String, // 'Y-m-d' : aujourd'hui par defaut
    };

    connect() {
        const base = this.jourInitialValue
            ? this.#parseISO(this.jourInitialValue)
            : new Date();
        this.ancre = new Date(base.getFullYear(), base.getMonth(), 1); // mois affiche
        this.jourSel = null;       // 'Y-m-d' du jour choisi
        this.personnes = 1;
        this.densites = {};        // 'Y-m-d' -> { etat, creneauxLibres }
        this.jourInitial = this.jourInitialValue || this.#toISO(new Date());

        this.#chargerMois().then(() => {
            // Pré-sélectionne le jour initial s'il est réservable, pour afficher
            // d'emblée les créneaux (moins de clics, RETEX réservation).
            const dens = this.densites[this.jourInitial];
            if (dens && dens.etat !== 'indispo' && dens.etat !== 'complet') {
                this.choisirJour(this.jourInitial);
            }
        });
    }

    /* ---- Calendrier ---- */

    async #chargerMois() {
        const ym = `${this.ancre.getFullYear()}-${String(this.ancre.getMonth() + 1).padStart(2, '0')}`;
        const params = new URLSearchParams({
            fb_mois: ym,
            fb_duree: this.#duree(),
        });
        this.calendrierTarget.setAttribute('aria-busy', 'true');
        try {
            const reponse = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!reponse.ok) {
                throw new Error(`HTTP ${reponse.status}`);
            }
            const data = await reponse.json();
            this.densites = data.jours || {};
        } catch (e) {
            this.densites = {};
        } finally {
            this.calendrierTarget.removeAttribute('aria-busy');
            this.#dessinerCalendrier();
        }
    }

    #dessinerCalendrier() {
        const annee = this.ancre.getFullYear();
        const mois = this.ancre.getMonth(); // 0-11
        const nbJours = new Date(annee, mois + 1, 0).getDate();
        // Lundi = 0 ... Dimanche = 6
        const premierJour = (new Date(annee, mois, 1).getDay() + 6) % 7;

        const moisFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        if (this.hasMoisLabelTarget) {
            this.moisLabelTarget.textContent = `${moisFr[mois]} ${annee}`;
        }

        const aujourdISO = this.#toISO(new Date());
        let html = '<div class="cal-dow"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>';
        html += '<div class="cal-grid">';
        for (let b = 0; b < premierJour; b++) {
            html += '<span></span>';
        }
        for (let d = 1; d <= nbJours; d++) {
            const iso = `${annee}-${String(mois + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dens = this.densites[iso] || { etat: 'indispo', creneauxLibres: 0 };
            const indispo = dens.etat === 'indispo' || dens.etat === 'complet';
            const today = iso === aujourdISO;
            const sel = iso === this.jourSel;
            const classes = ['cal-jour'];
            if (today) classes.push('cal-jour--today');
            if (sel) classes.push('cal-jour--sel');
            const titre = indispo
                ? (dens.etat === 'complet' ? 'Complet' : 'Indisponible')
                : `${dens.creneauxLibres} créneau${dens.creneauxLibres > 1 ? 'x' : ''} libre${dens.creneauxLibres > 1 ? 's' : ''}`;
            const pastille = (dens.etat === 'libre' || dens.etat === 'charge')
                ? `<span class="cal-pt cal-pt--${dens.etat}"></span>`
                : (dens.etat === 'complet' ? '<span class="cal-pt cal-pt--complet"></span>' : '');
            html += `<button type="button" class="${classes.join(' ')}" data-jour="${iso}" `
                + `${indispo ? 'disabled' : ''} title="${titre}">`
                + `<span class="cal-num">${d}</span>${pastille}`
                + `<span class="cal-free">${indispo ? '' : dens.creneauxLibres + ' libre' + (dens.creneauxLibres > 1 ? 's' : '')}</span>`
                + '</button>';
        }
        html += '</div>';
        this.calendrierTarget.innerHTML = html;

        this.calendrierTarget.querySelectorAll('.cal-jour:not([disabled])').forEach((b) => {
            b.addEventListener('click', () => this.choisirJour(b.getAttribute('data-jour')));
        });
    }

    moisPrecedent() {
        this.ancre = new Date(this.ancre.getFullYear(), this.ancre.getMonth() - 1, 1);
        this.#chargerMois();
    }

    moisSuivant() {
        this.ancre = new Date(this.ancre.getFullYear(), this.ancre.getMonth() + 1, 1);
        this.#chargerMois();
    }

    aujourdhui() {
        const now = new Date();
        this.ancre = new Date(now.getFullYear(), now.getMonth(), 1);
        this.#chargerMois();
    }

    /* Changement de duree : recalcule densites du mois ET creneaux du jour. */
    changerDuree() {
        this.#chargerMois();
        if (this.jourSel) {
            this.choisirJour(this.jourSel);
        }
    }

    /* ---- Jour -> creneaux ---- */

    async choisirJour(iso) {
        this.jourSel = iso;
        this.#dessinerCalendrier();

        if (this.hasChampJourTarget) {
            this.champJourTarget.value = iso;
        }
        if (this.hasJourLabelTarget) {
            this.jourLabelTarget.textContent = this.#libelleJour(iso);
        }

        // Tout changement de jour invalide le creneau choisi et les machines.
        this.champDebutTarget.value = '';
        this.#afficherPanier();

        const params = new URLSearchParams({
            fb_jour: iso,
            fb_duree: this.#duree(),
        });
        this.zoneCreneauxTarget.setAttribute('aria-busy', 'true');
        try {
            const reponse = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!reponse.ok) {
                throw new Error(`HTTP ${reponse.status}`);
            }
            this.zoneCreneauxTarget.innerHTML = await reponse.text();
        } catch (e) {
            this.zoneCreneauxTarget.innerHTML = '<p class="cat">Impossible de charger les créneaux. Réessayez.</p>';
        } finally {
            this.zoneCreneauxTarget.removeAttribute('aria-busy');
        }
    }

    /* ---- Creneau -> machines (panneau droit) ---- */

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

        this.zoneCreneauxTarget.querySelectorAll('.fb-creneau--choisi')
            .forEach((el) => el.classList.remove('fb-creneau--choisi'));
        bouton.classList.add('fb-creneau--choisi');

        this.champDebutTarget.value = debut;
        this.champDureeTarget.value = this.#duree();

        const params = new URLSearchParams({
            fb_creneau: debut,
            fb_duree: this.#duree(),
        });
        this.#afficherMachines();
        this.zoneMachinesTarget.setAttribute('aria-busy', 'true');
        try {
            const reponse = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!reponse.ok) {
                throw new Error(`HTTP ${reponse.status}`);
            }
            this.zoneMachinesTarget.innerHTML = await reponse.text();
            this.boutonAjouterTarget.hidden = false;
        } catch (e) {
            this.zoneMachinesTarget.innerHTML = '<p class="cat">Impossible de charger les machines. Réessayez.</p>';
            this.boutonAjouterTarget.hidden = true;
        } finally {
            this.zoneMachinesTarget.removeAttribute('aria-busy');
        }
    }

    /* ---- Stepper personnes ---- */

    personnesMoins() {
        if (this.personnes > 1) {
            this.personnes -= 1;
            this.#majPersonnes();
        }
    }

    personnesPlus() {
        if (this.personnes < 15) {
            this.personnes += 1;
            this.#majPersonnes();
        }
    }

    #majPersonnes() {
        if (this.hasValeurPersonnesTarget) {
            this.valeurPersonnesTarget.textContent = this.personnes;
        }
        if (this.hasChampPersonnesTarget) {
            this.champPersonnesTarget.value = this.personnes;
        }
    }

    /* ---- Bascule panneau droit ---- */

    #afficherPanier() {
        // Masque la zone machines et le bouton ; le panier (rendu serveur)
        // reste visible dans le panneau droit.
        this.zoneMachinesTarget.hidden = true;
        this.boutonAjouterTarget.hidden = true;
        const panier = this.element.querySelector('[data-resa-panier]');
        if (panier) {
            panier.hidden = false;
        }
    }

    #afficherMachines() {
        this.zoneMachinesTarget.hidden = false;
        const panier = this.element.querySelector('[data-resa-panier]');
        if (panier) {
            panier.hidden = true;
        }
    }

    /* ---- Utilitaires ---- */

    #duree() {
        return this.hasDureeTarget ? this.dureeTarget.value : '30';
    }

    #parseISO(iso) {
        const [a, m, j] = iso.split('-').map(Number);
        return new Date(a, m - 1, j);
    }

    #toISO(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    #libelleJour(iso) {
        const d = this.#parseISO(iso);
        const jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        const moisFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return `${jours[d.getDay()]} ${d.getDate()} ${moisFr[d.getMonth()]}`;
    }
}
