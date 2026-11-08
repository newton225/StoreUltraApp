# StoreUltra : gestion de la revente de kits de construction

Application web de gestion d'une activité d'**intermédiaire** : acheter des matériaux auprès de boutiques
fournisseurs, les assembler en **kits de construction**, les livrer chaque semaine à des **clients** répartis par
sous-préfecture, encaisser une **avance** puis le **relicat à la livraison**, et **suivre le bénéfice réel**.

Ce fichier présente le projet et explique comment le lancer. Le besoin complet est décrit dans les deux autres
fichiers :

| Fichier | Contenu |
| --- | --- |
| [`README.md`](README.md) | Vue d'ensemble, pile technique, installation, commandes (ce fichier) |
| [`README-FONCTIONNEL.md`](README-FONCTIONNEL.md) | Le besoin : acteurs, processus, règles de gestion, documents, écrans, paramètres, hors périmètre, points à confirmer |
| [`README-DONNEES.md`](README-DONNEES.md) | Le modèle de données et les formules de calcul (achat, vente, avance, relicat, bénéfice) |

Les règles générales de développement (qualité, sécurité, tests, interface, langues) sont dans
[`playbook/`](playbook/README.md) ; les conventions Laravel sont dans `AGENTS.md` et `CLAUDE.md`.

## 1. En bref

- **Qui l'utilise** : le responsable, des employés de bureau et des agents de terrain affectés à des
  sous-préfectures. Les clients n'ont **aucun accès** : ils sont enregistrés par le personnel.
- **Ce qui est vendu** : des kits de construction (fer à béton, tôle, chevrons, ciment, tuyaux PVC, porte
  métallique, etc.). Un client reçoit 1 TPE = 3 kits **par semaine** (nombre et rythme réglables).
- **D'où viennent les produits** : de 2 boutiques fournisseurs (nombre libre à l'avenir).
- **Comment le client paie** : une avance de 200 000 FCFA par semaine avant la livraison, puis le relicat à la
  livraison. Il paie le **prix de vente**.
- **Rythme** : chaque semaine, chaque client reçoit 3 kits (réglable) après avoir versé son avance. Une
  **fourniture récurrente** par client génère les commandes semaine après semaine.
