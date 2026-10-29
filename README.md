# Lorine

Lorine is a Laravel 12 workspace application with project/task management, daily activities, reports, attendance, leave, private documents, announcements, payroll, invitations, audit history, and role-based workspace access.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel and PDO MySQL
- Composer 2
- MySQL 8+ or a compatible MySQL/MariaDB server
- Node.js and npm for frontend assets
- Git

The test suite also uses the SQLite PHP extension.

## Download the project

Clone the repository and enter the project directory:

```bash
git clone <REPOSITORY_URL> lorine
cd lorine
```

Replace `<REPOSITORY_URL>` with the repository URL provided by your team.

If the project is already on your computer, pull the latest changes:

```bash
cd lorine
git status
git pull origin main
```

If your team uses another branch, replace `main` with that branch. Review local changes before pulling so that work in progress is not overwritten.

## Install dependencies

Install PHP and JavaScript dependencies:

```bash
composer install --no-interaction --prefer-dist
npm ci
```

For a development machine where `package-lock.json` is intentionally being changed, use `npm install` instead of `npm ci`.

## Configure the environment

Create the environment file if it does not already exist:

```bash
cp .env.example .env
```

On Windows PowerShell, use:

```powershell
Copy-Item .env.example .env
```

Edit `.env` and set the application URL and database credentials. The default local database settings are:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lorine
DB_USERNAME=root
DB_PASSWORD=
```

Create the empty `lorine` database in MySQL/MariaDB before running migrations. Do not commit `.env`, application keys, passwords, database dumps, or private storage files.

Generate an application key when `APP_KEY` is empty:

```bash
php artisan key:generate
```

The default local configuration uses file sessions/cache, the private filesystem disk, database queues, and `MAIL_MAILER=log`. Local email is written to the Laravel log rather than delivered externally.

## Run migrations and seed local data

On a fresh database:

```bash
php artisan migrate
php artisan db:seed --class=DemoWorkspaceSeeder
```

The demo account is:

```text
Email:    admin@meridian.test
Password: DemoAdmin!2026
```

Change the demo credentials with `DEMO_ADMIN_EMAIL` and `DEMO_ADMIN_PASSWORD` in `.env` before seeding for the first time.

For an existing database, make and verify a backup first, then run:

```bash
php artisan lorine:backup-workspace
php artisan migrate --no-interaction
```

Never use `php artisan migrate:fresh` on a database that contains data you need to keep.

The current workspace modules are added by migrations `2026_10_29_000003` through `2026_10_29_000008`. If a migration stopped part way through and `php artisan migrate:status` still shows it as **Pending**, inspect the database before retrying. A failed MySQL DDL migration can leave some of its tables behind. On a disposable development database, the simplest recovery is:

```bash
php artisan migrate:fresh
php artisan db:seed --class=DemoWorkspaceSeeder
```

On a database with data, back it up and remove only the incomplete migration's orphan tables after confirming they contain no required data. Then run `php artisan migrate` again.

## Start the application

Build frontend assets:

```bash
npm run build
```

Start the Laravel server:

```bash
php artisan serve
```

Open [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login).

During frontend development, run Vite in a second terminal:

```bash
npm run dev
```

Run the database queue worker in another terminal when using invitations or other queued jobs:

```bash
php artisan queue:work database --tries=3 --timeout=60
```

## Document scanning

Private document uploads are stored in quarantine until scanning succeeds. Set `PRIVATE_SCANNER_BINARY` to the approved scanner executable before enabling document downloads in an environment that requires malware scanning. If the scanner is unavailable or returns a failure, the document remains quarantined.

## Verify the installation

Run the backend checks:

```bash
composer lint
composer test
php artisan route:list
```

Check migration state with:

```bash
php artisan migrate:status
```

All required migrations should show `Ran` before using the attendance, leave, documents, reports, or payroll features.

## Updating an existing checkout

Use this sequence when pulling a new version:

```bash
git status
git pull origin main
composer install --no-interaction --prefer-dist
npm ci
php artisan migrate --no-interaction
npm run build
php artisan optimize:clear
```

Restart `php artisan queue:work` after application or job code changes. Review migration output and run `composer test` after updating.

## Useful commands

```bash
php artisan about
php artisan migrate:status
php artisan db:seed --class=DemoWorkspaceSeeder
php artisan queue:failed
php artisan queue:retry all
php artisan optimize:clear
```

## Project documentation

- [Backend setup and operational notes](docs/backend-setup.md)
- [Workspace access contract](docs/contracts/workspace-access.md)
- [Development tracker](claude_codex.md)

## License

This project is licensed under the MIT license.

