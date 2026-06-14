import { Controller } from '@hotwired/stimulus';

/*
 * Graphe de courbes interactif, rendu en SVG sans dépendance.
 *
 * Améliore la lisibilité et la visée (RETEX dataviz) :
 *  - survol croisé : un curseur vertical et une infobulle unique montrent la
 *    valeur de toutes les séries visibles pour le mois pointé (divulgation
 *    progressive : le détail apparaît au survol, sans encombrer par défaut) ;
 *  - zones de survol larges (toute la colonne du mois) pour une visée facile,
 *    indépendante de la taille des points ;
 *  - légende cliquable pour masquer ou afficher une série (cases à cocher) ;
 *  - série de comparaison masquée d'office si elle n'a aucune donnée (on ne
 *    montre pas un visuel vide, qui ferait douter de l'exactitude).
 *
 * Les données sont fournies par le serveur via des attributs data- (aucune
 * logique métier ici, seulement du rendu et de l'interaction).
 *
 * Valeurs :
 *   - series : JSON, liste de { cle, label, couleur, data: number[], visible }
 *   - mois   : JSON, libellés courts de l'axe X
 *   - max    : nombre, borne haute de l'axe Y
 */
export default class extends Controller {
    static targets = ['svg', 'couches', 'curseur', 'tip', 'legende', 'compareSelect'];

    static values = {
        series: Array,
        mois: Array,
        max: Number,
        // Optionnel : { "2025": [..12 valeurs..], "2024": [...] } pour le
        // sélecteur « comparer à ». La clé « actuelle » de séries reste la base.
        comparables: { type: Object, default: {} },
    };

    // Géométrie du tracé (repère SVG, viewBox 0 0 360 140).
    #geo = { x0: 34, x1: 350, y0: 104, yTop: 24 };

    connect() {
        this.series = structuredClone(this.seriesValue);
        // Masque d'office toute série sans aucune donnée non nulle.
        this.series.forEach((s) => {
            const aDesDonnees = Array.isArray(s.data) && s.data.some((v) => v !== null && v !== 0);
            if (!aDesDonnees) {
                s.visible = false;
                s.vide = true;
            }
        });
        this.#construireLegende();
        this.#dessiner();
        this.#brancherSurvol();
    }

    get #pas() {
        const n = this.moisValue.length;
        return n > 1 ? (this.#geo.x1 - this.#geo.x0) / (n - 1) : 0;
    }

