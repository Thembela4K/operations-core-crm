# Project & Quotation Assignment Tracker

A Laravel + MySQL/MariaDB web app for tracking project and quotation assignments, ratings, deadlines, reminders, documents, CSV imports/exports, and email notifications.

## Stack

- Laravel 13
- PHP 8.3+
- MySQL or MariaDB
- Blade + Tailwind CSS
- Vite

## Features

- Login-only access with no public registration.
- Roles: `super_admin`, `manager`, and `department_user`.
- Department-scoped visibility for department users.
- Project and quotation dashboards.
- Project and quotation CRUD.
- Assignment center with optional SMTP email notices.
- Document upload/download per project or quotation.
- CSV import/export for projects and quotations.
- Manual and scheduled due reminder emails.
- Admin screens for users and departments.

## Security Note

This is a public repository. Do not commit real `.env` files, passwords, production URLs, SMTP credentials, staff emails, exported databases, logs, or private documents.

The committed seed data is intentionally generic. Use your private local database or production database for real organization data.

## Local Setup

Install PHP 8.3+, Composer, Node.js, and MySQL/MariaDB.

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Create a local MySQL database:

```sql
CREATE DATABASE project_quotation_assignment_tracker;
```

Update `.env` with your private local values, then run:

```powershell
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

## Admin User

Set private admin values in your local `.env` before seeding:

```env
ADMIN_NAME=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

The app does not expose public registration. Admins create users from inside the application.

## Email

Set SMTP values privately in `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

Assignment and reminder emails are logged in the database whether they succeed or fail.

## Reminders

Manual reminder sending is available from the Reminders page.

For scheduled reminders, configure cron to run Laravel's scheduler:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

The app schedules `reminders:send-due` daily at 08:00.

## Hostinger Manual Upload

1. Run `composer install --no-dev --optimize-autoloader`.
2. Run `npm install` and `npm run build`.
3. Upload the Laravel project files to Hostinger.
4. Point the domain document root to Laravel's `public` directory.
5. Create a Hostinger MySQL/MariaDB database.
6. Configure production `.env` privately on Hostinger.
7. Run:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If SSH is unavailable, run equivalent commands locally where possible and upload the generated files, then use Hostinger tools to configure the database.

## Tests

```powershell
php artisan test
```

The test suite covers login, role-scoped visibility, project creation, scoring formulas, and duplicate-safe reminder logging.
