<?php

return [

    'settings' => [
        'title' => 'Settings',
        'description' => 'Manage your profile and account settings',
    ],

    'profile' => [
        'head' => 'Profile settings',
        'title' => 'Profile',
        'description' => 'Update your name and email address',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'email_placeholder' => 'Email address',
        'unverified' => 'Your email address is unverified.',
        'resend' => 'Click here to re-send the verification email.',
        'link_sent' => 'A new verification link has been sent to your email address.',
        'updated' => 'Profile updated.',
    ],

    'security' => [
        'head' => 'Security settings',
        'password_title' => 'Update password',
        'password_description' => 'Ensure your account is using a long, random password to stay secure',
        'current_password' => 'Current password',
        'current_password_placeholder' => 'Current password',
        'new_password' => 'New password',
        'new_password_placeholder' => 'New password',
        'password_updated' => 'Password updated.',
    ],

    'two_factor_required' => [
        'toast' => 'Turn on two-factor authentication first to continue.',
        'title' => 'Two-factor authentication is required',
        'description' => 'As the owner, you see the profit, create the users and change the settings. Until you turn on two-factor authentication below, the rest of the application stays closed.',
    ],

    'two_factor' => [
        'title' => 'Two-factor authentication',
        'description' => 'Manage your two-factor authentication settings',
        'enabled_text' => 'You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.',
        'disabled_text' => 'When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.',
        'disable' => 'Disable 2FA',
        'enable' => 'Enable 2FA',
        'continue_setup' => 'Continue setup',
        'enter_manually' => 'or, enter the code manually',
        'modal' => [
            'enabled_title' => 'Two-factor authentication enabled',
            'enabled_description' => 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
            'verify_title' => 'Verify authentication code',
            'verify_description' => 'Enter the 6-digit code from your authenticator app',
            'enable_title' => 'Enable two-factor authentication',
            'enable_description' => 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
        ],
        'recovery' => [
            'title' => '2FA recovery codes',
            'description' => 'Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.',
            'view' => 'View recovery codes',
            'hide' => 'Hide recovery codes',
            'regenerate' => 'Regenerate codes',
            'list_label' => 'Recovery codes',
            'loading' => 'Loading recovery codes',
            'help_before' => 'Each recovery code can be used once to access your account and will be removed after use. If you need more, click',
            'help_after' => 'above.',
        ],
    ],

    'passkeys' => [
        'title' => 'Passkeys',
        'description' => 'Manage your passkeys for passwordless sign-in',
        'empty_title' => 'No passkeys yet',
        'empty_description' => 'Add a passkey to sign in without a password',
        'not_supported' => 'Passkeys are not supported in this browser.',
        'add' => 'Add passkey',
        'name' => 'Passkey name',
        'name_placeholder' => 'e.g., MacBook Pro, iPhone',
        'name_help' => 'A name helps you identify this passkey later.',
        'register' => 'Register passkey',
        'registering' => 'Registering...',
        'device' => ':browser on :os',
        'added' => 'Added :when',
        'last_used' => 'Last used :when',
        'remove_title' => 'Remove passkey',
        'remove_description' => 'Are you sure you want to remove the ":name" passkey? You will no longer be able to use it to sign in.',
        'removing' => 'Removing...',
    ],

];
