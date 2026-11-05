<?php

return [
    'replay' => 'Visite guidée de cet écran',
    'progress' => 'Étape :current sur :total',
    'next' => 'Suivant',
    'previous' => 'Précédent',
    'skip' => 'Passer la visite',
    'finish' => 'Terminer',

    'dashboard' => [
        'menu' => [
            'title' => 'Le menu',
            'body' => 'Les écrans sont rangés par métier : ventes, achats, livraisons, frais, administration. Vous ne voyez que ce que votre profil autorise.',
        ],
        'filters' => [
            'title' => 'La période',
            'body' => 'Choisissez les dates à observer. Tous les chiffres et tous les graphiques de la page suivent ce choix, et le bouton « Appliquer » les recalcule.',
        ],
        'alerts' => [
            'title' => 'Ce qui demande une action',
            'body' => 'Chaque ligne est un point à traiter, avec le lien qui y mène. La même liste est dans la cloche, en haut à droite, sur tous les écrans.',
        ],
        'indicators' => [
            'title' => 'Les chiffres clés',
            'body' => 'Un chiffre par tuile, sur la période choisie. Le point d\'aide (?) à côté de chacun dit ce qu\'il compte et ce qu\'il laisse de côté.',
        ],
        'charts' => [
            'title' => 'Les graphiques',
            'body' => 'Semaine après semaine, pour voir une tendance plutôt qu\'un chiffre isolé. Chaque graphique peut aussi s\'afficher en tableau.',
        ],
    ],

    'orders' => [
        'new' => [
            'title' => 'Créer des commandes',
            'body' => 'Une commande pour un client, ou la même commande pour tous les TPE actifs d\'une sous-préfecture en une seule fois.',
        ],
        'batches' => [
            'title' => 'Une étape pour toute une sous-préfecture',
            'body' => 'Ces blocs n\'apparaissent que si des commandes d\'une sous-préfecture attendent la même étape : émettre leurs proformas, ou encaisser leurs avances, d\'un seul coup.',
        ],
        'filters' => [
            'title' => 'Retrouver une commande',
            'body' => 'Cherchez par référence ou par client, puis filtrez par semaine de livraison et par statut.',
        ],
        'table' => [
            'title' => 'La liste',
            'body' => 'Une ligne par commande, avec son statut. Ouvrez-la pour voir ses étapes, ses paiements, ses livraisons et ses documents.',
        ],
    ],

    'order_create' => [
        'modes' => [
            'title' => 'Un client ou toute une sous-préfecture',
            'body' => '« Un client » crée une commande. « Toute une sous-préfecture » crée la même commande pour chaque TPE actif de la sous-préfecture, que vous pouvez décocher un par un.',
        ],
        'form_single' => [
            'title' => 'La commande',
            'body' => 'Choisissez le client, le kit, le nombre de kits, la semaine de livraison et l\'avance demandée. Les prix du jour sont figés à la création : un changement de prix ultérieur ne touche pas cette commande.',
        ],
        'form_batch' => [
            'title' => 'La commande de la sous-préfecture',
            'body' => 'Choisissez la sous-préfecture, le kit, le nombre de kits par TPE, la semaine et l\'avance. La liste des TPE s\'affiche : un TPE qui a déjà une commande cette semaine-là est grisé et ne sera pas doublé.',
        ],
    ],

    'order_show' => [
        'progress' => [
            'title' => 'Où en est la commande',
            'body' => 'La frise suit le parcours : proforma émise, avance reçue, achats en boutiques, livraison au client, solde encaissé. L\'étape en cours est repérée.',
        ],
        'next_step' => [
            'title' => 'La prochaine étape',
            'body' => 'Le bouton fait avancer la commande d\'un cran, après confirmation. Chaque passage est enregistré dans le journal avec son auteur.',
        ],
        'tabs' => [
            'title' => 'Un onglet par sujet',
            'body' => 'Les lignes de la commande, les paiements, les livraisons et les documents (et le bénéfice si votre profil le permet) sont chacun dans leur onglet.',
        ],
    ],

    'cash' => [
        'tiles' => [
            'title' => 'La caisse en un coup d\'œil',
            'body' => 'Solde d\'ouverture + entrées − sorties = solde de caisse : l\'argent qui doit se trouver dans la caisse. Le point d\'aide (?) de chaque tuile explique son calcul.',
        ],
        'movement' => [
            'title' => 'Enregistrer un mouvement',
            'body' => 'Une entrée ou une sortie d\'argent, avec son motif. Un mouvement ne se modifie pas : une erreur se corrige en l\'annulant, ce qui reste visible dans l\'historique.',
        ],
        'closing' => [
            'title' => 'Clôturer la caisse',
            'body' => 'Comptez l\'argent réel et saisissez-le : l\'application le compare au solde attendu et inscrit l\'écart. Une fois la date clôturée, plus aucun mouvement ne peut être ajouté ni annulé à cette date ou avant.',
        ],
    ],

    'profits' => [
        'filters' => [
            'title' => 'La période',
            'body' => 'Choisissez les semaines de livraison à regarder, les commandes à compter, et comment regrouper le résultat : par semaine, par sous-préfecture, par client ou par kit.',
        ],
        'table' => [
            'title' => 'Prévu et réel',
            'body' => 'Pour chaque groupe : la marge théorique (prix de vente moins prix d\'achat prévu), la marge réelle (avec les achats réels et la logistique) et l\'écart entre les deux. Le point d\'aide (?) des colonnes explique chaque calcul.',
        ],
    ],
];
