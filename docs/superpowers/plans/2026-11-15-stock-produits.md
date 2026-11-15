# Stock des produits : plan de mise en œuvre

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Acheter aux boutiques des conditionnements entiers, garder le reliquat en stock valorisé au coût moyen, composer des produits fabriqués à partir d'autres produits, et ne compter le coût du reliquat que dans la commande qui l'utilise.

**Architecture:** Un registre de mouvements de stock immuable (`stock_movements`) et un état par produit (`product_stocks`, coût moyen pondéré) sont écrits par une seule action sous verrou. Les besoins d'une commande sont éclatés en composants (`order_item_needs`) à la génération des bons de commande ; le stock disponible est servi d'abord (plus ancienne commande en premier), le manque est acheté en conditionnements entiers. Les prix de vente ne sont jamais touchés.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia v3 + React 19 + Tailwind v4, Pest 4, Wayfinder, SQLite (dev/tests) et PostgreSQL (production).

**Spec:** `docs/superpowers/specs/2026-11-15-stock-produits-design.md` (approuvé par le responsable).

## Global Constraints

- Les **prix de vente n'ont rien à voir avec le prix d'achat** : `line_sale_total`, `total_sale`, `sale_price_per_kit`, `fixed_sale_price` et les paiements ne sont jamais modifiés par le stock.
- Totaux en FCFA entiers ; prix unitaires, coût moyen et `unit_cost` en `decimal(14,2)` lus par le cast `App\Casts\Amount` ; tout total calculé est arrondi au FCFA le plus proche.
- Quantités de stock : entiers. Quantité d'un composant par unité fabriquée : `decimal(10,3)` ; le besoin d'achat d'un composant est `ceil(quantité de la ligne × quantité du composant)`.
- Jamais de blocage de commande à cause du stock ; jamais de stock négatif (refus explicite d'une sortie ou d'une correction qui le rendrait négatif).
- `stock_movements` est immuable (modèle : `updating`/`deleting` renvoient `false`, comme `Payment`) ; écrire dans `audit_logs` uniquement par `AuditLog::record()`.
- Logique d'écriture dans `app/Actions/Stock/` et `app/Actions/Purchases/`, lectures dans `app/Support/`, contrôleurs minces.
- Textes fr/en dans `lang/{fr,en}/`, apostrophes françaises échappées `\'` (I18N-17), écrire les clés avec Edit/Write (pas de shell), puis `php -l lang/*/*.php`. Un nouveau groupe de langue visible côté interface s'ajoute à `config('app.client_translation_groups')`.
- Nouvelle action de droit = enum `PermissionAction`, routes `can:`, `lang/*/profiles.php`, menu, ligne de la checklist `playbook/10-checklists.md`, migration qui la donne aux profils existants (modèle : `2026_11_07_090000_give_actions_to_existing_profiles.php`).
- Un modèle de référence nouveau utilise le trait `Audited` et ses phrases vont dans `lang/*/orders.php` `audit.actions` (comme `ProductPrice`).
- Interface : expert UI/UX (playbook UI-00), thème clair et sombre, fr/en, téléphone au grand écran, tous les états, champs alignés.
- **Règle du responsable pour ce projet : les tests sont écrits mais jamais lancés** (ni Pest, ni tsc, ni PHPStan) sauf demande explicite ; seuls Pint, `php -l`, `npx vp fmt` et `npm run build` se lancent. Les étapes « Run » ci-dessous ne s'exécutent donc que si le responsable le demande. **Ne pas commiter** avant sa demande.
- À chaque tâche : mettre à jour `README-FONCTIONNEL.md` (règle) et `README-DONNEES.md` (tables) dans la même tâche.

## Review Focus

