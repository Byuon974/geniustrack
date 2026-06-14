import { Controller } from '@hotwired/stimulus';

/*
 * Selecteur de report (page dediee « Reporter ce creneau »).
 *
 * Reutilise le calendrier mensuel a densites et la liste de creneaux du
 * picker de reservation, mais en mode simplifie : on choisit un NOUVEAU
 * creneau (jour + heure) pour la meme duree, sans machines ni panier. Le
 * creneau choisi alimente le champ cache « nouvelle_date » (format
 * Y-m-d\TH:i, accepte par new DateTimeImmutable cote serveur) et active le
 * bouton de confirmation. Aucune saisie de date au clavier (anti-vandalisme).
 *
 * Cibles : calendrier, moisLabel, jourLabel, zoneCreneaux, champDate,
 *          recap, boutonConfirmer.
 */
export default class extends Controller {
    static targets = [
        'calendrier', 'moisLabel', 'jourLabel', 'zoneCreneaux',
        'champDate', 'recap', 'boutonConfirmer',
    ];

    static values = {
        url: String,
        jourInitial: String,
        duree: Number,
    };

    connect() {
        const base = this.jourInitialValue
            ? this.#parseISO(this.jourInitialValue)
            : new Date();
        this.ancre = new Date(base.getFullYear(), base.getMonth(), 1);
        this.jourSel = null;
        this.densites = {};

        this.#chargerMois().then(() => {
            const now = this.#toISO(new Date());
            const dens = this.densites[now];
            if (dens && dens.etat !== 'indispo' && dens.etat !== 'complet') {
                this.choisirJour(now);
            }
        });
    }

    async #chargerMois() {
        const ym = `${this.ancre.getFullYear()}-${String(this.ancre.getMonth() + 1).padStart(2, '0')}`;
        const params = new URLSearchParams({ fb_mois: ym, fb_duree: this.dureeValue });
        this.calendrierTarget.setAttribute('aria-busy', 'true');
        try {
            const r = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}`);
            }
            this.densites = (await r.json()).jours || {};
        } catch (e) {
            this.densites = {};
        } finally {
            this.calendrierTarget.removeAttribute('aria-busy');
            this.#dessinerCalendrier();
        }
    }

    #dessinerCalendrier() {
        const annee = this.ancre.getFullYear();
        const mois = this.ancre.getMonth();
        const nbJours = new Date(annee, mois + 1, 0).getDate();
        const premierJour = (new Date(annee, mois, 1).getDay() + 6) % 7;
        const moisFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        if (this.hasMoisLabelTarget) {
            this.moisLabelTarget.textContent = `${moisFr[mois]} ${annee}`;
        }

        const aujourdISO = this.#toISO(new Date());
        let html = '<div class="cal-dow"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div><div class="cal-grid">';
        for (let b = 0; b < premierJour; b++) {
            html += '<span></span>';
        }
        for (let d = 1; d <= nbJours; d++) {
            const iso = `${annee}-${String(mois + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dens = this.densites[iso] || { etat: 'indispo', creneauxLibres: 0 };
            const indispo = dens.etat === 'indispo' || dens.etat === 'complet';
            const today = iso === aujourdISO;
            const sel = iso === this.jourSel;
            const cls = ['cal-jour'];
            if (today) cls.push('cal-jour--today');
            if (sel) cls.push('cal-jour--sel');
            const pastille = (dens.etat === 'libre' || dens.etat === 'charge')
                ? `<span class="cal-pt cal-pt--${dens.etat}"></span>`
                : (dens.etat === 'complet' ? '<span class="cal-pt cal-pt--complet"></span>' : '');
            html += `<button type="button" class="${cls.join(' ')}" data-jour="${iso}" ${indispo ? 'disabled' : ''}>`
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

    async choisirJour(iso) {
        this.jourSel = iso;
        this.#dessinerCalendrier();
        if (this.hasJourLabelTarget) {
            this.jourLabelTarget.textContent = this.#libelleJour(iso);
        }
        // Choisir un nouveau jour invalide le creneau precedemment retenu.
        this.champDateTarget.value = '';
        if (this.hasRecapTarget) {
            this.recapTarget.textContent = 'Aucun créneau choisi pour l\'instant.';
        }
        if (this.hasBoutonConfirmerTarget) {
            this.boutonConfirmerTarget.disabled = true;
        }

        const params = new URLSearchParams({ fb_jour: iso, fb_duree: this.dureeValue });
        this.zoneCreneauxTarget.setAttribute('aria-busy', 'true');
        try {
            const r = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}`);
            }
            this.zoneCreneauxTarget.innerHTML = await r.text();
        } catch (e) {
            this.zoneCreneauxTarget.innerHTML = '<p class="cat">Impossible de charger les créneaux. Réessayez.</p>';
        } finally {
            this.zoneCreneauxTarget.removeAttribute('aria-busy');
        }
    }

    choisirCreneau(event) {
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

        this.champDateTarget.value = debut;
        if (this.hasRecapTarget) {
            this.recapTarget.textContent = `${this.#libelleJour(this.jourSel)} · ${bouton.getAttribute('data-heure')}`;
        }
        if (this.hasBoutonConfirmerTarget) {
            this.boutonConfirmerTarget.disabled = false;
        }
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
