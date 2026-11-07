# 10. Listes de contrôle

Des listes courtes à dérouler au bon moment. Chaque ligne renvoie à une règle des autres fichiers. Une case non
cochée est un point **supposé faux**.

## 1. Avant de commiter

- [ ] Le changement fait **une seule chose** (PRI-06, GIT-01).
- [ ] Un **test** couvre le comportement ; un correctif de bogue a son test qui reproduit le bogue (TEST-02).
- [ ] Les tests du fichier touché et des modules voisins ont tourné ; ce qui n'a pas tourné est écrit (PRI-08).
- [ ] Formatage et analyse statique **propres** sur les fichiers touchés (CODE-40, CODE-41, CODE-42).
- [ ] Les **textes** sont dans **toutes** les langues (I18N-04).
- [ ] L'écran est vérifié en **thème clair et sombre** et dans les **deux langues** (UI-67, UI-68, I18N-15).
- [ ] La **documentation** et la **version des textes légaux** avancent avec le comportement (PRIV-03, OPS-05).
- [ ] Aucun **secret**, fichier d'environnement, dump ni donnée réelle (GIT-03).
- [ ] Aucun fichier sans rapport (sorties de campagne, brouillons) n'est ajouté (GIT-06).
- [ ] Le message de commit dit **quoi et pourquoi**, sans emoji.

## 2. Nouvelle route ou nouveau point d'entrée

- [ ] **Documents émis uniformes** : en-tête et pied de page partagés, alimentés par la page de l'entreprise (nom, logo, contacts, mention) (UI-133).
- [ ] **Listes de choix qui grandissent** (zones, kits, clients, boutiques, produits, utilisateurs) : cherchables explicitement, filtre sans accents, clavier (UI-134, UI-121).
- [ ] **Image téléversée** : aperçu avant enregistrement et fenêtre de recadrage, au doigt et à la souris (UI-135).
- [ ] **Semaine** choisie dans une liste de semaines ayant des données, composant commun, « Toutes les semaines » pour un filtre (UI-132).
- [ ] **Sortie visible** de tout formulaire ou panneau déplié dans une page : « Garder … » qui referme et efface le champ et les erreurs (UI-131).
- [ ] **Boutons** : l'action et son objet dans les mots de l'utilisateur, jamais un verbe de jargon seul ; geste définitif répété en contour (UI-130).
- [ ] **Étape dépendant de faits enregistrés** (livrée, soldée) : refusée côté serveur sans eux, bouton grisé avec la situation réelle et le geste qui manque (UI-129).
- [ ] **Texte libre (observations, commentaires, notes, motifs) en zone de texte de plusieurs lignes** (UI-128).
- [ ] **Annulation d’un enregistrement sur lequel de l’argent est encaissé, et étapes critiques d’un cycle de vie (livré, soldé, clôturé)** : mot de passe de la personne connectée, vérifié côté serveur, refus journalisé (SEC-15).
- [ ] **Autorisation** vérifiée avant la validation (SEC-21), politique dédiée (CODE-12).
- [ ] **Chaque rôle** testé autorisé et refusé ; un client tiers reçoit **404** (TEST-10, SEC-27).
- [ ] **Validation** par un objet dédié, **affectation de masse** impossible (SEC-41).
- [ ] **Limiteur nommé** si la route est abusable (SEC-70) ; clé choisie avec soin (SEC-71).
- [ ] **Idempotente** si rejouable (DATA-25).
- [ ] **Journal d'accès** : pages ouvertes, connexions refusées et blocages, sans secret, avec conservation bornée et purge tracée (SEC-162).
- [ ] **Copies mensuelles du journal d'audit** : jamais remplacées, vérifiées chaque nuit, lecture tracée, copies purgées par ancienneté et par seuil de disque, manifestes conservés ; purge de la table seulement après copie vérifiée (SEC-163).
- [ ] **Journal d'audit** : toute création, modification ou suppression est tracée automatiquement (qui, quoi, quand, comment, avec quoi, d'où ; une entrée par élément pour une action de groupe ou un import ; SEC-161, SEC-105), alerte si le geste est sensible (SEC-106).
- [ ] La réponse ne transmet **que** les champs prévus (CODE-22).
- [ ] Un lien y mène depuis la navigation (UI-30), visible seulement pour qui a la permission (UI-12).
- [ ] **Clôture de période** (caisse, stock, compte) : écart écrit comme une écriture, période figée y compris par les chemins indirects, clôture immuable et tracée (DATA-76).
- [ ] **Nouvelle permission** : ajoutée au catalogue fermé, aux profils système qui doivent l'avoir, à l'écran des
  profils avec son explication dans toutes les langues (SEC-22, SEC-150 à SEC-160), et il apparaît dans le tableau croisé des droits par profil, menu et action (SEC-164).
