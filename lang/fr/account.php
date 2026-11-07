<?php

return [

    'settings' => [
        'title' => 'Paramètres',
        'description' => 'Gérez votre profil et les paramètres de votre compte',
    ],

    'profile' => [
        'head' => 'Paramètres du profil',
        'title' => 'Profil',
        'description' => 'Modifiez votre nom et votre adresse e-mail',
        'name' => 'Nom',
        'name_placeholder' => 'Nom complet',
        'email' => 'Adresse e-mail',
        'email_placeholder' => 'Adresse e-mail',
        'unverified' => 'Votre adresse e-mail n\'est pas vérifiée.',
        'resend' => 'Cliquez ici pour renvoyer l\'e-mail de vérification.',
        'link_sent' => 'Un nouveau lien de vérification a été envoyé à votre adresse e-mail.',
        'updated' => 'Profil mis à jour.',
    ],

    'security' => [
        'head' => 'Paramètres de sécurité',
        'password_title' => 'Modifier le mot de passe',
        'password_description' => 'Utilisez un mot de passe long et aléatoire pour protéger votre compte',
        'current_password' => 'Mot de passe actuel',
        'current_password_placeholder' => 'Mot de passe actuel',
        'new_password' => 'Nouveau mot de passe',
        'new_password_placeholder' => 'Nouveau mot de passe',
        'password_updated' => 'Mot de passe mis à jour.',
    ],

    'two_factor_required' => [
        'toast' => 'Activez d\'abord la double authentification pour continuer.',
        'title' => 'La double authentification est obligatoire',
        'description' => 'En tant que propriétaire, vous voyez les bénéfices, créez les utilisateurs et modifiez les paramètres. Tant que vous n\'avez pas activé la double authentification ci-dessous, le reste de l\'application reste fermé.',
    ],

    'two_factor' => [
        'title' => 'Double authentification',
        'description' => 'Gérez les paramètres de votre double authentification',
        'enabled_text' => 'Un code sécurisé aléatoire vous sera demandé à la connexion ; vous le trouverez dans l\'application d\'authentification (TOTP) de votre téléphone.',
        'disabled_text' => 'Si vous activez la double authentification, un code sécurisé vous sera demandé à la connexion. Ce code se trouve dans une application d\'authentification (TOTP) sur votre téléphone.',
        'disable' => 'Désactiver la double authentification',
        'enable' => 'Activer la double authentification',
        'continue_setup' => 'Poursuivre la configuration',
        'enter_manually' => 'ou saisissez le code manuellement',
        'modal' => [
            'enabled_title' => 'Double authentification activée',
            'enabled_description' => 'La double authentification est activée. Scannez le code QR ou saisissez la clé de configuration dans votre application d\'authentification.',
            'verify_title' => 'Vérifier le code d\'authentification',
            'verify_description' => 'Saisissez le code à 6 chiffres de votre application d\'authentification',
            'enable_title' => 'Activer la double authentification',
            'enable_description' => 'Pour terminer l\'activation, scannez le code QR ou saisissez la clé de configuration dans votre application d\'authentification',
        ],
        'recovery' => [
            'title' => 'Codes de récupération',
            'description' => 'Les codes de récupération vous permettent de retrouver l\'accès si vous perdez votre appareil. Conservez-les dans un gestionnaire de mots de passe sécurisé.',
            'view' => 'Afficher les codes de récupération',
            'hide' => 'Masquer les codes de récupération',
            'regenerate' => 'Régénérer les codes',
            'list_label' => 'Codes de récupération',
            'loading' => 'Chargement des codes de récupération',
            'help_before' => 'Chaque code de récupération ne peut être utilisé qu\'une fois et est supprimé après usage. Si vous en avez besoin d\'autres, cliquez sur',
            'help_after' => 'ci-dessus.',
        ],
    ],

    'passkeys' => [
        'title' => 'Clés d\'accès',
        'description' => 'Gérez vos clés d\'accès pour vous connecter sans mot de passe',
        'empty_title' => 'Aucune clé d\'accès',
        'empty_description' => 'Ajoutez une clé d\'accès pour vous connecter sans mot de passe',
        'not_supported' => 'Les clés d\'accès ne sont pas prises en charge par ce navigateur.',
        'add' => 'Ajouter une clé d\'accès',
        'name' => 'Nom de la clé d\'accès',
        'name_placeholder' => 'ex. : MacBook Pro, iPhone',
        'name_help' => 'Un nom vous aide à reconnaître cette clé d\'accès plus tard.',
        'register' => 'Enregistrer la clé d\'accès',
        'registering' => 'Enregistrement en cours...',
        'device' => ':browser sur :os',
        'added' => 'Ajoutée :when',
        'last_used' => 'Dernière utilisation :when',
        'remove_title' => 'Retirer la clé d\'accès',
        'remove_description' => 'Voulez-vous vraiment retirer la clé d\'accès « :name » ? Vous ne pourrez plus l\'utiliser pour vous connecter.',
        'removing' => 'Suppression en cours...',
    ],

];
