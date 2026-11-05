# Guide des animations d'interface

Catalogue des types d'animations, **du plus répandu au plus rare**. Pour chacune : ce que c'est, quand elle sert
vraiment, quand l'éviter, et comment la réaliser. Le classement est une appréciation de la fréquence d'usage dans les
produits web et mobiles, pas une mesure.

Repères communs à toutes (règles du playbook, chapitre 06) :

- Une animation **explique** un changement d'état, elle ne décore pas (UI-64).
- Durées : **0,15 à 0,3 s** pour un geste de l'utilisateur, **0,3 à 0,6 s** pour une entrée de contenu, jamais plus
  de 0,6 s (UI-70, UI-74).
- Courbe douce (`ease-out`, ou `cubic-bezier(0.22, 1, 0.36, 1)`), **jamais linéaire** pour un mouvement d'interface.
- On n'anime que `transform` et `opacity` (rapides, 60 images/s) ; jamais `width`, `height`, `top`, `margin` (UI-72).
- Rien ne doit retarder l'action : un bouton reste utilisable pendant qu'il s'anime (UI-74).
- Tout se désactive avec « réduire les animations » (`prefers-reduced-motion`), sauf l'indicateur de chargement
  (UI-53, UI-73).

Légende : **[A]** déjà dans l'application, **[·]** pas encore utilisée.

---

## 1. Transition d'état au survol et au focus — **[A]**

**Description.** Un élément change de couleur, de fond, de bordure ou d'ombre en douceur quand le curseur passe
dessus, qu'il reçoit le focus clavier ou qu'on appuie dessus.

**Quand l'utiliser.** Partout où un élément est cliquable : boutons, liens, lignes de tableau, entrées de menu,
champs. C'est le retour immédiat qui dit « ceci est interactif ».

**À éviter.** Durée supérieure à 0,2 s (l'interface paraît lourde) ; `transition: all` (anime aussi des propriétés
qu'on ne voulait pas animer).

**Réalisation.** `transition: color, background-color, border-color, box-shadow 150ms ease-out`.

## 2. Fondu (fade in / fade out) — **[A]**

**Description.** L'opacité passe de 0 à 1 (ou l'inverse). Le mouvement le plus simple et le plus discret.

**Quand l'utiliser.** Apparition d'un message d'erreur, d'une alerte, du contenu d'un onglet, d'une infobulle ;
disparition d'un élément retiré. Convient à tout ce qui change sans avoir de « direction ».

**À éviter.** Sur un élément qui doit rester lisible immédiatement (UI-54) : le texte ne doit jamais dépendre de
l'animation pour être visible.

**Réalisation.** `@keyframes fade-in { from { opacity: 0 } }`, 0,2 s `ease-out`.

## 3. Fondu avec montée (fade up / slide up) — **[A]**

**Description.** L'élément apparaît en fondu en montant de quelques pixels (0,5 à 1 rem) jusqu'à sa place.

**Quand l'utiliser.** Arrivée d'une page ou d'une section, d'une carte, d'un bloc sous la ligne de flottaison. Le
léger déplacement donne une impression de profondeur et dit « voici du contenu nouveau ».

**À éviter.** Grandes amplitudes (plus d'1 rem) : l'effet devient théâtral et fatigue à la longue.

**Réalisation.** `from { opacity: 0; transform: translateY(0.5rem) }`, 0,3 s.

## 4. Fenêtre qui s'ouvre et se ferme (modal, popover, menu déroulant, tiroir) — **[A]**

**Description.** Un panneau apparaît avec un fondu et un léger agrandissement (de 95 % à 100 %) ou un glissement
depuis son bord d'origine. Le fond s'assombrit en fondu.

**Quand l'utiliser.** Dialogues, menus déroulants, listes de choix, panneaux latéraux (« sheet »), infobulles. Le
mouvement part **du déclencheur** (le menu naît sous son bouton) pour montrer d'où il vient.

**À éviter.** Fermeture plus lente que l'ouverture ; animation qui masque le contenu au clavier ou aux lecteurs
d'écran (le focus doit être posé dès l'ouverture).

**Réalisation.** Radix/shadcn : `data-[state=open]:animate-in data-[state=open]:fade-in-0 zoom-in-95`.

## 5. Indicateur glissant (sliding indicator, « sliding pill ») — **[A]**

**Description.** Un seul repère (pastille de fond, soulignement) se déplace d'une option à l'autre au lieu de
disparaître ici et réapparaître là.

