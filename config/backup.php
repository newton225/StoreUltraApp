<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the backups go
    |--------------------------------------------------------------------------
    |
    | The filesystem disk that receives the encrypted archives. By default it is a folder of the server itself, which
    | protects from a mistake but not from losing the server: point BACKUP_DISK to a disk that lives elsewhere (an S3
    | compatible bucket, an SFTP server) in production. `app:check-production` reminds it while the default is used.
    |
    */

    'disk' => env('BACKUP_DISK', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Password of the archive
    |--------------------------------------------------------------------------
    |
    | The archive is encrypted (AES-256) with this password, which is also needed to restore it: keep it somewhere
    | other than the server. Without it the command refuses to run.
    |
    */

    'password' => env('BACKUP_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Database access of the backup
    |--------------------------------------------------------------------------
    |
    | PostgreSQL only. A read-only role of its own (`storeultra_backup`, database/postgres/roles.sql), never the role
    | of the application. `pg_dump` must be at least as recent as the database server. SQLite is copied as a file.
    |
    */

    'database' => [
        'username' => env('BACKUP_DB_USERNAME'),
        'password' => env('BACKUP_DB_PASSWORD'),
        'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
    ],

    /*
    |--------------------------------------------------------------------------
    | How long the copies are kept
    |--------------------------------------------------------------------------
    |
    | Every copy for `daily_after` days, then one per day up to `weekly_after` days, one per week up to `monthly_after`
    | days, one per month up to `max_days`, then nothing. `max_days` is also how long a deleted record can survive.
    |
    */

    'retention' => [
        'all_days' => (int) env('BACKUP_KEEP_ALL_DAYS', 7),
        'daily_days' => (int) env('BACKUP_KEEP_DAILY_DAYS', 30),
        'weekly_days' => (int) env('BACKUP_KEEP_WEEKLY_DAYS', 90),
        'max_days' => (int) env('BACKUP_KEEP_MAX_DAYS', 365),
    ],

];
