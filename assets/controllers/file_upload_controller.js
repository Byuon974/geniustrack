import { Controller } from '@hotwired/stimulus';

/*
 * Composant d'upload de fichiers (plans de projet).
 *
 * Habille un input file natif « multiple » : zone cliquable et glisser-deposer,
 * selection multiple, liste des fichiers choisis avec retrait individuel avant
 * l'envoi. Le meme controleur sert a la creation d'une demande et a l'ajout de
 * plans sur une demande modifiable (RETEX upload : zone de depot, multi-select
 * claire, liste de controle avant envoi).
 *
 * Cibles :
 *   - input  : l'input file reel (cache), qui porte la selection envoyee
 *   - zone   : la zone cliquable / de depot
 *   - liste  : le <ul> ou s'affichent les fichiers choisis
 *   - vide   : le message « aucun fichier »
 *   - envoyer: le bouton de soumission (desactive si aucun fichier), optionnel
 *
 * L'input reste la source de verite : on reconstruit son FileList via un
 * DataTransfer a chaque ajout ou retrait, pour que le formulaire envoie
 * exactement les fichiers listes.
 */
export default class extends Controller {
    static targets = ['input', 'zone', 'liste', 'vide', 'envoyer'];

    connect() {
        // Etat interne : la liste des fichiers retenus.
        this.fichiers = [];
        this.rendre();
    }

    // Clic sur la zone -> ouvre le selecteur natif.
    ouvrir() {
        this.inputTarget.click();
    }

    // Accessibilite : Entree ou Espace sur la zone declenche le selecteur.
    toucheZone(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.ouvrir();
        }
    }

    surDragover(event) {
        event.preventDefault();
        this.zoneTarget.classList.add('is-drag');
    }

    surDragleave() {
        this.zoneTarget.classList.remove('is-drag');
    }

    surDrop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('is-drag');
        this.ajouter(event.dataTransfer.files);
    }

    // Selection via l'input natif.
    surChangement() {
        this.ajouter(this.inputTarget.files);
    }

    ajouter(fileList) {
        for (const fichier of fileList) {
            // Evite les doublons evidents (meme nom et meme taille).
            const existe = this.fichiers.some((f) => f.name === fichier.name && f.size === fichier.size);
            if (!existe) {
                this.fichiers.push(fichier);
            }
        }
        this.synchroniserInput();
        this.rendre();
    }

    retirer(event) {
        const index = Number(event.params.index);
        this.fichiers.splice(index, 1);
        this.synchroniserInput();
        this.rendre();
    }

    // Reconstruit le FileList de l'input reel a partir de l'etat interne.
    synchroniserInput() {
        const dt = new DataTransfer();
        this.fichiers.forEach((f) => dt.items.add(f));
        this.inputTarget.files = dt.files;
    }

    rendre() {
        if (this.hasListeTarget) {
            this.listeTarget.innerHTML = '';
            this.fichiers.forEach((fichier, index) => {
                const li = document.createElement('li');
                li.className = 'upload__item';

                const nom = document.createElement('span');
                nom.className = 'upload__nom';
                nom.textContent = fichier.name;

                const taille = document.createElement('span');
                taille.className = 'upload__taille';
                taille.textContent = this.formaterTaille(fichier.size);

                const retrait = document.createElement('button');
                retrait.type = 'button';
                retrait.className = 'upload__retrait';
                retrait.textContent = 'Retirer';
                retrait.dataset.action = 'file-upload#retirer';
                retrait.dataset.fileUploadIndexParam = String(index);

                li.append(nom, taille, retrait);
                this.listeTarget.appendChild(li);
            });
        }

        if (this.hasVideTarget) {
            this.videTarget.hidden = this.fichiers.length > 0;
        }
        if (this.hasEnvoyerTarget) {
            this.envoyerTarget.disabled = this.fichiers.length === 0;
        }
    }

    formaterTaille(octets) {
        if (octets < 1024 * 1024) {
            return `${Math.round(octets / 1024)} Ko`;
        }
        return `${(octets / 1024 / 1024).toFixed(1)} Mo`;
    }
}
