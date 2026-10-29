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

Local defaults are `APP_NAME=Meridian`, `FILESYSTEM_DISK=private`, `QUEUE_CONNECTION=database` and `MAIL_MAILER=log`. Email is written to the application log, not sent externally. No worker or production daemon is installed by these changes.

## Current foundation behavior

### Demo admin seeder

After migrations, run `php artisan db:seed --class=DemoWorkspaceSeeder` for the demo account plus its organization and owner membership. On local/testing installations, `php artisan db:seed` calls this automatically (including `php artisan migrate --seed`). `AdminSeeder` remains available for an identity only. Repeated workspace seeding preserves existing memberships and grants.

Default local credentials:

- Email: `admin@meridian.test`
- Password: `DemoAdmin!2026`
- Display name: Demo Admin. There is no separate username.

The seeder creates a verified user with a generated UUID and hashed password. Rerunning it preserves an existing account with the same email, including any changed password or verification state. It does not reset passwords or promote existing users.

Override the credentials with `DEMO_ADMIN_EMAIL` and `DEMO_ADMIN_PASSWORD` in `.env` before the first seed. If configuration is cached, rebuild it after changing those values. On hosted installations outside local/testing, ordinary `db:seed` skips this account; explicitly run `php artisan db:seed --class=AdminSeeder --force` with a private `DEMO_ADMIN_PASSWORD` of at least 12 characters. The published local password is rejected there.

Open `/login` on your application server (normally `http://127.0.0.1:8000/login` with `php artisan serve`) and sign in with these credentials. Successful login opens the protected account home at `/home`. Use **Sign out** to end the session. The local `/dev/auth/login` preview also submits to the real `/login` endpoint.

Login renews the session ID and CSRF token. Logout is POST-only and invalidates the session and remember-me cookie. Five failed attempts for one email/IP pair cause a 60-second lockout; an additional IP limit permits at most 30 login submissions per minute. Invalid credentials use the same error for unknown emails and wrong passwords. Passwords are never flashed back into the form. This follows Laravel's [session authentication guidance](https://laravel.com/docs/12.x/authentication).

The demo account now owns the `Meridian Demo` workspace. Staff, roles, departments/teams, settings, audit and invitations operate under workspace permissions. Platform provisioning is a separate privilege and gives no routine access to workspace data. Membership suspension and role changes take effect on the next request. See `docs/contracts/workspace-access.md` for the full route/payload contract. Later project, reporting, document, attendance, leave and payroll modules remain pending.

Invitation mail requires the database queue worker described above. With `MAIL_MAILER=log`, accepted queue jobs write the invitation message/link locally; no external email is sent. Invitation tokens are hashed in the invitation table and encrypted in queued job payloads. Mail delivery is retried; exactly-once delivery is not promised.

Workspace operations require verified email. Existing password-reset flows remain intact; password changes now also invalidate previously authenticated browser sessions, in addition to rotating remember tokens. New invited users verify their email when accepting the one-use email invitation. Existing users must prove ownership of their account before joining another organization.

For platform provisioning, a trusted operator can run `php artisan platform:grant EMAIL` for an existing verified identity. That identity may POST `/platform/organizations` with name, slug, timezone, currency and owner_email; the owner joins by invitation. No platform privilege is granted by ordinary demo seeding.

### 29 October migration record

The workspace migration was already applied as batch 3 when this session resumed. A verified SQL backup was taken before the invitation migration: `storage/app/backups/before-foundation-20261029-030814.sql`, SHA-256 `d8b19a2703e495b906f1818e0a3d0c0344759d22160b01b0d8c8402836030850`. MariaDB initially rejected a non-null notification timestamp default; the pending migration was made retryable and given explicit defaults, then completed without deleting rows. The existing invitation table was preserved.

Before updating protected templates with missing personal self-service grants, another backup was taken: `storage/app/backups/before-foundation-20261029-032319.sql`, SHA-256 `441bd3fb942deb30988be3be87155ba32325e14a281baf848830f740a1ac46cc`. That data-only migration preserves broader grants and custom roles. Its rollback does not guess which grants were historical; restore the backup if reverting template data. Workspace/invitation schema rollback refuses when its tables contain business records. No full restore drill is claimed.

### Password reset

Added by Claude on 28 October 2026 while Codex was unavailable. See the cross-lane claim in `claude_codex.md` under P1.03.

Routes, all guest-only:

- GET `/forgot-password`, named `password.request`.
- POST `/forgot-password`, named `password.email`. Rate limited to 6 requests per minute per IP, on top of the broker's own per-address interval in `config/auth.php` (`passwords.users.throttle`, currently 60 seconds).
- GET `/reset-password/{token}`, named `password.reset`. The address travels in the query string so the field can be shown read-only.
- POST `/reset-password`, named `password.store`. Same IP limit.

Behaviour worth knowing:

- **The request form never reveals whether an address is known.** Unknown, known and throttled addresses all produce the same redirect and the same flash message. Accounts here exist by invitation, so confirming an address would confirm that a person works for the organization. The broker's return value is deliberately discarded.
- **Tokens are single use and expire after one hour** (`passwords.users.expire`). A token issued for one address cannot reset another, because the broker matches the token against the hashed record stored for that exact address.
- **Resetting rotates `remember_token`**, which signs out every remember-me session on other devices. Someone resetting a password may be doing it because they lost control of the account.
- **A successful reset does not sign the visitor in.** They are redirected to `/login` with a status message and must enter the new password. A reset link read by somebody else therefore does not hand over a live session.
- **The minimum length is 12 characters**, matching what this document already requires of a hosted demo administrator password. Length is the only composition rule. The two Blade hints state the same number.