- [ ] Les **erreurs** sont des réponses utiles, pas des exceptions (CODE-50, CODE-51).

## 3. Nouvel écran

- [ ] **Regardé en designer UI/UX** : hiérarchie, alignements, rythme des espaces, cohérence avec les autres écrans,
  rien de maladroit ; vu à trois largeurs, en thème clair et sombre, dans les deux langues (UI-00).
- [ ] Tous les **états** : chargement, vide, erreur, succès, permission refusée, hors ligne (UI tableau §2).
- [ ] **Composants** de la bibliothèque, pas de primitive maison (UI-01, UI-02).
- [ ] **Confirmation** pour tout geste difficile à annuler (UI-20 à UI-24).
- [ ] **Champs obligatoires** signalés d'après la validation serveur (UI-44).
- [ ] **Bouton « Enregistrer »** toujours visible, qui change d'apparence selon que le formulaire est modifié ou
  non, et qui prévient avant de quitter avec des modifications perdues (UI-13 à UI-15).
- [ ] **Couleur des boutons** selon leur nature : vert pour enregistrer, contour pour annuler, rouge pour supprimer
  (UI-16).
- [ ] **Accessibilité** : clavier, focus, contraste, couleur jamais seule, mouvement réduit (UI-50 à UI-54).
- [ ] **Mobile** : pas de largeur fixe, cibles de 44 px (UI-51, UI-60).
- [ ] **Formulaires** : champs en grille qui se remplit, champs longs sur toute la ligne, conteneur à la taille du
  formulaire, vérifiés à trois largeurs (UI-82 à UI-84, UI-91), boutons au libellé court et distinct de l'indication du champ (UI-92), placés contre le champ qu'ils valident (UI-93), champ de recherche de largeur bornée (UI-94), titres de section et tuiles de chiffres clés (UI-95), téléphones cliquables (UI-96), écran le plus simple possible et compréhensible sans notice (UI-97), infobulles en mots simples avec un exemple (UI-98), une donnée saisie à un seul endroit (UI-99), chaque alerte avec son bouton d'action (UI-100), sections d'une page en onglets (UI-101), action de l'onglet dans l'onglet (UI-102), champs alignés en haut malgré un message d'erreur (UI-103), titre de fenêtre contextuel (UI-104), aucun code technique montré ni saisi (UI-105), formulaire de création avant la liste (UI-106), liste de référence en tableau avec modification en fenêtre (UI-107), pas d'avertissement de sortie au survol d'un lien (UI-108), journal en phrases (UI-109), fiche à statut = frise des étapes + bloc « Prochaine étape » au bouton nommant l'action (UI-110), action critique confirmée par `ConfirmAction` et refus du serveur affiché (UI-111), transitions fluides entre pages, onglets (liens `?paramètre` compris), blocs d'état et messages qui apparaissent, posées d'office sans attendre qu'on les demande, coupées en mouvement réduit ; page qui arrive par étapes (cadre fixe, sections puis lignes en cascade, 0,6 s au plus), page quittée estompée, survol de tout composant animé en couleur, fond, bordure et ombre sans courbe linéaire, grilles de cartes en cascade (`cascade`), sélection du menu = repère qui glisse dès le clic (UI-112), astérisque rouge sur tout champ obligatoire, `required` aussi sur les listes (UI-113), invitation d'une liste (« — Choisir… — », gris italique) jamais confondue avec une donnée (UI-120), liste de huit choix ou plus recherchable (filtre sans accents, clavier, vrai select dessous) (UI-121), feuille commune des documents commerciaux (UI-122), bouton grisé de l'envoi à la réponse du serveur et modifications toujours détectées, y compris en lot (UI-123), documents d'une liste téléchargeables par ligne, par sélection zippée et par groupe, journalisés (UI-124), boutons et badges teintés selon leur sens, un ton par signification, une couleur propre à chaque étape d’un cycle de vie et montant perçu visible dans la liste, lisibles en clair et en sombre (UI-125, UI-16), blocs de saisie en liste aux fonds alternés (UI-126), contour des champs visible à 3:1 sur tout fond, en clair et en sombre (UI-127), formulaire au-dessus d'une liste repliable (UI-114), action de groupe : portée avec nombre, confirmation chiffrée, échecs isolés et comptés, idempotente (UI-115), liste exportable en CSV, Excel et PDF avec les filtres affichés, mêmes droits que la page, tracée (UI-116), formulaire d'un traitement automatique avec sa phrase de résumé, une seule limite, erreurs sous le champ (UI-117), création en lot en onglet, groupe = découpage déjà utilisé par le métier (liste fermée par numéro, jamais un texte libre), liste cochée, éléments déjà faits grisés, message ne nommant que le non-zéro et taille de lot bornée (UI-118).
