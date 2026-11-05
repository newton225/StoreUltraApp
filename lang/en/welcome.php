<?php

return [

    'nav' => [
        'features' => 'Features',
        'how' => 'How it works',
        'roles' => 'Team',
    ],

    'hero' => [
        'badge' => 'Supply and delivery management',
        'title' => 'From quote to delivery, every kit is tracked.',
        'subtitle' => 'StoreUltra helps an intermediary buy from shops, assemble kits, collect advances, deliver to clients and know the real profit, week after week.',
        'cta_login' => 'Sign in',
        'cta_dashboard' => 'Open the dashboard',
        'cta_discover' => 'Learn more',
    ],

    'preview' => [
        'title' => 'The journey of an order',
        'week' => 'Delivery week',
        'steps' => [
            'proforma' => 'Proforma issued',
            'advance' => 'Advance received',
            'supply' => 'Purchases in shops',
            'delivery' => 'Delivery to the client',
            'settled' => 'Balance collected',
        ],
    ],

    'steps' => [
        'title' => 'How it works',
        'subtitle' => 'A simple cycle, repeated every week for every client.',
        'items' => [
            'order' => [
                'title' => 'Order',
                'text' => 'The client orders kits for a delivery week. A proforma is issued with the amount of the advance.',
            ],
            'advance' => [
                'title' => 'Collect the advance',
                'text' => 'The advance is recorded with its payment method and reference. The order can then be prepared.',
            ],
            'supply' => [
                'title' => 'Buy and assemble',
                'text' => 'Products are bought in the shops, then assembled into kits. What is owed to each shop is tracked.',
            ],
            'deliver' => [
                'title' => 'Deliver and settle',
                'text' => 'The delivery note is signed, the balance is collected, the invoice is issued.',
            ],
        ],
    ],

    'features' => [
        'title' => 'Everything needed to run the business',
        'subtitle' => 'Every business rule is enforced by the application, not by someone\'s memory.',
        'items' => [
            'kits' => [
                'title' => 'Versioned kits',
                'text' => 'A kit\'s composition, purchase price and sale price are kept in history: an old order keeps its prices.',
            ],
            'clients' => [
                'title' => 'Clients and zones',
                'text' => 'Client file with trade register number, sub-prefecture, client cap and recurring supply.',
            ],
            'payments' => [
                'title' => 'Advances and balances',
                'text' => 'Every payment is traced, refunds are separate entries, and late advances are flagged.',
            ],
            'shops' => [
                'title' => 'Supplier shops',
                'text' => 'Purchase orders, receipts, payments and debts per shop, with a synthesis ready to check.',
            ],
            'documents' => [
                'title' => 'PDF documents',
                'text' => 'Numbered proformas, invoices, delivery notes and monthly statements, with the client\'s signature.',
            ],
            'profit' => [
                'title' => 'Real profit',
                'text' => 'Logistics costs are spread over the orders to show the margin actually earned.',
            ],
            'dashboard' => [
                'title' => 'Dashboard and exports',
                'text' => 'Indicators for the period, alerts, a PDF synthesis and CSV exports that open straight in Excel.',
            ],
            'attachments' => [
                'title' => 'Administrative documents',
                'text' => 'Trade register, certificates and other supporting documents, with an alert before they expire.',
            ],
            'audit' => [
                'title' => 'Audit log',
                'text' => 'Records are never edited: every correction leaves a trace of who did what and when.',
            ],
        ],
    ],

    'roles' => [
        'title' => 'Everyone sees what concerns them',
        'subtitle' => 'Three profiles, matching access.',
        'items' => [
            'owner' => [
                'title' => 'Owner',
                'text' => 'Full access: settings, users, profit, administrative documents.',
            ],
            'employee' => [
                'title' => 'Employee',
                'text' => 'Handles orders, payments, purchases and deliveries day to day.',
            ],
            'agent' => [
                'title' => 'Field agent',
                'text' => 'Only sees the clients and orders of their sub-prefectures.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Ready to follow your kits from end to end?',
        'text' => 'Access is by invitation from the owner.',
    ],

    'footer' => [
        'rights' => 'All rights reserved.',
    ],
];
