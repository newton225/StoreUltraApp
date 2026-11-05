<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for the owner
    |--------------------------------------------------------------------------
    |
    | The owner sees the profit, creates the users and changes the settings, so
    | they cannot use the application before they have set up a second factor.
    | Switch it off only on a local machine: `app:check-production` and the
    | boot of a production server refuse a configuration where it is off.
    |
    */

    'require_owner_two_factor' => (bool) env('SECURITY_REQUIRE_OWNER_TWO_FACTOR', true),

    /*
    |--------------------------------------------------------------------------
    | Audit of reference data
    |--------------------------------------------------------------------------
    |
    | Every creation, change and deletion of a client, zone, product, price,
    | kit, shop, list or setting is written to the audit journal (trait
    | Audited). Only the test suite switches it off, so that the factories do
    | not fill the journal; `app:check-production` refuses a configuration
    | where it is off.
    |
    */

    'audit_reference_data' => (bool) env('SECURITY_AUDIT_REFERENCE_DATA', true),

    /*
    |--------------------------------------------------------------------------
    | Access journal
    |--------------------------------------------------------------------------
    |
    | Every page a signed-in user opens, every failed sign-in and every lock-out
    | is written with the address and the browser (table access_logs, kept apart
    | from the audit journal). Lines older than the retention period are deleted
    | each day. Only the test suite switches it off; `app:check-production`
    | refuses a configuration where it is off.
    |
    */

    'access_log' => (bool) env('SECURITY_ACCESS_LOG', true),

    'access_log_retention_days' => (int) env('SECURITY_ACCESS_LOG_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Copies of the audit journal
    |--------------------------------------------------------------------------
    |
    | Each finished month of the audit journal is copied, with its fingerprints,
    | to this filesystem disk (app:archive-audit-log, monthly). The default disk
    | is a private folder of the server: for a real safeguard point it to a
    | storage the application can only add to (an object store with versioning).
    |
    */

    'audit_archive_disk' => env('SECURITY_AUDIT_ARCHIVE_DISK', 'audit-archive'),

    /*
    | The copies do not stay forever (app:prune-audit-archive, daily): a copy older
    | than the retention is deleted, and when the disk is fuller than the limit
    | (percent, 0 = no limit) the oldest copies go first, never the most recent
    | ones kept at all times. The small manifest of a deleted copy stays.
    */

    'audit_archive_retention_months' => (int) env('SECURITY_AUDIT_ARCHIVE_RETENTION_MONTHS', 36),

    'audit_archive_min_keep' => (int) env('SECURITY_AUDIT_ARCHIVE_MIN_KEEP', 3),

    'audit_archive_disk_max_percent' => (int) env('SECURITY_AUDIT_ARCHIVE_DISK_MAX_PERCENT', 85),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | enforce: violations are blocked. report-only: they are only shown in the
    | browser console (the default on a local machine, where the Vite server and
    | the Boost browser logger add scripts of their own). off: no header.
    |
    */

    'csp' => env('SECURITY_CSP', env('APP_ENV') === 'local' ? 'report-only' : 'enforce'),

    /*
    |--------------------------------------------------------------------------
    | Style injected by a library at run time
    |--------------------------------------------------------------------------
    |
    | Sonner writes its stylesheet into the page from JavaScript. The policy
    | allows that one text by its hash instead of allowing every inline style.
    | A test compares this hash with the installed sonner, so an update that
    | changes the text fails there instead of breaking the toasts.
    |
    */

    'csp_style_hashes' => [
        'sonner' => 'sha256-StEaX+se6YS7pqjzrzMIA0KaX9zF/8zAhvQXZAe5epY=',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Comma separated addresses of the reverse proxies in front of the
    | application. Never `*`: anyone could then forge `X-Forwarded-For` and
    | every limit by address would mean nothing.
    |
    */

    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