**Quand l'utiliser.** Menu de rubriques, onglets, sélecteur segmenté (clair/sombre/système, FR/EN), filtre à choix
unique. L'œil suit le mouvement et comprend quel choix est actif.

**À éviter.** Si les options sont éloignées ou séparées par des blocs : le glissement traverse des éléments
étrangers. Doit partir **au clic**, sans attendre le chargement (UI-112).

**Réalisation.** Un élément absolu, `transform: translate(x, y)` mesuré sur l'option active, transition 0,3 s ; la
première position est posée sans glissement.

## 6. Apparition en cascade (stagger) — **[A]**

**Description.** Les éléments d'une liste ou d'une grille apparaissent l'un après l'autre avec un petit décalage
(30 à 100 ms), au lieu de tous ensemble.

**Quand l'utiliser.** Grille de cartes, tuiles d'indicateurs, lignes d'un tableau, entrées d'une liste, étapes d'un
parcours. Guide le regard dans l'ordre de lecture et donne un rythme.

**À éviter.** Plus de 8 à 10 éléments en cascade (la fin arrive trop tard) ; chaque élément ne doit pas dépasser
0,6 s au total, délai compris.

**Réalisation.** Même animation sur chaque enfant avec `animation-delay` croissant (`:nth-child`).

## 7. Indicateur de chargement (spinner) — **[A]**

**Description.** Un cercle ou un symbole qui tourne en boucle pendant une attente.

**Quand l'utiliser.** Attente courte (1 à 5 s) dont on ne connaît pas la durée : envoi d'un formulaire, ouverture
d'une page. C'est la **seule** animation qui a le droit de boucler à l'infini (UI-74).

**À éviter.** Attente plus longue ou dont on connaît l'avancement : préférer une barre de progression. Ne jamais
l'afficher pour une action instantanée (il clignote).

**Réalisation.** `animation: spin 1s linear infinite` (la seule animation où la courbe linéaire est correcte).

## 8. Squelette de chargement (skeleton) — **[A]**

**Description.** Des formes grises à la place du futur contenu, avec un léger pulse ou un reflet qui passe.

**Quand l'utiliser.** Chargement d'une liste, d'une carte, d'un tableau dont on connaît la forme. Évite le saut de
mise en page à l'arrivée des données et paraît plus rapide qu'un spinner.

**À éviter.** Écran dont la forme est imprévisible ; chargement quasi instantané (le squelette clignote).

**Réalisation.** `animate-pulse` ou dégradé qui se déplace (`background-position`).

## 9. Notification qui apparaît (toast) — **[A]**

**Description.** Un message court glisse depuis un bord de l'écran, reste quelques secondes, puis repart.

**Quand l'utiliser.** Confirmer une action réussie ou signaler une erreur sans bloquer l'écran (« Enregistré »).

**À éviter.** Message important que l'utilisateur doit absolument lire (il disparaît seul) ; empilement de plusieurs
toasts.

**Réalisation.** Sonner ou équivalent ; entrée par `translateY` + fondu, sortie plus rapide.

## 10. Transition entre pages — **[A]**

**Description.** Le contenu d'une nouvelle page apparaît en fondu avec une légère montée, pendant que le cadre
(menu, en-tête) reste fixe. La page quittée peut s'estomper pendant le chargement.

**Quand l'utiliser.** Navigation d'un écran à l'autre dans une application à page unique : évite le « saut sec ».

**À éviter.** Rejouer l'animation quand seul un filtre ou un tri change (même page) ; durée de plus de 0,3 s.

**Réalisation.** Conteneur identifié par le chemin de la page ; l'animation se rejoue à chaque changement de clé.

## 11. Déploiement et repli (accordéon, collapsible) — **[A]**

**Description.** Une section s'ouvre ou se replie, avec la hauteur qui s'ajuste, et son contenu qui apparaît en
fondu. Souvent accompagnée d'une flèche qui pivote.

**Quand l'utiliser.** Questions fréquentes, filtres avancés, formulaire repliable, détails d'une ligne. Cache le
secondaire sans quitter la page.

**À éviter.** Animer directement la hauteur sur de grands blocs (saccades) ; masquer une information indispensable.

**Réalisation.** Pour rester fluide : `grid-template-rows: 0fr → 1fr` ou une hauteur mesurée ; le fondu du contenu
suffit souvent (UI-114).

## 12. Bouton à états (pressé, chargement, succès) — **[·]**

**Description.** Le bouton réagit à l'appui (légère réduction), puis montre qu'il travaille (spinner) et enfin que
c'est fait (coche).

