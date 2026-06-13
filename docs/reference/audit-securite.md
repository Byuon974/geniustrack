# Audit de sécurité

Audit de sécurité du projet conduit en posture offensive (attaques simulées par catégorie), puis correction des failles trouvées en s'appuyant sur l'état de l'art 2026 (OWASP Top 10, recommandations sécurité web) et les solutions natives Symfony / FOSS. Grille de départ : les exigences non fonctionnelles de sécurité du cahier des charges (BNF_3.x) et le Top 10 OWASP, dont la première catégorie reste le contrôle d'accès défaillant (Broken Access Control / IDOR).

---

## 1. Vecteurs testés et résultats

```
Vecteur d'attaque                      Résultat
─────────────────────────────────────  ──────────────────────────────────────
IDOR sur projets/réservations/plans    Protégé : ProjetVoter vérifie la
                                       propriété (deny-by-default)
Injection SQL                          Protégé : requêtes paramétrées partout
                                       (37 setParameter, aucune concaténation)
Upload de fichiers → RCE               Protégé : plans hors webroot (var/),
                                       servis par route contrôlée ; images
                                       validées (type + taille), nom régénéré
Jeton calendrier public (iCal)         Protégé : hash_equals (temps constant),
                                       jeton en variable d'environnement
Brute-force login                      CORRIGÉ : login_throttling activé
En-têtes de sécurité HTTP              CORRIGÉ : listener dédié (CSP, X-Frame,
                                       nosniff, HSTS, Referrer, Permissions)
Cookies de session                     DURCI : httponly, secure auto, samesite
Mass assignment (rôles, statut)        Protégé : liste de choix fermée, accès
                                       admin uniquement
Énumération de comptes au login        Protégé : message d'erreur générique
XSS via |raw                           Faible : markup interne uniquement,
                                       aucune donnée utilisateur n'y transite
```

---

## 2. Failles trouvées et corrigées

**Absence de protection anti brute-force sur le login (BNF_3.3).** Le formulaire de connexion n'avait aucune limite de tentatives : un attaquant pouvait tester des mots de passe sans entrave. Correctif : activation de `login_throttling` (composant Rate Limiter de Symfony), plafonné à 5 tentatives par minute et par couple identifiant+IP. Solution native, sans code applicatif. Note : cette limite protège l'authentification ; la protection contre le déni de service de bas niveau relève du serveur (FrankenPHP/Caddy expose un module de rate limit), pas du rate limiter PHP.

