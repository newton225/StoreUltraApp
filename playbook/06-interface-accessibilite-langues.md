# 06. Interface, accessibilité et langues

Comment un écran se construit pour être clair, accessible, traduisible et cohérent avec les autres.

## 0. Posture

- **UI-00.** **Toute interface se conçoit et se relit comme le ferait un designer UI/UX expert**, pas seulement
  comme du code qui fonctionne. Avant d'écrire : *qui* utilise cet écran, *dans quel but*, *quelle est
  l'action principale* ? Pendant : une hiérarchie visuelle claire (un seul point d'entrée du regard), des
  **alignements** et un **rythme d'espacement** réguliers, des champs groupés par sens, des composants et des
  couleurs **cohérents** avec le reste du produit, tous les états (UI tableau §2). Après : **regarder le
  résultat** (capture d'écran ou ouverture réelle) à **trois largeurs** (téléphone, tablette, grand écran), dans
  **les deux thèmes** et **les deux langues**, et se demander honnêtement : est-ce beau, est-ce évident, est-ce
  aligné, est-ce qu'un nouvel utilisateur comprendrait sans aide ? Ce qui est maladroit se corrige **avant** de
  rendre la main, sans attendre la remarque du responsable. Une rangée de champs avec un trou, un bouton perdu,
  un texte qui déborde, une couleur qui jure, un espace mal réparti sont des défauts, pas des détails. On
  **propose** ce qu'on améliorerait en plus (AI-01), on ne l'impose pas (AI-15).

## 1. Composants : réutiliser, ne pas réécrire

- **UI-01.** **Aucune primitive d'interface n'est codée à la main** (bouton, champ, liste déroulante, case à
  cocher, interrupteur, onglets, dialogue, infobulle, menu, notification, tableau, badge, calendrier). On installe
  une bibliothèque **headless et accessible** et on compose avec elle.
- **UI-02.** Avant d'écrire un composant, vérifier qu'il n'existe pas déjà dans la bibliothèque installée ou dans
  le dossier des composants du projet. Un composant maison se justifie seulement pour un élément **propre au
  métier**.
- **UI-03.** **Icônes** d'une bibliothèque unique, jamais dessinées à la main ; aucun emoji dans l'interface.
- **UI-04.** **Tableaux de données** : une bibliothèque de tableau pour le tri, le filtre, la pagination et la
  sélection, jamais une implémentation maison. Le rendu utilise les primitives de table du projet.
- **UI-05.** **Graphiques**, **dates**, **lecture de codes** : une bibliothèque chacun, choisie une fois, déclarée
  dans le fichier d'instructions du projet.
- **UI-06.** **Formulaires** : une seule manière dans tout le projet. Les règles sont celles du serveur ; le
  client ne les redéfinit pas. Si un formulaire existant utilise l'outil natif du cadre, on ne ré-introduit pas
  une seconde bibliothèque de formulaires.
- **UI-07.** **Validation en temps réel** : à la sortie d'un champ, le serveur rejoue les règles de ce champ seul
  (une seule définition des règles). **Jamais sur une route limitée ou à effet de bord** (inscription publique,
  connexion) : chaque requête de validation compterait dans le limiteur.
- **UI-08.** **Le message d'un champ disparaît dès qu'on le retouche.** Les erreurs de formulaire sont attachées
  au champ concerné, pas affichées en bloc en haut.
- **UI-09.** **Création à la volée d'une donnée de référence.** Un champ de choix dont la liste peut être vide ou
  incomplète (région, mode de règlement, catégorie…) propose un bouton **« + Ajouter »** à côté du champ,
  qui ouvre un petit dialogue : la valeur est enregistrée tout de suite, **sans quitter la page ni perdre la
  saisie en cours**, puis **choisie aussitôt** dans le champ. Le bouton n'apparaît que pour qui a le droit de créer
  cette donnée (le serveur le refuse aux autres), les mêmes règles de validation que l'écran d'administration
  s'appliquent (UI-06) et les erreurs s'affichent sous les champs du dialogue (UI-08). Quand la liste est vide, le
  champ dit quoi faire (voir « Vide » ci-dessous). Avec un clavier, Entrée valide le dialogue et ne soumet pas le
  formulaire en dessous.

## 2. États complets

Chaque vue prévoit ses états, tous :

| État | Ce qu'il faut |
| --- | --- |
| Chargement | Indicateur sur le bouton ou la zone, jamais un écran figé |
| Vide | Explique **quoi faire**, pas seulement « aucun résultat » ; différencie « rien encore » de « rien avec ce filtre » |
| Erreur | Dit ce qui s'est passé et l'action suivante |
| Succès | Confirmation brève (notification), pas une page entière |
| Permission refusée | Explique la raison, sans révéler ce qu'on n'a pas le droit de savoir |
| Hors ligne | Dit ce qui reste possible et ce qui sera synchronisé |

- **UI-10.** **Retour immédiat** sur chaque action : état de chargement du bouton, confirmation, mise à jour
  optimiste **seulement** quand l'échec est réversible.
- **UI-11.** **Une réponse du serveur donne un retour** : succès en notification, échec sous le champ ou en
  notification. Un geste silencieux est un bogue.
- **UI-12.** Un écran **sans la permission requise** n'apparaît pas dans la navigation ; la route, elle, refuse.
- **UI-13.** **Le bouton « Enregistrer » est toujours visible** : dans une barre collée au bas de l'écran
  (`position: sticky`), quelle que soit la longueur du formulaire, jamais perdu tout en bas d'une page à faire
  défiler. Un seul composant partagé du projet porte cette barre, pour que tous les formulaires l'aient. Le
  bouton se place **à gauche**, le message d'état à sa droite (choix du responsable du produit).
- **UI-14.** **Le bouton dit où en est le formulaire.** Il change d'**apparence** selon l'état : *modifications non
  enregistrées* (couleur principale, repère d'attention **et** texte, jamais la couleur seule : UI-43) ; *rien à
  enregistrer* (aspect calme, avec le texte qui le dit) ; *enregistrement en cours* (indicateur, bouton inactif :
  UI-22). Un formulaire de **création** est toujours à l'état principal, puisque rien n'est encore enregistré. L'état
  vient du formulaire (modifié depuis l'affichage ou le dernier enregistrement), pas d'une comparaison à la main, et
  les messages sont dans toutes les langues (I18N-04). Le changement d'aspect est animé (UI-70).
- **UI-15.** **Quitter avec des modifications non enregistrées prévient.** Fermer l'onglet, recharger ou naviguer
  ailleurs alors que le formulaire est modifié demande confirmation (UI-20), sans jamais perdre la saisie en silence.
- **UI-16.** **La couleur d'un bouton dit sa nature**, la même dans tout le projet, pour qu'on reconnaisse le geste
  avant de lire le texte :
  | Nature du geste | Variante | Exemples |
  | --- | --- | --- |
  | **Enregistrer, valider, émettre, envoyer** | **succès** (vert) | Enregistrer, Créer, Encaisser, Émettre |
  | **Annuler, retourner, fermer, abandonner** | **contour** neutre | Annuler, Retour, Fermer |
  | **Supprimer, retirer, annuler une opération** | **destructif** (rouge) | Supprimer, Retirer, Annuler la commande |
  | **Aller ailleurs, ouvrir, créer un nouvel objet, télécharger, exporter** | **information** (bleu) ; **contour bleu** pour les boutons répétés sur chaque ligne d'une liste | Nouveau client, Télécharger le zip, Exporter, Proforma |
  | **Suspendre, mettre en pause, remplacer, archiver** (réversible mais lourd de conséquences) | **attention** (ambre) | Mettre en pause, Annuler et remplacer |
  | Action secondaire d'un écran (chercher, filtrer, fermer une ligne) | **secondaire** (gris) ou **fantôme** | Rechercher, Filtrer, Ouvrir |
  Les couleurs viennent de **jetons** de design à deux valeurs, clair et sombre (UI-61), jamais d'une teinte écrite en
  dur ; le texte du bouton garde un contraste d'au moins 4,5:1 (UI-51). Deux gestes opposés (enregistrer et
  annuler) ne prennent **jamais** la même couleur, et la couleur ne porte jamais seule le sens : le texte le dit
  (UI-43). **Une seule action principale** par zone d'écran.

## 3. Confirmation et gestes risqués

- **UI-20.** **Rien d'irréversible sans confirmation** : suppression, purge, rejet, envoi en masse, validation
  d'un paiement, duplication, clôture. Le mot « irréversible » n'est pas le seul critère : **toute action qui ne
  s'annule pas facilement** demande la sienne.
- **UI-21.** La confirmation **nomme la conséquence exacte** et la chose touchée (« Une copie de "X" sera créée en
  brouillon... ») ; le résumé permet de vérifier qu'on agit sur la bonne ligne.
- **UI-22.** Le **focus s'ouvre sur « Annuler »**, jamais sur l'action. Le bouton d'action est inactif pendant
  l'envoi (double clic).
- **UI-23.** Une confirmation s'ouvre depuis un menu **non modal** quand elle est lancée depuis ce menu (sinon
  certains composants laissent la page inerte après la fermeture du menu).
- **UI-24.** Une action destructrice est **visuellement distincte** (rouge, séparée) et jamais placée à côté d'une
  action courante sans séparation.

## 4. Navigation

- **UI-30.** **Aucun écran orphelin** : un écran construit est accessible **dans le même changement** (lien de
  barre latérale pour un espace global, lien contextuel depuis la ressource dont il dépend). En reprenant une
  étape, vérifier d'abord qu'aucun écran déjà construit n'est resté sans lien.
- **UI-31.** Les **destinations d'une ressource** se rangent par moment d'usage (suivi, jour J, fiche) derrière un
  seul bouton, pas en dix liens par carte.
- **UI-32.** Le bouton **« retour »** du navigateur ne ramène pas à un formulaire déjà envoyé (connexion,
  inscription : vider l'historique de navigation après l'action).
- **UI-33.** **Un écran, une décision** : le parcours long est séquencé en **étapes numérotées** qui disent où se
  passe l'action, y compris quand elle a lieu hors de l'application.
- **UI-34.** Des **visites guidées** accompagnent les écrans complexes : une visite par écran, lancée à la
  première ouverture, rejouable par un bouton, suivie **en base** (par membre, quel que soit l'appareil), avec un
  repère par étape ; une étape dont le repère est absent (menu replié, bouton réservé à un autre profil) est
  **sautée**. Les textes d'une visite décrivent ce que fait le produit.
- **UI-35.** **Les icônes du menu se colorent par groupe**, une teinte par groupe de rubriques (une seule par
  groupe : pas une couleur par entrée, qui donnerait un arc-en-ciel sans sens). Les teintes viennent de la **même
  palette validée que les graphiques** (UI-87), en **jetons** à deux valeurs, clair et sombre, de sorte que le menu
  reste harmonieux dans les deux thèmes ; un groupe « technique » (configuration) prend un gris bleuté pour
  rester en retrait. L'icône porte la couleur, le libellé garde la couleur du texte, et la couleur n'est jamais la
  seule information : chaque entrée a son texte (UI-43). Les icônes colorées gardent un contraste d'au moins 3:1
  avec le fond de la barre latérale, dans les deux thèmes.

## 5. Textes et ton

- **UI-40.** **Pas de jargon.** L'utilisateur ne lit ni statut technique, ni nom de table, ni mot de
  l'architecture interne. (Dans un produit multi-clients, le mot technique « tenant » devient « organisation ».)
- **UI-41.** **Messages d'erreur utiles** : ce qui s'est passé, ce qu'on peut faire, jamais un code.
- **UI-42.** **Pas de redondance** : un badge « Invité » à côté du texte « L'invité » n'apprend rien ; n'afficher
  l'auteur nommé que quand il y a quelqu'un à nommer.
- **UI-43.** Une **information critique** (« pas encore envoyée », « refusé ») se distingue par plus que sa place :
  la couleur d'état **et** le texte, jamais la couleur seule.
- **UI-44.** Les **champs obligatoires** sont signalés (astérisque rouge plus mention pour lecteur d'écran, légende
  en tête d'un formulaire de plusieurs champs) et **déduits de la validation serveur**, jamais devinés. Un champ
  exigé seulement à la publication porte sa propre marque « pour publier » ; la page nomme ce qui manque encore.
- **UI-45.** **Saisie qui reconnaît sa fin** : un code à N chiffres s'envoie au dernier chiffre (le collage ne
  garde que les chiffres) ; ne pas l'appliquer à un formulaire de plusieurs champs.
- **UI-46.** **Listes triées, filtrées, paginées** : en-têtes de tri clairs, filtre ramenant à la première page,
  paramètres dans l'adresse pour que le retour arrière garde l'état. **Le tri se fait par un composant d'en-tête partagé** :
  un clic trie dans l'ordre croissant, le suivant dans l'ordre décroissant ; la colonne triée porte une flèche et
  `aria-sort`, les autres une icône discrète (jamais la couleur seule, UI-52) ; le tri porte sur **toute la liste, côté
  serveur** (DATA-70), à partir d'une **liste fermée de colonnes** que le serveur accepte (toute autre valeur retombe sur
  l'ordre par défaut, SEC-45), avec l'identifiant en dernier critère pour que les pages ne se chevauchent pas ; le choix
  **revient avec les filtres** de la page (et ne se perd pas quand on filtre), la pagination revient à la première page,
  et **l'export suit le même tri** (UI-116). Une colonne qu'on ne peut pas trier honnêtement (une valeur calculée à
  l'écran) n'a pas d'en-tête cliquable.

- **UI-47.** **La pagination a une seule apparence, définie ici, et elle se fait par un composant partagé** : jamais
  recopiée page par page, jamais « précédent / suivant » seuls. Sous la liste, dans une **section arrondie** (bordure fine et coins arrondis, comme les autres blocs de l'écran, jamais un bandeau carré), sur **une ligne** : à **gauche**
  « Page X sur Y » (texte discret) ; à **droite**, dans cet ordre, **Premier**, **Précédent**, les **numéros de page**,
  **Suivant**, **Dernier**. Les numéros sont la première page, la dernière, la page courante et ses deux voisines, avec
  des **points de suspension** à la place des pages omises (un trou d'une seule page affiche cette page). La **page
  courante est encadrée** et marquée pour les lecteurs d'écran (`aria-current="page"`) : la couleur ne la porte
  pas seule (UI-52). Un pas impossible (Premier et Précédent sur la première page, Suivant et Dernier sur la dernière)
  est **atténué et inactif**. **Compacte à la souris** (boutons d'environ 32 px de haut) et **44 px au moins sur écran tactile** (UI-51, variante « pointeur grossier »), libellés traduits dans toutes les langues, retour à
  la ligne propre sur mobile, **défilement conservé** au changement de page. Rien n'est dessiné quand tout tient sur
  une page. Les liens **gardent les filtres** de l'adresse ; quand une page porte plusieurs listes paginées, chacune
  a son **propre paramètre de page** (DATA-77).

## 6. Accessibilité

- **UI-50.** **Navigation clavier complète**, focus visible, rôles ARIA fournis par les composants headless.
- **UI-51.** **Contraste du texte** au moins 4,5:1, cibles tactiles au moins 44 px.
- **UI-52.** **Aucune information portée par la couleur seule.**
- **UI-53.** **`prefers-reduced-motion`** respecté : tout mouvement non essentiel se désactive.
- **UI-54.** **Aucun contenu visible seulement après une animation** ; le texte reste lisible et sélectionnable
  sans mouvement.
- **UI-55.** Les champs ont des **libellés** (visibles ou réservés aux lecteurs d'écran), les images utiles un
  texte alternatif, les titres une hiérarchie.
- **UI-56.** Un **composant tiers** (défi anti-robot, carte) suit le thème de la page, s'adapte à la largeur du
  formulaire et ne s'affiche qu'en cas de besoin (affichage sur doute).

## 7. Mise en page et design

- **UI-60.** **Mobile d'abord** pour le parcours des utilisateurs finaux et les écrans de terrain ; densité plus
  élevée pour le back-office. Aucune largeur fixe ; grilles qui se replient ; grilles en `minmax(0, 1fr)` pour
  éviter les débordements.
- **UI-61.** **Jetons de design** (couleurs, espacements, rayons) déclarés en variables ; **thème clair et
  sombre** pour chaque couleur ; un fond défini explicitement.
- **UI-62.** Couleurs de **marque d'un client** : appliquées seulement au parcours de ses utilisateurs, à ses
  documents et à ses messages ; le back-office reste neutre.
- **UI-63.** **Typographie** : sans-serif système pour l'interface, polices d'accent réservées aux documents et
  aux invitations.
- **UI-64.** **Animation sobre** : durées de 0,2 à 0,6 s, courbes douces, faible amplitude, opacité et translation
  plutôt que rotation et rebond ; **une seule animation dominante par section** ; seulement `transform` et
  `opacity`. Le mouvement **explique** un changement d'état, il ne décore pas.
- **UI-65.** **Le produit comme héros** sur un site commercial : montrer les artefacts réels (billet, file de
  preuves) plutôt que des illustrations abstraites.
- **UI-66.** **Données d'exemple visibles** : un écran bâti avant son serveur porte un bandeau et un indicateur ;
  aucun chiffre d'exemple ne se présente comme réel.
- **UI-67.** **Mode d'affichage choisi par l'utilisateur** : clair, sombre ou système (défaut). Un sélecteur est
  accessible depuis tout écran. Le choix est mémorisé (profil pour un utilisateur connecté, appareil sinon) et
  appliqué **avant le premier rendu**, sans éclair du mauvais thème.
- **UI-68.** **Chaque écran, composant et graphique est vérifié dans les deux thèmes**, avec un contraste
  suffisant dans chacun (UI-50 à UI-54). Les **documents destinés à l'impression ou au PDF** restent clairs,
  quel que soit le thème de l'interface.
- **UI-69.** **Défilement fluide** : les ancres internes et le retour en haut défilent en douceur
  (`scroll-behavior: smooth`), **uniquement** sous `prefers-reduced-motion: no-preference`. Un en-tête collant ne
  masque jamais la cible d'une ancre (marge de défilement sur chaque section ciblée). Aucun défilement détourné :
  pas de « scroll-jacking », pas de molette dont la vitesse est modifiée, pas de barre de défilement maison
  (le défilement natif garde l'inertie du système).
- **UI-70.** **Toute transition d'état est animée** : survol, focus, ouverture et fermeture (menu, dialogue,
  panneau), changement de thème, apparition d'une notification. Durée de 0,15 à 0,3 s pour un geste de l'utilisateur,
  0,3 à 0,6 s pour une entrée de contenu ; courbe douce (ease-out), jamais linéaire. Une propriété listée
  explicitement (`transition-colors`, `transition-transform`), jamais `transition: all`.
- **UI-71.** **Apparition au défilement** : un bloc sous la ligne de flottaison peut apparaître une seule fois
  (fondu et légère translation, avec un décalage de 60 à 100 ms entre éléments frères d'une grille). C'est un
  **enrichissement progressif** (voir UI-54) : un bloc déjà visible au chargement, un navigateur sans
  `IntersectionObserver` ou `prefers-reduced-motion: reduce` affichent le contenu **immédiatement**, sans
  masquage préalable ni saut de mise en page.
- **UI-72.** **Fluidité mesurable** : 60 images par seconde sur un appareil modeste. On anime `transform` et
  `opacity` seulement (UI-64) ; pas de `width`, `height`, `top`, `margin` animés ; `will-change` posé seulement
  sur ce qui s'anime et retiré ensuite. Une animation ne provoque **aucun décalage de mise en page** (réserver
  la place des images, polices et blocs différés).
- **UI-73.** **Les mouvements sont codés une fois** : keyframes et classes d'animation dans la feuille de style
  globale, composant d'apparition unique réutilisé ; jamais une bibliothèque d'animation ajoutée pour un effet
  qu'un CSS de dix lignes fait. Le mouvement se déclare **dans un seul bloc**
  `@media (prefers-reduced-motion: no-preference)` : hors de ce bloc, rien ne bouge.
- **UI-74.** **Pas d'animation qui retarde l'action** : un bouton, un lien ou un champ reste utilisable pendant
  qu'il s'anime ; aucune animation d'entrée ne dépasse 0,6 s ; rien ne boucle à l'infini sauf un indicateur de
  chargement.
- **UI-75.** **Vérification** : défiler la page de haut en bas, survoler et naviguer au clavier sur un
  navigateur réel, puis recommencer avec « réduire les animations » activé dans le système. Les tests
  automatisés ne voient pas le mouvement : c'est une vérification manuelle de la liste de contrôle.
- **UI-76.** **Le changement de thème se propage** depuis le point où l'utilisateur a cliqué (ou depuis l'élément
  focalisé s'il a agi au clavier) : un cercle découvre le nouveau thème par-dessus l'ancien, en 0,6 s au plus
  (API View Transitions, animation du `clip-path`). Le thème **change simplement**, sans animation, quand le
  navigateur ne gère pas l'API, quand `prefers-reduced-motion: reduce` est actif, quand il ne change pas
  réellement, et quand il est seulement aligné sur le profil au chargement.

### Infobulles

- **UI-77.** **Une infobulle est le composant de la bibliothèque**, jamais l'attribut HTML `title` : il n'est pas
  stylé, n'apparaît pas au clavier, met du temps à s'afficher et se lit mal. Un seul composant partagé du projet
  enveloppe l'élément visé, pour que toutes les infobulles aient le même aspect et le même comportement.
- **UI-78.** **Tout bouton ou lien sans texte visible** (icône seule) porte un **nom accessible** et une
  **infobulle du même texte**. Un bouton qui s'efface, se retourne ou change d'état (afficher / masquer) change
  aussi son infobulle.
- **UI-79.** **Un terme du métier ou un chiffre calculé s'explique**, dans une infobulle déclenchée par une petite
  marque d'aide placée à côté du libellé (focalisable au clavier) : ce que le chiffre compte, ce qu'il exclut,
  comment il se calcule. Une ou deux phrases, sans jargon (UI-40).
- **UI-80.** **L'infobulle complète, elle ne remplace rien.** Elle s'ouvre au survol **et** au focus clavier, se ferme
  par Échap, ne masque pas l'élément qu'elle décrit, et ne contient ni lien, ni bouton, ni long texte. Une
  information **nécessaire** pour agir reste visible à l'écran (un écran tactile n'a pas de survol).
- **UI-81.** **Les textes d'infobulle sont dans toutes les langues** (I18N-04), comme n'importe quel autre texte. Sur un
  graphique, le survol d'une marque affiche l'infobulle avec le libellé et la valeur formatée dans la langue de
  l'interface.

### Mise en page des formulaires

- **UI-82.** **Un formulaire occupe l'espace**, il ne s'empile pas dans une colonne étroite au milieu d'un grand
  écran. Les champs sont posés dans une **grille de six colonnes** (une seule colonne sur téléphone), où chaque champ
  prend **un tiers, une moitié, deux tiers ou toute la ligne**. La grille et ces quatre largeurs sont définies
  **une fois** (constantes partagées), pas recopiées formulaire par formulaire. Une grille « qui se remplit toute
  seule » (`auto-fit`) est refusée : elle laisse des trous et des champs orphelins, et l'alignement est laid.
- **UI-83.** **Chaque champ a la largeur de ce qu'il contient.** Un champ court (nom, téléphone, montant, date, choix
  dans une liste) tient dans une colonne ; un champ long ou libre (adresse, observations, liste de cases à cocher,
  titre de section) prend **deux tiers ou toute la ligne**. On n'étire jamais un champ court sur toute la largeur,
  et on ne coince jamais un champ long dans une colonne. **Chaque rangée est pleine** : ses largeurs font
  ensemble les six colonnes (3 + 3, 2 + 2 + 2, 4 + 2, 6), jamais un champ seul avec un vide à côté. On range les
  champs par groupes qui se suivent (identité, contact, adresse, observations) et on choisit leurs largeurs pour
  que cela tombe juste, quitte à changer l'ordre des champs ; quand un champ facultatif disparaît (un statut
  réservé au bureau), son voisin reprend la place.
- **UI-84.** **Le conteneur de la page est à la taille du formulaire** : environ 48 rem pour un formulaire de quatre à
  six champs, 64 rem pour un formulaire de dix champs ou plus. Les champs liés restent voisins (identité, puis
  contact, puis adresse). La barre d'enregistrement (UI-13) occupe toute la largeur de la grille. Un formulaire se
  vérifie à trois largeurs : téléphone, tablette, grand écran.

- **UI-91.** **L'ordre des champs suit les groupes, pas la base de données** : identité (nom, numéro), contact
  (téléphone, personne), lieu (zone, localité), adresse, puis observations. Un champ court isolé (statut, date)
  se range **à côté d'un champ long** (adresse 4 + statut 2) au lieu de finir seul sur sa ligne en bas de page,
  et quand il disparaît le champ long reprend toute la ligne. Une capture où un champ reste seul avec un vide à
  côté est un défaut à corriger sur tous les formulaires.

- **UI-92.** **Un bouton porte un verbe court qui dit ce qui va se passer** (« Rechercher », « Enregistrer »,
  « Filtrer »), jamais le texte d'aide ou l'indication d'un champ. Un même texte ne sert pas à deux usages : le
  champ de recherche a son indication (« Rechercher par nom, téléphone… »), le bouton a son propre libellé,
  dans une clé de langue distincte et dans toutes les langues. Le bouton garde la largeur de son libellé.

- **UI-93.** **Un bouton se place contre le champ sur lequel il agit, et seuls les champs qui en ont besoin en ont un.**
  Dans une barre de filtres, les listes déroulantes **et les dates** filtrent dès qu'on change leur valeur
  (aucun bouton) ; seul le champ de texte, qui ne filtre qu'à la validation (ou à la touche Entrée), a son bouton
  « Rechercher » **collé à lui**, à sa droite. Une barre sans champ de texte n'a **aucun bouton**. On ne met
  jamais un bouton « Filtrer » au bout de la ligne pour des champs qui s'appliquent déjà seuls.
- **UI-94.** **Un champ de recherche a une largeur bornée** (environ 22 à 26 rem, bouton compris), il ne prend pas
  « tout ce qui reste » de la ligne : sur grand écran un champ de 60 rem pour taper un nom est disproportionné
  et rejette les filtres loin de lui. La barre de filtres est alignée à gauche, chaque contrôle a la largeur de
  ce qu'il contient (colonne `minmax(0,26rem)` plutôt que `1fr`).

- **UI-95.** **Hiérarchie d'une page de détail** : le titre de page (`text-xl`), puis des titres de section lisibles
  (`text-base font-semibold`, jamais plus petits que le texte qu'ils annoncent), et les chiffres clés d'une fiche
  (totaux d'une commande, soldes) en **tuiles** (cadre, intitulé discret, valeur en gras) plutôt qu'en liste de
  texte. Une fiche qui regroupe beaucoup de blocs (commande, achat) a un conteneur d'environ 56 rem.
- **UI-96.** **Un numéro de téléphone affiché est cliquable** (`tel:`) : l'agent de terrain et le bureau appellent
  d'un geste depuis chaque liste qui montre un contact.

- **UI-97.** **Une interface est la plus simple et la plus intuitive possible** : chaque écran sert une tâche
  principale, visible au premier coup d'œil, et on comprend quoi faire sans lire une notice. Concrètement : peu de
  champs à la fois (les champs rares ou avancés sont repliés ou dans un second temps), des valeurs par défaut
  justes (la date du jour, le mode le plus courant), des mots du métier et non du système (« Rechercher », pas
  `order.created`), une seule action principale par écran (UI-16), pas de bouton qui n'a pas d'effet, pas de
  décision demandée que l'application peut prendre seule, et jamais de donnée technique brute (JSON, codes,
  identifiants) montrée à l'utilisateur. Si un écran demande une explication, on le simplifie avant d'ajouter un
  texte d'aide (les infobulles de UI-77 complètent un écran simple, elles ne réparent pas un écran confus).
  Avant de livrer un écran, on se pose la question : « une personne qui n'a jamais vu l'application sait-elle
  quoi faire ici ? ».

- **UI-98.** **Une infobulle d'aide parle comme à quelqu'un qui découvre l'application** : une ou deux phrases en mots
  simples, ce qui se passe **concrètement** quand on change la valeur, et un **exemple chiffré** quand le champ est
  un nombre. Pas de mot interne (« bloquée », « cycle », « statut ») sans le dire autrement. Le libellé du champ
  dit déjà ce qu'on règle (« Jours de retard avant alerte »), l'infobulle dit pourquoi et avec quel effet. Une
  infobulle que le responsable ne comprend pas est un défaut du texte, à réécrire.

- **UI-99.** **Une information se saisit à un seul endroit** : les autres écrans la montrent ou renvoient vers elle
  par un lien, ils ne proposent pas un second champ pour la même donnée (le registre de commerce se saisit dans les
  documents administratifs, les Paramètres y renvoient). Deux endroits pour une valeur finissent par se contredire.
- **UI-100.** **Une alerte propose son action** : « À ajouter : registre de commerce » est suivie d'un bouton
  « Ajouter » qui ouvre le formulaire déjà rempli (type, propriétaire), « Expire bientôt » d'un bouton
  « Renouveler ». Une alerte sans geste pour la résoudre oblige à chercher où agir. Le texte dit ce qu'il faut
  faire, pas l'état interne (« À ajouter », pas « Document requis manquant »), et nomme le propriétaire comme
  l'utilisateur le connaît (« Mon entreprise », jamais le nom technique de l'application).

- **UI-101.** **Ce qui change de section sur une même page est un onglet, pas une rangée de boutons.** Les onglets
  (composant `TabLinks`) sont alignés sur un trait, l'onglet courant est souligné et annoncé aux lecteurs d'écran,
  les autres sont discrets et se soulignent au survol ; chaque onglet est un lien (l'adresse change, le bouton
  « précédent » fonctionne). Un petit compteur rouge dit ce qui attend dans un onglet (documents à ajouter).
  Les boutons sont réservés aux actions (UI-16), jamais à la navigation entre sections.

- **UI-102.** **L'action d'un onglet vit dans cet onglet** : le bouton « Ajouter … » se place dans le corps de
  l'onglet, sur la ligne de son titre, à droite, et non dans l'en-tête de la page. Il s'applique à ce que l'onglet
  montre (ajouter un document de l'entreprise, d'une boutique, d'un client), et le formulaire qu'il ouvre est déjà
  réglé sur cet onglet. Le bouton de l'en-tête de page est réservé aux pages sans onglets.

- **UI-103.** **Un message d'erreur ne déplace pas les champs voisins.** Dans une grille de formulaire, chaque champ
  s'aligne **en haut** de sa cellule (`items-start`, déjà dans `formGrid`) : quand l'erreur d'un champ agrandit sa
  colonne, les autres champs de la rangée gardent leur place au lieu de descendre ou de s'étirer. Tout formulaire
  s'essaie avec une erreur affichée sur un seul champ d'une rangée à plusieurs colonnes.

- **UI-104.** **Une fenêtre dit dans quel contexte elle s'ouvre** : son titre nomme ce qu'elle concerne (« Ajouter un
  document d'une boutique », pas seulement « Ajouter un document »), pour que l'utilisateur sache où le document
  sera rangé sans regarder derrière la fenêtre. Le titre suit l'onglet ou la fiche d'où elle a été ouverte.

- **UI-105.** **Le code interne d'une donnée de référence ne se montre pas et ne se saisit pas.** Modes de paiement,
  types de frais, types de documents : l'utilisateur ne voit que leurs noms, le code technique est **calculé par
  l'application** à partir du nom français (minuscules, chiffres, tirets bas, rendu unique par un numéro) et
  figé à la création. Aucune colonne « Code » dans les listes, aucun champ « Code » dans les formulaires.

- **UI-106.** **Sur un écran qui porte à la fois un formulaire de création et la liste, le formulaire vient en premier,
  toujours** : l'action la plus fréquente est visible sans défiler, et la liste (qui peut compter des dizaines de
  lignes) vient dessous. Cela vaut pour tous les écrans de référence (catégories, types…). Un
  écran à onglets met le formulaire (ou son bouton) dans le corps de l'onglet (UI-102). Cela vaut aussi pour
  l'onglet d'une fiche : le formulaire d'ajout (« Enregistrer un règlement ») est
  **au-dessus** de l'historique correspondant, jamais en dessous.

- **UI-107.** **Une liste de référence est un tableau, la modification se fait dans une fenêtre.** Les types (de documents,
  de frais), modes de paiement et autres données de référence se montrent en **tableau** : le nom, la catégorie,
  des pastilles pour les règles (requis, expire…) et le statut, et un bouton « Modifier » qui ouvre le formulaire
  dans une fenêtre. On ne répète pas un formulaire complet (mêmes libellés, mêmes cases, un bouton Enregistrer)
  pour chaque ligne : une liste de dix éléments doit tenir en dix lignes lisibles, pas en dix cartes.

- **UI-108.** **L'avertissement de sortie ne se déclenche que sur une vraie sortie** : un clic sur un lien ou sur le
  bouton « précédent » avec des modifications non enregistrées. Il ne se déclenche jamais au survol d'un lien (le
  préchargement de la page n'est pas une sortie), ni à l'envoi du formulaire lui-même, ni à un rechargement de la
  même page. Une page qui porte plusieurs formulaires modifiés pose **une seule question** (garde unique, hook
  `useLeaveGuard`), jamais une par formulaire, et le formulaire en cours d'envoi ne compte plus comme modifié.
  Une fenêtre de confirmation qui s'ouvre sans qu'on ait cliqué est un défaut bloquant. En développement, la page
  se recharge quand on enregistre un fichier du projet : le navigateur peut alors demander confirmation, ce qui
  ne se produit pas en production.

- **UI-109.** **Un journal se lit comme des phrases, pas comme des codes** : l'action s'affiche en toutes lettres
  (« Commande créée », pas `order.created`), l'élément avec le nom du métier (« Commande #10 »), les valeurs en
  pastilles. Une action sans phrase de traduction retombe sur son code, jamais sur une page vide. Toute nouvelle
  action journalisée (`AuditLog::record`) reçoit sa phrase en français et en anglais.
- **UI-110.** **Un élément qui avance par étapes montre où il en est et ce qu'il faut faire ensuite.** Une frise
  de toutes les étapes (faites cochées, étape en cours marquée, étapes à venir en pointillé), puis **un seul bloc
  « Prochaine étape »** : une phrase qui dit ce qui se passe ensuite et **un bouton à l'infinitif qui nomme
  l'action** (« Valider le devis », pas « Étape suivante : Devis validé »). **Chaque passage d'étape ouvre
  une fenêtre de confirmation** (UI-20 à UI-22) qui nomme l'élément, l'étape visée et sa conséquence, focus sur
  « Annuler ». Le geste destructeur
  (annuler) est discret, avec confirmation et un bouton pour renoncer. Pas de pastille de statut en double de la
  frise, pas de mot technique (« lignes figées »), pas de répétition d'un mot (« Article Article… »), et un total en pied
  de tableau. Vaut pour toute fiche à statut.

- **UI-111.** **Toute action critique passe par la confirmation commune** (`ConfirmAction`) : envoi
  d'argent, remboursement, émission d'un document, passage d'une étape, annulation, fin d'un abonnement ou d'un
  contrat, suppression d'un fichier. La fenêtre nomme la conséquence (UI-21), jamais le `window.confirm`
  du navigateur. Dans un formulaire, les champs sont vérifiés avant d'ouvrir la fenêtre. **Un refus du serveur
  s'affiche toujours** à côté de l'action (une action lancée hors formulaire reçoit son propre message
  d'erreur) : un bouton qui ne fait rien en silence est un défaut. En ajoutant un bouton qui modifie des données,
  on se demande « est-ce que cela s'annule facilement ? » ; sinon, confirmation.

- **UI-112.** **Naviguer et changer d'état est toujours fluide, jamais un saut sec.** (1) **Entre deux pages** :
  la nouvelle page apparaît en fondu avec une légère montée (0,25 s, 0,5 rem, `ease-out`), posée **une fois dans la
  mise en page** (conteneur identifié par le chemin de la page, **sans** la chaîne de requête : un filtre ou un
  rechargement de la même page ne rejoue pas l'animation). (2) **Entre onglets ou panneaux d'une même page** :
  fondu de 0,2 s sur le contenu de l'onglet (conteneur identifié par l'onglet). (3) **Un composant qui change
  d'état** (étape atteinte, bouton actif, ligne ajoutée, fenêtre qui s'ouvre) anime sa couleur ou son opacité
  (UI-70), jamais `transition: all`. Les classes (`page-enter`, `fade-in`, `fade-up`) sont écrites **une fois**
  dans la feuille de style globale, dans le bloc `prefers-reduced-motion: no-preference` (UI-73) : sans
  préférence de mouvement, la page s'affiche sans animation. Pas de bibliothèque d'animation. Toute nouvelle mise
  en page de l'application reprend ce conteneur ; tout nouvel écran à onglets lui donne la même transition.
  Vérification dans un navigateur réel, avec et sans « réduire les animations » (UI-75).
  (4) **C'est automatique, jamais à la demande du propriétaire.** Sur tout écran créé ou touché, on pose la
  transition soi-même : contenu d'onglet (y compris onglets en liens vers la même page avec `?paramètre`, le
  conteneur est alors identifié par ce paramètre), bloc qui change avec l'état (« prochaine étape » identifié par
  le statut), message d'erreur ou d'alerte qui apparaît (`InputError`, `Alert` et tout `role="alert"` portent
  `fade-in`), panneau qui s'ouvre. Un écran livré avec un saut sec est un défaut, au même titre qu'un
  alignement cassé.
  (5) **Une page arrive par étapes, pas en un bloc.** Le cadre (menu, en-tête) ne bouge pas ; le contenu apparaît en
  cascade : la page en fondu (0,2 s), ses sections qui montent l'une après l'autre (décalage de 40 ms, six au plus,
  0,3 s chacune), puis les lignes de tableau en fondu (décalage de 20 ms, huit au plus). Le tout dure **0,6 s au
  plus** (UI-74), n'anime que `opacity` et `transform` (UI-72), avec un remplissage `backwards` pour qu'aucune
  transformation ne reste posée. La page quittée **s'estompe** (opacité réduite) quand le chargement de la
  suivante dépasse 100 ms, **seulement** pour un vrai changement de page (pas un filtre, un tri, un formulaire ni
  un préchargement) ; rien n'est bloqué. (6) **Le défaut est posé une fois, pour tous les composants** : tout
  élément interactif (lien, bouton, champ, ligne de tableau, onglet, option de menu) change couleur, fond,
  bordure et ombre en 150 ms, `ease-out`, par une règle de base de la feuille de style ; un composant de base ne
  liste **jamais** une transition sans le fond et la bordure (cas courant : `transition-[color,box-shadow]`, où le
  survol change le fond d'un coup), et aucune courbe linéaire. (7) Sous « réduire les animations », **tout** se
  fait sans durée (animations et transitions), sauf l'indicateur de chargement qui continue de tourner.
  (8) **Une grille ou une rangée de cartes (indicateurs, tuiles de chiffres) monte carte par carte** (classe
  `cascade` posée sur le conteneur, écrite une fois dans la feuille de style) : 30 ms d'écart, huit au plus, juste
  après sa section, 0,6 s au plus en tout. Un formulaire de filtres n'en fait pas partie. (9) **La sélection d'un
  menu de rubriques est un repère unique qui glisse** d'une rubrique à l'autre (`transform` seulement, 0,3 s,
  `ease-out`), comme un sélecteur : il part **au clic**, sans attendre le chargement de la page, revient à la
  rubrique active si la navigation échoue, ne glisse pas à la première apparition, suit le redimensionnement et le
  repli du menu, et se masque quand la page n'est pas dans le menu. Le survol garde sa pastille propre (UI-70).

- **UI-116.** **Toute liste consultée par des utilisateurs s'exporte en CSV, Excel et PDF**, depuis un menu
  « Exporter » placé dans l'en-tête de la page (composant commun). Règles : (1) le fichier reprend **les filtres
  affichés** à l'écran et le même tri ; (2) il suit **les mêmes droits et la même visibilité que la page** : ce
  qu'un utilisateur ne voit pas à l'écran (coûts, marges, éléments hors de son périmètre) n'est pas dans le
  fichier, et l'accès est refusé à qui ne peut pas ouvrir la page ; (3) les en-têtes sont **traduits** dans la langue
  de l'utilisateur, les montants et les quantités restent des **nombres** (un tableur peut les additionner) et
  les dates un format non ambigu ; (4) un texte qui ressemble à une formule (`=`, `+`, `-`, `@`) est neutralisé
  dans le CSV et jamais écrit comme formule dans l'Excel ; (5) le CSV porte un BOM UTF-8 et le séparateur `;`
  pour s'ouvrir correctement dans Excel ; le PDF a une ligne « généré le … par … », le nom de l'organisation en
  filigrane (comme les autres documents), répète l'en-tête à chaque page et signale quand il est **tronqué**
  (la mémoire d'un PDF est bornée : le tableur contient tout) ; (6) un plafond de lignes évite qu'une liste
  immense bloque le serveur ; (7) chaque export est **écrit au journal d'audit** avec la vue, le format, le nombre
  de lignes et les filtres (SEC-161) ; la réponse n'est jamais mise en cache (SEC-82) ; (8) l'Excel est produit
  **sans dépendance** quand le besoin se limite à une feuille simple ; une bibliothèque n'est ajoutée qu'avec
  l'accord du propriétaire (PRI-09).

- **UI-117.** **Un formulaire qui met en route un traitement automatique se lit comme une phrase.** (1) Il affiche, sous
  les champs et en direct, **une phrase** qui dit ce qui va se passer avec les valeurs saisies (« Chaque semaine, à
  partir du 4 nov., ce client recevra 3 kits, avec une avance de 200 000 F, sans date de fin »), avec les accords au
  singulier et au pluriel ; (2) une **limite** (jusqu'à quand) est **un seul choix** parmi « jamais », « à une date »,
  « après un nombre fixé », qui n'affiche que le champ concerné : jamais deux champs facultatifs qui peuvent se
  contredire ; (3) les mots sont ceux du métier (« livraison », pas « cycle », UI-40), et les rangées restent pleines
  quand le champ conditionnel apparaît (trois tiers au lieu de deux moitiés, UI-83) ; (4) les erreurs s'affichent
  **sous le champ**, avec **les messages du serveur** qui disent quoi faire (UI-41), pas dans la **bulle du
  navigateur** : le formulaire est **sans validation native** mais garde l'attribut « obligatoire » pour que
  l'astérisque s'affiche (UI-113) ; (5) le bouton porte un verbe court (UI-92).
- **UI-118.** **Créer en lot est un onglet de l'écran de création, pas un autre écran.** L'écran « Nouvelle commande » propose
  « Un client » et « Tout un groupe » (onglets, UI-101, le choix dans l'adresse). Le lot suit UI-115 : la **portée** se choisit
  dans une liste qui dit **combien** d'éléments elle contient ; **le groupe est le découpage que le métier utilise déjà** (celui de
  ses classeurs et de ses tournées, par exemple la zone géographique), choisi dans une **liste fermée identifiée par son numéro**,
  jamais un texte libre saisi à la main (un nom de village écrit de plusieurs façons regroupe mal) ; la liste des éléments concernés s'affiche, **tous cochés** ;
  ceux qui ont **déjà** ce qu'on va créer sont montrés, **grisés, non cochables**, avec la raison (« a déjà une commande cette
  semaine »), jamais cachés ; **l'étape suivante** (émettre le document) est une case **cochée par défaut** qui s'explique ;
  une **phrase de résumé** dit ce qui va se passer (UI-117) et la confirmation annonce le nombre. Le **message final ne nomme que
  ce qui n'est pas zéro** (« 4 commandes créées · 4 proformas émises »), au singulier et au pluriel. Le **nombre d'éléments par
  lot est borné** d'après l'étape la plus lente (un document PDF prend de l'ordre d'une demi-seconde), pour qu'une requête ne
  dépasse jamais le délai du serveur ; un lot plus gros se découpe.
- **UI-119.** **Une visite guidée (UI-34) est claire et précise.** (1) **Une étape = un élément de l'écran**, désigné par un
  repère (`data-tour`), mis en lumière pendant que le reste s'assombrit ; la bulle ne **couvre jamais** l'élément expliqué
  (dessous, dessus, à côté, sinon dans le coin ; en bas de l'écran sur téléphone). (2) **Le texte dit ce que l'élément fait
  et ce que l'utilisateur peut en faire**, en deux phrases au plus, dans les mots de l'écran ; il est **vérifié contre le
  comportement réel** (rien d'annoncé que le produit ne fait pas) et existe dans les deux langues. (3) **Quatre à six étapes**
  par écran, dans l'ordre où l'œil lit la page. (4) Un **compteur** (« Étape 2 sur 5 »), **Passer** à tout moment, **Précédent /
  Suivant**, **Échap** et les flèches du clavier ; le focus va au bouton principal ; la bulle est un dialogue annoncé aux lecteurs
  d'écran. (5) Elle **démarre seule à la première ouverture** (après la transition de page), **jamais deux fois** une fois vue ou
  passée (enregistré en base par membre, UI-34), et se **rejoue** par un bouton d'aide **toujours au même endroit de l'en-tête**,
  visible seulement sur un écran qui a une visite. (6) Une étape dont l'élément est absent ou invisible est **sautée** ; un écran
  sans aucune étape visible ne démarre pas. (7) Mouvement réduit respecté (UI-112) ; vérifiée en clair, en sombre et sur téléphone.
- **UI-120.** **Dans une liste de choix, l'invitation ne ressemble jamais à une donnée.** L'option vide d'une liste obligatoire
  (« Choisir un kit ») est une **invitation**, pas une valeur : elle s'écrit **entre tirets** (« — Choisir un kit — »), en
  **gris italique**, dans la liste ouverte comme dans le champ fermé, et le champ reste gris tant qu'aucune vraie valeur n'est choisie ;
  dès qu'une valeur est choisie, le champ reprend le texte normal. C'est le **composant commun** qui le fait (une seule fois), avec
  une propriété pour les listes facultatives dont l'option vide reste pourtant une invitation. Un filtre dont la première option est
  un vrai choix (« Tous les statuts ») garde l'aspect d'une valeur. Vérifié dans les deux thèmes.
- **UI-121.** **Une liste de choix qui peut devenir longue se filtre.** À partir de **huit choix**, la liste devient une **liste
  recherchable** (champ « Rechercher… » en haut du panneau) au lieu d'une liste à faire défiler ; en dessous, la liste native reste
  plus simple et plus rapide. C'est le **composant commun** (même propriétés, même comportement de formulaire) qui bascule, jamais
  chaque écran : (1) un **vrai `<select>` invisible** reste dessous (valeur envoyée, validation native d'un champ obligatoire, `label
  for` qui fonctionne) ; (2) le filtre **ignore les accents et la casse** (« ete » trouve « Été ») et n'impose aucun ordre ; (3) le
  **clavier** suffit : flèches pour se déplacer, Entrée pour choisir, Échap pour fermer et rendre le focus au champ ; (4) le panneau
  s'ouvre **sous** le champ, **au-dessus** s'il n'y a pas la place ; (5) l'invitation (UI-120) garde son aspect ; (6) « Aucun
  résultat » s'affiche quand rien ne correspond ; (7) un clic ailleurs ferme le panneau ; (8) rôles `combobox` et `listbox`, nom
  accessible repris du libellé du champ. Pas de nouvelle bibliothèque pour cela (PRI-09).
- **UI-122.** **Les documents commerciaux partagent une seule feuille.** La facture, la proforma et le bon de livraison (et tout futur
  devis ou bon commercial) sortent d'**une seule mise en page**, paramétrée par le type de document, jamais de deux copies qui divergent.
  Le **modèle fourni par le propriétaire est la référence** : on le reprend tel quel, on ne recopie pas ses erreurs de calcul ou
  d'orthographe (les chiffres viennent des données). De haut en bas : (1) le **titre du projet** et la **liste des zones**, puis un
  **bandeau** portant le titre du document et la catégorie des articles (deux réglages de l'entreprise, rien d'imprimé s'ils sont
  vides) ; (2) un **bloc d'identité sur deux colonnes** : fournisseur, client, lieu de livraison, zone, à gauche ; numéro,
  téléphones, dates de commande et de livraison, à droite ; (3) le **tableau du détail**, une ligne par produit (numéro, désignation,
  unité, quantité, prix unitaire, montant), le prix unitaire étant le montant de la ligne divisé par la quantité, arrondi, **le montant
  de la ligne restant la valeur de référence** ; (4) les **lignes de totaux** : net à payer, avance, relicat réglé s'il y en a un,
  reste à payer ; (5) le **montant en toutes lettres**, écrit par notre propre code (pas d'extension de PHP à exiger), avec les accords
  (« deux cents », « quatre-vingts », « soixante et onze ») et dans la langue du document ; (6) le **rapprochement avant nouvelle
  commande** : anciennes dettes du même client (une ligne par commande précédente livrée et impayée, avec sa référence et « non payé » ou
  « payé en partie »), trop-perçu éventuel, montant du nouveau document, avance, solde après avance, **total du reste à payer** ;
  (7) sur le document qui **laisse sortir la marchandise** (la facture), la **liste de contrôle avant sortie** à cocher à la main ;
  puis les **signatures** (caissier, magasinier, responsable du client), le caissier étant la personne qui a émis ; (8) un **pied de page**
  discret (entreprise, type, numéro, zones). Le document reste en **thème clair**, existe dans les **deux langues**, est **figé** à
  l'émission (les anciennes dettes sont celles du jour) et se vérifie en **ouvrant un PDF produit avec de vraies données**, jamais
  seulement en lisant le gabarit. Un document sans montants (bon de livraison) garde le même en-tête, les mêmes identités et les
  mêmes signatures, et remplace les blocs de prix par des quantités.
- **UI-123.** **Tout envoi grise son bouton, et tout formulaire détecte ses modifications.** (1) De la confirmation jusqu'à la **réponse
  du serveur**, le bouton qui envoie est **inactif et dit qu'il travaille** (« Enregistrement… »), pour que rien ne parte deux fois :
  bouton d'un formulaire (la barre d'enregistrement, UI-14), bouton de **confirmation** (UI-22) **et** bouton d'action isolé (annuler,
  archiver, mettre en pause). C'est le **composant ou le crochet commun** qui le fait, jamais chaque écran à sa façon : un bouton qui
  envoie une requête sans passer par eux est un défaut. Le travail de fond (page préchargée au survol, rafraîchissement automatique)
  ne grise rien. (2) **Tout formulaire qui reçoit une saisie** (création, modification, **traitement en lot**, même hors de la barre
  d'enregistrement) **détecte s'il a été modifié** depuis son affichage ou son dernier enregistrement : l'état alimente l'aspect du
  bouton (UI-14) et **l'avertissement avant de quitter** (UI-15). Un choix qui ne fait que déplacer une liste (portée, semaine
  affichée) n'est pas une saisie. (3) Vérification : ralentir la requête dans un navigateur réel, constater le bouton grisé et **une
  seule** requête envoyée, puis la page de destination.
- **UI-124.** **Une liste de fiches qui ont des documents émis permet de les télécharger sans ouvrir chaque fiche.** (1) **Par ligne** :
  un bouton par document **valide** (nom du type, icône, nom accessible qui dit de quel client ou de quelle fiche il s'agit) ; rien
  n'est affiché quand le document n'existe pas ou est annulé, et le téléchargement **n'émet jamais** un document manquant. (2) **Par
  sélection** : une case à cocher par ligne et une case « tout cocher » dans l'en-tête (cases nommées pour les lecteurs d'écran) ;
  une **barre d'action** apparaît dès qu'une ligne est cochée, dit combien, propose un téléchargement **zippé par type de document**
  avec le **nombre réellement téléchargeable** dans le libellé, et un bouton pour tout décocher. Seules les lignes de la page
  affichée comptent. (3) **Par groupe** : un bloc replié (UI-114) où l'on choisit le type de document, le groupe du métier
  (UI-118) et la période, sans cocher ligne par ligne. (4) Côté serveur : le téléchargement est une **requête de lecture** (lien
  simple, pas de formulaire), **validée** par une classe de requête, **limitée en fréquence**, **bornée en nombre de fichiers**
  (au-delà, message clair et rien n'est envoyé), réservée aux fiches que l'utilisateur a le droit de voir, n'embarque **que les
  documents valides** (un document annulé n'y est jamais), donne aux fichiers un **nom lisible en lettres simples** (numéro et
  client), envoie l'archive **sans cache** (SEC-24 : entête de réponse des routes sensibles) puis **supprime le fichier
  temporaire**, et écrit **une ligne de journal par document remis** (SEC-161). Quand il n'y a rien à télécharger, on revient à
  la page avec un message d'erreur, jamais un fichier vide.
- **UI-125.** **Les badges et les boutons portent la couleur de leur sens, pas une teinte neutre par défaut.** Cinq tons, définis par des
  **jetons à deux valeurs** (clair et sombre, UI-61), chacun avec sa variante de **texte** qui garde 4,5:1 sur le fond teinté (UI-51) :
  **gris** (pas commencé, terminé, inactif, archivé), **bleu** (en cours, information, à venir), **ambre** (en attente de quelqu'un,
  en pause, partiel, à surveiller), **vert** (fait, en vigueur, actif, payé), **rouge** (annulé, refusé, contre-passé, danger). (1) Un
  **badge d'état** est **teinté** (fond léger, texte et bordure du ton), jamais plein ni gris par défaut ; le ton d'un état vient
  d'**une seule table** du composant d'état (un état a la même couleur dans la liste, la fiche et le tableau de bord), et le
  libellé est toujours écrit (UI-52). **Dans un cycle de vie à plusieurs étapes, chaque étape a sa propre couleur** (deux étapes
  voisines ne partagent jamais un ton, sinon on ne les distingue pas dans une liste) : aux cinq tons s'ajoutent **sarcelle, violet et
  orange**, avec leurs jetons à deux valeurs, et la **suite de couleurs raconte la progression** (attente d'argent en ambre, argent
  reçu en sarcelle, en cours en bleu, matériel reçu en violet, livré mais à solder en orange, soldé en vert, annulé en rouge). Quand
  un état dépend d'un montant (avance perçue), **la liste montre le montant** perçu sur le montant dû, en couleur d'avancement (rien :
  gris, partiel : ambre, soldé : vert), sans ouvrir la fiche. Un badge de **catégorie** sans état (type de document, droit) reste neutre. (2) Un **bouton**
  prend la variante de sa nature (UI-16) : aucun bouton ne garde la variante « principal » neutre par habitude ; une page qui
  n'aurait que des boutons gris et blancs est un défaut à reprendre. (3) Les **tons** servent à un seul sens chacun : pas de rouge
  pour une simple mise en garde, pas de vert pour une information. (4) Vérification : parcourir les écrans en clair et en sombre et
  constater qu'on lit l'état d'une ligne ou la nature d'un bouton avant d'en lire le texte. (5) **Un état et une action ne se
  ressemblent jamais** : dans une même ligne, le badge d'état (teinté, sans bordure de bouton) et un bouton d'action (contour neutre,
  icône colorée, libellé d'action : « PDF proforma », pas le nom de l'état) ne prennent pas le même ton ni le même nom, sinon on prend l'un
  pour l'autre. (6) **Chaque composant construit se regarde** dans le navigateur, en clair et en sombre, à largeur d'ordinateur et de
  téléphone, avant d'être rendu.
- **UI-126.** **Une liste de blocs à saisir alterne les fonds.** Quand chaque ligne d'une liste est un **bloc de plusieurs champs** (une
  carte par fiche : montant, date, payeur…) et qu'on y saisit, **un bloc sur deux** a un fond légèrement teinté (jeton du thème, clair
  et sombre, UI-61) pour qu'on ne saisisse pas dans le mauvais bloc ; le fond **ne remplace pas** la séparation par un trait ni le nom
  de la fiche en tête de bloc (UI-52), et il reste discret pour que le texte garde son contraste (UI-51). Un bloc décoché ou inactif
  est atténué, jamais masqué. À vérifier en clair et en sombre.
- **UI-127.** **Le contour d'un champ se voit, sur tous les fonds, en clair et en sombre.** Le bord d'un champ de saisie, d'une liste, d'une
  case à cocher et d'un bouton à contour a **au moins 3:1 de contraste** avec le fond qui l'entoure (WCAG 1.4.11), **y compris sur un
  fond teinté** (lignes alternées, UI-126, cartes, bandeaux). Il vient d'**un seul jeton** (`--input`) à deux valeurs, jamais d'une
  teinte écrite à la main ni du même gris que les séparateurs et les fonds : un champ dont le bord se confond avec le fond ne dit
  pas où l'on saisit. Se vérifie à l'œil, en clair et en sombre, sur un formulaire ordinaire **et** sur une ligne à fond teinté.
- **UI-128.** **Un champ de texte libre est une zone de texte sur plusieurs lignes, pas une ligne de saisie.** Observations, commentaires,
  notes, motifs et descriptions utilisent un composant de zone de texte (même contour, mêmes états et même hauteur minimale de
  trois lignes, redimensionnable en hauteur), jamais le champ d'une ligne réservé aux valeurs courtes (nom, référence, montant). Le
  serveur borne la longueur (`max`) ; une seule ligne reste admise pour un motif posé dans une rangée de tableau où la place manque.
- **UI-129.** **Une étape qui découle de faits enregistrés ne se coche pas à la main sans eux.** « Livrée » n'est possible que quand tous les
  éléments sont réellement remis, « soldée » que quand tout est payé : le **serveur** refuse l'étape sinon (un écran contourné ne doit
  jamais produire un état faux), et le bloc « prochaine étape » **dit la situation réelle** (ce qui reste, en chiffres), **grise** le bouton
  de l'étape et propose **le geste qui manque** (« Enregistrer la livraison », « Enregistrer le paiement ») qui ouvre le bon onglet. Le texte
  explicatif d'un état n'est jamais montré comme si c'était la situation courante.
- **UI-113.** **Dans tous les formulaires, un champ obligatoire porte un astérisque rouge (`*`) après son
  libellé**, toujours, sans exception d'écran. Le marquage est produit **une seule fois** par le composant
  `Label`, qui lit l'attribut `required` du champ qu'il désigne (`htmlFor` = `id` du champ) : on ne l'écrit donc
  jamais à la main dans un texte de traduction. Conséquences : (1) tout champ que le serveur exige reçoit aussi
  `required` côté navigateur (liste déroulante comprise), sinon il n'a pas d'astérisque et le formulaire
  accepte l'envoi pour le voir refuser ; (2) tout libellé a son `htmlFor` et son champ son `id` ; (3) un champ
  facultatif n'est jamais marqué, et on écrit « facultatif » dans son libellé seulement si l'oubli prête à
  confusion ; (4) l'astérisque est décoratif (`aria-hidden`) : le lecteur d'écran annonce « obligatoire » par
  l'attribut `required`. Un champ obligatoire seulement selon un autre choix (la référence d'un mode de règlement qui l'exige)
  ne reçoit `required` que lorsqu'il est affiché.

- **UI-114.** **Un formulaire placé au-dessus d'une liste (UI-106) se replie** : son titre est un bouton
  (`aria-expanded`, `aria-controls`, chevron qui pivote) qui ouvre ou ferme le corps du formulaire, avec le
  composant commun `CollapsiblePanel`. Il est **ouvert par défaut** (c'est l'action la plus fréquente), se replie
  en un clic pour laisser voir la liste, et **garde la saisie** quand on le replie (le contenu est masqué, pas
  retiré). Le bouton de repli est au clavier, annonce son état, et l'ouverture est animée (UI-112).

- **UI-115.** **Une action de groupe (sur plusieurs enregistrements à la fois) suit toujours le même parcours** :
  (1) on choisit la **portée** (un lieu, une semaine, une catégorie…) dans une liste qui montre **combien** d'éléments sont
  concernés ; (2) un bouton `ConfirmAction` (UI-111) annonce le nombre exact et la conséquence ; (3) un élément en
  échec n'arrête pas les autres ; (4) le message final dit combien ont réussi et combien ont échoué ; (5) relancer
  l'action ne refait jamais ce qui est déjà fait (idempotence). L'action de groupe reprend la règle de l'action
  unitaire (mêmes droits, mêmes gardes, même trace d'audit). Le panneau est replié par défaut (UI-114). Quand l'action ne vaut que pour certains éléments (ceux qui remplissent
  une condition), seuls ceux-là sont proposés, **cochés par défaut**, et le serveur revérifie chacun avant d'agir.

### Graphiques

- **UI-85.** **Choisir la forme d'après la tâche du lecteur, pas l'inverse.** Comparer des grandeurs : barres ou
  colonnes ; une évolution : courbe ou colonnes par période ; une répartition : barres horizontales (un camembert
  seulement pour deux ou trois parts, jamais pour comparer des valeurs proches). **Un seul chiffre n'est pas un
  graphique** : c'est une tuile. Jamais deux axes verticaux sur un même graphique (deux échelles inventent une
  corrélation) : deux graphiques, ou deux séries de même unité.
- **UI-86.** **Une seule bibliothèque de graphiques** pour tout le projet, déclarée dans le fichier d'instructions ;
  un composant partagé (carte, graphique en barres, infobulle) que les écrans assemblent. Pas d'injection de
  `<style>` à l'exécution (la politique de contenu l'interdit : SEC-81).
- **UI-87.** **Les couleurs d'un graphique sont des jetons** (clair et sombre), pris **dans l'ordre fixe** d'une palette
  validée (CVD, contraste), jamais cyclés ni générés : au-delà de huit séries on regroupe. La couleur suit
  **l'entité**, pas son rang (filtrer ne repeint pas les survivantes). Une série unique prend une seule couleur.
  Les couleurs d'état (bon, alerte, critique) sont réservées à des états.
- **UI-88.** **Marques fines, décor discret** : barres étroites à bout arrondi de 4 px et petit espace entre elles,
  grille en filets à peine visibles, axes en retrait. Le texte (valeurs, libellés, légende) garde les couleurs de
  texte ; un petit carré coloré porte l'identité de la série. Pas de nombre sur chaque barre.
- **UI-89.** **Un graphique est lisible sans la couleur et sans la souris** : une légende dès deux séries, une
  infobulle au survol (catégorie, nom de la série, valeur formatée dans la langue : UI-81), un **nom accessible**
  (`aria-label`), et un **affichage en tableau** des mêmes chiffres, à un clic. Une période sans donnée affiche un
  message qui le dit, pas un cadre vide.
- **UI-90.** **Mouvement sobre** : l'animation d'entrée dure 0,5 s au plus et se coupe en mouvement réduit
  (UI-53, UI-64). Les graphiques se **vérifient à l'œil** (étiquettes qui se chevauchent, débordements, deux
  thèmes, trois largeurs) : un contrôle automatique ne voit pas la mise en page.

## 8. Internationalisation

- **I18N-01.** **Aucun texte affiché n'est écrit en dur**, ni côté serveur ni côté client.
- **I18N-02.** Textes dans des fichiers par **groupe thématique** (actions communes, navigation, compte,
  organisation, permissions, messages juridiques...). Les fichiers du framework sont **générés**, on n'y ajoute
  pas de clé applicative (elle serait écrasée).
- **I18N-03.** **Clés en anglais**, en notation pointée sémantique (`group.subgroup.key`) ; valeurs dans la langue
  concernée.
- **I18N-04.** **Toute nouvelle clé s'ajoute dans toutes les langues dans le même changement.** Une clé présente
  d'un seul côté est un bogue.
- **I18N-05.** Paramètres **nommés** (`:name`), jamais de concaténation. Pluriels par les règles du framework.
- **I18N-06.** Une clé absente **rend la clé elle-même** : le trou se voit à l'écran et en test.
- **I18N-07.** Seuls les groupes utiles sont **envoyés au navigateur** ; les messages de validation restent
  côté serveur.
- **I18N-08.** **Résolution de la langue**, dans cet ordre : paramètre d'adresse (mémorisé en session), langue de
  session, en-tête du navigateur **pour un visiteur non authentifié seulement**, langue par défaut. Le code de
  langue est **confronté à un catalogue fermé** avant d'être utilisé (voir SEC-45).
- **I18N-09.** Un sélecteur de langue écrit le choix en **session** plutôt que de traîner un paramètre dans
  chaque adresse.
- **I18N-10.** **Dates et nombres** formatés dans la langue **choisie dans l'interface**, pas celle de l'appareil ;
  montants dans la devise du produit, sans décimale quand la devise n'en a pas.
- **I18N-11.** Le **contenu saisi par un client** (nom d'événement, gabarit) se traduit par un mécanisme
  distinct de l'interface (colonnes traduisibles), pas par les fichiers de langue.
- **I18N-12.** Un test vérifie que **chaque langue a les mêmes clés** que la langue de référence.
- **I18N-13.** **Langue de l'interface choisie par l'utilisateur** parmi un catalogue fermé (par exemple français
  et anglais), mémorisée dans son profil s'il est connecté, en session sinon. Le sélecteur est accessible depuis
  tout écran et ne fait perdre aucune saisie en cours.
- **I18N-14.** Un **document généré** (PDF, courriel, reçu) a **sa propre langue**, fixée à la génération (langue du
  destinataire ou choix de l'opérateur), et non celle de l'interface de l'opérateur. Cette langue est conservée
  avec le document.
- **I18N-15.** Les écrans **supportent des textes plus longs** dans une langue que dans l'autre (l'anglais comme le
  français) : pas de largeur fixe, pas de texte tronqué sans repli.
- **I18N-16.** Les **libellés paramétrables** saisis par l'administrateur (types de frais, modes de paiement,
  zones) se traduisent par le mécanisme de I18N-11, pas par les fichiers de langue.