- [ ] **Fluidité** : défilement doux, transitions sur chaque état, apparitions sobres, tout coupé en mouvement
  réduit, vérifié à la main dans un navigateur (UI-69 à UI-75). Thème qui se propage depuis le clic (UI-76).
- [ ] **Menu** : icônes colorées par groupe, avec les jetons de la palette, lisibles dans les deux thèmes (UI-35).
- [ ] **Graphiques** : forme adaptée à la tâche, un seul composant et une seule bibliothèque, couleurs en jetons dans
  l'ordre, légende, infobulle, nom accessible, vue en tableau, mouvement réduit respecté (UI-85 à UI-90).
- [ ] **Infobulles** : composant partagé, sur chaque bouton d'icône et chaque chiffre calculé ou terme du métier,
  jamais l'attribut `title` (UI-77 à UI-81).
- [ ] **Listes** paginées, filtrées et triées côté serveur (DATA-70), en-têtes de colonne cliquables par le composant partagé, liste fermée de colonnes, tri repris par l'export (UI-46) ; totaux sur toute la liste, page propre à chaque liste, export au même filtre, composant de pagination partagé à l'apparence définie (UI-47), nombre de requêtes testé (DATA-77).
- [ ] **Textes** traduits, sans jargon ni redondance (I18N-04, UI-40, UI-42).
- [ ] **Visite guidée** ajoutée si l'écran est complexe (UI-34) : 4 à 6 étapes claires et vérifiées contre le comportement réel, bulle qui ne couvre pas l'élément, démarrage unique, bouton de rejeu dans l'en-tête, étapes absentes sautées, clair/sombre/téléphone (UI-119).

## 4. Nouvelle migration

- [ ] Elle **ajoute** : rien n'est renommé ni supprimé (DATA-01).
- [ ] Le **code précédent** continue de fonctionner pendant son exécution (DATA-02).
- [ ] **Portable** entre les moteurs supportés (DATA-06) ; testée sur celui de production si elle touche aux
      transactions ou aux verrous (DATA-11).
- [ ] **Index** et **contraintes** posés (DATA-07) ; cascades pensées (DATA-08).
- [ ] La **reprise de données** existantes se fait par lots (DATA-04).
- [ ] Une migration par client et une centrale : **placée au bon endroit** (DATA-31).
- [ ] Le **plan de retour** est la sauvegarde prise avant (DATA-03).

## 5. Nouvelle dépendance ou nouveau service

- [ ] **Alternatives comparées**, accord du responsable obtenu **avant** l'installation (PRI-09, CODE-61).
- [ ] Service tiers : **vérifié** (société, avis), prix confirmés, disponibilité locale (EXT-01 à EXT-04).
- [ ] **Interface** et pilote de journal d'abord (EXT-10, EXT-11).
- [ ] **Aucun appel réel en test** (EXT-18).
- [ ] Mentionné dans la **politique de confidentialité** s'il reçoit des données personnelles (PRIV-12).
- [ ] Ajouté à l'**inventaire** sans secret (EXT §4) et au **contrôle de production** (EXT cycle §6).
- [ ] **Panne** décidée : fermer ou laisser passer (EXT-17).

## 6. Avant de livrer

- [ ] **Outil de bout en bout** (le meilleur pour le framework, TEST-33) installé et utilisé pour regarder les écrans.
- [ ] Le code est sur la branche principale distante ; **commit en service noté** (OPS §2).
- [ ] **Sauvegarde** prise, surtout si le schéma change.
- [ ] **Version** choisie, **textes légaux** à jour.
- [ ] Le **contrôle de production** passe.
- [ ] Chaque variable d'environnement ajoutée est dans le fichier d'exemple, **commentée** (rôle, défaut, quand la changer, danger) (OPS-67).
- [ ] Une seule chose est livrée (OPS-02).
- [ ] Les **migrations** s'enchaînent avec arrêt au premier échec (OPS-07).
- [ ] **Après** : services sains, pages en 200, journaux sans erreur, santé technique au vert, écran modifié
      vérifié dans le navigateur.
- [ ] **Retour arrière** prêt : commit noté et sauvegarde.

## 7. Avant d'ouvrir au public

Voir `07-livraison-et-exploitation.md` §10. En résumé :

- [ ] Contrôle de production sans erreur, **rôle de la base** compris : trois rôles (migrateur, application, sauvegarde), l'application ne possède aucune table et ne peut pas modifier le journal (OPS-68 à OPS-70).
- [ ] **Suite complète** sur le moteur de production, tests d'interface et de bout en bout.
- [ ] **Grille de sécurité** (§8 ci-dessous) rejouée.
- [ ] **Restauration** éprouvée ; sauvegardes externes chiffrées.
- [ ] **Clé principale copiée** hors du serveur ; **second administrateur** de console.
- [ ] **Textes légaux relus** par un juriste ; identité de l'éditeur renseignée.
- [ ] **Essai réel** de bout en bout sur un vrai téléphone.
- [ ] **Limiteurs** vérifiés en conditions réelles.
- [ ] Ouverture **progressive** : pilote, surveillance, puis extension.