**Absence d'en-têtes de sécurité HTTP.** Aucune réponse ne portait d'en-tête de sécurité, laissant la porte ouverte au clickjacking, au MIME sniffing et facilitant l'exploitation d'un éventuel XSS. Correctif : un listener pose sur chaque réponse `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, une `Content-Security-Policy` restrictive, et `Strict-Transport-Security` sur HTTPS. Mesure « peu coûteuse, fort impact » selon l'état de l'art 2026. Posé en listener (et non au serveur) pour rester portable et testable.

**Cookies de session sans attributs de sécurité explicites.** Les défauts Symfony sont raisonnables, mais l'intention n'était pas inscrite. Durcissement : `cookie_httponly` (cookie illisible en JavaScript, limite le vol via XSS), `cookie_secure: auto` (HTTPS uniquement dès que la requête est sécurisée), `cookie_samesite: lax` (couche anti-CSRF sans casser la navigation).

---

## 3. Points solides confirmés

L'audit a confirmé plusieurs défenses déjà bien posées, qu'il faut préserver :

- **Contrôle d'accès par ressource** via Voter (`ProjetVoter`) vérifiant la propriété, et `access_control` par zone dans la configuration. C'est la défense centrale contre la faille numéro un d'OWASP.
- **Plans utilisateurs stockés hors du webroot** (`var/uploads/plans`) et servis uniquement par une route qui contrôle l'accès : un fichier malveillant ne peut être ni atteint directement ni exécuté.
- **Mots de passe** hachés en algorithme automatique (bcrypt/argon) ; comptes importés dotés d'un mot de passe aléatoire fort.
- **Comparaison du jeton calendrier** en temps constant (`hash_equals`), résistante aux attaques temporelles.
- **Garde-fou anti-verrouillage administrateur** (un admin ne peut se désactiver lui-même ni supprimer le dernier admin actif).

---

## 4. Durcissements réalisés

À la suite de l'audit, trois durcissements complémentaires ont été appliqués, calibrés sur le RETEX 2026 et les outils natifs :

- **Liste blanche du filtre de rôle** : la recherche d'utilisateurs n'accepte plus que des rôles connus (`ROLE_ETUDIANT`, `ROLE_FORMATEUR`, `ROLE_BDE`, `ROLE_ADMIN`). Toute autre valeur renvoie un résultat vide en amont de la requête. La valeur était déjà liée en paramètre (pas d'injection) ; cette liste blanche ferme en plus tout usage détourné des jokers LIKE. Défense en profondeur.
- **Audit des dépendances** (OWASP composants vulnérables) : `composer audit --locked` est intégré au CI (job dédié, échoue le build si une dépendance a une vulnérabilité connue) et exposé en commande locale (`make audit`) à lancer avant chaque déploiement. L'outil scanne le `composer.lock` (versions exactes, directes et transitives) contre la base d'avis de sécurité PHP. Solution native Composer 2.4+, recommandée par l'état de l'art.
- **Documentation des `|raw` résiduels** : les trois `|raw` des composants (en-tête de page, tableau) ne reçoivent que du markup interne (liens via `path()`, icônes via `include()`, chips), jamais de donnée utilisateur ; un audit de tous les appelants l'a confirmé. Plutôt qu'un refactoring lourd à gain de sécurité nul, l'invariant de sécurité est désormais inscrit en commentaire dans chaque composant. Le `|raw` du composant d'icône porte des chemins SVG codés en dur, sûr par nature.
- **Durcissement des uploads d'images** : les contraintes `Image` reçoivent un plafond de pixels (`maxPixels`) et la détection de corruption (`detectCorrupted`, via GD), en plus des bornes de dimensions. Une image peut peser peu et décoder des dimensions énormes (decompression bomb / pixel flood) qui épuisent la mémoire au traitement ; `maxPixels` borne le total décodé avant traitement. Le SVG reste exclu de la liste blanche des images (vecteur XSS). Plafond harmonisé à 5 Mo.
- **Protection anti zip-bomb sur l'upload de plans** : l'archive zip (acceptée pour permettre aux étudiants de grouper leurs fichiers) est inspectée par un validateur dédié (`App\Validator\ArchiveSaine`) qui lit le catalogue de l'archive AVANT extraction et la rejette si le ratio de décompression, la taille décompressée totale ou le nombre d'entrées dépassent les seuils. Approche tirée de l'état de l'art (la taille du zip ne protège pas : 42 Ko peuvent décompresser en pétaoctets ; c'est le ratio et la taille décompressée qu'il faut borner).

## 5. Durcissements encore possibles (non bloquants)

- **CSP sans `unsafe-inline`** : tolérée aujourd'hui pour les styles/scripts inline de Symfony UX ; à durcir via des nonces si l'application les retire.
- **`roave/security-advisories`** en dépendance de dev : complément à `composer audit` qui bloque l'installation de versions vulnérables dès la résolution (`composer update`).
- **Surveillance continue** (type Dependabot) : `composer audit` agit au déploiement ; un service de surveillance ouvre des correctifs entre les déploiements, quand un nouvel avis paraît.

---

## 6. Conclusion

Les défenses fondamentales étaient déjà en place (contrôle d'accès par ressource, requêtes paramétrées, uploads hors webroot, hachage fort). L'audit a trouvé et corrigé trois manques de durcissement classiques mais réels : l'anti brute-force, les en-têtes de sécurité HTTP, et les attributs de cookie. Tous les correctifs reposent sur des mécanismes natifs Symfony ou des patterns FOSS standard, sans logique maison fragile. Réserve de méthode : cet audit est statique (lecture de code et de configuration) ; il ne remplace pas un test d'intrusion en conditions réelles ni un scan automatisé périodique, recommandés à chaque mise en production.