**Quand l'utiliser.** Actions qui prennent un instant (envoyer, enregistrer, payer). Rassure : « j'ai bien cliqué,
ça travaille, c'est fini ».

**À éviter.** Changer la largeur du bouton en cours (saut de mise en page) ; désactiver le clic sans le dire.

**Réalisation.** `active:scale-[0.98]`, icône remplacée par un spinner puis une coche, largeur réservée.

## 13. Interrupteur et case à cocher animés (toggle, checkbox) — **[·]**

**Description.** Le curseur d'un interrupteur glisse d'un côté à l'autre, le fond change de couleur ; la coche se
dessine.

**Quand l'utiliser.** Réglages binaires (mode sombre, notifications). Le mouvement confirme que l'état a changé.

**À éviter.** Mouvement trop lent : la bascule doit paraître instantanée (0,15 à 0,2 s).

**Réalisation.** `transform: translateX()` sur le curseur, `transition` sur la couleur de fond.

## 14. Compteur qui s'incrémente (count-up) — **[·]**

**Description.** Un chiffre monte de 0 (ou de l'ancienne valeur) à sa valeur finale en une ou deux secondes.

**Quand l'utiliser.** Chiffres clés d'un tableau de bord ou d'une page de présentation, au premier affichage, pour
attirer l'œil sur un résultat.

**À éviter.** Chiffres financiers lus par un comptable : la valeur doit être lisible **tout de suite** et exacte
(UI-54). À réserver à une vitrine, jamais à un écran de travail.

**Réalisation.** Interpolation de la valeur par JavaScript ; `font-variant-numeric: tabular-nums` pour éviter que
les chiffres bougent.

## 15. Barre de progression — **[·]**

**Description.** Une barre qui se remplit pour montrer l'avancement d'une tâche dont on connaît le total.

**Quand l'utiliser.** Import de fichier, envoi volumineux, traitement en lot, étapes d'un parcours. Plus honnête
qu'un spinner quand on sait où l'on en est.

**À éviter.** Barre qui avance sans lien avec le vrai avancement (fausse progression) ; barre qui recule.

**Réalisation.** `transform: scaleX()` depuis la gauche plutôt que d'animer la largeur.

## 16. Graphiques animés (tracé, barres qui montent) — **[A]**

**Description.** Les barres montent, la courbe se dessine, les parts d'un camembert s'ouvrent à l'arrivée du
graphique. Les infobulles suivent le curseur.

**Quand l'utiliser.** Une seule fois à l'apparition, pour montrer d'où viennent les valeurs ; et lors d'un changement
de données, pour que l'œil voie ce qui a bougé.

**À éviter.** Rejouer à chaque survol ; animer un graphique que l'on doit comparer avec précision ; durée longue.

**Réalisation.** Recharts (animation intégrée, désactivée en mouvement réduit).

## 17. Retour d'erreur qui vibre (shake) — **[·]**

**Description.** Un champ ou une fenêtre se secoue rapidement de gauche à droite pour dire « refusé ».

**Quand l'utiliser.** Mot de passe ou code erroné, échec d'une saisie. Le mouvement dit « non » sans texte.

**À éviter.** Abuser : si tout vibre, plus rien ne signale rien ; ne remplace jamais le message écrit, qui reste
obligatoire.

**Réalisation.** `translateX(±6px)` sur 0,3 s, trois allers-retours.

## 18. Mise en évidence d'un changement (flash, highlight) — **[·]**

**Description.** Une ligne, une cellule ou une valeur change de fond un instant (jaune pâle) puis revient à la
normale.

**Quand l'utiliser.** Une ligne vient d'être ajoutée ou modifiée dans une liste ; une valeur s'est mise à jour en
direct. Aide à repérer « ce qui vient de bouger ».

**À éviter.** Flash de plus d'une seconde ; couleur agressive ; mise à jour très fréquente (l'écran clignote).

**Réalisation.** Animation de `background-color` de la teinte vers transparent, 1 s.

## 19. Réorganisation fluide (layout animation, FLIP) — **[·]**

**Description.** Quand des éléments changent de place (tri, ajout, suppression), ils glissent vers leur nouvelle
position au lieu de sauter.

**Quand l'utiliser.** Liste triable, tâches qui se réordonnent, filtre qui retire des cartes d'une grille. Montre
**où est passé** chaque élément.

**À éviter.** Longues listes (coût élevé) ; réordonnancement très fréquent.

**Réalisation.** Technique FLIP (First, Last, Invert, Play) par `transform`, ou l'API View Transitions.

