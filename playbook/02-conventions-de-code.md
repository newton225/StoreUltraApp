# 02. Conventions de code

Comment le code s'écrit pour rester lisible, prévisible et sûr. Les exemples citent Laravel, React et
TypeScript ; chaque règle se transpose.

## 1. Nommage

- **CODE-01.** Des noms qui disent ce que la chose **est ou fait**, dans le vocabulaire du métier :
  `reservationHold`, `proofSubmission`, `remainingSeats`. Jamais `data`, `tmp`, `obj`, `handleClick2`.
- **CODE-02.** Une seule langue dans le code (l'anglais est le choix courant) ; la langue des utilisateurs reste
  dans les textes affichés (voir `06-interface-accessibilite-langues.md`).
- **CODE-03.** Un booléen se nomme comme une question (`isExpired`, `hasProof`, `canRefund`), un événement
  comme un fait passé (`registrationConfirmed`), une action comme un verbe (`approveProof`).
- **CODE-04.** Le même concept porte **le même nom** partout : base, code, interface, documentation. Un mot
  technique (« tenant ») qui n'a pas de sens pour l'utilisateur est remplacé à l'écran par le sien
  (« organisation »).
- **CODE-05.** Pas d'abréviation obscure ; un acronyme du métier est défini une fois, dans le glossaire du projet.

## 2. Structure

- **CODE-10.** **Contrôleurs minces** : ils reçoivent, autorisent, délèguent, répondent. La logique métier vit
  dans des **actions ou services** testables sans HTTP.
- **CODE-11.** **Validation dans des objets dédiés** (Form Requests, schémas), avec les mêmes règles côté serveur
  que côté client ; le serveur est la source de vérité.
- **CODE-12.** **Autorisation dans des politiques** (Policies, Gates), jamais dispersée en `if` de rôle dans les
  vues. Côté client, on masque ce qui est interdit ; le serveur revérifie **chaque** route et **chaque** action.
- **CODE-13.** Les **états** sont des énumérations typées adossées aux colonnes, jamais des chaînes libres.
- **CODE-14.** Les **effets de bord** (envoi de message, journal, recalcul) passent par des événements et des
  écouteurs, ou par des tâches asynchrones : une action métier ne se mêle pas d'envoyer un courriel.
- **CODE-15.** **Aucune requête de données dans une vue ou un composant d'interface.** L'interface reçoit ce
  qu'il faut afficher, déjà préparé.
- **CODE-16.** **Un composant par fichier** côté interface ; pages, composants partagés et hooks dans des dossiers
  distincts ; aucune logique métier dans un composant.
- **CODE-17.** Les valeurs de configuration (seuils, durées, limites) vivent dans la **configuration** ou dans des
  réglages modifiables, pas en constantes éparpillées. Une valeur que le responsable peut vouloir changer est un
  réglage.

## 3. Types et contrats

- **CODE-20.** Types explicites sur tout ce qui traverse une frontière : serveur vers client, module vers
  module, application vers service externe.
- **CODE-21.** Pas de `any`, pas de conversion (`as`) pour faire taire le compilateur : on **corrige le type**.
- **CODE-22.** **Ne jamais transmettre un modèle de base entier à l'interface** : des objets de transfert
  explicites, champ par champ. Un `toArray()` envoie des colonnes que personne n'avait prévues (hachages,
  jetons, notes internes).
- **CODE-23.** Valider à la frontière, faire confiance à l'intérieur : on ne revalide pas dix fois la même
  donnée, mais on ne fait confiance à rien de ce qui vient de l'extérieur.
- **CODE-24.** Les montants sont des **entiers dans la plus petite unité** (ou des décimaux exacts), jamais des
  flottants.
- **CODE-25.** Les dates sont stockées en **UTC** et formatées à l'affichage avec la langue choisie par
  l'utilisateur, pas celle de l'appareil.

## 4. Commentaires

- **CODE-30.** Un commentaire explique **pourquoi**, pas **quoi** : une règle métier non évidente, un choix de
  verrouillage, une contrainte légale, un compromis, un piège qu'on a déjà rencontré.