Email goes wherever `MAIL_MAILER` points. Locally that is `log`, so the reset link appears in `storage/logs/laravel.log` rather than being delivered; search the log for `reset-password/`.

Tests: `tests/Feature/PasswordResetTest.php`, 11 cases covering delivery, the no-enumeration guarantee, token reuse, cross-address token use, expiry handling, length and confirmation rules, address normalisation, and the guest-only constraint.

Invitation expiry/replay protection, email verification, session invalidation and membership suspension checks are now implemented and tested. Final integration review and production security/recovery checks remain pending.

### Schema and infrastructure

- User records store `first_name`, optional `middle_name`, `last_name`, optional `phone`, email and a hashed password. UUIDs are generated server-side and unique.
- `name` is a computed display attribute, not a writable database column. Controllers should validate and write the individual name fields.
- The legacy global `employee_number` column remains nullable to preserve existing values. Do not assign employer metadata to new global identities. Moving employee numbers into organization memberships belongs to Phase 1.
- The old `permission` table is renamed to `permissions` without deleting records. Organization roles, role grants, delegation checks and staff query scopes now enforce workspace authorization.
- Audit rows have a nullable indexed `created_at`; historical dates are left unknown rather than fabricated. `AuditRecorder` appends workspace mutations with safe metadata; audit routes expose no update/delete action.
- Database queues have `jobs`, `job_batches`, and the existing `failed_jobs` table. Jobs dispatched on the database connection wait until their transaction commits; rollback discards pending dispatches.
- The private disk lives at `storage/app/private`, throws on write failure, and is not linked beneath `public/storage`. Authorized document uploads/downloads and malware scanning will be implemented with the documents module.

## Migration and rollback rules

## Backups and restore

After verifying the target database and before applying migrations, create a private dump with `php artisan lorine:backup-workspace`. The command uses a single-transaction MySQL-compatible dump, writes under `storage/app/backups` by default, and prints only the output path and SHA-256. Set `MYSQLDUMP_BINARY` when the executable is not on `PATH`. Keep the dump outside version control and test it in a disposable database before treating it as a recovery point.

The scheduled command runs daily at 02:45 when Laravel's scheduler is active. A production worker must supervise `php artisan schedule:work` or invoke `php artisan schedule:run` from cron. A restore requires maintenance mode, a verified dump, the database administrator's restore command, then `php artisan migrate --force` and application smoke checks. This repository does not claim a production restore drill.

## Privileged account two-factor authentication

After migrations `2026_10_29_000007_add_two_factor_authentication.php` and `2026_10_29_000008_create_compensation_adjustments.php`, an authenticated user can create a TOTP secret with `POST /security/2fa/begin` and confirm the six-digit authenticator code with `POST /security/2fa/confirm`. The secret is encrypted at rest and never returned after confirmation. In production, workspace routes require a confirmed factor for users holding organization, role or payroll administration grants. Configure the application key before enabling this flow; losing it makes encrypted secrets unrecoverable and requires an account recovery procedure.

Project lifecycle history is added by `2026_10_29_000009_create_project_history.php`. It records project creation, updates, status changes, and membership assignments with tenant, actor, before, and after values.

All schema changes are migrations. Only missing rollback methods were repaired in the five original custom migrations; their `up()` definitions are unchanged.

The identity alignment migration changes phone/legacy employee number to nullable, adds UUID uniqueness, adds the audit timestamp and renames the permission table. Its rollback refuses to run if new identities have null legacy required fields. Do not invent employee numbers or phone numbers to force a rollback; reconcile explicitly or restore a verified backup into a separate database.

Queue-table rollback deletes queued jobs and batch metadata. Audit timestamp and token-expiry rollback remove their metadata. These operations require backups and an explicit rollback decision; the successful automated rollback tests apply only to disposable in-memory databases.

Before the 28 October changes, the configured database had 10 applied migrations and zero business rows. A fresh SQL backup was saved in ignored local storage at `storage/app/backups/before-foundation-20261028-181448.sql`, SHA-256 `f99982558357f3bc281ae7f6be7ce53ae4666be5422e5321483c4b9313b6913d`. Table definitions and migration inserts were checked. This is not a completed production restore drill. The two new migrations are applied as batch 2; there are now 12 applied migrations.

## Verification and CI

`composer test` runs isolated in-memory SQLite tests, including forward migrations, rollback/reapply, legacy row preservation, UUID uniqueness, hashing, queue transaction behavior/consumption, private storage, framework boot and anonymous API denial. It cannot prove MySQL concurrency behavior.

`composer lint` performs PHP syntax checks across application/configuration/migration/route/test/tooling files. It does not rewrite files or claim full static type analysis.

`.github/workflows/backend.yml` runs Composer validation/install/platform checks, PHP lint and the backend tests on PHP 8.2. It rejects committed local environment files and backup paths; that guard is not a comprehensive secret scanner. References: [checkout action](https://github.com/actions/checkout), [PHP setup action](https://github.com/shivammathur/setup-php). The workflow is authored and parsed locally; a hosted run has not been triggered from this session.

Session login/logout tests now cover seeded credentials, protected routes, session/token renewal, remember-me, generic failure messages, validation, per-account/IP rate limits and enforced CSRF. A local HTTP cookie/form smoke check also passed against the configured MariaDB account. Invitation, suspension, tenant-isolation and permission tests remain Phase 1 work. Visual frontend/browser checks remain Claude's responsibility.