## 20. Glisser-déposer (drag and drop) avec retour — **[·]**

**Description.** L'élément suit le curseur, une ombre s'élargit, l'emplacement d'arrivée se dessine, et l'élément
« se pose » en fin de geste.

**Quand l'utiliser.** Réorganiser des éléments, déplacer une carte d'une colonne à l'autre (tableau kanban),
ordonner une liste.

**À éviter.** Comme seul moyen d'agir : prévoir toujours une alternative au clavier (boutons monter/descendre).

**Réalisation.** `transform` suivi du pointeur ; ressort ou `ease-out` au dépôt.

## 21. Effet de ressort (spring, rebond léger) — **[·]**

**Description.** Le mouvement dépasse légèrement sa cible puis revient, comme un objet élastique.

**Quand l'utiliser.** Interfaces mobiles ou ludiques : feuille qui remonte du bas, « j'aime » qui s'agrandit, élément
posé après un glisser. Donne de la vie.

**À éviter.** Outils de travail sérieux (finance, administration) : le rebond paraît gadget et ralentit la lecture.
Le playbook préfère translation et opacité à la rotation et au rebond (UI-64).

**Réalisation.** `cubic-bezier(0.34, 1.56, 0.64, 1)` ou bibliothèque de ressort ; à éviter ici (pas de bibliothèque
d'animation sans raison, UI-73).

## 22. Défilement avec apparition (scroll reveal) — **[A]**

**Description.** Un bloc apparaît (fondu et légère montée) la première fois qu'il entre dans la fenêtre pendant le
défilement.

**Quand l'utiliser.** Pages de présentation longues, sections successives d'une page d'accueil.

**À éviter.** Écrans de travail ; contenu déjà visible au chargement (il ne doit pas être masqué d'abord) ; rejouer à
chaque passage.

**Réalisation.** `IntersectionObserver` + classe `is-visible` (UI-71).

## 23. Défilement fluide (smooth scroll) — **[A]**

**Description.** Les ancres et le retour en haut défilent en douceur au lieu de sauter.

**Quand l'utiliser.** Menu d'ancres d'une page longue, bouton « haut de page ».

**À éviter.** Détourner la molette (« scroll-jacking ») ou ajouter une barre de défilement maison (UI-69).

**Réalisation.** `html { scroll-behavior: smooth }` seulement si le mouvement n'est pas réduit.

## 24. Effet de parallaxe — **[·]**

**Description.** Plusieurs couches de l'écran défilent à des vitesses différentes pour créer de la profondeur.

**Quand l'utiliser.** Page de présentation, récit visuel, vitrine de marque.

**À éviter.** Interfaces de travail ; appareils modestes (saccades) ; personnes sensibles au mouvement (nausées).
À proscrire dans cette application.

**Réalisation.** `transform: translateY()` lié au défilement ; `will-change` posé seulement pendant l'effet.

## 25. Changement de thème par propagation (clair/sombre) — **[A]**

**Description.** Le nouveau thème se découvre en cercle depuis le point cliqué, par-dessus l'ancien.

**Quand l'utiliser.** Bascule clair/sombre déclenchée par l'utilisateur : rend le changement lisible et élégant.

**À éviter.** Quand le navigateur ne gère pas l'API, ou quand le thème est seulement aligné au chargement : le thème
change alors simplement (UI-76).

**Réalisation.** API View Transitions + animation de `clip-path`, 0,6 s au plus.

## 26. Visite guidée (spotlight, onboarding) — **[A]**

**Description.** L'écran s'assombrit sauf l'élément expliqué, entouré d'un halo ; une carte d'explication se place à
côté et le halo se déplace d'une étape à la suivante.

**Quand l'utiliser.** Première ouverture d'un écran complexe, présentation d'une nouveauté (UI-119).

**À éviter.** Visite imposée à chaque ouverture ; carte qui masque l'élément expliqué.

**Réalisation.** Halo par `box-shadow` géant, transition de position douce, carte positionnée hors de la cible.

## 27. Micro-interaction de retour (like, favori, copie) — **[·]**

**Description.** Une petite icône change et réagit : le cœur se remplit, l'icône « copier » devient une coche
quelques secondes.

**Quand l'utiliser.** Actions légères et répétées (copier un lien, mettre en favori) où une confirmation lourde serait
disproportionnée.

**À éviter.** Action qui a des conséquences (supprimer, payer) : elle exige une vraie confirmation (UI-111).

**Réalisation.** Remplacement d'icône avec fondu et petit agrandissement, retour à l'état initial après 1,5 s.

## 28. Animation d'illustration ou d'icône (Lottie, SVG animé) — **[·]**

**Description.** Une illustration vectorielle s'anime : une coche qui se trace, une enveloppe qui s'envole, un état
vide qui respire.

**Quand l'utiliser.** États vides, écrans de réussite, pages d'accueil, onboarding : ajoute de la personnalité.

**À éviter.** Alourdir la page (fichiers lourds) ; boucle infinie qui distrait (UI-74) ; remplacer une information.

**Réalisation.** SVG avec `stroke-dashoffset` (tracé qui se dessine), ou Lottie (bibliothèque : à justifier).

## 29. Effet de survol riche (carte qui se soulève, inclinaison 3D) — **[·]**

**Description.** Une carte monte légèrement avec une ombre qui s'étend, ou s'incline en suivant la souris.

**Quand l'utiliser.** Galerie, catalogue, cartes de présentation où l'on veut inviter au clic.

**À éviter.** Tableaux et écrans de travail denses ; appareils tactiles (pas de survol).

**Réalisation.** `transform: translateY(-2px)` + `box-shadow` ; l'inclinaison demande JavaScript (à éviter ici).

## 30. Morphing de forme (une icône devient une autre) — **[·]**

**Description.** Une forme se transforme en continu en une autre : l'icône « menu » devient « fermer », « lecture »
devient « pause ».

**Quand l'utiliser.** Bouton à deux états opposés, où la transformation explique le changement.

**À éviter.** Effet gratuit sans changement d'état réel ; icônes complexes (rendu irrégulier).

**Réalisation.** SVG avec chemins compatibles, ou rotation et fondu croisé de deux icônes.

## 31. Animation de caractères (machine à écrire, texte qui se révèle) — **[·]**

**Description.** Le texte s'écrit lettre à lettre ou mot à mot.

**Quand l'utiliser.** Réponse d'un assistant en direct, titre d'accueil d'une vitrine.

**À éviter.** Tout texte à lire ou à copier dans un écran de travail : il doit être lisible tout de suite (UI-54) et
sélectionnable sans attendre.

**Réalisation.** Révélation progressive par JavaScript ou par `clip-path` ; à réserver à la vitrine.

## 32. Fond animé (dégradé qui bouge, particules, aurore) — **[·]**

**Description.** Le fond d'une section se déforme, ondule ou porte des éléments flottants en continu.

**Quand l'utiliser.** Page d'accueil ou de marque, écran de connexion de prestige.

**À éviter.** Tout écran de travail : mouvement permanent qui distrait, consomme la batterie, et enfreint la règle
« rien ne boucle à l'infini sauf le chargement » (UI-74).

**Réalisation.** Dégradés animés par `transform` d'un calque ; à proscrire dans cette application.

## 33. Transition d'éléments partagés (shared element, « hero ») — **[·]**

**Description.** Un élément (une vignette) s'agrandit pour devenir la vue de détail, comme s'il restait le même objet
d'un écran à l'autre.

**Quand l'utiliser.** Passage d'une liste de produits ou de photos à la fiche détaillée ; applications mobiles.

**À éviter.** Navigation fréquente d'un outil de gestion : le cérémonial ralentit le travail.

**Réalisation.** API View Transitions (`view-transition-name`) ou calcul FLIP.

---

## Comment choisir

1. **Quel changement veut-on expliquer ?** (apparition, déplacement, état, attente, succès, erreur). Pas de
   changement à expliquer, pas d'animation.
2. **Quelle est la plus petite animation qui suffit ?** Souvent un fondu de 0,2 s.
3. **Peut-on s'en passer sans perdre l'information ?** Toujours : le mouvement est un plus, jamais le seul porteur du
   sens (UI-54).
4. **Cela reste-t-il utilisable pendant le mouvement ?** Oui, sinon on raccourcit (UI-74).
5. **Et avec « réduire les animations » ?** Tout doit se passer sans mouvement, au même résultat final (UI-73).

## Pour un outil de gestion comme cette application

Les animations qui rendent le plus de service sont, dans l'ordre : transitions d'état (1), fondu (2), fondu avec
montée (3), fenêtres qui s'ouvrent (4), indicateur glissant (5), cascade (6), spinner et squelette (7, 8), toast (9),
transition entre pages (10), déploiement (11). Les effets de vitrine (parallaxe, fonds animés, machine à écrire,
morphing, compteurs) n'ont leur place que sur la page d'accueil publique, s'il en faut.
