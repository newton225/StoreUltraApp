<?php

return [

    'head' => 'Clients',
    'title' => 'Clients',
    'description' => 'The people who receive the kits',
    'new' => 'New client',
    'open' => 'Open',
    'edit' => 'Edit',
    'empty' => 'No client found',
    'created' => 'Client registered.',
    'updated' => 'Client updated.',
    'cap_exceeded' => 'Client registered. The cap is exceeded: :count clients for :cap places.',

    'capacity' => [
        'label' => ':count of :cap places used',
        'label_one' => ':count of :cap place used',
        'exceeded' => 'The client cap is exceeded: :count clients for :cap places. Nothing is blocked.',
    ],

    'filters' => [
        'search' => 'Search by name, phone, reference or commercial number',
        'apply' => 'Search',
        'zone' => 'Sub-prefecture',
        'status' => 'Status',
        'all_zones' => 'All sub-prefectures',
        'all_statuses' => 'All statuses',
    ],

    'columns' => [
        'reference' => 'Reference',
        'name' => 'Name',
        'phone' => 'Phone',
        'zone' => 'Sub-prefecture',
        'status' => 'Status',
    ],

    'status_help' => [
        'pending' => 'Registered by a field agent, waiting for the office to confirm.',
        'active' => 'The client receives their orders as usual.',
        'suspended' => 'No new order is created for this client while they are suspended.',
        'ended' => 'The follow-up of this client is over: they no longer count among the active clients.',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'ended' => 'Ended',
    ],

    'create' => [
        'head' => 'New client',
        'title' => 'New client',
        'description' => 'A client registered by a field agent stays pending until the office confirms it',
        'submit' => 'Register client',
    ],

    'edit_page' => [
        'head' => 'Edit client',
        'title' => 'Edit client',
    ],

    'form' => [
        'commercial_number_help' => 'Registration number of the client company in the trade register (RCCM). It is required.',
        'zone_help' => 'Sub-prefecture where the client is delivered. A field agent only sees the clients of their sub-prefectures.',
        'status_help' => 'Pending: registered by an agent, to be confirmed by the office. Active: receives orders. Suspended: no new order. Ended: no longer counted.',
        'commercial_number' => 'Commercial number (trade register)',
        'name' => 'Name or company name',
        'phone' => 'Phone',
        'zone' => 'Sub-prefecture',
        'choose_zone' => 'Choose a sub-prefecture',
        'add_zone' => 'Add a sub-prefecture',
        'add_zone_description' => 'It is saved right away and chosen for this client. What you have already typed is kept.',
        'zone_added' => 'Sub-prefecture ":name" added.',
        'no_zone_yet' => 'No sub-prefecture yet: use the + button to add one.',
        'locality' => 'Locality',
        'delivery_address' => 'Delivery address',
        'contact_person' => 'Contact person',
        'notes' => 'Notes',
        'status' => 'Status',
        'submit' => 'Save',
    ],

    'status_changed' => 'Client status: :status.',
    'status_actions' => [
        'title' => 'Client status',
        'help' => 'Changing the status does not change anything else on the page.',
        'pending' => 'Set to pending',
        'active' => 'Activate',
        'suspended' => 'Deactivate (suspend)',
        'ended' => 'End',
        'confirm_title' => 'Change the status of :name to ":status"?',
        'body' => [
            'pending' => 'The client waits for the office to confirm. No order is generated until it is active.',
            'active' => 'The client gets its automatically generated orders again.',
            'suspended' => 'No new order is generated for this client any more. Its orders, payments and documents stay intact, and it can be reactivated at any time.',
            'ended' => 'No new order is generated and the client no longer counts under the cap. Its orders, payments and documents stay intact, and it can be reactivated.',
        ],
    ],

    'show' => [
        'details' => 'Details',
        'contact_trade' => 'Manager\'s trade',
        'registered' => 'Registered on :date by :author',
        'orders_title' => 'Orders and payments',
        'orders_soon' => 'Orders, payments and deliveries will appear here.',
    ],

];
