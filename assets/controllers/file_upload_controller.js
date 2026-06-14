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
    static targets = ['input', 'zone', 'liste', 'vide', 'envoyer', 'erreur'];

    static values = {
        maxFichiers: { type: Number, default: 10 },
        maxTailleMo: { type: Number, default: 25 },   // par fichier
        maxTotalMo: { type: Number, default: 80 },    // cumulé
    };

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
        // On compte les refus par motif plutôt que de lister chaque fichier :
        // afficher cinquante lignes identiques est du bruit (les fichiers refusés
        // ne sont même pas envoyés). Un résumé court suffit (RETEX Dropzone, Uppy,
        // FilePond : message agrégé, pas une ligne par rejet).
        const refus = { nombre: 0, taille: 0, total: 0 };
        const maxOctetsFichier = this.maxTailleMoValue * 1024 * 1024;
        const maxOctetsTotal = this.maxTotalMoValue * 1024 * 1024;

        for (const fichier of fileList) {
            // Doublon évident (même nom et même taille).
            const existe = this.fichiers.some((f) => f.name === fichier.name && f.size === fichier.size);
            if (existe) {
                continue;
            }
            // Nombre maximal de fichiers.
            if (this.fichiers.length >= this.maxFichiersValue) {
                refus.nombre += 1;
                continue;
            }
            // Taille d'un fichier.
            if (fichier.size > maxOctetsFichier) {
                refus.taille += 1;
                continue;
            }
            // Poids total cumulé.
            const totalActuel = this.fichiers.reduce((somme, f) => somme + f.size, 0);
            if (totalActuel + fichier.size > maxOctetsTotal) {
                refus.total += 1;
                continue;
            }
            this.fichiers.push(fichier);
        }

        this.afficherErreurs(refus);
        this.synchroniserInput();
        this.rendre();
    }

    afficherErreurs(refus) {
        if (!this.hasErreurTarget) {
            return;
        }
        const total = refus.nombre + refus.taille + refus.total;
        if (total === 0) {
            this.erreurTarget.hidden = true;
            this.erreurTarget.innerHTML = '';
            return;
        }

        // Motifs agrégés, dans l'ordre où ils se présentent.
        const motifs = [];
        if (refus.nombre > 0) {
            motifs.push(`limite de ${this.maxFichiersValue} fichiers atteinte`);
        }
        if (refus.taille > 0) {
            motifs.push(`taille au-delà de ${this.maxTailleMoValue} Mo`);
        }
        if (refus.total > 0) {
            motifs.push(`total au-delà de ${this.maxTotalMoValue} Mo`);
        }

        const tete = total > 1 ? `${total} fichiers écartés` : 'Un fichier écarté';
        this.erreurTarget.hidden = false;
        this.erreurTarget.textContent = `${tete} : ${motifs.join(', ')}.`;
    }

    retirer(event) {
        const index = Number(event.params.index);
        this.fichiers.splice(index, 1);
        this.afficherErreurs({ nombre: 0, taille: 0, total: 0 }); // on refait de la place
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
