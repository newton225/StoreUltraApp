# Stock des produits achetés : conditionnement, reliquat et coût moyen

Document de conception. État : **à approuver par le responsable** (aucun code écrit).

## 1. Intention et pourquoi

Une boutique vend certains produits par **conditionnement** (exemple : la botte de barres de fer, 10 barres). Un kit
n'en consomme que quelques unités (exemple : 9 barres), et les produits préfabriqués des kits en prennent encore moins.
Le nombre d'unités dont les commandes ont besoin ne tombe donc presque jamais sur un nombre entier de conditionnements,
alors que la boutique se paie **au conditionnement entier**.

Aujourd'hui l'application ne connaît pas ce décalage : le bon de commande reprend la somme exacte des lignes de commande
(`GeneratePurchaseOrders`), la quantité à payer à la boutique ne colle pas, et rien ne retient le reliquat pour la
commande suivante. Le responsable veut :

- payer la boutique pour des conditionnements entiers ;
- garder le reliquat **en stock** et l'utiliser aux commandes suivantes ;
- ne compter ce reliquat en coût que **dans la commande qui l'utilise** (choix « B » du responsable), pas dans celle qui
  a déclenché l'achat.

Contrainte énoncée par le responsable : **les prix de vente n'ont rien à voir avec le prix d'achat.** Le stock ne
modifie jamais un prix de vente, une ligne de vente (`line_sale_total`), le prix d'un kit ni le total d'une commande.
Il ne touche que ce qu'on achète et ce que ça coûte (donc la marge).

## 2. Buts, non-buts, anti-buts

