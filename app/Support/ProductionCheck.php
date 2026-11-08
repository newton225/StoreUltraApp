<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * What a production server must not run with. Errors are dangerous settings and stop a production
 * server from answering; warnings are worth a look but do not stop it. Run `php artisan
 * app:check-production` after a deployment, or before one on the production settings.
 */
class ProductionCheck
{
    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function run(): array
    {
        $errors = [];
        $warnings = [];

        if (config('app.debug')) {
            $errors[] = 'APP_DEBUG is on: errors would show their stack and settings to visitors.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $errors[] = 'APP_URL does not start with https://.';
        }

        if ((string) config('app.key') === '') {
            $errors[] = 'APP_KEY is empty: sessions and encrypted values are not protected.';
        }

        foreach ($this->logChannels() as $channel => $level) {
            if ($level === 'debug') {
                $errors[] = "The log channel \"{$channel}\" is at the debug level (LOG_LEVEL): it would write personal data and tokens.";
            }
        }

        if (! config('security.require_owner_two_factor')) {
            $errors[] = 'SECURITY_REQUIRE_OWNER_TWO_FACTOR is off: the owner could work without a second factor.';
        }

        if (! config('security.access_log')) {
            $errors[] = 'SECURITY_ACCESS_LOG is off: page visits and failed sign-ins would leave no trace.';
        }

        if (! config('security.audit_reference_data')) {
            $errors[] = 'SECURITY_AUDIT_REFERENCE_DATA is off: changes to the reference data would leave no trace.';
        }

        $csp = (string) config('security.csp');

        if ($csp === 'off') {
            $errors[] = 'SECURITY_CSP is off: no Content Security Policy is sent.';
        } elseif ($csp !== 'enforce') {
            $warnings[] = 'SECURITY_CSP is not "enforce": the policy only reports violations.';
        }

        if (! config('session.secure')) {
            $errors[] = 'SESSION_SECURE_COOKIE is off: the session cookie could travel over plain HTTP.';
        }

        if (! config('session.encrypt')) {
            $errors[] = 'SESSION_ENCRYPT is off: the content of the sessions is stored in clear text.';
        }

        if (! config('session.http_only')) {
            $errors[] = 'The session cookie is readable by scripts (SESSION_HTTP_ONLY).';
        }

        $proxies = (array) config('security.trusted_proxies');

        if (in_array('*', $proxies, true) || in_array('**', $proxies, true)) {
            $errors[] = 'TRUSTED_PROXIES trusts every address: X-Forwarded-For could be forged and the limits by address would mean nothing.';
        } elseif ($proxies === []) {
            $warnings[] = 'TRUSTED_PROXIES is empty: behind a reverse proxy, the client address and the scheme would be wrong.';
        }

        if (config('database.default') === 'sqlite') {
            $errors[] = 'The database is SQLite: production uses PostgreSQL.';
        } elseif (in_array(config('database.connections.'.config('database.default').'.username'), ['postgres', 'root'], true)) {
            $warnings[] = 'The application connects to the database as a super-user: give it a role of its own.';
        }

        if ((string) config('backup.password') === '') {
            $warnings[] = 'BACKUP_PASSWORD is empty: app:backup refuses to run, so nothing is backed up.';
        } elseif (config('filesystems.disks.'.config('backup.disk').'.driver') === 'local') {
            $warnings[] = 'The backups are written on the server itself (BACKUP_DISK): they would be lost with it. Point BACKUP_DISK to a storage elsewhere.';
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $warnings[] = 'MAIL_MAILER does not send anything: password reset links would never arrive.';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * What the database itself says about the role the application connects with. It needs a connection, so the
     * command asks it (at every deployment), not every web request. A super-user is an error: it reads every
     * database, drops tables and switches off the triggers that keep the audit journal append-only. A role that owns
     * the tables, may create roles or databases, or may change the audit journal is a warning: the role of the
     * application should only read and write rows (database/postgres/roles.sql).
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function databaseRole(): array
    {
        $errors = [];
        $warnings = [];

        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return ['errors' => [], 'warnings' => []];
            }

            $role = DB::selectOne(
                'select current_user as name, r.rolsuper, r.rolcreaterole, r.rolcreatedb,
                    (select count(*) from pg_tables t where t.schemaname = current_schema() and t.tableowner = current_user) as owned,
                    coalesce(has_table_privilege(current_user, to_regclass(?), ?), false) as can_change_audit
                 from pg_roles r where r.rolname = current_user',
                ['audit_logs', 'UPDATE'],
            );
        } catch (Throwable) {
            return ['errors' => [], 'warnings' => ['The role of the database connection could not be checked.']];
        }

        if ($role === null) {
            return ['errors' => [], 'warnings' => []];
        }

        if ($role->rolsuper) {
            $errors[] = "The application connects to the database as a super-user ({$role->name}): it could read every database, drop tables and switch off the triggers that keep the audit journal append-only. Give it the role of database/postgres/roles.sql.";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        if ((int) $role->owned > 0) {
            $warnings[] = "The database role \"{$role->name}\" owns {$role->owned} tables: it could drop them or switch off the audit triggers. The application should connect with a role that only reads and writes rows, and migrations should run with the owner role.";
        }

        if ($role->rolcreaterole || $role->rolcreatedb) {
            $warnings[] = "The database role \"{$role->name}\" may create roles or databases: the application does not need it.";
        }

        if ($role->can_change_audit) {
            $warnings[] = "The database role \"{$role->name}\" may change the audit journal table: take UPDATE and DELETE on audit_logs away from it.";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * The migrations that were deployed but not run yet. Forgetting them after an update is dangerous here: a
     * new right or action that profiles receive through a migration would be missing, and users would be refused.
     * It needs the database, so only the command asks it (at every deployment), not every web request.
     *
     * @return list<string>
     */
    public function pendingMigrations(): array
    {
        try {
            $migrator = app('migrator');
            $repository = $migrator->getRepository();

            if (! $repository->repositoryExists()) {
                return ['The migrations table does not exist: run php artisan migrate.'];
            }

            $files = array_keys($migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]));
            $pending = array_values(array_diff($files, $repository->getRan()));
        } catch (Throwable) {
            return [];
        }

        return $pending === [] ? [] : [count($pending).' migration(s) not run yet ('.implode(', ', array_slice($pending, 0, 3)).(count($pending) > 3 ? ', …' : '').'): run php artisan migrate, otherwise profiles may miss new rights.'];
    }

    /**
     * Refuse to go on when a setting is dangerous.
     *
     * @throws RuntimeException listing what has to change
     */
    public function abortIfUnsafe(): void
    {
        $errors = $this->run()['errors'];

        if ($errors !== []) {
            throw new RuntimeException("The production configuration is unsafe:\n- ".implode("\n- ", $errors));
        }
    }

    /**
     * The level of each log channel in use.
     *
     * @return array<string, string>
     */
    private function logChannels(): array
    {
        $default = (string) config('logging.default');
        $names = config("logging.channels.{$default}.driver") === 'stack'
            ? (array) config("logging.channels.{$default}.channels")
            : [$default];
        $levels = [];

        foreach ($names as $name) {
            $level = config("logging.channels.{$name}.level");

            if (is_string($level)) {
                $levels[(string) $name] = $level;
            }
        }

        return $levels;
    }
}
