<?php

return [

    'head' => 'Settings',
    'title' => 'Business settings',
    'description' => 'Everything that can change without a new release. Changes apply to future orders only.',
    'updated' => 'Settings saved.',
    'save' => 'Save settings',

    'sections' => [
        'supply' => 'Supply',
        'rhythm' => 'Rhythms and invoicing',
        'alerts' => 'Alerts and cost sharing',
        'numbering' => 'Document numbering',
        'advanced' => 'Advanced settings',
        'advanced_help' => 'These values rarely change: they are already set for everyday use.',
    ],

    'fields' => [
        'kits_per_client_help' => 'Number of kits each client receives each cycle. It is the value suggested for a new order and can still be changed order by order.',
        'advance_amount_help' => 'Amount each client pays before delivery, each cycle. It is suggested as the advance due on new orders.',
        'advance_frequency_help' => 'How often the clients pay their advance (every week by default).',
        'delivery_frequency_help' => 'How often deliveries take place (every week by default).',
        'advance_required_help' => 'If on, an order only moves to “Advance received” once the advance due is paid in full. Otherwise it can move on without it.',
        'delivery_signature_required_help' => 'If on, a delivery cannot be recorded without the signature of the person who receives the kits.',
        'week_starts_on_help' => 'The day a week starts on: it decides which delivery week a date belongs to.',
        'document_prefix_receipt_help' => 'Letters at the start of the number of every payment receipt, for example REC-2026-0001.',
        'document_prefix_purchase_order_help' => 'Letters at the start of the number of every purchase order sent to a shop, for example BC-2026-0001.',
        'document_prefix_proforma_help' => 'Letters at the start of the number of every proforma, the provisional invoice sent to the client before the advance is paid.',
        'document_prefix_delivery_note_help' => 'Letters at the start of the number of every delivery note handed to the client with the kits.',
        'document_prefix_invoice_help' => 'Letters at the start of the number of every invoice.',
        'document_prefix_statement_help' => 'Letters at the start of the number of every statement, the document that lists the invoices of a period.',
        'proforma_validity_days_help' => 'Number of days a proforma stays valid after it is issued.',
        'fne_mode_help' => 'FNE: the electronic standardised invoice of the tax office (DGI). If required, every invoice without an FNE number is marked “FNE to issue” until the number is entered.',
        'expense_allocation_help' => 'How a logistics cost is shared between the orders it concerns: in proportion to the kits of each order, or equally.',
        'attachment_alert_days_help' => 'Number of days before the expiry date of an administrative document from which an alert is shown.',
        'blocked_order_days_help' => 'If an order is still not delivered this many days after its planned week, it shows up in the alerts so you can follow it up. Example: with 7, an order planned for the week of the 5th and not delivered by the 12th is flagged.',
        'kits_per_client' => 'Kits per client and per cycle',
        'advance_amount' => 'Advance per cycle (FCFA)',
        'advance_frequency' => 'Advance frequency',
        'delivery_frequency' => 'Delivery frequency',
        'advance_required' => 'Advance required before delivery',
        'delivery_signature_required' => 'Signature required at delivery',
        'client_cap' => 'Client cap (0 = no limit)',
        'order_lead_days' => 'Days ahead for creating orders',
        'order_lead_days_help' => 'Orders are created this many days before their week',
        'client_cap_help' => 'By default the number of clients is not limited (0). If you set a cap, a warning is shown beyond it, nothing is blocked',
        'invoice_period' => 'Statement period',
        'invoice_period_help' => 'Period covered by each statement listing the invoices',
        'week_starts_on' => 'First day of the week',
        'document_prefix_receipt' => 'Receipt number prefix',
        'document_prefix_purchase_order' => 'Purchase order number prefix',
        'document_prefix_proforma' => 'Proforma number prefix',
        'document_prefix_delivery_note' => 'Delivery note number prefix',
        'document_prefix_invoice' => 'Invoice number prefix',
        'document_prefix_statement' => 'Statement number prefix',
        'proforma_validity_days' => 'Proforma validity (days)',
        'fne_mode' => 'Normalized electronic invoice (FNE)',
        'expense_allocation' => 'Sharing of logistics costs',
        'attachment_alert_days' => 'Days before an expiry to raise an alert',
        'blocked_order_days' => 'Days late before an alert',
    ],

    'allocations' => [
        'kits' => 'In proportion to the kits',
        'equal' => 'Equally between the orders',
    ],

    'fne_modes' => [
        'not_required' => 'Not required',
        'required' => 'Required: the number is recorded on each invoice',
    ],

    'frequencies' => [
        'weekly' => 'Weekly',
        'biweekly' => 'Every two weeks',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
    ],

    'days' => [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ],

];
