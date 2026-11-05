<?php

return [

    'head' => 'Clients',
    'title' => 'Clients',
    'description' => 'Les personnes qui reçoivent les kits',
    'new' => 'Nouveau client',
    'open' => 'Ouvrir',
    'edit' => 'Modifier',
    'empty' => 'Aucun client trouvé',
    'created' => 'Client enregistré.',
    'updated' => 'Client mis à jour.',
    'cap_exceeded' => 'Client enregistré. Le plafond est dépassé : :count clients pour :cap places.',

    'capacity' => [
        'label' => ':count places utilisées sur :cap',
        'label_one' => ':count place utilisée sur :cap',
        'exceeded' => 'Le plafond de clients est dépassé : :count clients pour :cap places. Rien n\'est bloqué.',
    ],

    'filters' => [
        'search' => 'Rechercher par nom, téléphone, référence ou numéro commercial',
        'apply' => 'Rechercher',
        'zone' => 'Sous-préfecture',
        'status' => 'Statut',
        'all_zones' => 'Toutes les sous-préfectures',
        'all_statuses' => 'Tous les statuts',
    ],

    'columns' => [
        'reference' => 'Référence',
        'name' => 'Nom',
        'phone' => 'Téléphone',
        'zone' => 'Sous-préfecture',
        'status' => 'Statut',
    ],

    'status_help' => [
        'pending' => 'Enregistré par un agent de terrain, en attente de confirmation par le bureau.',
        'active' => 'Le client reçoit ses commandes normalement.',
        'suspended' => 'Aucune nouvelle commande n’est créée pour ce client tant qu’il est suspendu.',
        'ended' => 'Le suivi de ce client est terminé : il ne compte plus parmi les clients actifs.',
    ],

    'statuses' => [
        'pending' => 'En attente',
        'active' => 'Actif',
        'suspended' => 'Suspendu',
        'ended' => 'Terminé',
    ],

    'create' => [
        'head' => 'Nouveau client',
        'title' => 'Nouveau client',
        'description' => 'Un client enregistré par un agent de terrain reste en attente jusqu\'à confirmation du bureau',
        'submit' => 'Enregistrer le client',
    ],

    'edit_page' => [
        'head' => 'Modifier le client',
        'title' => 'Modifier le client',
    ],

    'form' => [
        'commercial_number_help' => 'Numéro d’immatriculation de l’entreprise cliente au registre du commerce (RCCM). Il est obligatoire.',
        'zone_help' => 'Sous-préfecture où le client est livré. Un agent de terrain ne voit que les clients de ses sous-préfectures.',
        'status_help' => 'En attente : enregistré par un agent, à confirmer par le bureau. Actif : reçoit des commandes. Suspendu : plus de nouvelle commande. Terminé : ne compte plus.',
        'commercial_number' => 'Numéro commercial (registre de commerce)',
        'name' => 'Nom ou raison sociale',
        'phone' => 'Téléphone',
        'zone' => 'Sous-préfecture',
        'choose_zone' => 'Choisir une sous-préfecture',
        'add_zone' => 'Ajouter une sous-préfecture',
        'add_zone_description' => 'Elle est enregistrée tout de suite et choisie pour ce client. Vous gardez ce que vous avez déjà saisi.',
        'zone_added' => 'Sous-préfecture « :name » ajoutée.',
        'no_zone_yet' => 'Aucune sous-préfecture pour le moment : utilisez le bouton + pour en ajouter une.',
        'locality' => 'Localité',
        'delivery_address' => 'Adresse de livraison',
        'contact_person' => 'Personne à contacter',
        'notes' => 'Observations',
        'status' => 'Statut',
        'submit' => 'Enregistrer',
    ],

    'status_changed' => 'Statut du client : :status.',
    'status_actions' => [
        'title' => 'Statut du client',
        'help' => 'Changer le statut ne modifie rien d\'autre sur la fiche.',
        'pending' => 'Mettre en attente',
        'active' => 'Activer',
        'suspended' => 'Désactiver (suspendre)',
        'ended' => 'Terminer',
        'confirm_title' => ':name : passer au statut « :status » ?',
        'body' => [
            'pending' => 'Le client attend la confirmation du bureau. Aucune commande n\'est générée tant qu\'il n\'est pas actif.',
            'active' => 'Le client reçoit de nouveau ses commandes générées automatiquement.',
            'suspended' => 'Plus aucune nouvelle commande n\'est générée pour ce client. Ses commandes, paiements et documents restent intacts, et on peut le réactiver à tout moment.',
            'ended' => 'Plus aucune nouvelle commande n\'est générée et le client ne compte plus dans le plafond. Ses commandes, paiements et documents restent intacts, et on peut le réactiver.',
        ],
    ],

    'show' => [
        'details' => 'Informations',
        'contact_trade' => 'Métier du responsable',
        'registered' => 'Enregistré le :date par :author',
        'orders_title' => 'Commandes et paiements',
        'orders_soon' => 'Les commandes, paiements et livraisons apparaîtront ici.',
    ],

];
