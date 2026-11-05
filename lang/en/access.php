<?php

return [

    'fields' => [
        'email' => 'Email address',
        'email_placeholder' => 'email@example.com',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'password_confirmation' => 'Confirm password',
        'password_confirmation_placeholder' => 'Confirm password',
    ],

    'login' => [
        'head' => 'Log in',
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'remember' => 'Remember me',
        'forgot' => 'Forgot your password?',
        'submit' => 'Log in',
    ],

    'forgot' => [
        'title' => 'Forgot password',
        'description' => 'Enter your email to receive a password reset link',
        'submit' => 'Email password reset link',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],

    'reset' => [
        'title' => 'Reset password',
        'description' => 'Please enter your new password below',
        'submit' => 'Reset password',
    ],

    'confirm' => [
        'title' => 'Confirm password',
        'description' => 'This is a secure area of the application. Please confirm your password before continuing.',
        'passkey' => 'Confirm with passkey',
        'passkey_loading' => 'Confirming...',
        'passkey_separator' => 'Or confirm with password',
        'submit' => 'Confirm password',
    ],

    'verify' => [
        'title' => 'Email verification',
        'description' => 'Please verify your email address by clicking on the link we just emailed to you.',
        'link_sent' => 'A new verification link has been sent to your email address.',
        'resend' => 'Resend verification email',
    ],

    'two_factor' => [
        'head' => 'Two-factor authentication',
        'code_title' => 'Authentication code',
        'code_description' => 'Enter the authentication code provided by your authenticator application.',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
        'recovery_placeholder' => 'Enter recovery code',
        'or_you_can' => 'or you can',
        'use_recovery' => 'login using a recovery code',
        'use_code' => 'login using an authentication code',
        'submit' => 'Continue',
    ],

    'passkey' => [
        'sign_in' => 'Sign in with a passkey',
        'authenticating' => 'Authenticating...',
        'or_email' => 'Or continue with email',
    ],

];
