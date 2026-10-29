# Backend foundation and Laravel 12 handoff

Owner: Codex. Frontend owner: Claude. Related tasks: P0.01, P0.02, P0.04, P0.05, P0.07.

## Current status, 28 October 2026

The database connectivity blocker below is resolved. All 10 previous migrations had been applied by the user; after a fresh verified SQL backup, Codex applied the two additional identity/audit and queue migrations. User model/factory alignment, password hashing, UUID uniqueness, missing legacy rollback methods, private storage and local database-queue/log-mail configuration are implemented. Tests cover full migration rollback/reapply and legacy-data preservation.

Use [Backend setup](backend-setup.md) for current commands, backup evidence and rollback limits, and [Foundation UI contract](contracts/foundation-ui.md) for the frontend handoff. The audit and upgrade sections below preserve the earlier findings; they are not a current blocker list.

## Active framework decision

The user requested Laravel 12 on 26 October 2026. This replaces the Laravel 13 target in the historical master plan. Use PHP 8.2+ and stable Composer dependencies. Keep the existing application/kernel/provider structure while upgrading its dependencies; no replacement application or frontend scaffold is needed.

Upgrade references reviewed:

- [Laravel 10 upgrade](https://laravel.com/framework/docs/10.x/upgrade): stable dependencies and framework compatibility changes.
- [Laravel 11 upgrade](https://laravel.com/framework/docs/11.x/upgrade): PHP 8.2, Sanctum 4, and compatibility with existing application structure.
- [Laravel 12 upgrade](https://laravel.com/framework/docs/12.x/upgrade): Laravel 12, PHPUnit 11, and Carbon 3.

## Repository audit

- Starting commit: `b63e66f` (initial project commit).
- Starting runtime: PHP 8.2.12 at the existing XAMPP installation; Composer 2.8.11.
- Starting framework: Laravel 9.52.22, with development-version constraints in the original lockfile.
- PHP extensions required by the readiness command are installed, including MySQL and SQLite PDO drivers.
- Original users migration requires UUID, first/last name, employee number and phone, but User and UserFactory expect `name`. This needs a separate data-aware identity migration in P0.04/P1.01.
- Five custom 2026 migrations have empty rollback methods. Audit logs lack timestamps; roles and permissions have no organization scope. These are recorded issues, not automatically repaired migrations.
- Existing token schema lacks `expires_at`, required by the upgraded Sanctum token workflow. An additive migration is supplied; the original migration is preserved.
- The configured MySQL server refused the initial migration-status connection. Existing database contents and applied migrations therefore remain unknown. Do not infer an empty database.
- The local app key was initially absent. It has been generated in the ignored local `.env`, without printing or committing it. Test configuration supplies a separate fixed disposable test key.

## Verified upgrade result (26 October 2026)

- Laravel 12.70.1, Sanctum 4.3.3, PHPUnit 11.5.57 installed; PHP 8.2.12 retained.
- `composer validate --strict --no-check-publish`, platform requirements and package discovery pass. Dependency update reported no security advisories.
- `php vendor/bin/phpunit --do-not-cache-result`: 10 tests, 39 assertions, all passing.
- `php artisan --version` and `php artisan route:list --except-vendor --no-ansi` pass.
- PHP syntax checks and backend `git diff --check` pass.
- Readiness diagnostic confirms runtime/extensions and the local application key. The optional database check reports unavailable; the original MySQL connection was refused. No live schema changes were applied.
- Source archive opens successfully (126 entries, includes Composer lockfile, excludes `.env`). It is not a database backup or completed restore drill.
- A transient Windows paging-file warning occurred during key generation; the subsequent diagnostic loaded all required extensions, including curl, successfully. No system settings were changed.

## Read-only readiness command

```powershell
php artisan lorine:backend-audit
php artisan lorine:backend-audit --database
```

The command emits JSON containing versions, required extension availability, configuration presence/driver names, and optional table counts and migration history. It never prints database passwords, application keys, employee row contents, or raw connection errors. The database option runs SELECT queries only. Inventory counts are not a transactionally consistent backup.

Exit code 0 means the configured runtime target, required extensions, app key and requested database inventory passed. Exit code 1 means a prerequisite is missing or not checked. This is a foundation diagnostic, not a production security/release certification. In particular, calling without `--database` cannot declare complete readiness.

If the database is unavailable, preserve the existing schema and resolve connectivity before migrations. A successful inventory still requires a verified backup before modifying existing data.

## Installation and verification

```powershell
composer install --no-interaction --prefer-dist
php artisan --version
php vendor/bin/phpunit --do-not-cache-result
composer validate --strict
composer check-platform-reqs
```

For a fresh local environment, copy `.env.example` only if `.env` does not exist, set the intended local database settings, and run `php artisan key:generate` only when the local key is absent. Never rotate an existing key as a troubleshooting step.

Default PHPUnit runs force `APP_ENV=testing`, an in-memory SQLite connection, an empty database URL, and a disposable encryption key. The test bootstrap refuses cached configuration or a non-isolated database. MySQL concurrency verification will need a separate explicitly isolated test configuration later; SQLite results do not certify MySQL locking behavior.

The upgrade tests cover application routes, unauthenticated Sanctum requests, CORS, read-only inventory/redaction, and additive token migration preservation/rollback. They do not claim completion of staff authentication or organization isolation.

## Data preservation and rollback

- A tracked-source snapshot is stored locally at `storage/app/backend-baseline-b63e66f.zip`, ignored by Git. It contains the starting tracked source, not `.env`, untracked plans, dependencies, uploaded files, or a database backup.
- Verify the archive can be opened before relying on it. Preserve current uncommitted work separately before any restoration.
- No application database migration should be run until connectivity, schema inventory and backup evidence are available.
- To review the previous source, extract the archive into a separate directory. Do not overwrite the active shared checkout or use a destructive Git reset.
- Dependency rollback means restoring matching prior Composer manifest/lock files in an isolated checkout and running `composer install`. Do not deploy a mixture of old code and new dependencies.
- The new token migration adds a nullable indexed expiration column. Its rollback removes only that column and index; token rows remain. Rolling back loses expiration metadata, so preserve it in a database backup first.
- No restore drill or database backup is claimed complete while the database is unavailable.

## Claude handoff

Continue owning Blade, Bootstrap, Vite, Alpine and browser presentation. The backend upgrade preserves existing routes and the legacy Laravel application structure; no frontend files are changed in this work. Do not build against Laravel 13-only APIs. Business-module route/view contracts remain pending Phase 1 design.

## Remaining foundation work

Next: organization/membership foundations, scoped authorization and real authentication. Frontend integration, a hosted CI run, production-database compatibility and independent review remain pending. The parent Phase 0 tasks stay open until their full acceptance requirements pass; current setup and partial-CI details are in backend-setup.md.
