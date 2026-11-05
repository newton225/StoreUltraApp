<?php

return [

    'head' => 'Livraisons',
    'title' => 'Livraisons',
    'description' => 'Les kits à remettre et les livraisons déjà faites',
    'recorded' => 'Livraison enregistrée.',

    'errors' => [
        'status' => 'Cette commande ne peut pas être livrée maintenant : ses marchandises doivent être reçues.',
        'kits' => 'Le nombre de kits dépasse ce qui reste à livrer.',
        'signature' => 'La signature n\'est pas une image valide.',
    ],

    'filters' => [
        'zone' => 'Sous-préfecture',
        'all_zones' => 'Toutes les sous-préfectures',
    ],

    'to_deliver' => [
        'title' => 'À livrer',
        'empty' => 'Rien à livrer',
        'client' => 'Client',
        'phone' => 'Téléphone',
        'zone' => 'Sous-préfecture',
        'week' => 'Semaine du',
        'remaining' => 'Kits restants',
        'open' => 'Livrer',
    ],

    'done' => [
        'title' => 'Livraisons faites',
        'empty' => 'Aucune livraison pour le moment',
        'reference' => 'Livraison',
        'date' => 'Date',
        'client' => 'Client',
        'kits' => 'Kits',
        'agent' => 'Agent',
        'received_by' => 'Reçu par',
        'signature' => 'Signature',
        'signed' => 'Signé',
    ],

    'panel' => [
        'kits_help' => 'Nombre de kits remis cette fois. Une livraison peut être partielle : le reste se livre plus tard.',
        'collect_amount_help' => 'Le relicat est le solde que le client paie à la livraison : le prix de vente moins l’avance déjà versée.',
        'title' => 'Livraisons',
        'remaining' => ':count kit(s) restant(s) à livrer',
        'complete' => 'Tous les kits ont été livrés',
        'empty' => 'Aucune livraison pour le moment',
        'record' => 'Enregistrer une livraison',
        'kits' => 'Kits remis',
        'date' => 'Date',
        'received_by' => 'Nom de la personne qui a reçu les kits',
        'agent' => 'Agent de terrain',
        'no_agent' => 'Non précisé',
        'notes' => 'Observations',
        'signature' => 'Signature de la personne qui a reçu',
        'signature_optional' => '(facultative)',
        'clear' => 'Effacer',
        'collect_title' => 'Encaisser le relicat maintenant (facultatif)',
        'collect_amount' => 'Montant encaissé (FCFA)',
        'method' => 'Mode de paiement',
        'choose_method' => 'Choisir un mode',
        'reference' => 'Référence de la transaction',
        'submit' => 'Enregistrer la livraison',
        'confirm_title' => 'Enregistrer cette livraison ?',
        'confirm_body' => 'La livraison sera enregistrée avec son bon de livraison et la signature. Elle ne peut plus être modifiée. Si de l\'argent est encaissé à la livraison, le paiement est enregistré en même temps.',
        'view_signature' => 'Voir la signature',
        'by' => 'par :name',
        'received_by_label' => 'reçu par :name',
    ],

];
