<?php

/*
|--------------------------------------------------------------------------
| Business Settings
|--------------------------------------------------------------------------
|
| Every value the owner can change without a new release. Each entry gives its
| type (integer, boolean or string), its default and, for a fixed list of
| choices, its options. A stored value always wins over the default.
|
*/

return [

    'settings' => [
        'kits_per_client' => ['type' => 'integer', 'default' => 3, 'min' => 1],
        'advance_amount' => ['type' => 'integer', 'default' => 200000, 'min' => 0],
        'advance_frequency' => [
            'type' => 'string',
            'default' => 'weekly',
            'options' => ['weekly', 'biweekly', 'monthly'],
        ],
        'delivery_frequency' => [
            'type' => 'string',
            'default' => 'weekly',
            'options' => ['weekly', 'biweekly', 'monthly'],
        ],
        'advance_required' => ['type' => 'boolean', 'default' => true],
        'delivery_signature_required' => ['type' => 'boolean', 'default' => true],
        'client_cap' => ['type' => 'integer', 'default' => 0, 'min' => 0],
        'order_lead_days' => ['type' => 'integer', 'default' => 7, 'min' => 0],
        'invoice_period' => [
            'type' => 'string',
            'default' => 'monthly',
            'options' => ['weekly', 'monthly', 'quarterly'],
        ],
        'week_starts_on' => [
            'type' => 'string',
            'default' => 'monday',
            'options' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ],
        'document_prefix_receipt' => ['type' => 'string', 'default' => 'REC'],
        'document_prefix_purchase_order' => ['type' => 'string', 'default' => 'BC'],
        'document_prefix_proforma' => ['type' => 'string', 'default' => 'PRO'],
        'document_prefix_delivery_note' => ['type' => 'string', 'default' => 'BL'],
        'document_prefix_invoice' => ['type' => 'string', 'default' => 'FAC'],
        'document_prefix_statement' => ['type' => 'string', 'default' => 'BOR'],
        'proforma_validity_days' => ['type' => 'integer', 'default' => 15, 'min' => 1],
        'expense_allocation' => ['type' => 'string', 'default' => 'kits', 'options' => ['kits', 'equal']],
        'attachment_alert_days' => ['type' => 'integer', 'default' => 30, 'min' => 1],
        'blocked_order_days' => ['type' => 'integer', 'default' => 7, 'min' => 1],
        'fne_mode' => ['type' => 'string', 'default' => 'not_required', 'options' => ['not_required', 'required']],
        'company_name' => ['type' => 'string', 'default' => null],
        'company_address' => ['type' => 'string', 'default' => null],
        'company_phone' => ['type' => 'string', 'default' => null],
        'document_project_title' => ['type' => 'string', 'default' => null],
        'document_subtitle' => ['type' => 'string', 'default' => null],
    ],

];
