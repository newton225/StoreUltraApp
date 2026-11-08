# Modèle de données et calculs

Ce document décrit **les données** que l'application conserve et **les formules** qu'elle applique. Le besoin
fonctionnel est dans [`README-FONCTIONNEL.md`](README-FONCTIONNEL.md).

Conventions :

- Les **montants** sont des entiers en FCFA (pas de décimale), stockés tels quels.
- Les noms de tables sont donnés en anglais (convention du code) ; les libellés affichés sont traduits.
- Chaque table a un identifiant, des dates de création et de modification. Les tables sensibles ne sont jamais
  supprimées physiquement (archivage ou écriture inverse).
- Une valeur **figée** est copiée dans la commande au moment de sa création : elle ne suit plus les changements de
  prix ou de paramètres.

## 1. Vue d'ensemble

```
Setting      Zone ──< Client >── Order ──< OrderItem >── Product ──< ProductPrice
 (réglages)            │            │                       │
                       │            ├──< Payment            └── Shop (boutique)
                       │            ├──< Delivery ──< Expense
                       │            └──< Document
                       └──< Attachment >── (Company | Shop | Client)

Kit ──< KitVersion ──< KitVersionItem >── Product
PurchaseOrder ──< PurchaseOrderItem >── Product        PurchaseOrder >── Shop
ShopPayment >── Shop                                   AuditLog (journal, tout objet)
```

## 2. Tables

### 2.1 Paramétrage

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `settings` | Réglages clé-valeur typés | `key`, `value`, `type` (entier, texte, booléen, liste), `scope` (global ou kit), `description` |
| `zones` | Sous-préfectures | `name`, `code`, `active` |
| `zone_user` | Affectation des agents aux zones | `zone_id`, `user_id` |
| `payment_methods` | Modes de paiement | `code`, `label_fr`, `label_en`, `requires_reference`, `active` |
| `expense_types` | Types de frais | `code`, `label_fr`, `label_en`, `active` |
| `attachment_types` | Types de documents administratifs | `code`, `label_fr`, `label_en`, `applies_to` (company, shop, client), `required`, `has_expiry`, `on_documents` (numéro imprimé en en-tête des documents émis), `active` |
| `document_sequences` | Numérotation | `document_type`, `prefix`, `year`, `last_number`, `format` |

