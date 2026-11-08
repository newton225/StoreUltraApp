# 05. Données, concurrence et isolation entre clients

La base de données, ses migrations, les accès simultanés, la suppression et la séparation des clients entre eux.

## 1. Migrations et schéma

- **DATA-01.** **Les migrations ajoutent, elles ne cassent pas.** Pas de renommage ni de suppression d'une
  structure existante sans accord explicite. Un besoin de renommer se règle par ajout, bascule, retrait plus tard.
- **DATA-02.** **Le site tourne sur l'ancien code pendant la migration** : le schéma doit rester compatible avec
  lui. Une migration qui ajoute une colonne nullable ou une table est sans risque ; une migration qui change le
  sens d'une colonne ne l'est pas.
- **DATA-03.** **Une migration ne s'annule pas à chaud.** Le retour arrière d'une migration qui a modifié des
  données se fait par **restauration d'une sauvegarde** prise juste avant, application arrêtée.
- **DATA-04.** Une migration qui **recalcule des données** existantes (reprise d'une colonne dérivée) le fait par
  lots, sans dépendre d'un modèle qui pourrait changer plus tard, et est testée sur un volume réaliste.
- **DATA-05.** **Ne pas modifier la configuration de base de données** (connexion, hôte, moteur, jeu de
  caractères) sans demande explicite : si un besoin semble l'exiger, le signaler et attendre l'accord.
- **DATA-06.** Les migrations restent **portables** entre les moteurs que le projet supporte : pas de syntaxe
  propre à un moteur dans une migration commune ; les fonctionnalités propres à un moteur (extensions, politiques
  de sécurité de ligne) arrivent dans des migrations séparées et tardives.
- **DATA-07.** Une table **nouvelle** se crée avec ses **index** (clés étrangères, colonnes de recherche, de
  tri, de comparaison de doublons) et ses **contraintes** (unicité, non-nullité, clés étrangères avec le bon
  comportement en cascade).
- **DATA-08.** Les **clés étrangères en cascade** expriment la vie des données : ce qui doit disparaître avec son
  parent disparaît ; ce qui doit survivre (traces anonymes, journaux) **ne porte pas de lien** vers lui.

## 2. Deux moteurs : un de test, un de production

Un projet qui développe sur un moteur léger (SQLite) et tourne en production sur un autre (PostgreSQL) :

- **DATA-10.** **Les deux doivent fonctionner** : une modification qui ne marche que sur l'un est un bogue.
- **DATA-11.** Tester **sur le moteur de production** tout ce qui touche aux transactions, aux verrous, aux
  créations de bases, aux extensions, aux collations. Incident réel : un défaut d'inscription n'était visible
  que sur le moteur de production (création d'une base dans une transaction).
- **DATA-12.** Compenser ce que le moteur léger ne fait pas : le **verrou de ligne** y est sans effet. La
  garantie ne repose donc pas dessus (voir §3) ; on continue d'écrire le verrou pour le jour où il sera actif.
- **DATA-13.** **Recherche sans accents ni casse** : une fonction unique côté serveur (`lower(unaccent(col)) like
  ?`) et une fonction unique côté client, jamais un `like` direct ni un `toLowerCase().includes()`.
- **DATA-14.** Ce qui est **propre à un moteur** (création de base hors transaction, noms de bases, nettoyage des
  fichiers) est isolé derrière une classe, pas dispersé.

## 3. Concurrence : places, stocks, attributions

Le scénario type : deux requêtes veulent la dernière unité d'une ressource.

- **DATA-20.** **Calcul de disponibilité et création** dans **une transaction** avec **verrou** sur la ressource
  concernée (verrou de ligne, ou verrou consultatif).
- **DATA-21.** **Contrainte d'unicité en base** sur ce qui ne doit pas exister deux fois (`(ressource, emplacement,
  position)`). C'est **la garantie principale** quand le verrou de ligne est indisponible, **pas une ceinture
  secondaire**. La base doit refuser ce que le code aurait laissé passer.
- **DATA-22.** **Verrou applicatif atomique** (`Cache::lock`, verrou consultatif) par ressource autour du calcul
  et de la création, quand le verrou de ligne n'existe pas. Documenter pourquoi.
- **DATA-23.** **Toute transition d'état** passe par une transaction avec verrou sur la ressource, pas seulement
  la création : deux validations simultanées de la même preuve, deux réclamations de la même place en liste
  d'attente.
- **DATA-24.** **Machine à états explicite** : chaque état liste ses transitions permises ; une transition
  depuis un état inattendu **échoue**, elle n'écrase pas.
- **DATA-25.** **Clés d'idempotence** sur toute soumission rejouable : dépôt de pièce, validation, rejet,
  réclamation, scan. Un double clic ou un rejeu réseau ne crée pas deux enregistrements.
- **DATA-26.** **Délais et expirations** : appliqués par une tâche planifiée **et** vérifiés au moment de l'action
  (une réservation expirée mais pas encore purgée ne se transforme pas en preuve acceptée).
- **DATA-27.** Les **tests de concurrence** sont obligatoires (voir `03-tests-et-qualite.md`), y compris avec de
  vrais processus parallèles pour la campagne de course.

## 4. Séparation des clients (multi-organisation)

Un produit qui sert plusieurs clients dans une même installation doit choisir son modèle d'isolation **au
départ**. Le plus solide : **une base de données séparée par client**.

| Modèle | Isolation | Coût |
| --- | --- | --- |
| Une base par client | La requête qui oublie un filtre **ne peut pas** lire les données d'un autre : elle n'est pas connectée à sa base | Création et migration de chaque base, tâches qui bouclent sur les clients |
| Une base partagée, filtre par identifiant de client | Applicatif : un oubli de filtre fuit | Simple, mais risque permanent |
| Base partagée avec sécurité de ligne du moteur | Bonne, mais dépend du moteur | Complexité de configuration |

Avec **une base par client** :

- **DATA-30.** **Plus de colonne d'identifiant de client** sur les tables propres à un client : la base est la
  frontière. Ce n'est pas une omission.
- **DATA-31.** Ce qui doit être lisible **à travers** les clients (registre des clients, comptes, appartenances,
  marque lue avant d'identifier le client) vit dans une base **centrale** et **garde** un identifiant de client,
  un filtre explicite, une vérification dans les politiques et le test « accès croisé attendu 404 ».
- **DATA-32.** **Le risque se déplace vers l'établissement de la connexion** : contexte jamais initialisé (job,
  commande, tâche planifiée), mauvais client résolu, route du domaine central accessible alors qu'un client est
  actif (ou l'inverse), base créée sans ses migrations.
- **DATA-33.** **Aucune logique métier ne s'exécute sans contexte de client résolu.** Un job, une commande ou une
  tâche planifiée sans client **refuse de s'exécuter** plutôt que de lire silencieusement la base centrale. Une
  tâche qui balaie tous les clients **boucle sur eux et pose le contexte à chaque tour** (jamais une requête
  large).
- **DATA-34.** **Résoudre le client avant la liaison des modèles** (middleware d'appartenance placé avant la
  résolution des paramètres de route) : les identifiants de ressource se résolvent déjà dans la bonne base.
- **DATA-35.** **Deux stratégies d'identification** peuvent coexister (sous-domaine pour le public, segment de
  chemin pour le back-office) ; **chacune est testée indépendamment**, et un jeton du client A présenté sous
  l'identification de B échoue **dans les deux sens**.
- **DATA-36.** **Les routes du domaine central et celles d'un client ne se traversent pas** (middleware qui
  interdit l'accès croisé aux deux groupes).
- **DATA-37.** **Création d'un client = création de sa base et de ses migrations dans la même opération.** Un
  client sans ses tables est un client cassé. Si la création échoue, **la base créée par la tentative est
  effacée** (jamais un fichier trouvé en place) et l'équipe est prévenue.
- **DATA-38.** **Noms de bases tirés au hasard** (identifiant universel), stockés pour toujours, jamais
  recalculés à partir d'un numéro (incident réel : une création échouée avait laissé une base orpheline, et le
  numéro suivant tombait sur elle). Un contrôle de santé repère les **bases orphelines**.
- **DATA-39.** **Cache, sessions, files et stockage** sont préfixés ou isolés par client par les amorces
  officielles de la bibliothèque de multi-organisation, pas par un préfixage maison.
- **DATA-40.** **Tests d'isolation** : pour chaque route, la requête d'un client tiers reçoit 404 **même en
  forçant l'accès** ; le test vérifie que la donnée **n'existe pas sur la connexion**, pas qu'un filtre a été
  appliqué.
- **DATA-41.** La suppression d'un client efface sa base, ses fichiers et ses preuves ; les copies de sauvegarde
  disparaissent à l'expiration de leur rétention, **annoncée** à qui supprime.

## 5. Suppression, désactivation, rétention

- **DATA-50.** **Désactiver plutôt que supprimer** ce dont l'historique dépend (une unité, un profil, un compte).
- **DATA-51.** **Corbeille avec délai** (30 jours par exemple) puis effacement réel, pour les organisations et
  les événements. Pendant le délai, l'équipe d'exploitation peut restaurer à la demande du propriétaire ; les
  propriétaires sont prévenus de la suppression et de la date d'effacement.
- **DATA-52.** **Une entité publiée ne se supprime pas, elle se clôture.** Seul un brouillon se supprime.
- **DATA-53.** **Suppression groupée** : attention aux pièges (suppression sans passer par le modèle qui retire
  les fichiers associés ; cascade de base qui laisse des fichiers orphelins). Supprimer les enfants avec leurs
  fichiers **avant** de supprimer en lot.
- **DATA-54.** Une suppression qui **efface de la preuve** (dossier rejeté, pièce douteuse) garde une **trace
  anonyme** de ce qui sert à reconnaître une réutilisation (référence normalisée, empreinte, motif), sans donnée
  personnelle. C'est le compromis entre minimisation et lutte contre la fraude.
- **DATA-55.** Un journal de purge **ne contient jamais de nom ni de numéro**, seulement des références
  techniques.
- **DATA-56.** **La rétention est écrite** pour chaque catégorie de données (comptes, contenus, journaux,
  sauvegardes, factures), tenue par le code (tâche de purge) et reprise dans la politique de confidentialité.
- **DATA-57.** Une donnée qui doit s'effacer au bout d'un délai (adresse IP) **s'efface séparément** sans casser
  la structure qui l'entoure (chaînage d'empreintes).

## 6. Données dérivées et comparaisons

- **DATA-60.** **Une valeur dérivée n'est pas stockée** quand elle peut diverger de sa source (capacité = somme
  des places ; « complet » = calcul) : un état stocké doit être mis à jour par quelqu'un, qui se trompera au pire
  moment.
- **DATA-61.** **Un état affiché peut être calculé** sans changer l'état stocké (ouvert / en cours / terminé
  d'après les dates), quand la clôture reste un geste humain qui a des conséquences.
- **DATA-62.** **Comparer des identifiants saisis à la main** (références de transaction, numéros) sur une **forme
  normalisée** (lettres et chiffres en majuscules), stockée dans une colonne indexée ; la valeur saisie reste telle
  quelle pour l'affichage. Une valeur vide ou sans caractère utile n'est jamais un doublon.
- **DATA-63.** **Numéros de téléphone** : une forme unique (E.164) enregistrée à la saisie ; comparaison par une
  fonction qui connaît les variantes, jamais sur la chaîne brute.
- **DATA-64.** **Montants** : entiers dans la plus petite unité. Les devises sans décimale (franc CFA) l'écrivent
  tel quel.
- **DATA-65.** Un **plafond de capacité ne descend jamais** sous ce qui est déjà pris ; une configuration
  qui rendrait l'état incohérent est refusée.

## 7. Listes, volumes et performances

- **DATA-70.** **Toute liste qui grandit** pagine, cherche, filtre et trie **côté serveur** : le navigateur ne
  reçoit que la page affichée. Exceptions : listes courtes par nature (unités, profils, membres), et recherches
  volontairement bornées.
- **DATA-71.** **Pas de requête par ligne** : charger en lot. Un **budget de requêtes** testé par page garantit
  un nombre constant (voir `03-tests-et-qualite.md`).
- **DATA-72.** Une recherche « sans scan » ou de dépannage est **bornée** (trois caractères minimum, dix
  résultats au plus) : elle ne doit pas permettre de **parcourir la liste** de toutes les personnes.
- **DATA-73.** Les **exports lourds** ont leur plafond de temps et de mémoire mesuré, leurs limiteurs et leur
  journal.
- **DATA-74.** Les **tâches planifiées** sont **idempotentes** : une colonne « envoyé à » par envoi (jamais un
  statut déduit), pour qu'une tâche rejouée toutes les cinq minutes n'envoie ni deux cartes ni deux rappels.
- **DATA-75.** Les écritures annexes d'un planificateur (relevé d'exécution) sont protégées : leur échec ne doit
  jamais empêcher une tâche de tourner.
- **DATA-76.** **Une clôture de période fige la période.** Quand on clôture une caisse, un stock ou un compte à une
  date : (1) on saisit le **montant compté** et l'application le compare à ce que les livres attendent ; (2) l'**écart**
  n'est jamais une modification : c'est **une écriture** datée du jour de la clôture, pour que les livres rejoignent le
  comptage ; (3) la clôture elle-même est **immuable** (ni modifiée ni supprimée), avec son auteur, sa date et sa
  référence, et **tracée** ; (4) **plus rien de daté à cette date ou avant ne peut être saisi ni annulé**, y compris par
  un chemin indirect (une dépense liée, un rattachement) : on refuse avec un message clair, la correction se fait par une
  écriture postérieure ; (5) la date de clôture est **postérieure à la précédente** et jamais dans le futur ; (6) tout
  traitement de rattachement d'anciennes écritures (rattrapage) **ignore** celles d'une période clôturée et celles déjà
  rattachées, ne coche **rien par défaut**, et trace **une ligne par écriture**.
- **DATA-77.** **Une liste paginée reste exacte et lisible.** (1) Les **totaux et compteurs** (reste à payer, nombre de
  lignes) portent sur **toute la liste filtrée**, jamais sur la seule page affichée ; (2) quand une page porte
  **plusieurs listes paginées**, chacune a **son propre paramètre de page**, pour que tourner l'une ne remette pas l'autre
  à la première page ; (3) la **recherche** neutralise les jokers de la base saisis par l'utilisateur et se fait côté
  serveur ; (4) l'**export** de la liste reprend **le même filtre et la même recherche** que l'écran ; (5) la pagination
  est **le composant partagé** de l'interface, à l'apparence définie par UI-47, jamais recopié page par page ; (6) un test fixe le **nombre de requêtes** de la page : il ne dépend pas du nombre de lignes (DATA-71).
- **DATA-78.** **La recherche sans accents tient en une seule classe, et chaque moteur a son piège.** Tout filtre texte du
  serveur passe par elle (DATA-13), les listes **et** leurs exports. (1) PostgreSQL : l'extension `unaccent` se crée par
  **migration** avec le rôle migrateur (jamais à la main) ; la fonction n'est pas indexable, ce qui suffit tant que les listes
  restent de quelques milliers de lignes. (2) SQLite (tests, poste de développement) : la fonction `unaccent` est enregistrée
  par le code sur la connexion, sinon la même requête ne tournerait pas. (3) Piège du pilote PDO de PostgreSQL : il lit
  `'\'` comme un guillemet échappé et **avale le paramètre qui suit** (« parameter was not defined ») ; PostgreSQL échappe déjà
  par barre oblique inverse, donc n'écrire la clause `escape` que pour SQLite. (4) Le nom de colonne est écrit dans le code
  (type `literal-string`), jamais construit à partir d'une saisie. (5) Un test cherche « eleonore » pour « Éléonore », dans les
  deux sens, avec les jokers `%` `_` et `\` saisis, **sur les deux moteurs**.
