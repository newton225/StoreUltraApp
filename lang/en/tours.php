<?php

return [
    'replay' => 'Guided tour of this screen',
    'progress' => 'Step :current of :total',
    'next' => 'Next',
    'previous' => 'Previous',
    'skip' => 'Skip the tour',
    'finish' => 'Finish',

    'dashboard' => [
        'menu' => [
            'title' => 'The menu',
            'body' => 'Screens are grouped by job: sales, purchases, deliveries, costs, administration. You only see what your profile allows.',
        ],
        'filters' => [
            'title' => 'The period',
            'body' => 'Pick the dates to look at. Every figure and chart on the page follows this choice, and the "Apply" button recalculates them.',
        ],
        'alerts' => [
            'title' => 'What needs action',
            'body' => 'Each line is something to deal with, with the link that leads there. The same list is in the bell, top right, on every screen.',
        ],
        'indicators' => [
            'title' => 'The key figures',
            'body' => 'One figure per tile, over the chosen period. The help mark (?) next to each says what it counts and what it leaves out.',
        ],
        'charts' => [
            'title' => 'The charts',
            'body' => 'Week after week, to see a trend rather than a single figure. Each chart can also be shown as a table.',
        ],
    ],

    'orders' => [
        'new' => [
            'title' => 'Create orders',
            'body' => 'An order for one client, or the same order for every active TPE of a locality in one go.',
        ],
        'batches' => [
            'title' => 'One step for a whole locality',
            'body' => 'These blocks only appear when orders of a locality are waiting for the same step: issue their proformas, or record their advances, all at once.',
        ],
        'filters' => [
            'title' => 'Find an order',
            'body' => 'Search by reference or by client, then filter by delivery week and by status.',
        ],
        'table' => [
            'title' => 'The list',
            'body' => 'One row per order, with its status. Open it to see its steps, payments, deliveries and documents.',
        ],
    ],

    'order_create' => [
        'modes' => [
            'title' => 'One client or a whole locality',
            'body' => '"One client" creates one order. "A whole locality" creates the same order for each active TPE of the locality, which you can untick one by one.',
        ],
        'form_single' => [
            'title' => 'The order',
            'body' => 'Choose the client, the kit, the number of kits, the delivery week and the advance asked. Today\'s prices are frozen at creation: a later price change does not touch this order.',
        ],
        'form_batch' => [
            'title' => 'The locality\'s order',
            'body' => 'Choose the locality, the kit, the number of kits per TPE, the week and the advance. The list of TPE appears: a TPE that already has an order that week is greyed out and will not be doubled.',
        ],
    ],

    'order_show' => [
        'progress' => [
            'title' => 'Where the order stands',
            'body' => 'The timeline follows the journey: proforma issued, advance received, purchases in shops, delivery to the client, balance collected. The current step is marked.',
        ],
        'next_step' => [
            'title' => 'The next step',
            'body' => 'The button moves the order forward one step, after confirmation. Every change is written to the journal with its author.',
        ],
        'tabs' => [
            'title' => 'One tab per subject',
            'body' => 'The order lines, payments, deliveries and documents (and the profit if your profile allows it) each have their own tab.',
        ],
    ],

    'cash' => [
        'tiles' => [
            'title' => 'The cash box at a glance',
            'body' => 'Opening balance + money in − money out = cash balance: the money that should be in the box. The help mark (?) on each tile explains its calculation.',
        ],
        'movement' => [
            'title' => 'Record a movement',
            'body' => 'Money in or out, with its reason. A movement cannot be edited: a mistake is corrected by cancelling it, which stays visible in the history.',
        ],
        'closing' => [
            'title' => 'Close the cash box',
            'body' => 'Count the real money and enter it: the application compares it with the expected balance and records the difference. Once a date is closed, no movement can be added or cancelled on that date or before.',
        ],
    ],

    'profits' => [
        'filters' => [
            'title' => 'The period',
            'body' => 'Choose the delivery weeks to look at, which orders to count, and how to group the result: by week, by sub-prefecture, by client or by kit.',
        ],
        'table' => [
            'title' => 'Expected and real',
            'body' => 'For each group: the theoretical margin (sale price minus planned purchase price), the real margin (with real purchases and logistics) and the gap between the two. The help mark (?) on the columns explains each calculation.',
        ],
    ],
];
