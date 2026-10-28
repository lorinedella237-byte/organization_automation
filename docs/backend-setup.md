# Backend setup (Laravel 12)

## Prerequisites

Use PHP 8.2+, Composer 2 and a configured MySQL-compatible database. PHP needs the extensions checked by `php artisan lorine:backend-audit`, plus PDO SQLite for the default test suite. The current developer machine uses XAMPP MariaDB 10.4.28; this local check does not replace validation against the intended production MySQL version.

## Fresh checkout

1. Run `composer install --no-interaction --prefer-dist`.
2. Copy `.env.example` to `.env` only when `.env` does not already exist. Set the intended local database name and credentials. The example uses `lorine`; an existing checkout's configured database must not be silently renamed.
3. Create that empty database using your database administrator/client. The application does not create database servers or schemas implicitly.
4. Run `php artisan key:generate` only if the local application key is absent. Preserve existing keys and never commit `.env`.
5. Run `php artisan lorine:backend-audit --database` and review existing tables. For an existing database, take and verify a schema/data backup before migrations. Do not use `migrate:fresh` or reset an existing database.
6. Run `php artisan migrate --no-interaction`.
7. Run `composer lint` and `composer test`.
8. For the UI, use Claude's frontend setup/build instructions. The current frontend scripts support `npm ci` and `npm run build`; backend tests deliberately do not require Vite or built assets.
9. Run `php artisan serve`. For background jobs, run `php artisan queue:work database --tries=3 --timeout=60` in a separate terminal. The queue's retry interval is 90 seconds. Stop and restart workers after code changes.

Local defaults are `APP_NAME=LORINE`, `FILESYSTEM_DISK=private`, `QUEUE_CONNECTION=database` and `MAIL_MAILER=log`. Email is written to the application log, not sent externally. No worker or production daemon is installed by these changes.

## Current foundation behavior

### Demo admin seeder

After migrations, run `php artisan db:seed --class=AdminSeeder`. On local/testing installations, `php artisan db:seed` also calls this seeder automatically (including `php artisan migrate --seed`).

Default local credentials:

- Email: `admin@meridian.test`
- Password: `DemoAdmin!2026`
- Display name: Demo Admin. There is no separate username.

The seeder creates a verified user with a generated UUID and hashed password. Rerunning it preserves an existing account with the same email, including any changed password or verification state. It does not reset passwords or promote existing users.

Override the credentials with `DEMO_ADMIN_EMAIL` and `DEMO_ADMIN_PASSWORD` in `.env` before the first seed. If configuration is cached, rebuild it after changing those values. On hosted installations outside local/testing, ordinary `db:seed` skips this account; explicitly run `php artisan db:seed --class=AdminSeeder --force` with a private `DEMO_ADMIN_PASSWORD` of at least 12 characters. The published local password is rejected there.

**Current limitation:** this seeds the intended demo administrator's identity only. Login endpoints, organization memberships and administrator authorization remain pending Phase 1. The preview login form cannot authenticate yet; the seeder does not grant a global authorization bypass. Once membership/RBAC exists, extend this seeder to provision the demo organization and its scoped administrator role.

### Schema and infrastructure

- User records store `first_name`, optional `middle_name`, `last_name`, optional `phone`, email and a hashed password. UUIDs are generated server-side and unique.
- `name` is a computed display attribute, not a writable database column. Controllers should validate and write the individual name fields.
- The legacy global `employee_number` column remains nullable to preserve existing values. Do not assign employer metadata to new global identities. Moving employee numbers into organization memberships belongs to Phase 1.
- The old `permission` table is renamed to `permissions` without deleting records. Configurable scoped roles and organization authorization are not implemented yet.
- Audit rows have a nullable indexed `created_at`; historical dates are left unknown rather than fabricated. The audit-writing service is still pending.
- Database queues have `jobs`, `job_batches`, and the existing `failed_jobs` table. Jobs dispatched on the database connection wait until their transaction commits; rollback discards pending dispatches.
- The private disk lives at `storage/app/private`, throws on write failure, and is not linked beneath `public/storage`. Authorized document uploads/downloads and malware scanning will be implemented with the documents module.

## Migration and rollback rules

All schema changes are migrations. Only missing rollback methods were repaired in the five original custom migrations; their `up()` definitions are unchanged.

The identity alignment migration changes phone/legacy employee number to nullable, adds UUID uniqueness, adds the audit timestamp and renames the permission table. Its rollback refuses to run if new identities have null legacy required fields. Do not invent employee numbers or phone numbers to force a rollback; reconcile explicitly or restore a verified backup into a separate database.

Queue-table rollback deletes queued jobs and batch metadata. Audit timestamp and token-expiry rollback remove their metadata. These operations require backups and an explicit rollback decision; the successful automated rollback tests apply only to disposable in-memory databases.

Before the 28 October changes, the configured database had 10 applied migrations and zero business rows. A fresh SQL backup was saved in ignored local storage at `storage/app/backups/before-foundation-20261028-181448.sql`, SHA-256 `f99982558357f3bc281ae7f6be7ce53ae4666be5422e5321483c4b9313b6913d`. Table definitions and migration inserts were checked. This is not a completed production restore drill. The two new migrations are applied as batch 2; there are now 12 applied migrations.

## Verification and CI

`composer test` runs isolated in-memory SQLite tests, including forward migrations, rollback/reapply, legacy row preservation, UUID uniqueness, hashing, queue transaction behavior/consumption, private storage, framework boot and anonymous API denial. It cannot prove MySQL concurrency behavior.

`composer lint` performs PHP syntax checks across application/configuration/migration/route/test/tooling files. It does not rewrite files or claim full static type analysis.

`.github/workflows/backend.yml` runs Composer validation/install/platform checks, PHP lint and the backend tests on PHP 8.2. It rejects committed local environment files and backup paths; that guard is not a comprehensive secret scanner. References: [checkout action](https://github.com/actions/checkout), [PHP setup action](https://github.com/shivammathur/setup-php). The workflow is authored and parsed locally; a hosted run has not been triggered from this session.

Real login, invitation, suspension, tenant-isolation and permission tests will be added with Phase 1. Frontend builds/browser checks remain Claude's responsibility.
