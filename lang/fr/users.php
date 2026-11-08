<?php

return [

    'head' => 'Utilisateurs',
    'title' => 'Utilisateurs',
    'description' => 'Créez les comptes et choisissez ce que chacun peut faire',
    'new' => 'Nouvel utilisateur',
    'edit' => 'Modifier',
    'empty' => 'Aucun utilisateur',
    'created' => 'Utilisateur créé.',
    'updated' => 'Utilisateur mis à jour.',
    'created_invited' => 'Utilisateur créé. Un e-mail d\'invitation a été envoyé à :email.',
    'created_not_sent' => 'Utilisateur créé, mais l\'e-mail n\'a pas pu partir. Affichez le lien ci-dessous pour le lui transmettre.',
    'invitation_sent' => 'E-mail envoyé à :email.',
    'invitation_not_sent' => 'L\'e-mail n\'a pas pu partir. Affichez le lien pour le transmettre vous-même.',

    'filters' => [
        'search' => 'Rechercher par nom ou adresse e-mail',
        'apply' => 'Rechercher',
        'all_profiles' => 'Tous les profils',
        'all_states' => 'Tous les statuts',
        'no_match' => 'Aucun utilisateur ne correspond à ces filtres',
    ],

    'invitation' => [
        'title' => 'Mot de passe',
        'pending' => 'La personne n\'a pas encore choisi son mot de passe : elle ne peut pas se connecter.',
        'done' => 'La personne a choisi son mot de passe. Vous ne le connaissez pas, et c\'est voulu.',
        'sent_at' => 'Dernier e-mail remis au serveur de messagerie le :date. S\'il n\'arrive pas, la personne doit regarder ses courriers indésirables.',
        'never_sent' => 'Aucun e-mail n\'est encore parti pour ce compte.',
        'send' => 'Envoyer un e-mail pour choisir le mot de passe',
        'send_help' => 'Un nouveau lien, valable 48 heures. Il remplace le précédent. Utile aussi si la personne a oublié son mot de passe.',
        'show_link' => 'Afficher le lien à transmettre',
        'show_link_title' => 'Afficher le lien ?',
        'show_link_description' => 'Ce lien permet de fixer le mot de passe de ce compte. Ne le transmettez qu\'à la personne concernée. Un nouveau lien remplace le précédent, et l\'affichage est inscrit au journal.',
        'link_label' => 'Lien à transmettre',
        'link_help' => 'Valable 48 heures, utilisable une seule fois.',
        'copy' => 'Copier',
        'copied' => 'Lien copié.',
    ],

    'columns' => [
        'name' => 'Nom',
        'email' => 'Adresse e-mail',
        'role' => 'Rôle',
        'profile' => 'Profil',
        'status' => 'Statut',
    ],

    'roles' => [
        'owner' => 'Responsable',
        'employee' => 'Employé',
        'agent' => 'Agent de terrain',
    ],

    'status' => [
        'active' => 'Actif',
        'inactive' => 'Désactivé',
        'pending' => 'Invitation en attente',
    ],

    'create' => [
        'head' => 'Nouvel utilisateur',
        'title' => 'Nouvel utilisateur',
        'description' => 'La personne reçoit un e-mail pour choisir elle-même son mot de passe',
        'submit' => 'Créer et envoyer l\'invitation',
    ],

    'form' => [
        'role_help' => 'Le profil décide de ce que l’utilisateur a le droit de faire (voir l’écran Profils). Un profil limité aux sous-préfectures ne voit que les clients, commandes et livraisons des sous-préfectures affectées.',
        'name' => 'Nom',
        'name_placeholder' => 'Nom complet',
        'email' => 'Adresse e-mail',
        'role' => 'Rôle',
        'profile' => 'Profil',
        'invitation_note' => 'Un e-mail sera envoyé à cette adresse : la personne y choisira son mot de passe, que vous ne connaîtrez pas.',
        'zones' => 'Sous-préfectures',
        'zones_help' => 'Un agent de terrain ne voit que les clients de ces sous-préfectures',
        'zones_empty' => 'Aucune sous-préfecture n\'existe encore',
        'is_active' => 'Compte actif',
        'is_active_help' => 'Un utilisateur désactivé ne peut plus se connecter',
        'submit' => 'Enregistrer',
    ],

    'edit_page' => [
        'head' => 'Modifier l\'utilisateur',
        'title' => 'Modifier l\'utilisateur',
        'description' => 'Modifiez les informations, le rôle ou le statut de ce compte',
    ],

    'errors' => [
        'own_role' => 'Vous ne pouvez pas modifier votre propre rôle.',
        'own_status' => 'Vous ne pouvez pas désactiver votre propre compte.',
    ],

];