## 8. Grille de sécurité à rejouer

**Cloisonnement.**

- [ ] Pour chaque route du back-office, requête d'un client tiers : **404** (TEST-10, TEST-11).
- [ ] Requête forcée **sans** filtre : la donnée n'existe pas sur la connexion.
- [ ] Job ou tâche **sans client** : échec, pas lecture silencieuse (DATA-33).
- [ ] Jeton public présenté sous le sous-domaine d'un autre client : 404 **dans les deux sens** (DATA-35).

**Concurrence et état.**

- [ ] Deux requêtes sur la dernière unité : une seule aboutit ; cent en parallèle : aucune survente (TEST-12).
- [ ] Action après expiration : refusée ; purge : unités rendues, journal écrit (TEST-15).
- [ ] Double validation d'un même objet : une seule prise en compte (DATA-23).
- [ ] Transition depuis un état inattendu : échec (DATA-24).

**Fichiers déposés.**

- [ ] SVG avec script : refusé. PDF avec script : refusé ou neutralisé. Polyglotte image et HTML : refusé.
- [ ] Image à décompression explosive : refusée. Extension falsifiée : refusée.
- [ ] Métadonnées après réencodage : **absentes**. Fichier atteignable sans URL signée : impossible (SEC-50 à SEC-55).

**Jetons et liens.**

- [ ] Jeton modifié d'un octet, d'un autre contexte, expiré, rejoué : refusé (TEST-18).
- [ ] Appareil hors ligne compromis : incapable de forger un jeton (SEC-120).
- [ ] Lien inconnu, lien altéré, ressource jamais publiée : **même réponse** (SEC-64).

**Argent et comptes critiques.**

- [ ] Modification sans re-authentification : refusée. Sans la permission dédiée : refusée.
- [ ] Validation du délai par le demandeur : refusée ; par un non-privilégié : refusée.
- [ ] Alerte et délai d'activation **effectifs** (SEC-112 à SEC-114).

**Profils et permissions.**

- [ ] Attribution d'une permission non détenue : refusée. Affectation d'un profil plus puissant : refusée.
- [ ] Modification de son propre profil : refusée. Suppression du dernier privilégié : refusée.
- [ ] Profil exigeant la 2FA sans 2FA : back-office fermé ; contrôle **actif en production** (SEC-19).

**Débit et robots.**

- [ ] Chaque limiteur atteint, y compris avec un `X-Forwarded-For` falsifié (TEST-16).
- [ ] Réservations en boucle : bloquées avant épuisement de la ressource (SEC-75).

**Exposition.**

- [ ] Props inspectées sur les écrans sensibles (SEC-90).
- [ ] Export avec valeurs hostiles : formules neutralisées (SEC-43).
- [ ] URL signée réutilisée après expiration : refusée (SEC-55).
- [ ] En-têtes de sécurité présents, CSP sans `unsafe-inline` (SEC-81, SEC-82).
- [ ] `DEBUG` désactivé en production, aucune pile dans une réponse (SEC-141).

## 9. Correction d'un bogue

- [ ] Test qui **reproduit** le bogue, **rouge**, écrit d'abord (TEST-02).
- [ ] Cause **comprise** avant d'écrire le correctif (pas de correctif à l'aveugle).
- [ ] Le même défaut existe-t-il **ailleurs** ? (recherche du même motif dans le code).
- [ ] Les tests voisins repassent ; aucun contrôle affaibli (PRI-05).
- [ ] Si le bogue venait d'un **piège d'environnement**, il est ajouté à la liste des pièges (TEST-50 à TEST-54,
      OPS §9).

## 10. Incident en production

1. **Stabiliser** : un retour arrière (code) ou une restauration (données), selon `07-livraison-et-exploitation.md`.
2. **Ne pas improviser sur le serveur** : si une action hors procédure est nécessaire, la **consigner**.
3. **Comprendre** : journaux, journal d'audit, journal de sécurité, écran de santé.
4. **Évaluer l'exposition** : des données ont-elles été lues, modifiées ? Des secrets sont-ils exposés ? Si oui,
   rotation (SEC-32, SEC-140) et information (PRIV-50).
5. **Corriger avec un test** (§9), livrer selon la procédure.
6. **Écrire** ce qui s'est passé, la cause, ce qui a été changé pour que ça ne revienne pas.