- Deux générations de bons presque simultanées ne doivent pas servir deux fois la même unité de stock : test avec une seule unité en stock et deux commandes, une seule la reçoit.
- Une commande annulée après prise au stock rend les unités **à leur coût de sortie** et son bon n'est pas recréé sans elles ; une commande livrée ne rend rien.
- Une réception partielle puis une deuxième envoyée avec la même quantité cumulée ne double pas l'entrée en stock (seul le delta compte).
- Changer `pack_size` d'un produit n'altère ni les bons existants ni les mouvements passés ; seules les générations suivantes l'utilisent.
- Un composant archivé, sans boutique ou sans prix : la génération le compte comme « sans boutique » (déjà traité pour les lignes sans boutique) et ne plante pas ; un produit fabriqué sans composant n'est pas acheté.

---

### Task 1: Conditionnement et arrondi du bon

**Files:**
- Create: `database/migrations/2026_11_17_090000_add_pack_size_to_products.php`, `app/Support/PackQuantity.php`
- Modify: `app/Models/Product.php`, `app/Http/Requests/StoreProductRequest.php`, `app/Http/Requests/UpdateProductRequest.php`, `app/Http/Controllers/ProductController.php`, `app/Actions/Purchases/GeneratePurchaseOrders.php`, `resources/js/pages/products/create.tsx`, `resources/js/pages/products/edit.tsx`, `lang/{fr,en}/catalog.php`, `lang/{fr,en}/validation.php`
- Test: `tests/Feature/Purchases/PackRoundingTest.php`, `tests/Feature/Catalog/ProductManagementTest.php`

**Interfaces:**
- Produces: `products.pack_size` (unsigned int, défaut 1) ; `Product::$pack_size: int` ; `App\Support\PackQuantity::units(int $needed, int $packSize): int` (plus petit multiple de `$packSize` ≥ `$needed`, 0 si `$needed <= 0`) et `PackQuantity::packs(int $needed, int $packSize): int` (nombre de conditionnements).

