-- StoreUltra: the PostgreSQL roles of the application (playbook OPS-68 to OPS-70).
--
-- Three roles, none of them a super-user:
--   storeultra_migrator  owns the database and every table; the only one that changes the structure
--                        (migrations, at deployment time). It is never used by the running application.
--   storeultra_app       what the running application connects with: read and write the rows, nothing else.
--                        It owns nothing, so it can neither drop a table nor switch off the database triggers
--                        that keep the audit journal append-only, and it cannot change or delete an audit line.
--   storeultra_backup    read only, for the backups (pg_dump).
--
-- Run it as a super-user, from a machine you trust, with the three passwords in the environment (never on the
-- command line, never in a file of the project). psql 15 or later is needed for \getenv.
--
--   STOREULTRA_MIGRATOR_PASSWORD=... STOREULTRA_APP_PASSWORD=... STOREULTRA_BACKUP_PASSWORD=... \
--   psql -v ON_ERROR_STOP=1 -U postgres -d postgres -f database/postgres/roles.sql
--
-- STOREULTRA_DB names the database (default: storeultra). The script can be run again at any time: it creates
-- what is missing and sets the passwords and the rights again. Run it once before the first migration, then
-- once more after it: the second run applies the rights to the tables the migrations created and takes UPDATE and
-- DELETE on the audit journal away from the application (the database triggers already refuse them; this is the
-- second lock).

\set ON_ERROR_STOP on
\set db storeultra
\getenv env_db STOREULTRA_DB
\if :{?env_db}
    \set db :env_db
\endif

\getenv migrator_password STOREULTRA_MIGRATOR_PASSWORD
\getenv app_password STOREULTRA_APP_PASSWORD
\getenv backup_password STOREULTRA_BACKUP_PASSWORD
\if :{?migrator_password}
\else
    \echo 'STOREULTRA_MIGRATOR_PASSWORD is not set: nothing was done.'
    \quit
\endif
\if :{?app_password}
\else
    \echo 'STOREULTRA_APP_PASSWORD is not set: nothing was done.'
    \quit
\endif
\if :{?backup_password}
\else
    \echo 'STOREULTRA_BACKUP_PASSWORD is not set: nothing was done.'
    \quit
\endif

-- Keep the passwords out of the server log.
SET log_statement = 'none';

-- 1. The roles (no super-user right, no right to create a database or a role).
SELECT 'CREATE ROLE storeultra_migrator' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'storeultra_migrator') \gexec
SELECT 'CREATE ROLE storeultra_app' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'storeultra_app') \gexec
SELECT 'CREATE ROLE storeultra_backup' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'storeultra_backup') \gexec

ALTER ROLE storeultra_migrator WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD :'migrator_password';
ALTER ROLE storeultra_app WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD :'app_password';
ALTER ROLE storeultra_backup WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD :'backup_password';

-- 2. The database, owned by the migrator, closed to everyone else.
SELECT format('CREATE DATABASE %I OWNER storeultra_migrator', :'db') WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'db') \gexec

REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
GRANT ALL ON DATABASE :"db" TO storeultra_migrator;
GRANT CONNECT ON DATABASE :"db" TO storeultra_app, storeultra_backup;

\connect :"db"

-- 3. The schema: only the migrator creates things in it.
ALTER SCHEMA public OWNER TO storeultra_migrator;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO storeultra_app, storeultra_backup;

-- 4. The application reads and writes rows. Nothing else: no CREATE, no DROP, no ALTER, no TRIGGER, no TRUNCATE.
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO storeultra_app;
GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO storeultra_app;
ALTER DEFAULT PRIVILEGES FOR ROLE storeultra_migrator IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO storeultra_app;
ALTER DEFAULT PRIVILEGES FOR ROLE storeultra_migrator IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO storeultra_app;

-- The audit journal is append-only: the application may add lines and read them, never change or remove one.
-- (Its chain head, audit_chain, is updated with every line and stays writable.)
SELECT 'REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM storeultra_app' WHERE to_regclass('public.audit_logs') IS NOT NULL \gexec

-- 5. The backup reads everything and writes nothing.
GRANT SELECT ON ALL TABLES IN SCHEMA public TO storeultra_backup;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO storeultra_backup;
ALTER DEFAULT PRIVILEGES FOR ROLE storeultra_migrator IN SCHEMA public GRANT SELECT ON TABLES TO storeultra_backup;
ALTER DEFAULT PRIVILEGES FOR ROLE storeultra_migrator IN SCHEMA public GRANT SELECT ON SEQUENCES TO storeultra_backup;

-- On a cluster that holds other databases, PostgreSQL lets every role connect to them by default (the right is
-- given to PUBLIC). Closing that is a decision for each database, so it is not done here:
--   REVOKE CONNECT ON DATABASE <other database> FROM PUBLIC;

-- A database that already holds tables created by another role (for example by "postgres") must first hand them to
-- the migrator, with a backup taken before:   REASSIGN OWNED BY <old owner> TO storeultra_migrator;
-- It is not done here: it changes the owner of everything the old role owns, in every database.

\echo
\echo 'Roles and rights of the database' :db ':'
SELECT rolname, rolsuper AS superuser, rolcreatedb AS createdb, rolcreaterole AS createrole
FROM pg_roles WHERE rolname LIKE 'storeultra\_%' ORDER BY rolname;
