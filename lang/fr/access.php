<?php

return [

    'fields' => [
        'email' => 'Adresse e-mail',
        'email_placeholder' => 'email@exemple.com',
        'password' => 'Mot de passe',
        'password_placeholder' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'password_confirmation_placeholder' => 'Confirmer le mot de passe',
    ],

    'login' => [
        'head' => 'Connexion',
        'title' => 'Connexion à votre compte',
        'description' => 'Saisissez votre adresse e-mail et votre mot de passe pour vous connecter',
        'remember' => 'Se souvenir de moi',
        'forgot' => 'Mot de passe oublié ?',
        'submit' => 'Se connecter',
    ],

    'forgot' => [
        'title' => 'Mot de passe oublié',
        'description' => 'Saisissez votre adresse e-mail pour recevoir un lien de réinitialisation',
        'submit' => 'Envoyer le lien de réinitialisation',
        'return_to' => 'Ou retourner à la',
        'log_in' => 'connexion',
    ],

    'invitation' => [
        'title' => 'Choisissez votre mot de passe',
        'description' => 'Votre compte est créé : choisissez un mot de passe que vous seul connaîtrez',
        'submit' => 'Enregistrer mon mot de passe',
        'expired' => 'Ce lien n\'est plus valable (déjà utilisé ou expiré). Demandez-en un nouveau à la personne qui a créé votre compte.',
        'done' => 'Mot de passe enregistré. Vous pouvez vous connecter.',
    ],

    'reset' => [
        'title' => 'Réinitialiser le mot de passe',
        'description' => 'Saisissez votre nouveau mot de passe ci-dessous',
        'submit' => 'Réinitialiser le mot de passe',
    ],

    'confirm' => [
        'title' => 'Confirmer le mot de passe',
        'description' => 'Vous accédez à une zone sécurisée de l\'application. Veuillez confirmer votre mot de passe pour continuer.',
        'passkey' => 'Confirmer avec une clé d\'accès',
        'passkey_loading' => 'Confirmation en cours...',
        'passkey_separator' => 'Ou confirmer avec le mot de passe',
        'submit' => 'Confirmer le mot de passe',
    ],

    'verify' => [
        'title' => 'Vérification de l\'adresse e-mail',
        'description' => 'Veuillez vérifier votre adresse e-mail en cliquant sur le lien que nous venons de vous envoyer.',
        'link_sent' => 'Un nouveau lien de vérification a été envoyé à votre adresse e-mail.',
        'resend' => 'Renvoyer l\'e-mail de vérification',
    ],

    'two_factor' => [
        'head' => 'Double authentification',
        'code_title' => 'Code d\'authentification',
        'code_description' => 'Saisissez le code fourni par votre application d\'authentification.',
        'recovery_title' => 'Code de récupération',
        'recovery_description' => 'Confirmez l\'accès à votre compte en saisissant l\'un de vos codes de récupération d\'urgence.',
        'recovery_placeholder' => 'Saisissez le code de récupération',
        'or_you_can' => 'ou vous pouvez',
        'use_recovery' => 'vous connecter avec un code de récupération',
        'use_code' => 'vous connecter avec un code d\'authentification',
        'submit' => 'Continuer',
    ],

    'passkey' => [
        'sign_in' => 'Se connecter avec une clé d\'accès',
        'authenticating' => 'Authentification en cours...',
        'or_email' => 'Ou continuer avec l\'adresse e-mail',
    ],

];
