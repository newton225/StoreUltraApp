<?php

return [

    'head' => 'Purchases',
    'title' => 'Purchases',
    'description' => 'What is bought from the shops for each week of deliveries',
    'generated' => ':created purchase order(s) created. Lines without a shop: :unassigned.',
    'sent' => 'Purchase order sent.',
    'received' => 'Reception recorded.',
    'cancelled' => 'Purchase order cancelled.',
    'payment_recorded' => 'Payment to the shop recorded.',
    'empty' => 'No purchase order for this week',

    'errors' => [
        'not_draft' => 'Only a draft can be sent.',
        'not_receivable' => 'Goods can only be received on a purchase order that was sent.',
        'not_cancellable' => 'Only a draft can be cancelled.',
        'above_ordered' => 'More than what was ordered.',
        'below_received' => 'Less than what was already received.',
        'refund_too_high' => 'A refund cannot be more than what was paid to this shop.',
    ],

    'status_help' => [
        'draft' => 'The purchase order is prepared but not sent to the shop yet.',
        'sent' => 'The purchase order is sent to the shop: the goods are awaited.',
        'partially_received' => 'Part of the goods is received, the rest is awaited.',
        'received' => 'All the goods are received.',
        'cancelled' => 'The purchase order is cancelled.',
    ],

    'next_step' => [
        'draft' => 'Send to the shop',
        'sent' => 'Record the reception',
        'partially_received' => 'Record the rest of the reception',
        'received' => 'Done',
        'cancelled' => 'None',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partially_received' => 'Partly received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'orders_title' => 'Purchase orders of the week',

    'generate' => [
        'title' => 'Prepare the purchases of the week',
        'description' => 'Orders whose advance is paid are grouped by shop: one purchase order per shop, to send in order to buy the products.',
        'week' => 'Week of',
        'submit' => 'Prepare the purchase orders',
        'confirm_title' => 'Prepare this week\'s purchase orders?',
        'confirm_body' => 'A purchase order is created for each shop from the orders whose advance is paid. Check the chosen week before continuing.',
        'pending' => ':count line(s) waiting to be bought',
        'nothing_pending' => 'Everything is already in purchase orders: see the list below.',
        'unassigned' => ':count line(s) cannot be bought: the product has no shop',
    ],

    'filters' => [
        'week' => 'Week',
        'shop' => 'Shop',
        'all_shops' => 'All shops',
        'status' => 'Status',
        'all_statuses' => 'All statuses',
        'apply' => 'Show',
    ],

    'columns' => [
        'reference' => 'Reference',
        'shop' => 'Shop',
        'status' => 'Status',
        'expected' => 'Expected',
        'actual' => 'Received value',
        'next' => 'Next step',
    ],

    'summary' => [
        'title' => 'Money received for this week',
        'description' => 'The money already received from clients is split between the shops, according to what you buy from them.',
        'advances' => 'Advances received',
        'purchase' => 'Purchase',
        'allocated' => 'Advance allocated',
        'to_finance' => 'Still to pay to the shops',
        'to_finance_help' => 'Advances received from clients are used first to pay the shops. "Advance surplus" means the advances already cover more than the purchase: nothing to add.',
        'surplus' => 'Advance surplus: :amount',
        'covered' => 'Covered',
    ],

    'accounts' => [
        'title' => 'Shop accounts',
        'description' => 'What each shop was asked for, what it delivered, what was paid and what is still owed.',
        'ordered' => 'Ordered',
        'received' => 'Received',
        'paid' => 'Paid',
        'debt' => 'Still owed',
    ],

    'show' => [
        'week_of' => 'Week of :date',
        'orders_count' => ':count order(s) covered',
        'lines' => 'Lines',
        'product' => 'Product',
        'ordered' => 'Ordered',
        'received' => 'Received',
        'shortfall' => 'Missing',
        'expected_price' => 'Expected price',
        'actual_price' => 'Price paid',
        'variance' => 'Gap',
        'totals' => 'Totals',
        'expected_total' => 'Expected',
        'actual_total' => 'Received value',
        'send' => 'Mark as sent',
        'send_confirm_title' => 'Mark the purchase order as sent to :shop?',
        'send_confirm_body' => 'The order to the shop is considered transmitted: you now wait for the goods. Check that the order has really been handed to the shop.',
        'cancel_confirm_title' => 'Cancel this purchase order?',
        'cancel_confirm_body' => 'The purchase order will be cancelled. This cannot be undone.',
        'receive_confirm_title' => 'Record this reception?',
        'receive_confirm_body' => 'The quantities and real prices entered are recorded: they are used to compute real purchases and margins. Check them before continuing.',
        'cancel' => 'Cancel the draft',
        'receive_title' => 'Record the reception',
        'receive_help' => 'Enter the total received so far for each line, and the price really paid if it differs.',
        'receive_submit' => 'Record the reception',
        'payments' => 'Payments to the shop',
        'payments_empty' => 'No payment yet',
    ],

    'payment' => [
        'confirm_title' => 'Record this payment to the shop?',
        'confirm_body' => 'The payment is entered in the shop account. It can no longer be edited; a mistake is corrected by a refund.',
        'title' => 'Pay the shop',
        'kind' => 'Kind',
        'kinds' => ['payment' => 'Payment', 'refund' => 'Refund from the shop'],
        'amount' => 'Amount (FCFA)',
        'method' => 'Method',
        'choose_method' => 'Choose a method',
        'reference' => 'Reference',
        'date' => 'Date',
        'submit' => 'Record',
    ],

];
