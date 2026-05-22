# Tender Proposal & Quotation Assignment Tracker

A Laravel + MySQL/MariaDB web app for tracking tender proposal and quotation assignments, due dates, reminders, documents, department submissions, and email notifications.

## Stack

- Laravel 13
- PHP 8.3+
- MySQL or MariaDB
- Blade + Tailwind CSS
- Vite

## Features

- Login-only access with no public registration.
- Roles: `super_admin`, `manager`, `director`, `reception`, and `department_user`.
- Department-scoped visibility for assigned department users.
- Tender proposal and quotation dashboards.
- Tender due dates with optional site visit and clarification window dates.
- Assignment center with due dates, instructions, and optional SMTP email notices.
- Department submissions with draft/finished status and technical/financial/supporting documents.
- Reviewer inbox for configured submission recipients.
- Document upload/download per tender proposal, quotation, or submission.
- Manual and scheduled due reminder emails.
- Admin screens for users, departments, and private app settings.

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

For a Gmail or Google Workspace sender, use one dedicated outgoing mailbox and a Google app password:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-sender@example.com
MAIL_PASSWORD=your-private-app-password
MAIL_FROM_ADDRESS=your-sender@example.com
MAIL_FROM_NAME="Datamatics Eswatini Notifications"
```

The email signature is controlled through private `.env` values so real contact details are not committed:

```env
MAIL_SIGNATURE_COMPANY=
MAIL_SIGNATURE_PHONE=
MAIL_SIGNATURE_LANDLINE=
MAIL_SIGNATURE_EMAIL=
MAIL_SIGNATURE_WEBSITE=
MAIL_SIGNATURE_ADDRESS=
MAIL_SIGNATURE_IMAGE_PATH=
```

Set `MAIL_SIGNATURE_IMAGE_PATH=images/email-signature.png` if you prefer one full signature image stored privately in `public/images`. Leave it blank to use the built-in HTML signature block.

Send a development test email with:

```powershell
php artisan mail:test
```

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

The test suite covers login, role-scoped visibility, tender proposal creation, submissions, and duplicate-safe reminder logging.
