<?php

return [

    'head' => 'Paramètres',
    'title' => 'Paramètres de l\'activité',
    'description' => 'Tout ce qui peut changer sans nouvelle version. Les changements ne s\'appliquent qu\'aux commandes à venir.',
    'updated' => 'Paramètres enregistrés.',
    'save' => 'Enregistrer les paramètres',

    'sections' => [
        'supply' => 'Fourniture',
        'rhythm' => 'Rythmes et facturation',
        'alerts' => 'Alertes et répartition',
        'numbering' => 'Numérotation des documents',
        'advanced' => 'Réglages avancés',
        'advanced_help' => 'Ces valeurs changent rarement : elles sont déjà réglées pour un usage courant.',
    ],

    'fields' => [
        'kits_per_client_help' => 'Nombre de kits que reçoit chaque client à chaque cycle. C’est la valeur proposée pour une nouvelle commande ; elle reste modifiable commande par commande.',
        'advance_amount_help' => 'Somme que chaque client verse avant la livraison, à chaque cycle. Elle est proposée comme avance due sur les nouvelles commandes.',
        'advance_frequency_help' => 'Rythme auquel les clients versent leur avance (chaque semaine par défaut).',
        'delivery_frequency_help' => 'Rythme auquel les livraisons ont lieu (chaque semaine par défaut).',
        'advance_required_help' => 'Si activé, une commande ne passe à « Avance reçue » que lorsque l’avance due est payée en entier. Sinon, elle peut avancer sans.',
        'delivery_signature_required_help' => 'Si activé, une livraison ne s’enregistre pas sans la signature de la personne qui reçoit les kits.',
        'week_starts_on_help' => 'Jour par lequel commence une semaine : il décide à quelle semaine de livraison appartient une date.',
        'document_prefix_receipt_help' => 'Lettres au début du numéro de chaque reçu de paiement, par exemple REC-2026-0001.',
        'document_prefix_purchase_order_help' => 'Lettres au début du numéro de chaque bon de commande envoyé à une boutique, par exemple BC-2026-0001.',
        'document_prefix_proforma_help' => 'Lettres au début du numéro de chaque proforma, la facture provisoire envoyée au client avant le paiement de l’avance.',
        'document_prefix_delivery_note_help' => 'Lettres au début du numéro de chaque bon de livraison remis au client avec ses kits.',
        'document_prefix_invoice_help' => 'Lettres au début du numéro de chaque facture.',
        'document_prefix_statement_help' => 'Lettres au début du numéro de chaque bordereau, le document qui liste les factures d’une période.',
        'proforma_validity_days_help' => 'Nombre de jours pendant lesquels une proforma reste valable après son émission.',
        'fne_mode_help' => 'FNE : facture normalisée électronique de la DGI. Si elle est obligatoire, chaque facture sans numéro FNE est marquée « FNE à émettre » jusqu’à ce que le numéro soit saisi.',
        'expense_allocation_help' => 'Comment un frais de logistique est réparti entre les commandes qu’il concerne : au prorata des kits de chaque commande, ou à parts égales.',
        'attachment_alert_days_help' => 'Nombre de jours avant la date d’expiration d’un document administratif à partir duquel une alerte est affichée.',
        'blocked_order_days_help' => 'Si une commande n\'est toujours pas livrée ce nombre de jours après la semaine prévue, elle apparaît dans les alertes pour que vous la relanciez. Exemple : avec 7, une commande prévue la semaine du 5 et non livrée le 12 est signalée.',
        'kits_per_client' => 'Kits par client et par cycle',
        'advance_amount' => 'Avance par cycle (FCFA)',
        'advance_frequency' => 'Rythme de l\'avance',
        'delivery_frequency' => 'Rythme des livraisons',
        'advance_required' => 'Avance obligatoire avant la livraison',
        'delivery_signature_required' => 'Signature obligatoire à la livraison',
        'client_cap' => 'Plafond de clients (0 = aucune limite)',
        'order_lead_days' => 'Jours d\'avance pour créer les commandes',
        'order_lead_days_help' => 'Les commandes sont créées ce nombre de jours avant leur semaine',
        'client_cap_help' => 'Par défaut, le nombre de clients n\'est pas limité (0). Si vous fixez un plafond, un avertissement s\'affiche au-delà, rien n\'est bloqué',
        'invoice_period' => 'Période du bordereau',
        'invoice_period_help' => 'Période couverte par chaque bordereau listant les factures',
        'week_starts_on' => 'Premier jour de la semaine',
        'document_prefix_receipt' => 'Préfixe des numéros de reçu',
        'document_prefix_purchase_order' => 'Préfixe des numéros de bon de commande',
        'document_prefix_proforma' => 'Préfixe des numéros de proforma',
        'document_prefix_delivery_note' => 'Préfixe des numéros de bon de livraison',
        'document_prefix_invoice' => 'Préfixe des numéros de facture',
        'document_prefix_statement' => 'Préfixe des numéros de bordereau',
        'proforma_validity_days' => 'Validité de la proforma (jours)',
        'fne_mode' => 'Facture normalisée électronique (FNE)',
        'expense_allocation' => 'Répartition des frais de logistique',
        'attachment_alert_days' => 'Jours avant expiration pour alerter',
        'blocked_order_days' => 'Jours de retard avant alerte',
    ],

    'allocations' => [
        'kits' => 'Au prorata des kits',
        'equal' => 'À parts égales entre les commandes',
    ],

    'fne_modes' => [
        'not_required' => 'Non requise',
        'required' => 'Requise : le numéro est enregistré sur chaque facture',
    ],

    'frequencies' => [
        'weekly' => 'Hebdomadaire',
        'biweekly' => 'Toutes les deux semaines',
        'monthly' => 'Mensuel',
        'quarterly' => 'Trimestriel',
    ],

    'days' => [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ],

];
