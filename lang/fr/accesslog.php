<?php

return [

    'head' => 'Journal d\'accès',
    'title' => 'Journal d\'accès',
    'description' => 'Qui a ouvert quelle page, quand, depuis quelle adresse et quel navigateur, avec les connexions refusées. Les lignes sont gardées :days jours puis supprimées. Pour ce que les personnes ont modifié, voir le Journal.',
    'empty' => 'Aucune ligne pour ces filtres.',
    'kinds' => [
        'page' => 'Page ouverte',
        'login_failed' => 'Connexion refusée',
        'lockout' => 'Compte bloqué (trop d\'essais)',
    ],
    'filters' => [
        'all_users' => 'Tous les utilisateurs',
        'all_kinds' => 'Tous les types',
        'from' => 'Du',
        'to' => 'Au',
        'ip' => 'Adresse IP',
        'apply' => 'Filtrer',
    ],
    'columns' => [
        'when' => 'Quand',
        'who' => 'Qui',
        'page' => 'Page',
        'status' => 'Réponse',
        'ip' => 'Adresse IP',
        'browser' => 'Navigateur',
    ],

];
