# Votre premier tour de GeniusLab

Ce tutoriel vous fait monter GeniusLab depuis zéro et le prendre en main, jusqu'à voir l'application vivante avec de vraies données et faire votre première action dedans. À la fin, vous saurez démarrer le projet, vous y connecter, et vous repérer dans ses écrans principaux. Pas besoin de connaître Symfony ni le métier du FabLab : on avance pas à pas, et chaque étape produit quelque chose que vous voyez à l'écran.

Comptez environ vingt minutes. Suivez les étapes dans l'ordre, sans en sauter : chacune prépare la suivante.

> Ce tutoriel est un parcours d'apprentissage. Pour une mise en production réelle ou le détail des opérations, voir les guides `../guides/reprise-equipe.md` et `../guides/mise-en-production.md`. Pour comprendre les choix d'architecture, voir `../explications/`.

---

## Avant de commencer

Il vous faut une seule chose installée sur votre machine : **Docker avec Docker Compose**. C'est tout. L'application tourne entièrement dans des conteneurs, vous n'avez pas à installer PHP ni PostgreSQL.

Vous avez aussi reçu le kit du projet (le dossier `geniuslab-kit`). Ouvrez un terminal et placez-vous dedans :

```bash
cd geniuslab-kit
```

---

## Étape 1 : Assembler le projet

Le kit contient le code, mais pas encore un projet exécutable. Une commande le fabrique :

```bash
./assembler-geniuslab.sh --kit .
```

Le script travaille quelques instants, puis crée un dossier `geniuslab` **à côté** du kit. C'est votre projet, prêt à démarrer. Entrez-y :

```bash
cd ../geniuslab
```

Vous êtes maintenant dans le projet assemblé. Toutes les commandes qui suivent se lancent d'ici.

---

## Étape 2 : Démarrer l'application

Une seule commande allume tout (le serveur web, PHP, la base de données) :

```bash
make up
```

La première fois, Docker télécharge et construit les images : c'est un peu long, c'est normal, ça n'arrive qu'une fois. Quand la commande rend la main, l'application tourne.

Préparez la base de données :

```bash
make db
```

Ouvrez maintenant votre navigateur sur **https://localhost**. Le navigateur vous avertira que le certificat n'est pas reconnu : c'est attendu en local, acceptez et continuez. Vous voyez la page d'accueil publique de GeniusLab, avec sa bannière et la présentation du FabLab.

Vous venez de faire tourner l'application. Elle est vide pour l'instant : remplissons-la.

---

## Étape 3 : Créer votre compte administrateur

Pour entrer dans la partie privée, il vous faut un compte. Créez un administrateur :

```bash
docker compose exec php bin/console app:create-admin
```

La commande vous pose des questions. Donnez une adresse en `@cci.re` (par exemple `vous@cci.re`) et un mot de passe. L'adresse doit finir par `@cci.re`, c'est la règle du projet.

Amorcez ensuite les textes de la vitrine :

```bash
docker compose exec php bin/console app:init-vitrine
```

---

## Étape 4 : Remplir l'application avec des données

Une application vide est difficile à explorer. Chargez le jeu de démonstration, qui crée des machines, des consommables, des comptes, des projets d'exemple, des réservations et un historique de mouvements de stock :

```bash
make reseed
```

En quelques secondes, le FabLab se peuple : machines de toutes sortes, stock de consommables, projets à différents stades, réservations aux durées variées qui garnissent le calendrier, et mouvements de stock datés sur les dernières semaines qui alimentent la supervision. C'est ce qui va rendre votre première visite parlante, y compris la page de supervision (taux d'utilisation des machines, fluctuations des consommables).

---

## Étape 5 : Se connecter et explorer

Retournez sur **https://localhost** et connectez-vous avec l'adresse et le mot de passe que vous venez de créer.

Vous arrivez dans l'application. Prenez le temps de regarder, en suivant ce petit parcours :

1. **Le tableau de bord.** C'est votre point de départ d'admin. Il montre en un coup d'œil les demandes en attente, le stock sous le seuil, les machines actives. Les chiffres viennent des données de démonstration que vous avez chargées.
2. **Les machines.** Ouvrez la liste des machines (menu Ressources). Vous y voyez le parc du FabLab, chaque machine avec son type lisible et son état. Essayez la barre de recherche en haut, et cliquez sur une en-tête de colonne pour trier : la liste réagit immédiatement.
3. **Le stock.** Toujours dans Ressources, ouvrez les stocks. Les articles sont rangés par espace machine, avec une barre qui montre le niveau par rapport au seuil d'alerte. Les chips en haut filtrent par catégorie.
4. **Les demandes.** Si des projets sont en attente, vous pouvez les valider ou les refuser depuis la file de validation. Vous tenez là le cœur du métier : décider des projets étudiants.
5. **La supervision.** Dans le menu Pilotage, ouvrez la supervision. C'est la vue analytique du labo : l'activité de réservation mois par mois, le taux d'utilisation de chaque machine, et les derniers mouvements de stock. Grâce aux données de démonstration, les trois axes sont déjà parlants. Vous pouvez aussi exporter ces données en CSV ou en tableur depuis cette page.

Vous venez de faire le tour de l'application avec de vraies données, et de toucher à ses fonctions principales.

---

## Étape 6 : Faire votre première action

Pour finir, faites une modification et voyez-la prise en compte. Dans la liste des machines, cliquez sur **Modifier** pour l'une d'elles, changez son état (par exemple en « Maintenance »), et enregistrez.

De retour sur la liste, la machine porte son nouvel état. Et si vous retournez au tableau de bord, le compteur de machines actives a changé en conséquence. Vous avez agi sur l'application, et l'effet se propage là où il doit.

---

## Ce que vous avez appris

En partant de rien, vous avez assemblé le projet, démarré l'application, créé un compte, chargé des données, exploré les écrans principaux et fait une première modification qui se répercute. Vous savez maintenant vous repérer dans GeniusLab et le faire tourner.

Pour arrêter l'application quand vous avez fini :

```bash
make down
```

## Et ensuite

Vous êtes prêt à aller plus loin selon ce que vous voulez faire :

- pour les opérations courantes et le dépannage, le guide `../guides/reprise-equipe.md` ;
- pour comprendre comment le projet est construit, `../explications/architecture.md` ;
- pour le point d'entrée complet de la documentation, `../explications/reprise-equipe-guide.md`.