- [ ] **Step 1: Write the failing tests** — `PackQuantityTest` (unitaire, sans base) : `units(99, 10) === 100`, `units(90, 10) === 90`, `units(0, 10) === 0`, `packs(99, 10) === 10`, `units(5, 1) === 5`. Dans `PackRoundingTest` : un produit `pack_size` 10 avec deux commandes totalisant 99 unités donne une ligne de bon à `quantity_ordered` 100 et `expected_total` = 100 × prix ; un produit `pack_size` 1 garde exactement le comportement actuel (une ligne par `produit|prix`). Dans `ProductManagementTest` : `pack_size` absent ou 0 est refusé, `10` est enregistré à la création et à la modification.
- [ ] **Step 2: Run (only if the owner asks):** `php artisan test --compact tests/Feature/Purchases/PackRoundingTest.php` — attendu : FAIL.
- [ ] **Step 3: Migration + modèle** — colonne `unsignedInteger('pack_size')->default(1)` ; ajouter `pack_size` au `#[Fillable]` et au cast `integer` de `Product` ; règle `'pack_size' => ['required', 'integer', 'min:1', 'max:100000']` dans les deux requêtes ; `ProductController::store/update` l'incluent dans `only([...])`.
- [ ] **Step 4: `PackQuantity`** (classe `final`, méthodes statiques, `intdiv`/`ceil`, pas d'état).
- [ ] **Step 5: `GeneratePurchaseOrders`** — pour un produit à `pack_size > 1`, regrouper **par produit** (pas par prix), arrondir la somme avec `PackQuantity::units`, prendre le prix de la sous-ligne qui a la plus grande quantité ; pour `pack_size = 1` garder le regroupement `produit|prix` actuel. `$expected` = somme des `quantity_ordered × price` arrondie à l'entier.
- [ ] **Step 6: Écran produit** — champ « Unités par conditionnement » (nombre, défaut 1, aide : « 1 botte = 10 barres ») dans `create.tsx` et `edit.tsx`, aligné sur la grille existante, avec la ligne d'aide « Prix du conditionnement : X FCFA » calculée côté client depuis le prix d'achat saisi ; textes fr/en `catalog.products.form.pack_size*`, attribut de validation fr/en.
- [ ] **Step 7: Vérifier** — `vendor/bin/pint --format agent`, `php -l` sur les fichiers de langue, `npx vp fmt` sur les pages, `npm run build`.

### Task 2: Produits fabriqués (composition) et éclatement du besoin

**Files:**
- Create: `database/migrations/2026_11_17_100000_create_product_components_table.php`, `app/Models/ProductComponent.php`, `app/Support/ProductPricing.php`, `app/Support/OrderNeeds.php`, `app/Http/Requests/Concerns/ProductCompositionRules.php`, `database/factories/ProductComponentFactory.php`
- Modify: `app/Models/Product.php`, `app/Http/Requests/UpdateProductRequest.php`, `app/Http/Controllers/ProductController.php`, `app/Actions/Orders/CreateOrder.php`, `app/Support/KitPricing.php`, `app/Http/Controllers/KitController.php`, `app/Actions/Purchases/GeneratePurchaseOrders.php`, `resources/js/pages/products/edit.tsx`, `lang/{fr,en}/catalog.php`, `lang/{fr,en}/orders.php` (phrases d'audit)
- Test: `tests/Feature/Catalog/ProductCompositionTest.php`, `tests/Feature/Purchases/ManufacturedNeedsTest.php`

**Interfaces:**
- Consumes: `Product::$pack_size`, `PackQuantity::units` (Task 1).
- Produces: table `product_components(id, product_id, component_product_id, quantity decimal(10,3), timestamps)` avec unique `(product_id, component_product_id)` ; `ProductComponent` (trait `Audited`, `#[Fillable(['product_id','component_product_id','quantity'])]`, cast `quantity` => `'float'`) ; `Product::components(): HasMany<ProductComponent>` ; `Product::isManufactured(): bool` (a au moins un composant) ; `ProductPricing::purchasePrice(Product $product): int|float|null` (produit simple : `currentPrice->purchase_price` ; fabriqué : somme `quantité × prix d'achat courant` de ses composants, `null` si un composant n'a pas de prix) ; `OrderNeeds::forItems(Collection $items): Collection` de lignes `array{order_item_id: int, product_id: int, shop_id: int|null, quantity: int, unit_price: int|float}` où un produit simple donne une ligne égale à lui-même et un fabriqué une ligne par composant avec `quantity = (int) ceil($item->quantity × $component->quantity)` et `unit_price` = prix d'achat courant du composant.

- [ ] **Step 1: Write the failing tests** — `ProductCompositionTest` : enregistrer une composition valide ; refuser un composant égal au produit, un composant lui-même fabriqué, un produit déjà composant d'un autre qui devient fabriqué, un composant archivé, une quantité ≤ 0, un composant en double. `ManufacturedNeedsTest` : un kit contenant un produit fabriqué (2 barres + 1 sac de ciment) commandé en 3 kits de 9 unités génère un bon avec les **composants** (54 barres, 27 sacs) et aucune ligne pour le produit fabriqué ; le prix d'achat figé de la ligne fabriquée à la création de la commande vaut `2 × prix barre + 1 × prix sac` ; `OrderNeeds::forItems` renvoie `ceil(9 × 0,5)` = 5 pour un composant à 0,5.
- [ ] **Step 2: Run (only if the owner asks):** `php artisan test --compact tests/Feature/Catalog/ProductCompositionTest.php tests/Feature/Purchases/ManufacturedNeedsTest.php` — attendu : FAIL.
- [ ] **Step 3: Migration, modèle, factory, relations** — clés étrangères `restrictOnDelete`, unique composé ; phrases d'audit fr/en de `product_component`.
- [ ] **Step 4: `ProductCompositionRules`** (trait renvoyant `compositionRules(): array`) utilisé par `UpdateProductRequest` : `components` tableau facultatif de `{component_product_id, quantity}` avec les refus ci-dessus (règle `Closure` pour les cas qui lisent la base) ; `ProductController::update` remplace les composants dans une transaction (supprimer puis créer, une écriture d'audit par composant via `Audited`).
- [ ] **Step 5: `ProductPricing`** puis remplacer l'accès direct à `currentPrice->purchase_price` dans `CreateOrder` (prix figé de la ligne), `KitPricing::summary` (prix de revient d'une ligne) et `KitController` (`unit_purchase_price` affiché) ; un fabriqué dont un composant n'a pas de prix compte comme « prix manquant » comme un produit simple sans prix.
- [ ] **Step 6: `OrderNeeds` + `GeneratePurchaseOrders`** — remplacer la lecture directe des `order_items` par `OrderNeeds::forItems`, regrouper par `shop_id` du composant (les composants sans boutique restent « non affectés »), puis appliquer le regroupement et l'arrondi de la Task 1. Une ligne fabriquée sans composant n'est pas achetée.
- [ ] **Step 7: Écran** — bloc « Composition » dans `products/edit.tsx` (liste produit + quantité, ajouter/retirer, aide, état vide), un produit fabriqué masque boutique et prix d'achat avec la mention « calculé depuis ses composants ».
- [ ] **Step 8: Vérifier** — Pint, `php -l`, `vp fmt`, `npm run build`.

### Task 3: Registre de stock, coût moyen, corrections (noyau)

**Files:**
- Create: `database/migrations/2026_11_17_110000_create_stock_tables.php`, `app/Enums/StockMovementType.php`, `app/Models/StockMovement.php`, `app/Models/ProductStock.php`, `app/Exceptions/InvalidStockMovement.php`, `app/Actions/Stock/RecordStockMovement.php`, `app/Actions/Stock/AdjustStock.php`, `app/Support/StockLevels.php`, `database/factories/StockMovementFactory.php`
- Modify: `app/Models/Product.php`, `lang/{fr,en}/orders.php` (phrases `stock.*`), `lang/{fr,en}/validation.php`
- Test: `tests/Feature/Stock/RecordStockMovementTest.php`, `tests/Feature/Stock/AdjustStockTest.php`

**Interfaces:**
- Produces: `StockMovementType` backed string : `Receipt='receipt'`, `Allocation='allocation'`, `Release='release'`, `Adjustment='adjustment'` ; tables `stock_movements(id, product_id, type, quantity int signé, unit_cost decimal(14,2), order_item_id nullable, purchase_order_item_id nullable, reason nullable, user_id nullable, created_at)` et `product_stocks(product_id unique, quantity_on_hand int, average_cost decimal(14,2))` ; `RecordStockMovement::handle(Product $product, StockMovementType $type, int $quantity, int|float $unitCost, ?User $user = null, ?string $reason = null, ?int $orderItemId = null, ?int $purchaseOrderItemId = null): StockMovement` ; `AdjustStock::handle(Product $product, int $quantity, int|float|null $unitCost, string $reason, User $user): StockMovement` ; `StockLevels::forProduct(int $productId): array{on_hand: int, average_cost: int|float, value: int}` ; `InvalidStockMovement(string $field, string $messageKey)`.

- [ ] **Step 1: Write the failing tests** — `RecordStockMovementTest` : une entrée de 10 à 1 000 sur stock vide donne `on_hand` 10, coût moyen 1 000 ; puis une entrée de 10 à 1 200 donne `on_hand` 20, coût moyen 1 100 ; une sortie de 5 laisse le coût moyen à 1 100 ; une sortie de 16 sur 15 en stock lève `InvalidStockMovement('quantity', 'stock.errors.insufficient')` et n'écrit rien ; `quantity` 0 refusé ; `Allocation` doit être négative, `Receipt`/`Release` positives ; le modèle refuse `update()` et `delete()` ; deux appels concurrents simulés (verrou `lockForUpdate`) laissent un solde cohérent ; le mouvement écrit une entrée d'audit `stock.received|allocated|released|adjusted` selon le type. `AdjustStockTest` : motif vide refusé, correction positive sans coût ou à coût 0 refusée, correction négative prend le coût moyen courant, jamais sous zéro.
- [ ] **Step 2: Run (only if the owner asks):** `php artisan test --compact tests/Feature/Stock` — attendu : FAIL.
- [ ] **Step 3: Migration + modèles** — `StockMovement` immuable (`const UPDATED_AT = null`, événements `updating`/`deleting`), cast `unit_cost` par `Amount`, `type` par l'enum ; `ProductStock` clé `product_id`.
- [ ] **Step 4: `RecordStockMovement`** dans `DB::transaction` : `lockForUpdate` sur la ligne `product_stocks` (créée si absente), contrôle du signe selon le type et du solde, nouveau coût moyen `round((stock × moyen + q × coût) / (stock + q), 2)` pour toute quantité positive, inchangé pour une sortie, mouvement créé, `AuditLog::record` avec `product_id`, `type`, `quantity`, `unit_cost`.
- [ ] **Step 5: `AdjustStock`** : valide motif, coût (positif exigé pour une entrée), appelle `RecordStockMovement` avec `Adjustment` (coût moyen courant pour une sortie).
- [ ] **Step 6: `StockLevels`** (lecture seule, `value` = `round(on_hand × average_cost)`).
- [ ] **Step 7: Vérifier** — Pint, `php -l` sur les langues. README-DONNEES : tables `stock_movements`, `product_stocks`.

### Task 4: Écran Stock et droit « corriger le stock »

**Files:**
- Create: `app/Http/Controllers/StockController.php`, `app/Http/Requests/AdjustStockRequest.php`, `resources/js/pages/stock/index.tsx`, `resources/js/pages/stock/show.tsx`, `lang/fr/stock.php`, `lang/en/stock.php`, `database/migrations/2026_11_17_120000_give_stock_adjust_to_existing_profiles.php`
- Modify: `app/Enums/PermissionAction.php`, `routes/web.php`, `resources/js/components/app-sidebar.tsx`, `config/app.php` (groupe `stock`), `lang/{fr,en}/navigation.php`, `lang/{fr,en}/profiles.php`, `playbook/10-checklists.md`
- Test: `tests/Feature/Stock/StockPagesTest.php`

**Interfaces:**
- Consumes: `StockLevels`, `AdjustStock::handle(...)` (Task 3).
- Produces: `PermissionAction::StockAdjust = 'stock.adjust'` ; routes `stock.index` (GET `stock`), `stock.show` (GET `stock/{product}`), `stock.adjust` (POST `stock/{product}/adjust`, `can:stock.adjust`), toutes sous `can:manage-purchases`.

- [ ] **Step 1: Write the failing tests** — un profil avec le menu achats voit `stock.index` (props : liste `{product_id, name, unit, pack_size, on_hand, average_cost, value}`, produits fabriqués exclus) ; sans le menu : 403 ; `stock.show` renvoie les mouvements du produit du plus récent au plus ancien ; `stock.adjust` exige le droit, un mot de passe valide (`current_password`), un motif, et refuse un stock négatif ; un ajustement écrit un mouvement et une entrée d'audit ; la migration donne `stock.adjust` aux profils qui ont déjà `purchases.generate`.
- [ ] **Step 2: Run (only if the owner asks):** `php artisan test --compact tests/Feature/Stock/StockPagesTest.php` — attendu : FAIL.
- [ ] **Step 3: Droit, routes, migration, langues, menu** (« Stock » sous Achats).
- [ ] **Step 4: `StockController`** (index, show, adjust) minces : lecture via `StockLevels`, écriture via `AdjustStock`, `InvalidStockMovement` renvoyé en erreur de champ.
- [ ] **Step 5: Pages** — tableau produit · conditionnement · en stock · coût moyen · valeur avec état vide et tri par nom ; fiche produit avec mouvements (date, type, quantité signée, coût, motif, qui) ; panneau « Corriger le stock » (quantité signée, coût si entrée, motif, mot de passe, confirmation).
- [ ] **Step 6: Vérifier** — Pint, `php -l`, `vp fmt`, `npm run build` ; ligne de checklist ajoutée (droit + écran).

### Task 5: Bons de réapprovisionnement et réception en stock

**Files:**
- Create: `database/migrations/2026_11_17_130000_add_kind_to_purchase_orders.php`, `app/Actions/Purchases/CreateRestockPurchaseOrder.php`, `app/Http/Requests/StoreRestockPurchaseRequest.php`, `resources/js/pages/purchases/restock.tsx`
- Modify: `app/Models/PurchaseOrder.php`, `app/Actions/Purchases/ReceivePurchaseOrder.php`, `app/Http/Controllers/PurchaseController.php`, `routes/web.php`, `resources/js/pages/purchases/index.tsx`, `resources/js/pages/stock/index.tsx` (bouton « Commander pour le stock »), `lang/{fr,en}/purchases.php`
- Test: `tests/Feature/Purchases/RestockPurchaseTest.php`

**Interfaces:**
- Consumes: `RecordStockMovement::handle(...)` (Task 3), `Product::$pack_size`.
- Produces: `purchase_orders.kind` (`'orders'` défaut, `'restock'`) ; `PurchaseOrder::KIND_ORDERS`, `KIND_RESTOCK` ; `CreateRestockPurchaseOrder::handle(Shop $shop, array $lines, ?User $user = null): PurchaseOrder` avec `$lines` = liste de `array{product_id: int, packs: int}` ; routes `purchases.restock.create` (GET `purchases/restock`) et `purchases.restock.store` (POST `purchases/restock`), middleware `can:purchases.generate`.

- [ ] **Step 1: Write the failing tests** — création : `packs: 3` sur un produit à `pack_size` 10 donne `quantity_ordered` 30 au prix d'achat courant, `expected_total` = 30 × prix, `kind` = `restock`, aucune ligne de commande rattachée ; produit d'une autre boutique, produit fabriqué, archivé ou `packs` ≤ 0 refusés ; réception complète d'un bon de réapprovisionnement crée un mouvement `Receipt` de 30 au prix réel (`unit_price_actual` sinon prévu) et le coût moyen suit ; réception partielle 12 puis cumul 30 crée deux mouvements (12 puis 18), jamais 12 + 30 ; renvoyer le même cumul ne crée rien ; l'annulation d'un bon brouillon ne touche pas le stock ; la dette « Dû aux boutiques » compte ce bon dès réception.
- [ ] **Step 2: Run (only if the owner asks):** `php artisan test --compact tests/Feature/Purchases/RestockPurchaseTest.php` — attendu : FAIL.
- [ ] **Step 3: Migration + modèle, action de création** (numéro par `DocumentNumbers::next('purchase_order')`, audit `purchase_order.created` avec `kind`).
- [ ] **Step 4: `ReceivePurchaseOrder`** — lire `quantity_received` avant la mise à jour, calculer le delta par ligne, et pour un bon `restock` appeler `RecordStockMovement` (`Receipt`, `quantity` = delta, `unit_cost` = prix réel sinon prévu, `purchaseOrderItemId`) dans la même transaction que la mise à jour des lignes.
- [ ] **Step 5: Contrôleur, requête, routes, écran** — page « Commander pour le stock » (boutique, produits de cette boutique, nombre de conditionnements, total estimé), lien depuis l'écran Stock et la liste des bons ; la liste et la fiche d'un bon affichent le type (« Pour le stock » / « Pour des commandes »).
- [ ] **Step 6: Vérifier** — Pint, `php -l`, `vp fmt`, `npm run build`.

### Task 6: Prise au stock à la génération, annulation, coût de revient

**Files:**
- Create: `database/migrations/2026_11_17_140000_create_order_item_needs_table.php`, `app/Models/OrderItemNeed.php`, `app/Actions/Stock/ReleaseOrderStock.php`, `app/Actions/Stock/AllocatePurchasedStock.php`
- Modify: `app/Actions/Purchases/GeneratePurchaseOrders.php`, `app/Actions/Purchases/ReceivePurchaseOrder.php`, `app/Actions/Purchases/CancelPurchaseOrder.php`, `app/Actions/Orders/TransitionOrder.php` (ou l'action d'annulation en cours), `app/Support/OrderProfit.php`, `app/Http/Controllers/PurchaseController.php`, `app/Http/Controllers/OrderController.php`, `resources/js/pages/purchases/show.tsx`, `resources/js/components/order-products.tsx` (ou la vue des produits de la commande), `lang/{fr,en}/purchases.php`, `lang/{fr,en}/orders.php`
- Test: `tests/Feature/Stock/StockAllocationTest.php`, `tests/Feature/Stock/OrderCostWithStockTest.php`

**Interfaces:**
- Consumes: `OrderNeeds::forItems`, `PackQuantity::units` (Tasks 1-2), `RecordStockMovement::handle`, `StockLevels` (Task 3).
- Produces: table `order_item_needs(id, order_item_id, product_id, quantity int, quantity_from_stock int default 0, stock_unit_cost decimal(14,2) nullable, quantity_purchased int default 0, purchase_unit_cost decimal(14,2) nullable, purchase_order_item_id nullable, released_at nullable, timestamps)` ; `OrderItemNeed` (relation `orderItem`, `product`, `purchaseOrderItem`) ; `ReleaseOrderStock::handle(Order $order, ?User $user = null): int` (unités rendues) ; `AllocatePurchasedStock::handle(PurchaseOrderItem $item, int $delta, int|float $unitCost, ?User $user = null): void` ; `OrderProfit::actualCost(OrderItem $item): int` utilise les besoins quand l'article en a.

- [ ] **Step 1: Write the failing tests** — `StockAllocationTest` : 30 barres en stock à 1 000 et deux commandes de 20 barres (la plus ancienne d'abord) : la première prend 20 du stock, la seconde 10 et en achète 10, arrondi à la botte de 10 → le bon porte 10 ; commande à 100 % couverte par le stock : aucune ligne de bon pour ce produit ; stock à 1 unité et deux commandes : une seule la reçoit (Review Focus 1) ; annuler une commande qui a pris 20 du stock rend 20 au coût de sortie et le coût moyen se recalcule ; commande livrée annulée : rien rendu ; annuler le bon brouillon rend les unités et supprime les besoins pour permettre de régénérer ; réception d'un bon de commandes (60 reçus pour 50 nécessaires à 1 100) : entrée 60, sortie 50 attribuée aux besoins, **10 restent en stock à 1 100** ; réception partielle puis cumul ne double rien ; changer `pack_size` après génération ne modifie ni bon ni mouvements. `OrderCostWithStockTest` : coût d'une ligne = `from_stock × coût du stock + (quantité − from_stock) × prix réel` ; le reliquat n'entre dans aucun coût tant qu'une commande ne l'utilise pas ; `line_sale_total` et `total_sale` identiques avant et après ; une ligne sans besoin garde l'ancien calcul.
- [ ] **Step 2: Run (only if the owner asks):** `php artisan test --compact tests/Feature/Stock/StockAllocationTest.php tests/Feature/Stock/OrderCostWithStockTest.php` — attendu : FAIL.
- [ ] **Step 3: Migration + `OrderItemNeed`** (clés étrangères `cascadeOnDelete` sur la ligne, `nullOnDelete` sur la ligne de bon).
- [ ] **Step 4: `GeneratePurchaseOrders`** dans la transaction existante : verrouiller les `product_stocks` concernés ; pour chaque produit du besoin, parcourir les lignes de la plus ancienne commande à la plus récente ; part servie = `min(reste du stock, quantité)` → mouvement `Allocation` (négatif, au coût moyen du moment, `orderItemId`), `quantity_from_stock` et `stock_unit_cost` écrits sur le besoin ; manque total par produit → `PackQuantity::units` (le surplus d'arrondi n'est attribué à aucun besoin) ; `quantity_ordered` du bon = ce total arrondi, prix de la Task 1 ; si le manque est nul, aucune ligne de bon ; les besoins sont reliés à leur ligne de bon (`purchase_order_item_id`).
- [ ] **Step 5: `AllocatePurchasedStock` + `ReceivePurchaseOrder`** — pour un bon `orders`, à chaque delta reçu : `Receipt` de tout le delta, puis `Allocation` de `min(delta, manque non encore attribué)` répartie sur les besoins du plus ancien au plus récent, qui reçoivent `quantity_purchased` et `purchase_unit_cost` (prix réel sinon prévu) ; le surplus d'arrondi reste en stock à ce prix.
- [ ] **Step 6: Annulation** — `ReleaseOrderStock` appelé quand une commande non livrée est annulée (mouvement `Release` au `stock_unit_cost` de chaque besoin, `released_at` posé) ; `CancelPurchaseOrder` rend les prises au stock de ses besoins et les supprime avant de détacher les lignes.
- [ ] **Step 7: `OrderProfit::actualCost`** — si l'article a des besoins : somme sur les besoins de `quantity_from_stock × stock_unit_cost + (quantity − quantity_from_stock) × (purchase_unit_cost sinon prix prévu du besoin)`, arrondie au FCFA ; sinon calcul actuel inchangé. La synthèse par boutique du tableau de bord en profite sans changement de structure ; les lignes de produits fabriqués restent dans la ligne « — » de cette synthèse (limite connue, à signaler au responsable).
- [ ] **Step 8: Écrans** — fiche d'un bon : colonnes « Besoin », « Pris au stock », « À acheter (en conditionnements) » ; fiche commande, onglet Produits : mention « dont N en stock » sur la ligne concernée ; textes fr/en.
- [ ] **Step 9: Vérifier** — Pint, `php -l` sur les langues, `vp fmt`, `npm run build`, puis relecture des tests écrits.

---

## Auto-relecture

- **Couverture du document de conception :** §5.1 données → Tasks 1-3 et 6 ; §5.2 règles 1-3 → Task 6 (et Task 1 pour l'arrondi), règle 4 → Task 6, règle 5 → Tasks 3-4, règle 6 → Contraintes globales, règle 7 → Task 1 (`pack_size` = 1 inchangé) et Task 6 (lignes sans besoin) ; §5.3 écrans → Tasks 1, 2, 4, 5, 6 ; §5.4 droits et audit → Tasks 3-4 ; §5.5 reprise → aucune migration de données, stock d'ouverture par correction (Task 4) ; §5.6 composition → Task 2 ; §5.7 réapprovisionnement → Task 5. Aucun écart.
- **Cohérence des types :** `PackQuantity::units/packs`, `ProductPricing::purchasePrice`, `OrderNeeds::forItems`, `RecordStockMovement::handle`, `AdjustStock::handle`, `StockLevels::forProduct`, `CreateRestockPurchaseOrder::handle`, `ReleaseOrderStock::handle` et `AllocatePurchasedStock::handle` portent les mêmes noms dans les tâches qui les définissent et celles qui les utilisent.
- **Limites à dire au responsable à la livraison :** la synthèse par boutique range les lignes de produits fabriqués dans « — » ; un seul niveau de composition ; main-d'œuvre non comptée ; « en stock » = disponible après réservation.