- **Ce que l'on suit** : commandes, achats, livraisons, encaissements, factures, frais de transport et imprévus,
  documents administratifs (dont les numéros d'entreprise), et le bénéfice théorique et réel.
- **Tout ce qui peut changer est un paramètre** : montant de l'avance, rythme hebdomadaire, nombre de kits par
  client, contenu d'un kit, prix, zones, numérotation, etc. Rien de métier n'est figé dans le code.
- **Langues** : français et anglais, au choix de l'utilisateur. **Thèmes** : clair, sombre ou système.
- **Monnaie** : FCFA, sans décimale.

## 2. Situation de départ (chiffres du classeur Excel existant)

Le classeur `ZEBE TABLEAU_DE_BORD_AVEC_SYNTHESE_BOUTIQUES.xlsx` sert de référence pour les premiers produits et
de jeu de test pour les calculs.

Les montants ci-dessous sont **pour une semaine** de livraison.

| | Par kit | Pour 1 client (3 kits) | Pour 56 clients (168 kits) |
| --- | --- | --- | --- |
| Prix d'achat | 91 525 F | 274 575 F | 15 376 200 F |
| Prix de vente | 112 200 F | 336 600 F | 18 849 600 F |
| Bénéfice théorique | 20 675 F | 62 025 F | 3 473 400 F |

Le bénéfice théorique ignore le transport et les imprévus. Le classeur contient aussi des erreurs de formule, listées
dans [`README-DONNEES.md`](README-DONNEES.md#7-anomalies-du-classeur-excel-à-ne-pas-reproduire).

## 3. Pile technique

Déjà en place (kit de démarrage Laravel + React) :

| Domaine | Choix |
| --- | --- |
| Serveur | Laravel 13, PHP 8.3 |
| Interface | Inertia v3, React 19, TypeScript, Tailwind v4, composants shadcn/ui |
| Authentification | Laravel Fortify : connexion, double authentification (2FA), clés d'accès (passkeys) |
| Routes typées côté client | Laravel Wayfinder |
| Tests | Pest 4 |
| Qualité | Pint (PHP), PHPStan niveau 7, Vite+ (`vp`) pour le contrôle JS/TS, `tsc` |
| Base de données | SQLite en développement et en test ; **PostgreSQL en production** (décidé) |

Briques logicielles à choisir avec le responsable avant d'ajouter (voir `AGENTS.md` : aucune dépendance sans
accord). Ce sont des bibliothèques ou services tiers, pas des fonctions à coder soi-même :

- génération des **PDF** (proforma, reçus, bons, factures, bordereaux) ;
- capture de **signature** à l'écran ;
- stockage des fichiers (signatures, justificatifs de frais, documents facultatifs).

La base de production (PostgreSQL) est décidée ; les documents administratifs se limitent à leurs **numéros**, le
fichier joint est facultatif. Le détail des options proposées est à valider avant toute installation.

## 4. Installation et commandes

```bash
composer setup            # dépendances, .env, clé, migrations, npm install, build
composer dev              # serveur, file d'attente et Vite
```

| Besoin | Commande |
| --- | --- |
| Tous les tests | `php artisan test --compact` |
| Un fichier de test | `php artisan test --compact tests/Feature/Auth/AuthenticationTest.php` |
| Un test par nom | `php artisan test --compact --filter=nomDuTest` |
| Formater le PHP modifié | `vendor/bin/pint --dirty --format agent` |
| Analyse statique PHP | `composer types:check` |
| Contrôle JS/TS | `npm run check` (corriger : `npm run check:fix`) |
| Types TypeScript | `npm run types:check` |
| Tout ce que l'intégration continue exécute | `composer ci:check` |
| Reconstruire le front | `npm run build` |
| Parcours de bout en bout (navigateur réel, application et base à part) | `npm run build` puis `npm run e2e` (arrêter le serveur Vite avant : `public/hot`) |

### Base de données en production : trois rôles

L'application ne se connecte **jamais** avec `postgres` ni avec le rôle qui possède les tables : elle pourrait supprimer une table
ou éteindre les déclencheurs qui rendent le journal d'audit infalsifiable. Le script `database/postgres/roles.sql` crée trois rôles :

| Rôle | Sert à | Droits |
| --- | --- | --- |
| `storeultra_migrator` | le déploiement, **seulement** | propriétaire de la base et des tables ; change la structure |
| `storeultra_app` | l'application en marche | lit et écrit les lignes ; ne possède rien ; **aucune** modification du journal d'audit |
| `storeultra_backup` | les sauvegardes | lecture seule |

Premier déploiement (super-utilisateur, depuis une machine de confiance ; psql 15 ou plus ; mots de passe dans l'environnement) :

```bash
STOREULTRA_MIGRATOR_PASSWORD=… STOREULTRA_APP_PASSWORD=… STOREULTRA_BACKUP_PASSWORD=… \
  psql -v ON_ERROR_STOP=1 -U postgres -d postgres -f database/postgres/roles.sql      # 1. rôles, base, droits
DB_MIGRATOR_USERNAME=storeultra_migrator DB_MIGRATOR_PASSWORD=… \
  php artisan migrate --database=pgsql_migrator --force                               # 2. structure, par le migrateur
STOREULTRA_… psql … -f database/postgres/roles.sql                                    # 3. mêmes droits sur les tables créées
php artisan db:seed --force                                                           # 4. données de base, par l'application
php artisan app:check-production                                                      # 5. le rôle de l'application est contrôlé
```

Le fichier `.env` de l'application porte `DB_USERNAME=storeultra_app` ; les identifiants du migrateur ne sont donnés qu'à l'étape de
migration. Le script est rejouable. Une base qui a déjà des tables appartenant à un autre rôle demande d'abord
`REASSIGN OWNED BY <ancien rôle> TO storeultra_migrator` après une sauvegarde (voir le commentaire du script). Sur un serveur qui héberge
d'autres bases, PostgreSQL laisse par défaut tout rôle s'y connecter : `REVOKE CONNECT ON DATABASE <autre base> FROM PUBLIC`.

**Vérifié** sur une base jetable de PostgreSQL 18 : migrations par le migrateur, chargement des données de base et vérification de la
chaîne d'audit par l'application (274 lignes intactes) ; l'application est refusée sur `DROP TABLE`, `ALTER TABLE … DISABLE TRIGGER`,
`UPDATE`/`DELETE` sur le journal, `TRUNCATE`, `CREATE TABLE`, `CREATE ROLE`, `CREATE DATABASE` ; le rôle de sauvegarde fait un `pg_dump` et ne
peut rien écrire.

**Mots de passe de test.** Le mot de passe de `postgres` de la machine de développement a été saisi dans des échanges avec l'assistant : il doit
être **changé**, et la base de test `storeultra_test` doit avoir son propre rôle (jamais `postgres`).

### Recherche sans accents

Toutes les recherches de texte (clients, produits, commandes, paiements, documents, documents administratifs et leurs exports) ignorent les
accents et la casse : « eleonore » trouve « Éléonore ». Elles passent par `App\Support\Search`. Sous PostgreSQL elle utilise l'extension
`unaccent`, créée par la migration `2026_11_08_100000` avec le rôle migrateur ; sous SQLite la fonction est fournie par le code.

### Sauvegarde et restauration

`php artisan app:backup` (planifiée chaque jour à 01 h 30) fait **une archive unique, chiffrée par mot de passe (AES-256)** : la base de
données (PostgreSQL : `pg_dump` au format personnalisé, avec le rôle `storeultra_backup` ; SQLite : copie cohérente du fichier), **tous les
fichiers privés** (documents émis, pièces, justificatifs, signatures, copies du journal d'audit) et un `manifest.json` avec l'empreinte de
chaque élément. Elle est déposée sur le disque `BACKUP_DISK`, puis la **rétention** supprime les vieilles copies : tout pendant 7 jours, une
par jour jusqu'à 30 jours, une par semaine jusqu'à 90 jours, une par mois jusqu'à 365 jours, rien au-delà (`app:backup --prune-only` ne
fait que ce nettoyage). Les variables sont décrites dans `.env.example`. Le client `pg_dump` doit être **au moins aussi récent que le
serveur** (la commande refuse sinon), et `BACKUP_PASSWORD` est **obligatoire** : sans lui rien n'est sauvegardé.

À savoir : le disque par défaut est un dossier du serveur, qui ne survit pas à la perte du serveur. Pointez `BACKUP_DISK` vers un stockage
extérieur (bucket compatible S3, SFTP) ; `php artisan app:check-production` le rappelle tant que ce n'est pas fait. Gardez le mot de passe
**ailleurs que sur le serveur** : sans lui les archives ne s'ouvrent plus. Une donnée supprimée peut survivre jusqu'à 365 jours dans les copies.

**Restauration, à la main, application arrêtée** (pas de bouton : écraser toutes les données ne se fait pas d'un clic) :

1. Récupérer l'archive voulue et l'extraire avec le mot de passe (7-Zip, `unzip -P`, ou `ZipArchive` en PHP) : elle contient `database.dump`
   (ou `database.sqlite`), le dossier `files/` et `manifest.json`.
2. Vérifier les empreintes du `manifest.json` (`sha256sum`) contre les fichiers extraits.
3. Remettre le contenu de `files/` dans `storage/app/private/`.
4. PostgreSQL : recréer une base vide au même nom (propriétaire `storeultra_migrator`), puis
   `pg_restore --no-owner --role=storeultra_migrator --dbname=<base> database.dump`, et relancer `database/postgres/roles.sql` pour redonner
   les droits. SQLite : remplacer le fichier de base par `database.sqlite`.
5. `php artisan migrate --database=pgsql_migrator --force` (ne fait rien si l'archive est à jour), `php artisan app:verify-audit-log` pour
   contrôler la chaîne du journal, puis remettre l'application en route.

**Faire un exercice de restauration** sur une machine propre avant l'ouverture au public, puis régulièrement : une sauvegarde jamais
restaurée n'est qu'une hypothèse (OPS-65).

## 5. Structure du code

```
app/Http/Controllers/     Contrôleurs (un sous-dossier par domaine, ex. Settings/)
app/Http/Requests/        Validation des formulaires
app/Models/               Modèles Eloquent
app/Actions/              Actions métier (Fortify aujourd'hui, calculs et opérations demain)
database/migrations/      Schéma
resources/js/pages/       Pages Inertia (le nom de la page = son chemin)
resources/js/components/  Composants applicatifs ; components/ui = shadcn (ne pas modifier)
resources/js/layouts/     Mises en page, choisies par préfixe de page dans app.tsx
routes/                   web.php et settings.php
tests/Feature, tests/Unit Tests Pest
playbook/                 Règles générales de projet
```

## 6. État d'avancement

Le dépôt contient pour l'instant **uniquement le socle** : authentification, profil, sécurité du compte,
apparence et tableau de bord vide. Aucun écran métier n'est encore construit. L'ordre de construction proposé est
dans [`README-FONCTIONNEL.md`](README-FONCTIONNEL.md#12-ordre-de-construction-proposé).

L'inscription publique fournie par le kit de démarrage devra être **désactivée** : seuls des utilisateurs créés par
le responsable peuvent se connecter (voir [`README-FONCTIONNEL.md`](README-FONCTIONNEL.md#3-acteurs-et-droits)).