Réglages initiaux : voir le [chapitre 7 du besoin fonctionnel](README-FONCTIONNEL.md#7-paramètres).

### 2.2 Utilisateurs

| Table | Champs principaux |
| --- | --- |
| `profiles` | Profils de droits : `code` (unique, renseigné pour les 3 profils système `owner`, `employee`, `agent`), `label_fr`, `label_en`, `restricted_to_zones` (limité aux sous-préfectures affectées), `is_system` (non modifiable, non supprimable), `permissions` (JSON, catalogue fermé : gérer les commandes, les achats, les frais, le catalogue, voir les bénéfices) |
| `users` | Table existante (Fortify) + `profile_id` (profil de droits), `role` (responsable, employé, agent : reflet du profil), `password_set_at` (date où la personne a choisi son mot de passe ; vide tant que l'invitation n'est pas acceptée), `locale` (fr ou en), `appearance` (clair, sombre, système), `active` |

Les droits se déduisent du rôle et, pour l'agent, des zones affectées (`zone_user`).

### 2.3 Boutiques, produits, prix

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `shops` | Boutiques fournisseurs | `name`, `contact`, `phone`, `address`, `payment_terms`, `active` |
| `products` | Catalogue | `name`, `unit`, `shop_id` (boutique habituelle), `archived_at` |
| `product_prices` | Historique des prix | `product_id`, `shop_id` (boutique du produit au moment de la saisie), `purchase_price`, `sale_price` (facultatif : prix de vente unitaire), `effective_from`, `created_by` |

Le prix de vente **d'un kit** n'est pas stocké ici : le classeur donne un prix de vente par ligne de kit (par exemple 40 200 F pour 7 sacs de ciment, soit un prix unitaire non entier). Il vit dans `kit_version_items.sale_price_per_kit` (étape 3). `product_prices.sale_price` ne sert qu'à une vente à l'unité et reste vide pour les produits vendus seulement en kit.

Le prix en vigueur d'un produit est la ligne de `product_prices` dont `effective_from` est la plus récente et
n'est pas dans le futur. Une ligne n'est jamais modifiée : un nouveau prix est une nouvelle ligne.

### 2.4 Kits

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `kits` | Type de kit | `name_fr`, `name_en`, `active` |
| `kit_versions` | Composition à une date | `kit_id`, `version`, `sale_price_mode` (somme ou fixé), `fixed_sale_price`, `valid_from` |
| `kit_version_items` | Lignes d'une version | `kit_version_id`, `product_id`, `quantity_per_kit`, `sale_price_per_kit` (si fixé par ligne) |

Modifier un kit crée une nouvelle `kit_versions`. Les commandes gardent la version utilisée.

### 2.5 Clients

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `access_logs` | Journal d'accès, purgé après 365 jours | `kind` (page, login_failed, lockout), `user_id`, `email`, `method`, `route`, `path`, `query` (secrets masqués), `status`, `ip_address`, `user_agent`, `locale`, `created_at` |
| `tour_completions` | Visites guidées déjà vues, par membre (préférence d'affichage, non tracée) | `user_id`, `tour` (unique avec `user_id`), `completed_at` |
| `cash_closings` | Clôtures de caisse (immuables) | `reference` (CLO-0001), `closed_on` (unique), `expected_amount`, `counted_amount`, `difference` (compté − attendu), `notes`, `closed_by` |
| `cash_movements` | Mouvements de caisse divers (immuables, annulables) | `reference` (CAI-0001), `expense_id` (frais payé), `closing_id` (écart d'une clôture), `direction` (in, out), `amount`, `occurred_on`, `label`, `counterparty`, `document_reference`, `notes`, `idempotency_key`, `recorded_by`, `cancelled_at`, `cancelled_by`, `cancel_reason` |
| `client_members` | Responsable d'un client TPE (un seul, sans interface de gestion) | `client_id`, `name`, `role` (manager), `trade` (métier), `phone`, `position` |
| `clients` | Clients (sans accès) | `reference` (CLI-0001), `commercial_number` (obligatoire, unique), `name`, `phone` (obligatoire), `zone_id`, `locality`, `delivery_address`, `contact_person`, `status`, `notes`, `created_by` |

Statuts : en attente, actif, suspendu, terminé.

### 2.6 Fourniture récurrente et commandes

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `supply_plans` | Fourniture récurrente d'un client | `client_id`, `kit_id`, `kits_per_cycle` (3 par défaut), `advance_amount` (200 000 par défaut), `frequency` (hebdomadaire par défaut), `starts_on`, `ends_on` ou `cycles_count` (nuls permis), `status` (active, en pause, terminée) |

La commande `orders:generate` (planifiée chaque nuit, et lancée aussi à la création d'un plan) crée pour chaque plan
actif d'un client actif les commandes des cycles dus : de la semaine en cours jusqu'à `order_lead_days` jours devant
(7 par défaut). Les semaines passées ne sont jamais créées. Une semaine qui a déjà une commande, même annulée, n'est
jamais recréée (unicité `supply_plan_id` + `delivery_week`). Sauter une semaine revient à annuler sa commande.
Modifier un plan ne touche pas aux commandes déjà générées. Chaque commande utilise la version de kit en vigueur à sa
semaine (ou aujourd'hui pour la semaine en cours). Si un prix manque, la commande n'est pas créée et le cas est compté.

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `orders` | Commande d'un client | `reference` (ORD-000123), `client_id`, `supply_plan_id` (nul permis), `kit_version_id`, `kits_count`, `delivery_week` (début de semaine), `status`, `advance_due` (avance due), `total_sale`, `total_purchase_expected`, `notes` |
| `order_items` | Lignes **figées** | `order_id`, `product_id`, `shop_id`, `quantity`, `unit_purchase_price`, `unit_sale_price` |
| `order_status_changes` | Transitions d'état | `order_id`, `from`, `to`, `reason`, `user_id`, `at` |

`total_sale` et `total_purchase_expected` sont calculés à la création à partir de `order_items` et conservés.

### 2.7 Encaissements

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `payments` | Versements clients | `receipt_number`, `order_id`, `kind` (avance, relicat, remboursement), `amount`, `method_id`, `reference`, `payer_name`, `payer_phone` (qui a remis l'argent, le client par défaut), `paid_on` (date), `collected_by` (qui l'a reçu), `recorded_by` (le compte connecté qui l'a saisi), `reverses_payment_id`, `notes` |

Un versement ne se modifie ni ne se supprime. Une correction crée une ligne de type remboursement qui pointe vers le
versement annulé (`reverses_payment_id`) et reprend son montant. Le numéro de reçu vient de `document_sequences`
(`REC-2026-0001`, sans trou, remis à zéro chaque année, préfixe réglable). L'avance est prise jusqu'à la réception, le
relicat à la réception ou à la livraison, jamais au-delà du prix de vente.

### 2.8 Achats aux boutiques

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `purchase_orders` | Bon de commande à une boutique | `number`, `shop_id`, `week`, `status` (brouillon, envoyé, reçu partiel, reçu), `expected_total`, `actual_total` |
| `purchase_order_items` | Lignes | `purchase_order_id`, `product_id`, `quantity_ordered`, `quantity_received`, `unit_price_expected`, `unit_price_actual` |
| `shop_payments` | Sommes versées aux boutiques | `shop_id`, `purchase_order_id`, `amount`, `method_id`, `reference`, `paid_at` |

### 2.9 Livraisons et logistique

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `deliveries` | Livraison d'une commande | `number`, `order_id`, `planned_on`, `delivered_on`, `agent_id`, `kits_delivered`, `status`, `signature_path`, `signed_at`, `signed_by_name` |
| `expenses` | Frais de logistique | `expense_type_id`, `amount`, `incurred_on`, `delivery_id` (nul permis), `zone_id`, `shop_id`, `week`, `proof_path`, `created_by` |
| `expense_allocations` | Part de frais affectée à une commande | `expense_id`, `order_id`, `amount` |

### 2.10 Documents émis et documents administratifs

| Table | Rôle | Champs principaux |
| --- | --- | --- |
| `invoices` | Factures | `number`, `client_id`, `order_id`, `issued_on`, `total`, `paid_amount`, `fne_status` (non requise, à émettre, émise), `fne_number` (nul permis), `cancelled_at` |
| `documents` | PDF émis | `type` (proforma, reçu, bon de commande, bon de livraison, facture, bordereau), `number`, `subject_type`, `subject_id`, `locale`, `file_path`, `period_start`, `period_end` (bordereau), `issued_at`, `issued_by`, `cancelled_at`, `cancelled_by`, `cancel_reason`, `replaced_by_id` |
| `invoices` | Factures | `number`, `order_id`, `client_id`, `issued_on`, `total`, `paid_amount` (payé à l'émission), `fne_status` (non requise, à émettre, émise), `fne_number`, `cancelled_at` |
| `attachments` | Documents administratifs | `attachment_type_id`, `owner_id` (nul pour l'entreprise ; la catégorie du propriétaire vient du type), `number`, `issued_on`, `expires_on`, `file_path` (nul permis : le numéro suffit), `notes` |

Un bordereau couvre une période (`period_start`, `period_end` sur `documents`) et référence les factures qu'il
liste (table pivot `document_invoices`). Une facture figure dans le bordereau de la période où elle a été émise.

### 2.11 Traçabilité

| Table | Champs principaux |
| --- | --- |
| `audit_logs` | Journal en ajout seul : `user_id` (sans clé étrangère : le journal survit au compte), `created_at`, `action`, `subject_type`, `subject_id`, `before`, `after`, `ip_address`, `user_agent`, `hash` (empreinte chaînée à l'entrée précédente ; l'adresse et le navigateur n'y entrent pas) |
| `audit_chain` | Tête de la chaîne du journal : `id` (toujours 1), `last_hash` |

La table est en **ajout seul** : aucune modification ni suppression par l'application.

## 3. Formules

Notations : `q` = quantité par kit, `pa` = prix d'achat unitaire, `pv` = prix de vente par kit de la ligne, `K` =
nombre de kits par client, `N` = nombre de clients.

### 3.1 Par kit

```
Prix d'achat d'un kit      = somme des (q × pa) de toutes les lignes
Prix de vente d'un kit     = somme des pv des lignes   (ou prix fixé du kit)
Bénéfice théorique du kit  = prix de vente − prix d'achat
```

### 3.2 Par commande (K kits)

```
Total d'achat prévu   = K × prix d'achat d'un kit
Total de vente        = K × prix de vente d'un kit            (valeurs figées dans order_items)
Avance due            = avance par versement                  (paramètre, modifiable par commande)
Relicat dû            = total de vente − somme des avances reçues
Bénéfice théorique    = total de vente − total d'achat prévu
```

Le client paie le **prix de vente** : l'avance est un acompte sur ce prix, le relicat est le complément à la
livraison. Le classeur comparait par erreur l'avance au prix d'achat.

### 3.3 Pour plusieurs clients

Les totaux de N clients sont la **somme des commandes** concernées, jamais un produit global (`× 56`) : les prix
figés peuvent différer d'une commande à l'autre.

### 3.4 Répartition de l'avance entre boutiques

La part de l'avance affectée à une boutique est proportionnelle à son prix d'achat dans le total :

```
Part de la boutique b = prix d'achat de b / prix d'achat total
Avance affectée à b   = part de b × avance totale reçue
Solde dû à b          = prix d'achat de b − avance affectée à b (− sommes déjà versées)
```

Comme les montants sont entiers, l'arrondi se fait par la méthode du **plus fort reste** : la somme des parts est
exactement égale au total.

### 3.5 Bénéfice réel

```
Chiffre d'affaires encaissé = somme des avances et relicats (hors remboursements)
Coût d'achat réel           = somme des prix d'achat réels reçus (purchase_order_items)
Frais de logistique         = somme des expense_allocations de la commande
Bénéfice réel               = chiffre d'affaires encaissé − coût d'achat réel − frais de logistique
Écart                       = bénéfice réel − bénéfice théorique
```

Répartition des frais sur les commandes : **au prorata du nombre de kits** par défaut (paramétrable).

Précisions d'implémentation :

- Un frais a une portée : commande, livraison, zone, boutique ou semaine. Il est réparti entre les commandes concernées (au prorata des kits ou à parts égales, paramètre `expense_allocation`) ; le reste de la division est distribué par plus fort reste, donc la somme des parts égale toujours le frais.
- Un frais enregistré est immuable : seule son annulation (avec motif) est possible, et elle retire ses parts du calcul.
- Le **bénéfice projeté** = prix de vente complet − coût d'achat réel − frais ; l'**écart** = bénéfice projeté − bénéfice théorique.
- Le bénéfice n'est visible que par le propriétaire, et par les utilisateurs dont le profil porte le droit « voir les bénéfices » (`view_profit`). Par défaut, le profil Agent ne l'a pas.

## 4. Intégrité et règles de données

- Un montant ne peut pas être négatif, sauf une écriture inverse explicitement typée.
- Une commande ne passe à « Commandée » que si l'avance exigée est reçue (si le paramètre l'impose).
- Les numéros de documents sont uniques et attribués dans une transaction (aucun trou, aucun doublon).
- Un document émis ne se modifie pas ; il s'annule et se remplace.
- Un client est rattaché à une seule sous-préfecture ; un agent ne lit que les clients des zones qui lui sont
  affectées (filtrage appliqué côté serveur, y compris pour les fichiers).
- Les fichiers (documents administratifs, signatures, justificatifs) sont stockés hors dossier public et servis après contrôle des
  droits.
- Les migrations sont additives et portables entre SQLite (développement, tests) et **PostgreSQL** (production)
  (`playbook/05`). Les tests sensibles (numérotation sans trou, verrous, contraintes d'unicité) doivent aussi
  tourner sur PostgreSQL avant la mise en ligne.

## 5. Données initiales (classeur Excel)

Produits et prix à importer (unité : `u`). Prix par kit = quantité × prix d'achat ; la vente est le prix de vente
par kit du classeur.

| N° | Désignation | Boutique | Qté par kit | Achat unitaire | Vente par kit |
| --- | --- | --- | --- | --- | --- |
| 1 | Barre de fer à béton de 8 | 1 | 3 | 1 775 | 9 000 |
| 2 | Tôle ondulée ordinaire | 1 | 3 | 2 800 | 10 500 |
| 3 | Chevron 6/4 | 1 | 3 | 2 300 | 7 500 |
| 4 | Ciment CPA 42,5 (sac de 50 kg) | 1 | 7 | 4 600 | 40 200 |
| 5 | Coude PVC Ø 110 | 1 | 1 | 1 300 | 1 500 |
| 6 | Tuyau PVC Ø 110 | 1 | 1 | 6 000 | 6 500 |
| 7 | Tuyau PVC Ø 75 (1/2 barre = 3 m) | 1 | 1 | 2 000 | 2 500 |
| 8 | Coude PVC Ø 75 | 1 | 1 | 800 | 1 000 |
| 9 | Té PVC Ø 75 | 1 | 1 | 800 | 1 000 |
| 10 | Collier de fixation tuyau PVC Ø 75 | 1 | 1 | 800 | 1 000 |
| 11 | Porte métallique | 1 | 1 | 22 000 | 25 000 |
| 12 | SATOPAN simple | 2 | 1 | 5 000 | 6 500 |
| 13 | À compléter par l'utilisateur | 2 | 1 | 0 | 0 |

Le produit 13 est créé vide : il s'enregistre comme n'importe quel produit une fois ses prix connus.

## 6. Jeu de référence pour les tests

Ces valeurs, calculées à partir des données du chapitre 5, doivent être reproduites **exactement** par
l'application (kit à 12 produits renseignés, 56 clients, 3 kits par client, avance de 200 000 F par client). Elles
valent **pour une semaine** de livraison ; pour plusieurs semaines, on additionne les commandes de chaque semaine.

| Mesure | Valeur |
| --- | --- |
| Prix d'achat d'un kit | 91 525 F |
| Prix de vente d'un kit | 112 200 F |
| Bénéfice théorique d'un kit | 20 675 F |
| Prix d'achat d'un client (3 kits) | 274 575 F |
| Prix de vente d'un client (3 kits) | 336 600 F |
| Bénéfice théorique d'un client | 62 025 F |
| Relicat à la livraison d'un client | 136 600 F |
| Prix d'achat pour 56 clients | 15 376 200 F |
| Prix de vente pour 56 clients | 18 849 600 F |
| Bénéfice théorique pour 56 clients | 3 473 400 F |
| Total des avances (56 × 200 000) | 11 200 000 F |
| Boutique 1 : achat pour 1 client / vente pour 1 client | 259 575 F / 317 100 F |
| Boutique 2 : achat pour 1 client / vente pour 1 client | 15 000 F / 19 500 F |
| Boutique 1 : achat pour 56 clients | 14 536 200 F |
| Boutique 2 : achat pour 56 clients | 840 000 F |
| Part de la boutique 1 dans l'achat total | 94,54 % |
| Part de la boutique 2 dans l'achat total | 5,46 % |
| Avance affectée aux boutiques 1 et 2 | 10 588 145 F et 611 855 F (somme 11 200 000 F) |
| Solde d'achat après avance (total) | 4 176 200 F |

## 7. Anomalies du classeur Excel à ne pas reproduire

| Où | Constat | Traitement dans l'application |
| --- | --- | --- |
| Feuille « Synthèse boutiques », lignes « Bénéfice » | Calculé en **achat − vente**, donc négatif (−3 473 400 F) | Toujours **vente − achat** |
| Feuille « TABLEAU DE BORD », totaux généraux (cellules des 56 clients) | Pointent vers la ligne du produit 13 (`H16`, `J16`, `L16`) au lieu de la ligne TOTAL (`H17`, `J17`, `L17`) ; ils affichent 0 | Totaux calculés par somme réelle des commandes |
| Produit 13 | Ligne « à compléter », prix à 0 | Produit saisissable normalement |
| Valeurs figées | Avance 200 000, 56 clients, 3 kits, 3 écrits dans les formules | Paramètres |
| Avance et prix | L'avance était répartie selon le prix d'achat et comparée à lui | L'avance est un acompte sur le **prix de vente** ; la répartition entre boutiques reste au prorata de l'achat |
| Frais | Aucun transport ni imprévu | Frais de logistique et bénéfice réel |
| Note du projet « 365 100 pour 3 kits » | Erreur de calcul reconnue par le responsable | Ignorée ; référence = 336 600 F |
