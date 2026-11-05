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
    ],

    'create' => [
        'head' => 'Nouvel utilisateur',
        'title' => 'Nouvel utilisateur',
        'description' => 'La personne se connecte avec cette adresse e-mail et ce mot de passe',
        'submit' => 'Créer l\'utilisateur',
    ],

    'form' => [
        'role_help' => 'Le profil décide de ce que l’utilisateur a le droit de faire (voir l’écran Profils). Un profil limité aux sous-préfectures ne voit que les clients, commandes et livraisons des sous-préfectures affectées.',
        'name' => 'Nom',
        'name_placeholder' => 'Nom complet',
        'email' => 'Adresse e-mail',
        'role' => 'Rôle',
        'profile' => 'Profil',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'password_optional' => 'Laissez vide pour conserver le mot de passe actuel',
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
