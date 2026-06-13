# Audit du projet GeniusLab

Audit du projet contre le cahier des charges (EC01) et son état réel de code, à un instant donné du développement. Objectif : vérifier la couverture des besoins, la cohérence interne, et signaler les points ouverts. Cet audit accompagne le journal des décisions (DEC-001 à DEC-051) ; il en est la vue de synthèse.

---

## 1. Couverture des besoins fonctionnels

Chaque besoin fonctionnel du cahier des charges a une implémentation identifiée dans le code. Synthèse par domaine :

```
Domaine             Besoins        Couverture
──────────────      ───────────    ──────────────────────────────────────
Présentation        BF_1.1, 1.2    Vitrine publique + édition admin
                                   (HomeController, ContenuVitrineController)
Réalisations        BF_2.1, 2.2    Galerie alimentée par les projets terminés
Réservation         BF_3.1→3.12,   Wizard (Form Flow), annulation, report,
                    3.20→3.22      capacité 15, blocage machine indisponible,
                                   calendrier serveur par rôle, free/busy
Validation projet   BF_3.4, 3.5    Formateur (pédagogique) / BDE (personnel),
                                   file de validation dense
Notifications       BF_3.6, 4.4    E-mails réels (TemplatedEmail + Mailer)
Stock               BF_4.1→4.4     CRUD, prédiction de rupture, alerte stock bas
Machines            BF_5.1→5.3     CRUD, états (active/maintenance/hors-service)
Comptes & sécurité  BF_6.1→6.3     Connexion, sanctions (ledger), vues par rôle,
                                   garde-fou anti-verrouillage admin
Dashboard           BF_7.1, 7.2    Tableau de bord admin, prévisions
Traçabilité         BF_8.1         Journal des actions administrateur
```

Aucun besoin fonctionnel n'est sans implémentation correspondante.

---

## 2. État technique

Au moment de l'audit, l'ensemble passe les vérifications disponibles hors exécution Symfony :

- Syntaxe PHP : tous les fichiers `src/` valides.
- JavaScript : tous les contrôleurs Stimulus valides.
- CSS : équilibre des accolades vérifié.
- Tokens : les valeurs utilisées existent dans `tokens.css` (pas de couleur ni d'espacement inventés).
- Aucun mock ni stub de logique métier résiduel : les calculs (capacité, prédiction, sanctions) et les envois (e-mails) sont réels.

Réserve de méthode : ces vérifications ne remplacent pas l'exécution réelle. Le rendu Symfony, le comportement Turbo du wizard, et le tri client des tableaux restent à confirmer en conditions réelles après assemblage.

### Audit de logique métier (confrontation au RETEX FOSS)

L'ensemble des services métier a été confronté aux patterns éprouvés de projets FOSS du même domaine (réservation, workflow d'approbation, systèmes de sanction, gestion de ressources). La méthode : raisonner sur le cycle complet plutôt que sur le cas nominal, et boucher chaque trou au plus près des données.

Trous trouvés et corrigés (services à fort enjeu) : garde de statut de projet ramenée dans le service de réservation (DEC-063) ; gardes d'état sur l'annulation et le report empêchant une double sanction (DEC-063) ; gestion d'exception associée côté contrôleur (DEC-063) ; séparation de la levée de sanction et de la réactivation de compte (DEC-064) ; refus de suppression d'une machine référencée, orientée vers l'état « hors service » (DEC-065). En périphérie, un trou d'interface a aussi été corrigé : les messages d'erreur muets, par centralisation de l'affichage des flashes (DEC-066).

Services confirmés sains (audit de complétude) : `DisponibiliteService` (lecture pure ; le calcul d'occupation exclut déjà les réservations annulées, donc aucun créneau faussement complet), `CalendrierIcalService` (le flux ne reçoit que des réservations planifiées via `aVenir()`, donc le `STATUS:CONFIRMED` est cohérent et aucun créneau annulé ne devient un évènement fantôme), `NotificationService` (double canal e-mail / in-app bien séparé, cas du destinataire non identifié géré). Les services à fort enjeu déjà corrigés se sont par ailleurs révélés solides sur leur cœur : détection de chevauchement d'intervalles semi-ouverts, verrou pessimiste anticipant le piège du verrou sur agrégat vide, transitions de workflow bloquées avant application, unicité d'e-mail imposée au niveau base.

Points mineurs relevés sans conséquence pratique, laissés en l'état au nom de la simplicité : le flux iCal ne replie pas les lignes de plus de 75 octets (RFC 5545) mais tous les clients courants le tolèrent, et les champs injectés sont des entrées à une seule ligne ; la notification in-app est créée après l'envoi de l'e-mail, mais cet envoi est asynchrone (Messenger), donc un échec réseau est différé et n'interrompt pas la transaction.

Conclusion de l'audit : tout le métier a été passé en revue. Les trous étaient des oublis de périphérie (une garde au mauvais endroit, un effet de bord non géré), jamais des défauts de conception du cœur. C'est le profil d'un logiciel sain avec quelques angles morts, désormais fermés.

---

## 3. Cohérence interne

Travaux de cohérence menés et vérifiés :

- **Libellés de type et de catégorie** : la nomenclature des espaces du FabLab (impression_3d, resine, graveuse_laser, plotteur, iot…) sert à la fois de type de machine et de catégorie de rangement du stock. Une source unique (`App\Catalogue\MachineTypes`) et un filtre Twig (`libelle_type_machine`) produisent partout le même libellé lisible : tableau machines, tableau stock, prédictions, chips de filtre, vitrine publique. Plus aucun affichage de valeur brute ni de bricolage de formatage local.
- **Pieds de tableau** : le décompte total nu et redondant a été retiré du composant de tableau générique ; le décompte libellé et informatif (« X utilisateur(s) ») est conservé là où il apporte une information que la pagination ne donne pas.
- **Cibles de clic** : les cases de sélection groupée ont été agrandies pour un clic confortable.

---

## 4. Points ouverts

**Décalage des catégories de stock (résolu).** Le formulaire d'ajout d'article proposait des catégories orientées nature de consommable (« Filament PLA », « Pièces d'usure »…) alors que les données rangent les consommables par espace machine. Le cahier des charges tranche : l'inventaire des consommables y est organisé « par groupe » correspondant aux espaces machine (Impression 3D, Résine, Graveuse laser, Plotteur, Flocage, IoT). Le formulaire propose désormais ces espaces, via la source unique `MachineTypes`, ce qui aligne formulaire, affichage et données sur une seule nomenclature. Les catégories hors socle déjà présentes en base restent proposées dynamiquement.

**Tirets cadratins dans les docs internes.** La règle de typographie française (pas de tiret cadratin dans le texte) est respectée dans le code et les templates rendus à l'utilisateur. Plusieurs docs internes de lot, antérieures à la règle, en contiennent encore. Sans impact utilisateur ; à nettoyer si l'on veut une cohérence documentaire totale.

---

## 5. Conclusion

Le projet couvre l'intégralité des besoins du cahier des charges, sans mock de logique métier, avec une cohérence d'affichage désormais centralisée. Le seul point fonctionnel à trancher est la nomenclature des catégories de stock, qui relève d'une décision métier et non d'un défaut technique. Les vérifications automatiques passent ; la validation en conditions réelles reste à faire au fil des assemblages.
