import { Controller } from '@hotwired/stimulus';

/*
 * Tableau de données enrichi, entièrement côté client (le jeu de données stock
 * est petit, une centaine de lignes au plus). Quatre fonctions :
 *  - recherche plein texte instantanée (champ en haut, jamais caché) ;
 *  - filtres rapides par catégorie (chips, résultat immédiat sans bouton) ;
 *  - tri par colonne au clic sur l'en-tête, indicateur visuel asc/desc ;
 *  - pagination client avec taille réglable et indicateur du total.
 *
 * Les données viennent des attributs data-* posés sur chaque ligne par Twig.
 * Aucun appel réseau : tout se fait sur les lignes déjà rendues.
 */
export default class extends Controller {
    static targets = ['row', 'search', 'chip', 'pageInfo', 'pageSize', 'pager', 'empty', 'header'];

    static values = {
        page: { type: Number, default: 1 },
        sortKey: { type: String, default: '' },
        sortDir: { type: String, default: 'asc' },
        categorie: { type: String, default: '' },
    };

    connect() {
        this.render();
    }

    rechercher() {
        this.pageValue = 1;
        this.render();
    }

    filtrerCategorie(event) {
        const cat = event.currentTarget.dataset.categorie || '';
        // Un second clic sur la chip active la désactive (retour à « toutes »).
        this.categorieValue = this.categorieValue === cat ? '' : cat;
        this.pageValue = 1;
        this.render();
    }

    trier(event) {
        const key = event.currentTarget.dataset.sortKey;
        if (!key) { return; }
        if (this.sortKeyValue === key) {
            this.sortDirValue = this.sortDirValue === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortKeyValue = key;
            this.sortDirValue = 'asc';
        }
        this.render();
    }

    pagePrecedente() {
        if (this.pageValue > 1) { this.pageValue -= 1; this.render(); }
    }

    pageSuivante() {
        this.pageValue += 1;
        this.render();
    }

    changerTaille() {
        this.pageValue = 1;
        this.render();
    }

    // Cœur : applique recherche + filtre + tri + pagination, met à jour le DOM.
    render() {
        const terme = (this.hasSearchTarget ? this.searchTarget.value : '').trim().toLowerCase();
        const cat = this.categorieValue;

        let lignes = this.rowTargets.filter((tr) => {
            const texte = (tr.dataset.recherche || '').toLowerCase();
            const correspondTexte = terme === '' || texte.includes(terme);
            const correspondCat = cat === '' || tr.dataset.categorie === cat;
            return correspondTexte && correspondCat;
        });

        if (this.sortKeyValue !== '') {
            const key = this.sortKeyValue;
            const dir = this.sortDirValue === 'asc' ? 1 : -1;
            lignes.sort((a, b) => {
                const va = a.dataset['sort' + this.capitaliser(key)] || '';
                const vb = b.dataset['sort' + this.capitaliser(key)] || '';
                const na = parseFloat(va);
                const nb = parseFloat(vb);
                const numerique = !Number.isNaN(na) && !Number.isNaN(nb);
                if (numerique) { return (na - nb) * dir; }
                return va.localeCompare(vb, 'fr') * dir;
            });
        }

        // Masque toutes les lignes, puis réaffiche la tranche de page.
        this.rowTargets.forEach((tr) => { tr.style.display = 'none'; });

        const taille = this.hasPageSizeTarget ? parseInt(this.pageSizeTarget.value, 10) : 20;
        const total = lignes.length;
        const nbPages = Math.max(1, Math.ceil(total / taille));
        if (this.pageValue > nbPages) { this.pageValue = nbPages; }
        const debut = (this.pageValue - 1) * taille;
        const tranche = lignes.slice(debut, debut + taille);

        const parent = this.hasRowTarget ? this.rowTargets[0].parentNode : null;
        tranche.forEach((tr) => {
            tr.style.display = '';
            if (parent) { parent.appendChild(tr); }
        });

        if (this.hasPageInfoTarget) {
            const fin = Math.min(debut + taille, total);
            this.pageInfoTarget.textContent = total === 0
                ? '0 résultat'
                : `${debut + 1} à ${fin} sur ${total}`;
        }
        if (this.hasEmptyTarget) {
            this.emptyTarget.style.display = total === 0 ? '' : 'none';
        }

        this.chipTargets.forEach((chip) => {
            chip.classList.toggle('is-active', (chip.dataset.categorie || '') === cat);
        });

        this.headerTargets.forEach((th) => {
            const key = th.dataset.sortKey;
            th.classList.toggle('is-sorted-asc', key === this.sortKeyValue && this.sortDirValue === 'asc');
            th.classList.toggle('is-sorted-desc', key === this.sortKeyValue && this.sortDirValue === 'desc');
        });
    }

    capitaliser(s) {
        return s.charAt(0).toUpperCase() + s.slice(1);
    }
}
