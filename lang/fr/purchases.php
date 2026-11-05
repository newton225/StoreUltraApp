<?php

return [

    'head' => 'Achats',
    'title' => 'Achats',
    'description' => 'Ce qui est acheté aux boutiques pour chaque semaine de livraison',
    'generated' => ':created bon(s) de commande créé(s). Lignes sans boutique : :unassigned.',
    'sent' => 'Bon de commande envoyé.',
    'received' => 'Réception enregistrée.',
    'cancelled' => 'Bon de commande annulé.',
    'payment_recorded' => 'Paiement à la boutique enregistré.',
    'empty' => 'Aucun bon de commande pour cette semaine',

    'errors' => [
        'not_draft' => 'Seul un brouillon peut être envoyé.',
        'not_receivable' => 'On ne peut recevoir que sur un bon de commande envoyé.',
        'not_cancellable' => 'Seul un brouillon peut être annulé.',
        'above_ordered' => 'Plus que la quantité commandée.',
        'below_received' => 'Moins que ce qui a déjà été reçu.',
        'refund_too_high' => 'Un remboursement ne peut pas dépasser ce qui a été payé à cette boutique.',
    ],

    'status_help' => [
        'draft' => 'Le bon de commande est préparé mais pas encore envoyé à la boutique.',
        'sent' => 'Le bon de commande est envoyé à la boutique : on attend les marchandises.',
        'partially_received' => 'Une partie des marchandises est reçue, le reste est attendu.',
        'received' => 'Toutes les marchandises sont reçues.',
        'cancelled' => 'Le bon de commande est annulé.',
    ],

    'statuses' => [
        'draft' => 'Brouillon',
        'sent' => 'Envoyé',
        'partially_received' => 'Reçu en partie',
        'received' => 'Reçu',
        'cancelled' => 'Annulé',
    ],

    'orders_title' => 'Bons de commande de la semaine',

    'generate' => [
        'title' => 'Préparer les achats de la semaine',
        'description' => 'Les commandes dont l\'avance est payée sont regroupées par boutique : un bon de commande par boutique, à envoyer pour acheter les produits.',
        'week' => 'Semaine du',
        'submit' => 'Préparer les bons de commande',
        'confirm_title' => 'Préparer les bons de commande de cette semaine ?',
        'confirm_body' => 'Un bon de commande est créé pour chaque boutique à partir des commandes dont l\'avance est payée. Vérifiez la semaine choisie avant de continuer.',
        'pending' => ':count ligne(s) à acheter',
        'unassigned' => ':count ligne(s) ne peuvent pas être achetées : le produit n\'a pas de boutique',
    ],

    'filters' => [
        'week' => 'Semaine',
        'shop' => 'Boutique',
        'all_shops' => 'Toutes les boutiques',
        'status' => 'Statut',
        'all_statuses' => 'Tous les statuts',
        'apply' => 'Afficher',
    ],

    'columns' => [
        'reference' => 'Référence',
        'shop' => 'Boutique',
        'status' => 'Statut',
        'expected' => 'Prévu',
        'actual' => 'Valeur reçue',
    ],

    'summary' => [
        'title' => 'Argent reçu pour cette semaine',
        'description' => 'L\'argent déjà reçu des clients est réparti entre les boutiques, selon ce que vous leur achetez.',
        'advances' => 'Avances reçues',
        'purchase' => 'Achat',
        'allocated' => 'Avance affectée',
        'to_finance' => 'À financer',
        'to_finance_help' => 'Un montant négatif signifie que les avances couvrent plus que l\'achat.',
    ],

    'accounts' => [
        'title' => 'Comptes des boutiques',
        'description' => 'Ce qui a été demandé à chaque boutique, ce qu\'elle a livré, ce qui a été payé et ce qui reste dû.',
        'ordered' => 'Commandé',
        'received' => 'Reçu',
        'paid' => 'Payé',
        'debt' => 'Reste dû',
    ],

    'show' => [
        'week_of' => 'Semaine du :date',
        'orders_count' => ':count commande(s) couverte(s)',
        'lines' => 'Lignes',
        'product' => 'Produit',
        'ordered' => 'Commandé',
        'received' => 'Reçu',
        'shortfall' => 'Manquant',
        'expected_price' => 'Prix prévu',
        'actual_price' => 'Prix payé',
        'variance' => 'Écart',
        'totals' => 'Totaux',
        'expected_total' => 'Prévu',
        'actual_total' => 'Valeur reçue',
        'send' => 'Marquer comme envoyé',
        'send_confirm_title' => 'Marquer le bon de commande comme envoyé à :shop ?',
        'send_confirm_body' => 'La commande à la boutique est considérée comme transmise : on attend maintenant les marchandises. Vérifiez que le bon a bien été remis à la boutique.',
        'cancel_confirm_title' => 'Annuler ce bon de commande ?',
        'cancel_confirm_body' => 'Le bon de commande sera annulé. Cette annulation ne se défait pas.',
        'receive_confirm_title' => 'Enregistrer cette réception ?',
        'receive_confirm_body' => 'Les quantités et les prix réels saisis sont enregistrés : ils servent à calculer les achats réels et les marges. Vérifiez-les avant de continuer.',
        'cancel' => 'Annuler le brouillon',
        'receive_title' => 'Enregistrer la réception',
        'receive_help' => 'Saisissez le total reçu à ce jour pour chaque ligne, et le prix réellement payé s\'il diffère.',
        'receive_submit' => 'Enregistrer la réception',
        'payments' => 'Paiements à la boutique',
        'payments_empty' => 'Aucun paiement pour le moment',
    ],

    'payment' => [
        'title' => 'Payer la boutique',
        'confirm_title' => 'Enregistrer ce paiement à la boutique ?',
        'confirm_body' => 'Le paiement est inscrit dans le compte de la boutique. Il ne peut plus être modifié ; une erreur se corrige par un remboursement.',
        'kind' => 'Type',
        'kinds' => ['payment' => 'Paiement', 'refund' => 'Remboursement par la boutique'],
        'amount' => 'Montant (FCFA)',
        'method' => 'Mode de paiement',
        'choose_method' => 'Choisir un mode',
        'reference' => 'Référence',
        'date' => 'Date',
        'submit' => 'Enregistrer',
    ],

];
