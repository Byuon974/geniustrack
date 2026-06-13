# Mettre le projet en production

Guide des étapes et de la configuration nécessaires pour passer du développement à un déploiement réel. Il rassemble les points qui ne se devinent pas et que l'on découvre sinon au pire moment. Pour l'installation et les opérations courantes, voir `reprise-equipe.md` ; ce guide-ci ne couvre que ce qui est propre à la mise en production.

---

## 1. Variables d'environnement à définir

En production, ne pas s'appuyer sur les valeurs par défaut du `.env`. Les surcharger dans un `.env.local` non versionné (ou via l'environnement du conteneur).

- `APP_ENV=prod` et `APP_DEBUG=0` : sans quoi des informations de débogage fuitent.
- `APP_SECRET` : régénérer une chaîne aléatoire (par exemple `openssl rand -hex 16`).
- `DATABASE_URL` : la connexion PostgreSQL réelle.
- `MAILER_DSN` : un vrai service d'envoi (en dev, les mails ne partent pas).
- `CALENDRIER_ICAL_TOKEN` : jeton du flux calendrier public. **Sans lui, le conteneur s'arrête au démarrage.** Le générer une fois :
  ```
  CALENDRIER_ICAL_TOKEN=$(openssl rand -hex 24)
  ```
  L'URL d'abonnement (jeton inclus) s'affiche ensuite dans Tableau de bord puis Calendrier ; l'admin la copie dans son agenda via « S'abonner à un agenda par URL ».

---

## 2. Répertoires d'upload (droits d'écriture)

Deux répertoires doivent être accessibles en écriture par le conteneur PHP, sinon les uploads échouent silencieusement :

- `public/uploads/machines/` : photos de machines (servies publiquement).
- `var/uploads/plans/` : plans de projet (hors webroot, servis par route contrôlée, voir l'audit de sécurité).

Ajuster les droits (`chmod`/`chown`) selon l'environnement d'hébergement. Tester un upload une fois le projet monté, car le code PHP ne s'exécute pas tant que le projet n'est pas assemblé.

Les limites d'upload des plans (25 Mo par fichier, 10 fichiers, 80 Mo au total) sont fixées à la fois côté application (validation Symfony) et côté serveur (`frankenphp/conf.d/10-app.ini` : `upload_max_filesize`, `post_max_size`, `max_file_uploads`). Si vous changez d'hébergement ou de reverse proxy, vérifiez que la limite de taille de requête du proxy (par exemple `client_max_body_size` sous nginx) est au moins égale à `post_max_size`, sinon les envois multiples seront coupés en amont.

---

## 3. Schéma de base et amorçage

Le schéma est dérivé des entités à l'assemblage (pas de migrations manuelles). Au premier déploiement :

```bash
make db                                                  # schéma de la base
docker compose exec php bin/console app:create-admin     # premier compte admin
docker compose exec php bin/console app:init-vitrine     # textes de la vitrine
```

Ne pas charger les données de démonstration (`make reseed`) en production : elles sont faites pour exercer les écrans en développement.

La commande `app:init-vitrine` est idempotente : on peut la relancer sans risque. Elle crée les blocs manquants et resynchronise leurs libellés (les étiquettes de présentation), sans jamais écraser la valeur éditoriale saisie par l'admin. Relancer cette commande est donc le moyen de propager une correction de libellé sur une installation déjà en service.

---

## 4. Vérifications de sécurité avant ouverture

- Lancer `make audit` : vérifie que les dépendances n'ont pas de vulnérabilité connue.
- Confirmer que le site est servi en HTTPS (les en-têtes HSTS et le cookie sécurisé n'ont d'effet qu'en HTTPS).
- Vérifier qu'au moins un compte administrateur actif existe (le garde-fou anti-verrouillage empêche d'en perdre l'accès, mais il faut en avoir un au départ).

Les protections de fond (anti brute-force, en-têtes HTTP, cookies durcis, contrôle d'accès) sont décrites dans `../reference/audit-securite.md`.

---

## 5. Sauvegarde

Le script de sauvegarde de la base est fourni à la racine du projet. Le programmer (cron) selon la criticité des données. Détail des opérations dans `reprise-equipe.md`.