- **CODE-31.** Pas de commentaire qui paraphrase le code, pas de bandeau décoratif, pas de code commenté laissé
  « au cas où » (le gestionnaire de versions s'en souvient).
- **CODE-32.** Un compromis qui contourne une bonne pratique s'écrit **à l'endroit du compromis**, avec la
  raison et, si elle existe, la date de la décision.
- **CODE-33.** Un point provisoire est marqué (`PROVISOIRE`, `TODO` avec la raison et le responsable) et suivi ;
  un marqueur oublié est une dette cachée.

## 5. Forme et style

- **CODE-40.** Un **formateur automatique** pour chaque langage (PHP : Pint ; TypeScript : Prettier ou outil
  équivalent ; Python : Black ; etc.), appliqué par un crochet de pré-commit ou une vérification d'intégration.
  On ne débat pas de la forme.
- **CODE-41.** Un **analyseur statique** au niveau le plus strict que le projet tient (PHPStan ou Larastan,
  `tsc --noEmit`, ESLint). Zéro erreur est l'état normal ; on n'ajoute pas de baseline pour cacher du neuf.
- **CODE-42.** Ne pas lancer un formateur **sur tout le dépôt** pour un petit changement : il réécrit des fichiers
  sans rapport et noie la vraie modification. Cibler les fichiers touchés.
- **CODE-43.** Règle de style des textes (choix du projet, à fixer au départ) : par exemple, aucun emoji et aucun
  tiret cadratin dans le code, les commentaires, les messages de commit et les textes affichés ; la virgule, le
  deux-points ou la parenthèse à la place.

## 6. Gestion des erreurs et des journaux

- **CODE-50.** Une erreur attendue (validation, droit manquant, ressource absente) est une **réponse**, pas une
  exception qui remonte jusqu'au navigateur.
- **CODE-51.** Les messages à l'utilisateur disent **ce qui s'est passé et l'action suivante**, jamais un code
  technique, une pile d'appel ni un nom de table.
- **CODE-52.** Une panne d'un service tiers **ne ferme pas** une fonction centrale quand une alternative sûre
  existe : on dégrade (en journalisant) plutôt que de bloquer. Décider explicitement, service par service, entre
  « échouer fermé » et « échouer ouvert ».
- **CODE-53.** Une écriture annexe (relevé d'exécution, trace) **ne doit jamais empêcher** la tâche principale :
  elle est protégée pour que son échec ne remonte pas.
- **CODE-54.** On **journalise** ce qui aide à comprendre un incident, jamais un secret, un jeton, un code à usage
  unique, un numéro complet de document ou le contenu d'un fichier déposé (voir `04-securite.md`).

## 7. Dépendances

- **CODE-60.** Verrous de versions commités ; versions épinglées ; mises à jour de sécurité suivies. Un audit des
  dépendances est une étape d'intégration **bloquante**.
- **CODE-61.** Avant d'ajouter un paquet : comparer, présenter, attendre l'accord (voir
  `01-principes-et-methode.md`, PRI-09).
- **CODE-62.** On préfère **les paquets d'un même éditeur de confiance** pour les briques transverses (cohérence,
  maintenance, versions compatibles entre elles).
- **CODE-63.** On n'embarque pas de dépendance pour trois lignes de code.

## 8. Gestionnaire de versions

- **GIT-01.** **Un commit, un sujet.** Un message qui dit ce qui change **et pourquoi**, à l'impératif ou au
  passé de façon constante, sans emoji.
- **GIT-02.** Un commit **laisse le dépôt cohérent** : le code, ses tests, ses textes dans les deux langues, sa
  documentation et sa migration partent ensemble.
- **GIT-03.** Jamais de secret, de fichier d'environnement, de dump de base ni de donnée réelle dans le dépôt.
  Fichiers générés et sorties de test dans l'ignorance de versionnement.
- **GIT-04.** Ne jamais forcer une mise à jour (`--force`) sur une branche partagée sans demande explicite ; ne
  jamais contourner les crochets (`--no-verify`) : on corrige la cause.
- **GIT-05.** Une branche principale **toujours livrable**. Le travail en cours vit sur une branche, ou derrière un
  interrupteur.
- **GIT-06.** Ce qui est volontairement **non versionné** (rapports de campagne de test, brouillons) est listé,
  pour qu'on ne le commite pas par erreur.
- **GIT-07.** Le serveur lit le dépôt avec une **clé de déploiement en lecture seule**, propre au projet.
- **GIT-08.** Pour une branche d'intégration commune : l'historique reste lisible ; on ne réécrit pas ce qui est
  déjà publié.

## 9. Revue de code

À chaque revue, au minimum :

- Le changement fait-il **une** chose ? Est-il couvert par un test qui échouerait sans lui ?
- Un contrôle d'accès est-il présent sur **toute** nouvelle route, et testé pour un tiers refusé ?
- Une donnée sensible traverse-t-elle une frontière (props, journal, URL, export) ?
- Les deux langues, les deux moteurs de base (si le projet en supporte deux), les états d'interface vides,
  chargement, erreur ?
- Une migration est-elle **additive** et compatible avec l'ancien code, qui tourne pendant son exécution ?
- La documentation et la version des textes légaux avancent-elles avec le comportement ?
