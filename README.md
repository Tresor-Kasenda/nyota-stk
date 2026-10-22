# Nyota — Laravel 13

Portage de `Tresor-Kasenda/nyota-stk`, révision CodeIgniter `b1f3584ce4d4e96051b5d9af8a881d75cfa54b18`.

Les vues PHP, Bootstrap, CSS, images, polices, textes, identifiants de formulaires et parcours AJAX historiques sont conservés. Aucun moteur CodeIgniter ni couche d'émulation CodeIgniter n'est inclus. Laravel gère HTTP, sessions, validation, CSRF et configuration ; `app/Domain` et `app/Application` sont en PHP indépendant du framework. La persistance est fournie par le port `Store` et son adaptateur PDO.

## Installation

PHP 8.4 ou 8.5, Composer 2, extensions PDO MySQL ou SQLite, mbstring, XML et curl. Aucun build npm n'est nécessaire : les ressources historiques sont dans `public/public/` afin de conserver leurs URL `/public/...`.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurer APP_URL et la connexion de base dans .env avant la suite.
php artisan migrate
php artisan nyota:admin administrateur@example.com
php artisan serve
```

`nyota:admin` demande le mot de passe sans l'afficher. Aucun compte de démonstration n'est créé. `--password-stdin` permet de fournir le mot de passe par l'entrée standard en automatisation.

La racine HTTP doit pointer vers le répertoire **public/** de Laravel. `APP_URL` doit contenir l'URL canonique complète, y compris un éventuel sous-répertoire. Ne pas servir la racine du projet. En production : `APP_ENV=production`, `APP_DEBUG=false`, HTTPS et `SESSION_SECURE_COOKIE=true`.

## Données historiques

La migration réutilise les tables, noms de colonnes, identifiants et empreintes bcrypt historiques ; elle ajoute les tables de paiements/idempotence manquantes et la version de révocation des sessions. Sur MySQL, les soldes cash et dette passent d'entiers à décimaux. Tester ces opérations sur une copie de la base avant la bascule.

Deux chemins sont possibles : configurer une copie de la base MySQL existante puis lancer `php artisan migrate`, ou migrer une base vide puis importer un export SQL de données :

```bash
php artisan nyota:import /chemin/prive/export.sql
```

L'import remplace les données métier dans une transaction et invalide les sessions. Il interprète uniquement les lignes INSERT de tables connues et leurs valeurs littérales ; il n'exécute pas les instructions SQL arbitraires du fichier. Les dumps avec procédures, expressions, formats binaires ou extensions personnalisées nécessitent une adaptation. Aucun export contenant les comptes ou données de l'ancien dépôt n'est embarqué dans cette version.

## Fonctionnalités portées

- Connexion email/téléphone, rôles Super/Basic, suspension, suppression logique et réinitialisation des mots de passe.
- Tableau de bord, classements, statistiques quotidiennes/mensuelles/annuelles et graphiques.
- Articles, catégories multiples, recherche, tris, pagination, seuils critiques et mouvements de stock.
- Ventes par sélection ou scanner clavier, remises/TVA pour Super, cash/dette/mixte, reçus et impression, rapports par dates.
- Règlements de dettes, coûts, journaux d'événements et d'approvisionnement.
- Exports Excel BIFF `.xls` et sauvegarde/restauration de données SQL, stockées hors racine publique.

Les droits sont vérifiés sur chaque requête. Les montants de vente utilisent des centimes entiers ; les prix sont relus en base. Les quantités négatives, stocks insuffisants et paiements supérieurs au solde sont refusés. Les écritures stock/vente/journal sont atomiques ; les règlements utilisent un verrou MySQL et une mise à jour conditionnelle. Les appels vente/paiement acceptent `Idempotency-Key`, que le transport AJAX fournit pour les requêtes en cours et leurs nouvelles tentatives après erreur réseau.

Les URL et clés JSON historiques sont conservées. Les mutations sensibles sont exclusivement POST avec protection CSRF. Trois scripts métier ont des adaptations internes : `main.js` pour l'URL de base et la sauvegarde, `transactions.js` et `dettes.js` pour créer les options de sélection sans interpréter les noms comme du HTML. `laravel-transport.js` ajoute CSRF, idempotence et déconnexion POST. Les autres ressources sont contrôlées par empreinte SHA-256.

`NYOTA_ASSETS_MODE=legacy` reproduit le choix local/CDN d'origine selon l'hôte canonique ; `local` force les fichiers vendoriés et `cdn` les liens historiques. Les anciennes versions des bibliothèques frontend ont été conservées pour la fidélité ; leur modernisation et leur audit de sécurité restent un chantier distinct.

## Sauvegardes

Les sauvegardes locales sont privées, sous `storage/app/private/backups`. Leur téléchargement exige le rôle Super. Une restauration invalide les sessions et nécessite une nouvelle connexion. Le bouton et les réponses AJAX d'origine sont conservés.

La sauvegarde distante est inactive tant que `NYOTA_BACKUP_URL`, `NYOTA_BACKUP_TOKEN` et `NYOTA_BACKUP_KEY` ne sont pas configurés. La clé est une clé aléatoire de 32 octets encodée en base64, conservée séparément de la sauvegarde. Le transfert utilise HTTPS vérifié, authentification et fichier chiffré AES-256-CBC avec authentification Laravel. Le destinataire doit accepter ce format ; la compatibilité avec l'ancien service distant n'a pas été confirmée et aucun envoi réel n'a été effectué. La sauvegarde locale reste disponible si la connexion distante échoue.

## Éléments déjà incomplets dans la version source

`Supplier` et `Command` ne faisaient aucun traitement et ne produisaient pas de contenu : leurs routes restent présentes. L'écran Clients existe mais le script `customers.js` et ses actions CRUD sont absents du dépôt source ; il est conservé en l'état. Les écrans génériques Audit/Rapports sont des maquettes, non des fonctionnalités métier achevées. Les callbacks de validation et les routes de test CodeIgniter ne sont pas des API à conserver.

Corrections internes explicites : recherche de transactions protégée, tri sur liste autorisée, valeur cachée de catégorie initialisée, JSON sans sortie de debug, encodage des champs affichés, réauthentification après révocation, exports de transactions utilisant les transactions plutôt que les articles. Les régressions de sécurité identifiées dans l'ancienne version ne sont pas réintroduites pour reproduire ses défauts.

## Vérifications

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
composer audit --locked
```

Les tests couvrent les écrans, contrats AJAX, ventes et reçus, règlements, CRUD et rôles, rejet des écritures invalides, CSRF, révocation, idempotence, restauration et exports. Les références de présentation sous `tests/fixtures` figent le HTML statique, les styles intégrés et les ressources de la révision source. Elles doivent être modifiées seulement après une décision explicite sur l'interface.

La CI exécute les contrats sous PHP 8.4/8.5 et SQLite/MySQL 8.4. Ces tests ne constituent pas une comparaison de captures navigateur : une recette visuelle desktop/mobile, scanner et impression, puis une répétition de migration sur une copie représentative de la base restent nécessaires avant bascule. Aucune garantie absolue de fidélité future ne remplace ces contrôles.
