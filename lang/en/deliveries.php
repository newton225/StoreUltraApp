<?php

return [

    'head' => 'Deliveries',
    'title' => 'Deliveries',
    'description' => 'The kits to hand over and the deliveries already made',
    'recorded' => 'Delivery recorded.',

    'errors' => [
        'status' => 'This order cannot be delivered now: its goods must have been received.',
        'kits' => 'The number of kits is more than what is left to deliver.',
        'signature' => 'The signature is not a valid image.',
    ],

    'filters' => [
        'zone' => 'Sub-prefecture',
        'all_zones' => 'All sub-prefectures',
    ],

    'to_deliver' => [
        'title' => 'To deliver',
        'empty' => 'Nothing to deliver',
        'client' => 'Client',
        'phone' => 'Phone',
        'zone' => 'Sub-prefecture',
        'week' => 'Week of',
        'remaining' => 'Kits left',
        'open' => 'Deliver',
    ],

    'done' => [
        'title' => 'Deliveries made',
        'empty' => 'No delivery yet',
        'reference' => 'Delivery',
        'date' => 'Date',
        'client' => 'Client',
        'kits' => 'Kits',
        'agent' => 'Agent',
        'received_by' => 'Received by',
        'signature' => 'Signature',
        'signed' => 'Signed',
    ],

    'panel' => [
        'kits_help' => 'Number of kits handed over this time. A delivery can be partial: the rest is delivered later.',
        'collect_amount_help' => 'The balance is what the client pays at delivery: the sale price minus the advance already paid.',
        'title' => 'Deliveries',
        'remaining' => ':count kit(s) left to deliver',
        'complete' => 'Every kit has been delivered',
        'empty' => 'No delivery yet',
        'record' => 'Record a delivery',
        'kits' => 'Kits handed over',
        'date' => 'Date',
        'received_by' => 'Name of the person who received the kits',
        'agent' => 'Field agent',
        'no_agent' => 'Not specified',
        'notes' => 'Notes',
        'signature' => 'Signature of the person who received',
        'signature_optional' => '(optional)',
        'clear' => 'Clear',
        'collect_title' => 'Collect the balance now (optional)',
        'collect_amount' => 'Amount collected (FCFA)',
        'method' => 'Method',
        'choose_method' => 'Choose a method',
        'reference' => 'Transaction reference',
        'submit' => 'Record the delivery',
        'confirm_title' => 'Record this delivery?',
        'confirm_body' => 'The delivery will be recorded with its delivery note and signature. It can no longer be edited. If money is collected at delivery, the payment is recorded at the same time.',
        'view_signature' => 'View the signature',
        'by' => 'by :name',
        'received_by_label' => 'received by :name',
    ],

];