**Buts.** Conditionnement par produit ; bon de commande en conditionnements entiers, après déduction du stock
disponible ; stock par produit alimenté par la réception et réduit par les commandes ; coût de revient d'une commande
juste (reliquat valorisé, compté à l'usage) ; corrections de stock tracées ; tout lisible dans un écran de stock.

**Non-buts (première version).** Plusieurs entrepôts ; emplacements ; numéros de lot ; péremption ; seuils d'alerte de
réapprovisionnement ; inventaire par scan ; prix de vente liés au stock.

**Anti-buts (un échec même si ça « marche »).** Modifier l'historique (commandes, prix figés, bons déjà reçus) ; bloquer
la création d'une commande à cause du stock (règle du responsable : jamais de blocage) ; un stock négatif silencieux ;
une correction de stock sans motif ni trace.

## 3. Contraintes du projet

Montants en FCFA entiers pour les totaux et décimaux pour les prix unitaires (README-DONNEES) ; enregistrements
immuables et audités (`AuditLog::record`, jamais d'écriture directe) ; logique dans `app/Actions/Stock/`, lectures dans
`app/Support/` ; textes fr/en échappés (`\'`, I18N-17) ; thème clair et sombre ; une nouvelle permission suppose enum,
gate, routes, `lang/*/profiles.php`, menu et ligne de checklist ; le playbook (UI-00, SEC, DATA) s'applique à l'écran.

## 4. Approches envisagées

1. **Registre de mouvements + coût moyen pondéré (retenue par le responsable).** Chaque produit a un registre
   (entrées, sorties, corrections) et un coût moyen par unité. Simple à lire et à auditer ; le bénéfice d'une commande
   se calcule sans dépendre de l'ordre des achats.
2. **Lots premier entré, premier sorti.** Précis quand les prix bougent, mais le bénéfice d'une commande dépend de
   l'ordre des achats, plus dur à suivre et à corriger. Écartée.
3. **Simple compteur de reliquat sans valorisation.** Ne tient pas le coût du reliquat quand le prix de la boutique
   change : contraire au choix « B ». Écartée.

## 5. Conception retenue

### 5.1 Données

- `products.pack_size` : entier ≥ 1, défaut 1 (« 1 botte = 10 barres »). Le **prix d'achat reste par unité** (la barre),
  comme aujourd'hui ; le formulaire produit affiche l'équivalent « prix du conditionnement ». *Choix du concepteur.*
- `stock_movements` (immuable, jamais modifié ni supprimé, comme `payments`) : `product_id`, `type`
  (`receipt`, `allocation`, `release`, `adjustment`), `quantity` (signée), `unit_cost` (décimal 14,2), `order_item_id`
  nullable, `purchase_order_item_id` nullable, `reason` (obligatoire pour `adjustment`), `user_id`, `created_at`.
- `product_stocks` (une ligne par produit, mise à jour sous verrou dans la même transaction que le mouvement) :
  `quantity_on_hand`, `average_cost` (décimal 14,2). Reconstructible à partir des mouvements.
- `order_items.quantity_from_stock` (entier, défaut 0) et `order_items.stock_unit_cost` (décimal, nul si pas de stock) :
  ce que la ligne a pris au stock et à quel coût moyen à ce moment-là.

### 5.2 Règles

1. **Génération des bons de commande** (semaine donnée, par produit) : besoin = somme des quantités des lignes non
   encore couvertes ; disponible = `quantity_on_hand` (au moins 0) ; les lignes sont servies **de la plus ancienne à la
   plus récente** ; à acheter = `ceil((besoin − disponible) / pack_size) × pack_size` si besoin > disponible, sinon
   0 (pas de ligne de bon pour ce produit). Chaque ligne servie par le stock reçoit `quantity_from_stock` et
   `stock_unit_cost` (coût moyen du jour) et produit un mouvement `allocation` (négatif).
2. **Réception d'un bon** (déjà prévue : quantités reçues et prix réel) : un mouvement `receipt` de la quantité reçue au
   prix réel par unité, qui recalcule le coût moyen
   `(stock × coût moyen + reçu × prix réel) / (stock + reçu)` ; puis la part achetée pour les commandes concernées est
   attribuée à leurs lignes à ce prix réel (c'est ce que fait déjà `OrderProfit::actualCost`) ; **le surplus d'arrondi
   reste en stock**, valorisé à ce prix réel. Une réception partielle ne crée que ce qui est reçu.
3. **Coût de revient d'une ligne** = `quantity_from_stock × stock_unit_cost` + `(quantité − quantity_from_stock) ×
   prix réel (sinon prévu)`. Le coût du reliquat n'est donc compté que dans la commande qui l'utilise (choix B). Les
   lignes sans stock se calculent comme aujourd'hui.
4. **Annulation** d'une commande dont les lignes ont pris au stock et qui n'a pas été livrée : mouvement `release`
   qui rend les unités au stock **au coût de leur sortie** (le coût moyen est recalculé). Une commande déjà livrée ne
   rend rien (la correction est une écriture de stock manuelle).
5. **Correction manuelle** (casse, perte, inventaire, stock d'ouverture) : mouvement `adjustment` signé, **motif
   obligatoire**, mot de passe demandé, droit dédié ; un ajustement positif à coût nul est refusé, il doit donner un coût
   unitaire. Le stock ne peut pas devenir négatif par une correction.
6. **Jamais de blocage de commande par le stock** : le stock n'intervient qu'à la génération des bons.
7. **Produits sans stock suivi** : un produit à `pack_size` = 1 et sans mouvement se comporte exactement comme
   aujourd'hui (rien à acheter en plus, rien en stock). Aucun changement pour les commandes déjà passées ni pour
   celles qui avancent à la main (comme ORD-000029, sans bon de commande).

### 5.3 Écrans (à valider visuellement à la mise en œuvre)

- **Stock** (menu Achats ou Catalogue, *choix du concepteur*) : tableau produit · conditionnement · en stock · coût
  moyen · valeur ; clic sur un produit = ses mouvements ; bouton « Corriger le stock » (motif, quantité, coût,
  mot de passe).
- **Fiche produit** : champ « Unités par conditionnement » avec aide et équivalent prix du conditionnement.
- **Bon de commande** : colonnes « Besoin », « Pris au stock », « À acheter (en conditionnements) » pour voir d'où
  vient chaque quantité.
- **Commande, onglet Produits** : mention « dont N en stock » sur la ligne concernée.

### 5.4 Rapports et droits

- `OrderProfit` et la synthèse par boutique utilisent le coût de revient du §5.2.3 ; les prix de vente, les totaux et
  les paiements ne changent pas.
- Droits : consultation avec le droit d'achats existant ; **nouvelle action `stock.adjust`** pour les corrections
  (donnée aux profils qui gèrent déjà les achats par une migration, comme les actions précédentes).
- Audit : `stock.adjusted`, `stock.received`, `stock.allocated`, `stock.released` avec leurs phrases fr/en.

### 5.5 Mise en place et reprise

Migrations additives, sans modifier l'existant. Stock d'ouverture saisi par corrections. Aucun recalcul rétroactif :
les commandes déjà passées gardent leurs chiffres.

### 5.6 Produits fabriqués (composition) — AJOUT, à confirmer

Précision du responsable : **un produit fabriqué est composé d'autres produits** (matériaux achetés en boutique). Ce
n'est donc pas lui qu'on achète : ce sont ses composants. Cela change le calcul du besoin.

- `product_components` : `product_id` (le produit fabriqué), `component_product_id`, `quantity` (entier ou décimal,
  par unité fabriquée). Un produit qui a des composants est dit « fabriqué » ; il n'a ni boutique ni prix d'achat
  propre. Un seul niveau de composition en première version (un composant n'est pas lui-même fabriqué) ; un produit ne
  peut pas se contenir lui-même.
- **Besoin d'achat** : à la génération des bons, chaque ligne de commande d'un produit fabriqué est **éclatée en ses
  composants** (quantité de la ligne × quantité par unité fabriquée), puis ajoutée au besoin de chaque composant. Le
  §5.2 s'applique ensuite composant par composant (stock, conditionnement, arrondi).
- **Coût de revient** d'une ligne fabriquée = somme des coûts de ses composants (au coût réel ou au coût moyen du stock,
  §5.2.3). La main-d'œuvre n'est pas comptée en première version (à confirmer).
- **Prix de vente** : toujours indépendant du coût. La ligne de kit garde son prix de vente propre.
- **Stock** : suivi sur les **composants** seulement. Le produit fabriqué est produit à la demande, pour chaque
  commande (hypothèse à confirmer). Fabriquer à l'avance en série supposerait un stock de produits finis et une
  « fiche de fabrication » : hors première version.
- **Écrans** : la fiche produit gagne « Composition » (liste composant + quantité) ; le bon de commande et la commande
  montrent les composants à acheter, pas le produit fabriqué.
- **Découpage** : cette composition s'ajoute entre les étapes 1 et 3 du §8 ; elle ne peut pas se livrer avant le stock
  des composants.

### 5.7 Réapprovisionnement du stock auprès des boutiques — AJOUT, à confirmer

Précision du responsable : le stock se remplit par un **vrai processus de commande et de livraison avec les boutiques**,
pas seulement au fil des commandes clients.

- Le circuit existant des bons de commande (brouillon, envoyé, reçu en tout ou en partie, annulé, règlement de la
  boutique) sert aussi à acheter **pour le stock**. Un bon est de l'un de deux types : **pour des commandes clients**
  (généré comme aujourd'hui, §5.2) ou **de réapprovisionnement** (créé à la main : boutique, produits, quantités en
  conditionnements entiers, sans commande rattachée).
- Un bon de réapprovisionnement ne porte pas de lignes de commande (`order_items.purchase_order_id` reste nul) ; sa
  réception crée un mouvement `receipt` par ligne reçue, au prix réel, avec le recalcul du coût moyen (§5.2.2). **Tout
  ce qui est reçu entre en stock** (aucune part n'est attribuée à une commande au moment de la réception).
- Il se règle à la boutique comme les autres bons : « Dû aux boutiques » et les comptes de boutique le comptent dès
  qu'il est reçu et non payé.
- Les bons générés pour des commandes (§5.2.1) utilisent ensuite ce stock : le besoin net est réduit d'autant, et
  peut tomber à zéro (aucun achat à faire).
- Composants : seuls les produits **achetés** (non fabriqués) se commandent ; un produit fabriqué ne figure jamais
  sur un bon (§5.6).
- Un bon de réapprovisionnement peut être préparé à partir de l'écran Stock (« Commander pour le stock »), avec la
  quantité conseillée laissée libre en première version (pas de seuil automatique : non-but).
- **Droits** : générer, envoyer, recevoir, annuler et payer un bon suivent les actions d'achats existantes
  (`purchases.*`) ; aucune nouvelle action pour les bons.

## 6. Hors de ce document (ce que le constructeur tranche)

Arrondi des centimes du coût moyen (2 décimales, arrondi au plus proche) ; nom exact du menu ; ordre des colonnes ;
messages d'erreur ; gabarit PDF du bon de commande avec la colonne « Pris au stock ».

## 7. Points relevés par la relecture (choix du concepteur à confirmer)

1. **Moment de la sortie du stock** : à la génération du bon de commande (réservation), pas à la livraison. Raison :
   éviter que deux semaines de commandes comptent la même unité. Contrepartie : « en stock » = disponible, pas
   physiquement présent.
2. **Prix par unité, pas par conditionnement.** Raison : aucun changement des prix existants.
3. **Dû aux boutiques** reste lié aux bons reçus. La question ouverte (commandes avancées sans bon) est un sujet
   voisin, traité à part.
4. **Livraison de kits** : le coût moyen est figé au moment de la sortie du stock ; il ne bouge plus ensuite.

## 8. Découpage proposé

1. Conditionnement + arrondi du bon (sans stock).
2. Composition des produits fabriqués et éclatement du besoin en composants (§5.6).
3. Registre de stock, réception, coût moyen, écran Stock, corrections.
4. Bons de réapprovisionnement et leur réception en stock (§5.7).
5. Prise au stock à la génération, annulation, coût de revient dans les rapports.
6. Fiche produit, colonnes du bon, mentions sur la commande.
