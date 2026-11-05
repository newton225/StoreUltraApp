<?php

return [

    'head' => 'Kits',
    'title' => 'Kits',
    'description' => 'What a kit contains, what it costs and what it sells for',
    'new' => 'New kit',
    'open' => 'Open',
    'empty' => 'No kit yet',
    'created' => 'Kit created.',
    'updated' => 'Kit updated.',
    'version_created' => 'Version added.',
    'no_version' => 'No version in effect',
    'incomplete' => 'Some prices are missing',

    'columns' => [
        'name' => 'Kit',
        'version' => 'Current composition',
        'version_help' => 'A kit is never edited: a change creates a new version (1, 2, 3…). New orders use the current version; orders already made keep theirs, with their prices.',
        'purchase' => 'Purchase price',
        'purchase_help' => 'What the products of the kit cost at the shops, at the purchase prices in effect.',
        'sale' => 'Sale price',
        'margin' => 'Margin',
        'margin_help' => 'Theoretical margin: sale price minus purchase price. The real profit is worked out with the purchases really paid and the logistics (Profit page).',
    ],

    'create' => [
        'head' => 'New kit',
        'title' => 'New kit',
        'description' => 'The first version of the composition is recorded with the kit',
        'submit' => 'Create kit',
    ],

    'form' => [
        'name_fr' => 'Name (French)',
        'name_en' => 'Name (English)',
        'active' => 'Active',
        'save' => 'Save',
    ],

    'composition' => [
        'sale_price_mode_help' => '“Sum of the line prices”: the price of the kit is the sum of the prices of its products. “Fixed price”: you give the kit a single price, whatever the lines.',
        'valid_from_help' => 'Date from which this composition applies to new orders. Orders already created keep their prices.',
        'title' => 'Composition',
        'sale_price_mode' => 'Sale price',
        'mode_lines' => 'Sum of the line prices',
        'mode_fixed' => 'Fixed price for the kit',
        'fixed_sale_price' => 'Kit sale price (FCFA)',
        'valid_from' => 'Applies from',
        'add_line' => 'Add a product',
        'remove_line' => 'Remove this line',
        'product' => 'Product',
        'quantity' => 'Quantity per kit',
        'line_sale_price' => 'Sale price of the line (FCFA)',
        'choose_product' => 'Choose a product',
        'no_lines' => 'Add at least one product',
    ],

    'show' => [
        'versions_title' => 'Versions',
        'versions_description' => 'A version is never edited: a change is a new version, and orders keep the one they were made with. Amounts use today\'s purchase prices.',
        'current' => 'Current',
        'upcoming' => 'Upcoming',
        'new_version_title' => 'New version',
        'new_version_description' => 'Start from the current composition and change what you need',
        'new_version_submit' => 'Add version',
        'version' => 'Version :number',
        'from' => 'from :date',
        'unit_purchase' => 'Unit purchase price',
        'line_purchase' => 'Purchase of the line',
        'line_sale' => 'Sale of the line',
        'missing_price' => 'no price',
    ],

    'summary' => [
        'title' => 'Prices',
        'per_kit' => 'Per kit',
        'per_client' => 'Per client (:kits kits)',
        'for_clients' => 'For :clients clients',
        'clients' => 'Number of clients',
        'purchase' => 'Purchase',
        'purchase_help' => 'Cost of the products of the kit at the purchase prices in effect.',
        'sale' => 'Sale',
        'margin' => 'Margin',
        'margin_help' => 'Theoretical margin: sale minus purchase. The real margin is on the Profit page.',
        'missing' => 'Purchase price missing for: :products',
    ],

];
