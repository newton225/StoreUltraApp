<?php

return [

    'head' => 'Profils',
    'title' => 'Profils et droits',
    'description' => 'Ce que chaque type d\'utilisateur a le droit de faire. Les trois profils de départ ne changent pas ; pour des droits différents, créez un nouveau profil.',
    'new' => 'Nouveau profil',
    'view' => 'Voir',
    'edit' => 'Modifier',
    'create_title' => 'Nouveau profil',
    'create_description' => 'Un profil personnalisé : vous choisissez ce qu\'il a le droit de faire, puis vous le donnez à des utilisateurs.',
    'edit_title' => 'Modifier le profil',
    'created' => 'Profil créé.',
    'updated' => 'Profil mis à jour.',
    'deleted' => 'Profil supprimé.',

    'kinds' => [
        'system' => 'Système',
        'custom' => 'Personnalisé',
    ],

    'columns' => [
        'name' => 'Profil',
        'kind' => 'Type',
        'rights' => 'Droits accordés',
        'users' => 'Utilisateurs',
    ],

    'users_count' => ':count utilisateur(s)',
    'no_rights' => 'Aucun droit en plus de la consultation',
    'restricted_badge' => 'Sous-préfectures affectées seulement',
    'all_rights' => 'Tous les droits',
    'matrix_title' => 'Droits par profil',
    'matrix_description' => 'Ce que chaque profil peut faire, d’un coup d’œil. Les gestions des utilisateurs, des profils et des paramètres restent toujours réservées au propriétaire.',
    'matrix_right' => 'Droit',
    'granted' => 'Accordé',
    'not_granted' => 'Non accordé',
    'restricted_row' => 'Limité aux sous-préfectures affectées',

    'system_notice' => 'Profil système : il ne peut être ni modifié ni supprimé. Pour une version personnalisée, créez un nouveau profil à partir de celui-ci.',
    'duplicate' => 'Dupliquer',
    'from_this' => 'Créer un profil à partir de celui-ci',
    'template_notice' => 'Les droits de « :name » sont recopiés : changez-les à votre guise.',
    'delete' => 'Supprimer ce profil',
    'delete_confirm' => 'Supprimer le profil « :name » ? Cette action ne peut pas être annulée.',
    'in_use_help' => 'Ce profil est donné à des utilisateurs : changez d\'abord leur profil pour pouvoir le supprimer.',

    'form' => [
        'label_fr' => 'Nom en français',
        'label_en' => 'Nom en anglais',
        'permissions' => 'Droits accordés',
        'permissions_help' => 'Cochez ce que ce profil a le droit de faire. Tout ce qui n\'est pas coché lui est refusé.',
        'restricted' => 'Limité aux sous-préfectures affectées',
        'restricted_help' => 'Les utilisateurs de ce profil ne voient que les clients, les commandes et les livraisons des sous-préfectures qui leur sont affectées, comme un agent de terrain.',
        'submit' => 'Enregistrer le profil',
    ],

    'errors' => [
        'in_use' => 'Ce profil est donné à des utilisateurs : changez d\'abord leur profil.',
    ],

    'permissions' => [
        'manage_orders' => 'Gérer les commandes et les encaissements',
        'manage_orders_help' => 'Créer des commandes et des livraisons automatiques, les faire avancer, encaisser et rembourser, émettre les documents (proforma, facture, bon de livraison), voir les paiements et les avances en retard, et télécharger les exports correspondants.',
        'manage_purchases' => 'Gérer les achats aux boutiques',
        'manage_purchases_help' => 'Générer, envoyer, réceptionner et annuler les bons de commande des boutiques, et enregistrer ce qui leur est payé.',
        'manage_expenses' => 'Gérer les frais de logistique',
        'manage_expenses_help' => 'Enregistrer, consulter et annuler les frais de logistique, et ouvrir leurs justificatifs.',
        'manage_cash' => 'Gérer la caisse',
        'manage_cash_help' => 'Voir la caisse, enregistrer et annuler les mouvements d\'argent liquide, clôturer la caisse, rattacher d\'anciens frais payés en espèces, et marquer un frais « payé depuis la caisse ».',
        'manage_catalog' => 'Gérer le catalogue',
        'manage_catalog_help' => 'Créer et modifier les boutiques, les produits, leurs prix et les kits.',
        'view_profit' => 'Voir les bénéfices',
        'view_profit_help' => 'Voir le bénéfice par commande, par semaine et sur le tableau de bord, et télécharger l\'export des bénéfices.',
    ],

];
