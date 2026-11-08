<?php

return [

    'head' => 'System health',
    'title' => 'System health',
    'description' => 'What lets you trust the system: backups, automatic tasks, queue, journal, database, disk and configuration. Nothing here changes anything.',
    'unit_mb' => 'MB',
    'recheck' => 'Check again',
    'checked_at' => 'Checked at :time',
    'details' => 'Technical details',
    'todo' => 'To do',

    'summary' => [
        'ok' => 'All good: nothing to deal with.',
        'attention' => ':errors to fix, :warnings to watch.',
    ],

    'status' => [
        'ok' => 'Good',
        'warning' => 'To watch',
        'error' => 'To fix',
    ],

    'checks' => [
        'backup' => [
            'title' => 'Backup',
            'ok' => 'Last backup: :ago (:size).',
            'late' => 'The last backup is from :ago (:size): one backup a day is expected.',
            'never' => 'No backup has been made yet.',
            'not_configured' => 'The backup is not set up: no password is defined, so nothing is backed up.',
            'local_disk' => 'Last backup: :ago (:size), but it is kept on the server itself: it would be lost with it.',
            'todo' => 'Set BACKUP_PASSWORD in the .env file, point BACKUP_DISK to a storage outside the server, then run "php artisan app:backup". Keep the password somewhere other than the server.',
        ],
        'scheduler' => [
            'title' => 'Automatic tasks',
            'ok' => 'The scheduler is running: the daily tasks (backup, automatic orders, journal checks) are started.',
            'never' => 'The scheduler has never given a sign of life: no automatic task is started.',
            'stopped' => 'The scheduler has been stopped for :minutes minutes: no automatic task is started any more.',
            'todo' => 'Check that the scheduled job of the server runs "php artisan schedule:run" every minute (cron, or "php artisan schedule:work" in development).',
        ],
        'queue' => [
            'title' => 'Queue',
            'ok' => 'The queue is empty or moving normally.',
            'sync' => 'Tasks run immediately, without a queue.',
            'failed' => ':count failed task(s) in the queue.',
            'stuck' => 'Tasks have been waiting for more than :minutes minutes: the queue worker may be stopped.',
            'unknown' => 'The state of the queue could not be read.',
            'todo' => 'Check that "php artisan queue:work" is running (server supervisor), then retry the failed tasks ("php artisan queue:retry all").',
        ],
        'audit_chain' => [
            'title' => 'Audit journal',
            'ok' => 'Journal chain intact (:entries entries), checked :ago.',
            'never' => 'The journal chain has not been checked yet.',
            'late' => 'The last check of the journal chain is from :ago: it is expected every day.',
            'broken' => 'The journal chain is broken (checked :ago): an entry was changed or removed.',
            'todo' => 'Run "php artisan app:verify-audit-log" for the detail. A broken chain is serious: do not touch the database, and restore the latest healthy backup if needed.',
        ],
        'migrations' => [
            'title' => 'Database',
            'ok' => 'The database structure is up to date.',
            'pending' => 'Database updates have not been run: profiles may miss their new rights.',
            'todo' => 'Run "php artisan migrate" (in production: with the migrator role, option --database=pgsql_migrator --force).',
        ],
        'configuration' => [
            'title' => 'Configuration',
            'ok' => 'No dangerous setting.',
            'errors' => ':count setting(s) dangerous for a production server.',
            'warnings' => ':count setting(s) worth a look (no immediate danger).',
            'todo' => 'Run "php artisan app:check-production" for the list, then fix the .env file.',
        ],
        'disk' => [
            'title' => 'Disk space',
            'ok' => ':percent% free (:free).',
            'low' => 'Only :percent% of space left (:free).',
            'full' => 'Disk almost full: :percent% free (:free). Files and backups may fail.',
            'unknown' => 'The disk space could not be measured.',
            'todo' => 'Free some space or enlarge the disk; check that the backups really leave the server.',
        ],
        'mail' => [
            'title' => 'Mail',
            'ok' => 'Sending mail is set up.',
            'not_sending' => 'No mail is really sent: password reset links would never arrive.',
            'todo' => 'Set MAIL_MAILER and the MAIL_* settings in the .env file.',
        ],
    ],

];
