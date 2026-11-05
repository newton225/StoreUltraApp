<?php

return [

    'head' => 'Profiles',
    'title' => 'Profiles and rights',
    'description' => 'What each kind of user is allowed to do. The three starting profiles do not change; for different rights, create a new profile.',
    'new' => 'New profile',
    'view' => 'View',
    'edit' => 'Edit',
    'create_title' => 'New profile',
    'create_description' => 'A custom profile: you choose what it is allowed to do, then give it to users.',
    'edit_title' => 'Edit the profile',
    'created' => 'Profile created.',
    'updated' => 'Profile updated.',
    'deleted' => 'Profile deleted.',

    'kinds' => [
        'system' => 'System',
        'custom' => 'Custom',
    ],

    'columns' => [
        'name' => 'Profile',
        'kind' => 'Type',
        'rights' => 'Rights granted',
        'users' => 'Users',
    ],

    'users_count' => ':count user(s)',
    'no_rights' => 'No right besides viewing',
    'restricted_badge' => 'Assigned sub-prefectures only',
    'all_rights' => 'All rights',
    'matrix_title' => 'Rights by profile',
    'matrix_description' => 'What each profile can do, at a glance. Managing users, profiles and settings always stays reserved to the owner.',
    'matrix_right' => 'Right',
    'granted' => 'Granted',
    'not_granted' => 'Not granted',
    'restricted_row' => 'Limited to the assigned sub-prefectures',

    'system_notice' => 'System profile: it can be neither changed nor deleted. For a custom version, create a new profile from this one.',
    'duplicate' => 'Duplicate',
    'from_this' => 'Create a profile from this one',
    'template_notice' => 'The rights of ":name" are copied: change them as you wish.',
    'delete' => 'Delete this profile',
    'delete_confirm' => 'Delete the profile ":name"? This cannot be undone.',
    'in_use_help' => 'This profile is given to users: change their profile first to be able to delete it.',

    'form' => [
        'label_fr' => 'Name in French',
        'label_en' => 'Name in English',
        'permissions' => 'Rights granted',
        'permissions_help' => 'Tick what this profile is allowed to do. Whatever is not ticked is refused.',
        'restricted' => 'Limited to the assigned sub-prefectures',
        'restricted_help' => 'Users of this profile only see the clients, orders and deliveries of the sub-prefectures assigned to them, like a field agent.',
        'submit' => 'Save the profile',
    ],

    'errors' => [
        'in_use' => 'This profile is given to users: change their profile first.',
    ],

    'permissions' => [
        'manage_orders' => 'Manage orders and collections',
        'manage_orders_help' => 'Create orders and automatic deliveries, move them along, collect and refund, issue the documents (proforma, invoice, delivery note), see the payments and the late advances, and download the matching exports.',
        'manage_purchases' => 'Manage purchases from the shops',
        'manage_purchases_help' => 'Generate, send, receive and cancel the purchase orders of the shops, and record what is paid to them.',
        'manage_expenses' => 'Manage logistics costs',
        'manage_expenses_help' => 'Record, view and cancel logistics costs, and open their proofs.',
        'manage_cash' => 'Manage the cash box',
        'manage_cash_help' => 'See the cash box, record and cancel cash movements, close the cash box, link older costs paid in cash, and mark a cost as « paid from the cash box ».',
        'manage_catalog' => 'Manage the catalogue',
        'manage_catalog_help' => 'Create and edit the shops, the products, their prices and the kits.',
        'view_profit' => 'See the profit',
        'view_profit_help' => 'See the profit by order, by week and on the dashboard, and download the profit export.',
    ],

];