    #x(i) {
        return this.#geo.x0 + i * this.#pas;
    }

    #y(v) {
        const { y0, yTop } = this.#geo;
        const max = this.maxValue > 0 ? this.maxValue : 1;
        return y0 - (v / max) * (y0 - yTop);
    }

    #ns(nom, attrs = {}) {
        const el = document.createElementNS('http://www.w3.org/2000/svg', nom);
        for (const [k, v] of Object.entries(attrs)) {
            el.setAttribute(k, v);
        }
        return el;
    }

    #dessiner() {
        this.couchesTarget.innerHTML = '';
        this.series.forEach((s) => {
            if (!s.visible) {
                return;
            }
            const pts = s.data.map((v, i) => `${this.#x(i).toFixed(1)},${this.#y(v).toFixed(1)}`).join(' ');
            this.couchesTarget.appendChild(this.#ns('polyline', {
                fill: 'none', stroke: s.couleur, 'stroke-width': '2.5',
                'stroke-linejoin': 'round', 'stroke-linecap': 'round', points: pts,
            }));
            s.data.forEach((v, i) => {
                this.couchesTarget.appendChild(this.#ns('circle', {
                    cx: this.#x(i), cy: this.#y(v), r: '2.8', fill: s.couleur,
                    class: `pt pt-${s.cle}-${i}`,
                }));
            });
        });
    }

    #construireLegende() {
        if (!this.hasLegendeTarget) {
            return;
        }
        this.legendeTarget.innerHTML = '';
        this.series.forEach((s) => {
            // Une série vide n'a pas d'entrée de légende (rien à activer).
            if (s.vide) {
                return;
            }
            const b = document.createElement('button');
            b.type = 'button';
            b.className = `leg-item ${s.visible ? '' : 'off'}`;
            b.setAttribute('aria-pressed', String(s.visible));
            b.innerHTML = `<span class="leg-pastille" style="background:${s.couleur}"></span>${s.label}`;
            b.addEventListener('click', () => this.#basculerSerie(s.cle, b));
            this.legendeTarget.appendChild(b);
        });
    }

    #basculerSerie(cle, bouton) {
        const s = this.series.find((x) => x.cle === cle);
        if (!s) {
            return;
        }
        s.visible = !s.visible;
        bouton.classList.toggle('off', !s.visible);
        bouton.setAttribute('aria-pressed', String(s.visible));
        this.#dessiner();
    }

    /*
     * Sélecteur « comparer à » : remplace (ou retire) la série de comparaison
     * selon l'année choisie, recalcule la borne Y, redessine et reconstruit la
     * légende. Année vide ou sans données => pas de série de comparaison.
     */
    comparer(event) {
        const annee = event.target.value;
        // Retire l'éventuelle série de comparaison existante.
        this.series = this.series.filter((s) => s.cle !== 'compare');
        const data = annee && this.comparablesValue[annee];
        if (data && data.some((v) => v !== null && v !== 0)) {
            this.series.push({
                cle: 'compare', label: annee, couleur: '#e8702a',
                data, visible: true,
            });
        }
        this.#dessiner();
        this.#construireLegende();
    }

    #brancherSurvol() {
        const { yTop, y0 } = this.#geo;
        const n = this.moisValue.length;
        // Zones de survol larges, une par mois.
        const hits = this.#ns('g');
        for (let i = 0; i < n; i++) {
            const r = this.#ns('rect', {
                x: this.#x(i) - this.#pas / 2, y: yTop - 6,
                width: this.#pas, height: y0 - yTop + 6, class: 'hit',
            });
            r.addEventListener('mouseenter', () => this.#activer(i));
            hits.appendChild(r);
        }
        this.svgTarget.appendChild(hits);
        this.element.addEventListener('mouseleave', () => this.#desactiver());
    }

    #activer(i) {
        if (this.hasCurseurTarget) {
            this.curseurTarget.setAttribute('x1', this.#x(i));
            this.curseurTarget.setAttribute('x2', this.#x(i));
            this.curseurTarget.style.opacity = '1';
        }
        this.element.querySelectorAll('.pt--actif').forEach((p) => p.classList.remove('pt--actif'));

        let html = `<div class="tip__mois">${this.moisValue[i]}</div>`;
        this.series.forEach((s) => {
            if (!s.visible) {
                return;
            }
            const pt = this.element.querySelector(`.pt-${s.cle}-${i}`);
            if (pt) {
                pt.classList.add('pt--actif');
            }
            html += `<div class="tip__ligne"><span class="tip__pastille" style="background:${s.couleur}"></span>${s.label} : <b>${s.data[i]}</b></div>`;
        });

        if (this.hasTipTarget) {
            this.tipTarget.innerHTML = html;
            this.tipTarget.classList.add('on');
            const rect = this.svgTarget.getBoundingClientRect();
            const base = this.element.getBoundingClientRect();
            this.tipTarget.style.left = `${rect.left + (this.#x(i) / 360) * rect.width - base.left}px`;
            this.tipTarget.style.top = `${(this.#geo.yTop / 140) * rect.height}px`;
        }
    }

    #desactiver() {
        if (this.hasCurseurTarget) {
            this.curseurTarget.style.opacity = '0';
        }
        if (this.hasTipTarget) {
            this.tipTarget.classList.remove('on');
        }
        this.element.querySelectorAll('.pt--actif').forEach((p) => p.classList.remove('pt--actif'));
    }
}
