# 03. Tests et qualité

Ce qu'on teste, comment on l'écrit, comment on le lance, comment on lit le résultat.

## 1. Méthode : le test avant le code

- **TEST-01.** Dès qu'un comportement est vérifiable (règle métier, calcul, autorisation, validation, transition
  d'état, point d'entrée), **écrire le test en premier**. Cycle : test rouge, code minimal qui le fait passer,
  remaniement.
- **TEST-02.** **Une correction de bogue commence par un test qui le reproduit.** Sans lui, rien ne dit qu'on a
  corrigé la bonne chose, ni que ça ne reviendra pas.
- **TEST-03.** **Une règle métier écrite dans la spécification a un test avant son implémentation.** Aucune
  exception pour les règles sur l'argent, la disponibilité, les priorités, les purges et l'isolation entre clients.
- **TEST-04.** Un test **par règle**, nommé comme une phrase lisible : « refuse une preuve déposée après
  expiration du décompte ».
- **TEST-05.** Pas de test pour du code trivial sans logique (accesseur, mapping direct, composant purement
  présentationnel). Le nombre de tests n'est pas un objectif ; la couverture de **règles** l'est.

## 2. Ce qu'il faut toujours tester

| Id | Sujet | Test attendu |
| --- | --- | --- |
| TEST-10 | Autorisation | Sur **chaque** route protégée : chaque rôle testé autorisé et refusé ; un utilisateur d'un autre client reçoit 404 |
| TEST-11 | Isolation entre clients | Aucune route ne répond avec les données d'un autre client, **même en forçant l'accès** (identifiant d'un autre client, jeton croisé) |
| TEST-12 | Concurrence | Deux requêtes simultanées sur la dernière unité d'une ressource : une seule aboutit |
| TEST-13 | Idempotence | Rejouer un envoi, un double clic, un rejeu réseau ne crée pas deux enregistrements |
| TEST-14 | Transitions d'état | Une transition depuis un état inattendu échoue au lieu d'écraser |
| TEST-15 | Expirations | Action après expiration d'un délai : refusée ; purge à l'échéance : ressources rendues, journal écrit |
| TEST-16 | Limitation de débit | Chaque limiteur atteint jusqu'à la réponse limitée, y compris avec un en-tête de proxy falsifié |
| TEST-17 | Validation | Valeurs limites, vides, trop longues, caractères hostiles, formats voisins de l'attendu |
| TEST-18 | Signatures et jetons | Jeton modifié d'un octet, rejoué, d'un autre contexte, expiré : refusé. Signature valide et invalide d'un webhook |
| TEST-19 | Journal d'audit | Chaque geste sensible écrit une entrée avec acteur, avant et après |
| TEST-20 | Droits à l'effacement | La suppression efface bien ce qu'elle promet, et seulement cela |
| TEST-21 | Calculs d'argent | Totaux, arrondis, remboursements, plafonds, sur des cas limites chiffrés à la main |
| TEST-22 | Dépendances externes | Panne, délai, réponse inattendue d'un service tiers : le comportement choisi est celui qui est testé |

**Note sur la concurrence.** Avec un moteur mono-écrivain (certaines bases de test), le test de course ne
vérifie que la **contrainte d'intégrité**, pas une vraie course. Le nom du test doit le dire, et la même
exigence est rejouée sur le moteur de production avant l'ouverture (voir `07-livraison-et-exploitation.md`).

## 3. Les niveaux de tests

| Niveau | Rôle | Vitesse |
| --- | --- | --- |
| Unitaire | Logique pure : calculs, règles, formatage | Très rapide |
| Fonctionnel (feature) | Une requête complète, base comprise : routes, autorisations, validations, effets | Rapide |
| Composant d'interface | Ce que l'écran affiche selon ses données | Rapide |
| De bout en bout | Parcours critiques dans un vrai navigateur, sur une application à part avec ses propres données | Lent |
| Sécurité | Balayage de toutes les routes, fuzz des entrées publiques, isolation, mutation des jetons | Moyen |
| Performance | Budget de requêtes par page, temps avec un volume réaliste | Moyen |
| Intrusion, concurrence réelle, charge | Outils dédiés, lancés à part, jamais en parallèle de la suite | Long |

- **TEST-30.** Les tests d'interface vivent **à côté du fichier** qu'ils vérifient. Un composant qui lit ses
  textes par un hook de traduction se teste avec ce hook simulé : la traduction rend la clé, le test porte sur ce
  que le composant affiche.
- **TEST-31.** Les tests de bout en bout visent les **parcours critiques** (inscription, paiement, accès,
  parcours d'administration) et désignent les éléments par un attribut de test dédié (`data-test`), pas par leur
  texte ou leur style.
- **TEST-32.** Les tests de bout en bout tournent contre une application **lancée à part, sur ses propres bases
  et fichiers**, remise à zéro à chaque exécution. Jamais contre les données de développement ni de production.
- **TEST-33.** **Tout projet d'interface web a son outil de bout en bout installé dès le départ**, et c'est le
  **meilleur outil du moment pour son framework et son langage** (pas un outil choisi par habitude). On le
  vérifie en début de projet (documentation, compatibilité avec la version du framework, maintenance active), on
  l'installe comme dépendance de développement, et on l'utilise aussi pour **regarder les écrans** (captures
  claires et sombres, français et anglais, téléphone et grand écran) avant de livrer. Référence : Laravel +
  Inertia/React → Playwright (Dusk seulement si le projet n'a pas de JavaScript propre). Un outil ajouté après
  coup, pour une seule vérification, est un oubli de départ. Cette installation est **autorisée d'avance par le
  propriétaire** : elle se fait sans demander (exception à `01` PRI-09, limitée à cet outil), en le signalant
  dans le compte rendu.

## 4. Les trois balayages à posséder

### 4.1 Balayage des accès

Un test qui **énumère toutes les routes** du back-office et, pour chacune : refuse l'anonyme, refuse chaque rôle
non autorisé, renvoie 404 pour un autre client, et refuse l'écriture **avant** la validation (pour ne pas
renseigner sur les champs attendus). Une route nouvelle qui échappe au balayage fait échouer le test.

### 4.2 Fuzz de la surface publique

Entrées hostiles sur chaque lien public, jeton et formulaire : octets nuls, très longues chaînes, types
inattendus, injections, jetons presque corrects. Attendu : jamais de 500, jamais de fuite d'information, une
réponse **uniforme**.

### 4.3 Budget de requêtes

Pour chaque page sensible, le **nombre de requêtes à la base est constant** quel que soit le volume (pas de
requête par ligne). Le test pose deux volumes et compare. Un second test mesure le **temps** avec un volume
réaliste (par exemple deux mille lignes) contre un plafond écrit.

## 5. Données de test et de démonstration

- **TEST-40.** Chaque modèle a une **usine** (factory) avec des données crédibles et des **états nommés** pour les
  cas métier (`held()`, `confirmed()`, `expired()`, `purged()`).
- **TEST-41.** Les jeux de démonstration (seeders) sont **idempotents** : les rejouer ne duplique rien. Ils
  couvrent tous les états (en cours, ouvert, brouillon, terminé, complet) et les cas douteux.
- **TEST-42.** Un compte de développement existe pour travailler, **créé uniquement hors production**, et son mot
  de passe n'est jamais un identifiant valide en production.
- **TEST-43.** Un test ne dépend d'**aucune donnée réelle** ni d'aucun service réel. Toute requête sortante non
  simulée **échoue** (interdire les appels réseau « perdus » dans les tests). Un incident réel : des tests ont
  appelé un vrai service de messagerie parce que des identifiants de production traînaient dans l'environnement.
- **TEST-44.** Le temps est **figé ou injecté** dans les tests qui en dépendent. Un test qui dépend de « la
  date du jour » casse le jour où elle change (événement « à venir » devenu passé).

## 6. Hygiène des tests

- **TEST-50.** Un test **n'est pas indépendant de l'état de compilation** du projet : le rendu des pages en test
  ne lit pas le manifeste des ressources compilées (désactiver cette lecture dans la classe de base). Sinon une
  page ajoutée depuis la dernière compilation répond 500 en test, et seul un fichier périmé l'avait masqué.
- **TEST-51.** Un test dont **l'attente est devenue fausse parce qu'une décision a changé** se met à jour, avec
  la décision en commentaire. On ne le supprime pas, on ne l'affaiblit pas en silence.
- **TEST-52.** Les tests ne partagent pas d'état : chacun crée ce dont il a besoin. Deux exécutions en
  parallèle **ne se lancent pas** sur les mêmes bases de test (elles s'effacent mutuellement) : une seule série
  à la fois.
- **TEST-53.** Un échec **qui ne se reproduit pas** n'est pas classé « instable » sans enquête : relancer seul,
  relancer en boucle (30 fois), relancer dans le lot, regarder ce qui tournait à côté (compilation, formatage,
  autre série). Documenter ce qui est écarté.
- **TEST-54.** Les lots longs se **découpent** (un dossier à la fois) et écrivent leur résultat **au fur et à
  mesure** dans un fichier : une machine qui manque de mémoire ne doit pas faire perdre une heure de résultats.
- **TEST-55.** Les assertions et fabriques **du framework** plutôt que des vérifications maison sur les mêmes
  faits.

- **TEST-60.** **Un test de bout en bout isole vraiment ses fichiers, et le prouve.** Base, port ET dossier de fichiers sont à
  part, mais une variable d'environnement n'est pas toujours lue par le serveur de développement du langage (`php -S` laisse
  `$_ENV` vide : le dossier de fichiers demandé était **ignoré** et les essais ont **écrasé de vrais PDF**). Le lanceur
  règle ce qu'il faut pour que ce soit lu **et vérifie** qu'une requête écrit bien dans son dossier, sinon il s'arrête avant de
  tester. Il refuse aussi de tourner si un serveur de développement du front est actif (`public/hot`) : il testerait des
  scripts rechargés à chaud, pas ceux de la version construite. Les parcours partagent **une seule connexion** par compte
  (la page de connexion limite les tentatives).
- **TEST-61.** **Un document imprimé se vérifie sur le vrai PDF, de chaque type, avec des données réalistes.** Une commande vide
  ne montre ni la longueur réelle d'un tableau ni les champs vides. On ouvre chaque type de document (devis, facture, reçu,
  bon de livraison, bon de commande, bordereau, synthèse, exports) avec l'identité complète, **dans chaque langue**, et on
  contrôle : une page quand le contenu est normal (jamais une page qui ne porte que les signatures), aucun libellé sans sa
  valeur ni répété, le nom de l'organisation et jamais celui du logiciel, des nombres écrits à la locale (virgule en
  français), un numéro de téléphone jamais coupé. Chaque défaut trouvé devient un **test qui échoue sur l'ancien gabarit**.
- **TEST-62.** **Ne jamais lancer plusieurs serveurs sur le même port sans les arrêter, et vérifier lequel répond.** Sous
  Windows plusieurs processus peuvent écouter le même port : une requête arrive au hasard sur un ancien serveur, avec une
  autre base. Chercher les processus par leur ligne de commande (pas par le mot « LISTEN », traduit selon la langue) et
  les arrêter en fin d'essai.


## 7. Politique de lancement

À convenir explicitement avec le responsable au début du projet :

- **Qui lance quoi, quand.** Une suite complète occupe la machine ; certains projets exigent un accord avant
  chaque exécution, ou la réservent à des créneaux (le soir).
- **Quoi lancer à chaque changement** : les tests du fichier touché, plus ceux des modules voisins ; la suite
  entière avant une livraison.
- **Les campagnes lourdes** (intrusion, concurrence réelle, charge, bout en bout complet) se lancent à part, sur
  une application dédiée, **jamais** en parallèle de la suite.
- **Rendre compte** : ce qui a tourné, combien de tests, combien d'échecs, lesquels, ce qui est **non rejoué**.

## 8. Intégration continue (quand elle existe)

Étapes bloquantes, dans cet ordre : formatage, analyse statique, audit des dépendances, tests unitaires et
fonctionnels, tests d'interface, construction. Les tests de bout en bout et de sécurité à une cadence propre.
Une branche principale rouge se répare **avant** tout autre travail.

## 9. Ce qu'un rapport de campagne contient

Un document de campagne ne reprend que ce qui est **établi** :

- ce qui est couvert, avec le chemin du test ou de l'outil ;
- les résultats **conservés** (fichiers de mesure, dates, plafonds) ;
- les campagnes **sans résultat conservé**, à rejouer pour qu'un chiffre existe ;
- les défauts trouvés et corrigés, avec leur commit ;
- les pièges d'exécution rencontrés (voir §6).
