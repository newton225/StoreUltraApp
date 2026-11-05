<?php

return [

    'head' => 'Kits',
    'title' => 'Kits',
    'description' => 'Ce que contient un kit, ce qu\'il coûte et son prix de vente',
    'new' => 'Nouveau kit',
    'open' => 'Ouvrir',
    'empty' => 'Aucun kit',
    'created' => 'Kit créé.',
    'updated' => 'Kit mis à jour.',
    'version_created' => 'Version ajoutée.',
    'no_version' => 'Aucune version en vigueur',
    'incomplete' => 'Des prix sont manquants',

    'columns' => [
        'name' => 'Kit',
        'version' => 'Composition en cours',
        'version_help' => 'Un kit n\'est jamais modifié : un changement crée une nouvelle version (1, 2, 3…). Les nouvelles commandes utilisent la version en cours ; les commandes déjà faites gardent la leur, avec leurs prix.',
        'purchase' => 'Prix d\'achat',
        'purchase_help' => 'Ce que coûtent les produits du kit chez les boutiques, aux prix d\'achat en vigueur.',
        'sale' => 'Prix de vente',
        'margin' => 'Marge',
        'margin_help' => 'Marge théorique : prix de vente moins prix d\'achat. Le bénéfice réel se calcule avec les achats réellement payés et la logistique (page Bénéfice).',
    ],

    'create' => [
        'head' => 'Nouveau kit',
        'title' => 'Nouveau kit',
        'description' => 'La première version de la composition est enregistrée avec le kit',
        'submit' => 'Créer le kit',
    ],

    'form' => [
        'name_fr' => 'Nom (français)',
        'name_en' => 'Nom (anglais)',
        'active' => 'Actif',
        'save' => 'Enregistrer',
    ],

    'composition' => [
        'sale_price_mode_help' => '« Somme des prix des lignes » : le prix du kit est la somme des prix de ses produits. « Prix fixé » : vous donnez un prix unique au kit, quelles que soient les lignes.',
        'valid_from_help' => 'Date à partir de laquelle cette composition s’applique aux nouvelles commandes. Les commandes déjà créées gardent leurs prix.',
        'title' => 'Composition',
        'sale_price_mode' => 'Prix de vente',
        'mode_lines' => 'Somme des prix des lignes',
        'mode_fixed' => 'Prix fixé pour le kit',
        'fixed_sale_price' => 'Prix de vente du kit (FCFA)',
        'valid_from' => 'S’applique à partir du',
        'add_line' => 'Ajouter un produit',
        'remove_line' => 'Retirer cette ligne',
        'product' => 'Produit',
        'quantity' => 'Quantité par kit',
        'line_sale_price' => 'Prix de vente de la ligne (FCFA)',
        'choose_product' => 'Choisir un produit',
        'no_lines' => 'Ajoutez au moins un produit',
    ],

    'show' => [
        'versions_title' => 'Versions',
        'versions_description' => 'Une version n\'est jamais modifiée : un changement est une nouvelle version, et les commandes gardent celle avec laquelle elles ont été faites. Les montants utilisent les prix d\'achat d\'aujourd\'hui.',
        'current' => 'Actuelle',
        'upcoming' => 'À venir',
        'new_version_title' => 'Nouvelle version',
        'new_version_description' => 'Partez de la composition actuelle et modifiez ce qui doit l\'être',
        'new_version_submit' => 'Ajouter la version',
        'version' => 'Version :number',
        'from' => 'à partir du :date',
        'unit_purchase' => 'Prix d\'achat unitaire',
        'line_purchase' => 'Achat de la ligne',
        'line_sale' => 'Vente de la ligne',
        'missing_price' => 'pas de prix',
    ],

    'summary' => [
        'title' => 'Prix',
        'per_kit' => 'Par kit',
        'per_client' => 'Par client (:kits kits)',
        'for_clients' => 'Pour :clients clients',
        'clients' => 'Nombre de clients',
        'purchase' => 'Achat',
        'purchase_help' => 'Coût des produits du kit aux prix d\'achat en vigueur.',
        'sale' => 'Vente',
        'margin' => 'Marge',
        'margin_help' => 'Marge théorique : vente moins achat. La marge réelle se voit sur la page Bénéfice.',
        'missing' => 'Prix d\'achat manquant pour : :products',
    ],

];
