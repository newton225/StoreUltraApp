<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version of the legal texts
    |--------------------------------------------------------------------------
    |
    | The date of the last change of the three texts (legal notice, privacy policy, terms of use). It goes up in the
    | same commit as any change of what the texts say (a duration, a provider, a collected data). It is not a setting:
    | do not change it from the environment.
    |
    */

    'version' => '2026-11-08',

    /*
    |--------------------------------------------------------------------------
    | Legal review
    |--------------------------------------------------------------------------
    |
    | The texts are written from what the application really does; they are NOT legal advice and must be read by a
    | lawyer before real use. Put the version a lawyer has approved here: while it differs from `version`,
    | `app:check-production` refuses a production server (and the health screen says so).
    |
    */

    'reviewed_version' => env('LEGAL_REVIEWED_VERSION'),

    /*
    |--------------------------------------------------------------------------
    | Who else is in the texts
    |--------------------------------------------------------------------------
    |
    | The company itself (name, address, e-mail, signatory) comes from the "Mon entreprise" page. What follows is not
    | on that page: the host of the server, the mail provider, where the data is, the country and its data
    | protection law and authority. A blank is printed as "[à compléter]" and stops a production server.
    |
    */

    'host_name' => env('LEGAL_HOST_NAME'),
    'host_address' => env('LEGAL_HOST_ADDRESS'),
    'host_country' => env('LEGAL_HOST_COUNTRY'),
    'mail_provider' => env('LEGAL_MAIL_PROVIDER'),
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL'),
    'country' => env('LEGAL_COUNTRY'),
    'data_protection_law' => env('LEGAL_DATA_PROTECTION_LAW'),
    'data_protection_authority' => env('LEGAL_DATA_PROTECTION_AUTHORITY'),

];
