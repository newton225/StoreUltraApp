<?php

return [

    'nav' => [
        'features' => 'Fonctionnalités',
        'how' => 'Comment ça marche',
        'roles' => 'Équipe',
    ],

    'hero' => [
        'badge' => 'Gestion d\'approvisionnement et de livraison',
        'title' => 'Du devis à la livraison, chaque kit est suivi.',
        'subtitle' => 'StoreUltra aide un intermédiaire à acheter dans les boutiques, assembler des kits, encaisser les avances, livrer ses clients et connaître son bénéfice réel, semaine après semaine.',
        'cta_login' => 'Se connecter',
        'cta_dashboard' => 'Ouvrir le tableau de bord',
        'cta_discover' => 'Découvrir',
    ],

    'preview' => [
        'title' => 'Le parcours d\'une commande',
        'week' => 'Semaine de livraison',
        'steps' => [
            'proforma' => 'Proforma émise',
            'advance' => 'Avance reçue',
            'supply' => 'Achats en boutiques',
            'delivery' => 'Livraison au client',
            'settled' => 'Solde encaissé',
        ],
    ],

    'steps' => [
        'title' => 'Comment ça marche',
        'subtitle' => 'Un cycle simple, répété chaque semaine pour chaque client.',
        'items' => [
            'order' => [
                'title' => 'Commander',
                'text' => 'Le client commande ses kits pour une semaine de livraison. Une proforma est émise avec le montant de l\'avance.',
            ],
            'advance' => [
                'title' => 'Encaisser l\'avance',
                'text' => 'L\'avance est enregistrée avec son mode de paiement et sa référence. La commande peut alors être préparée.',
            ],
            'supply' => [
                'title' => 'Acheter et assembler',
                'text' => 'Les produits sont achetés dans les boutiques puis assemblés en kits. Les dettes envers chaque boutique sont suivies.',
            ],
            'deliver' => [
                'title' => 'Livrer et solder',
                'text' => 'Le bordereau de livraison est signé, le solde est encaissé, la facture est émise.',
            ],
        ],
    ],

    'features' => [
        'title' => 'Tout ce qu\'il faut pour piloter l\'activité',
        'subtitle' => 'Chaque règle de gestion est appliquée par l\'application, pas par la mémoire de quelqu\'un.',
        'items' => [
            'kits' => [
                'title' => 'Kits versionnés',
                'text' => 'Composition, prix d\'achat et prix de vente d\'un kit sont historisés : une ancienne commande garde ses prix.',
            ],
            'clients' => [
                'title' => 'Clients et zones',
                'text' => 'Fiche client avec numéro de registre de commerce, sous-préfecture, plafond de clients et approvisionnement récurrent.',
            ],
            'payments' => [
                'title' => 'Avances et soldes',
                'text' => 'Chaque paiement est tracé, les remboursements sont des écritures à part, et les retards d\'avance sont signalés.',
            ],
            'shops' => [
                'title' => 'Boutiques fournisseurs',
                'text' => 'Bons d\'achat, réceptions, paiements et dettes par boutique, avec la synthèse prête à vérifier.',
            ],
            'documents' => [
                'title' => 'Documents en PDF',
                'text' => 'Proformas, factures, bordereaux de livraison et bordereaux mensuels numérotés, avec signature du client.',
            ],
            'profit' => [
                'title' => 'Bénéfice réel',
                'text' => 'Les frais de logistique sont répartis sur les commandes pour afficher la marge réellement gagnée.',
            ],
            'dashboard' => [
                'title' => 'Tableau de bord et exports',
                'text' => 'Indicateurs de la période, alertes, synthèse PDF et exports CSV ouverts directement dans Excel.',
            ],
            'attachments' => [
                'title' => 'Documents administratifs',
                'text' => 'Registre de commerce, attestations et autres justificatifs, avec alerte avant leur expiration.',
            ],
            'audit' => [
                'title' => 'Journal d\'audit',
                'text' => 'Les enregistrements ne sont pas modifiés : toute correction laisse une trace de qui a fait quoi et quand.',
            ],
        ],
    ],

    'roles' => [
        'title' => 'Chacun voit ce qui le concerne',
        'subtitle' => 'Trois profils prêts à l\'emploi, et des profils sur mesure réglés menu par menu et action par action.',
        'items' => [
            'owner' => [
                'title' => 'Propriétaire',
                'text' => 'Accès complet : paramètres, utilisateurs et profils, bénéfices, documents administratifs.',
            ],
            'employee' => [
                'title' => 'Employé',
                'text' => 'Gère les commandes, les paiements, les achats et les livraisons au quotidien.',
            ],
            'agent' => [
                'title' => 'Agent de terrain',
                'text' => 'Ne voit que les clients et les commandes de ses sous-préfectures.',
            ],
        ],
    ],

    'cta' => [
        'title' => 'Prêt à suivre vos kits de bout en bout ?',
        'text' => 'L\'accès se fait sur invitation du propriétaire : vous recevez un e-mail et choisissez vous-même votre mot de passe.',
    ],

    'footer' => [
        'rights' => 'Tous droits réservés.',
    ],
];
