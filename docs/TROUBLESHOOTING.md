# Troubleshooting

Start with:

```bat
C:\xampp\php\php.exe artisan about
C:\xampp\php\php.exe artisan migrate:status
```

and read `storage/logs/laravel.log` (or the configured log channel). With
`APP_DEBUG=true` locally, the error page shows the exception and stack trace.

## 1. PHP and Composer

### `php` is not recognized

Use the XAMPP binary directly:

```bat
C:\xampp\php\php.exe artisan --version
```

or add `C:\xampp\php` to the Windows PATH. In Git Bash the path is
`/c/xampp/php/php.exe`.

### Missing PHP extension

Symptoms: Composer platform check failure, `could not find driver`, or image uploads
failing. Enable `extension=pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl`,
`bcmath` and `xml` in `C:\xampp\php\php.ini`, then restart Apache/CLI.

### `composer install` is slow or blocked

Check network/proxy access to Packagist. Use `composer install --no-interaction`. The
project already contains vendored front-end assets, so `npm`/`vite` are **not** needed.

## 2. Database

### `SQLSTATE[HY000] [1045] Access denied`

`.env` credentials are wrong or the MySQL user lacks privileges for the database. Verify
with:

```bat
"C:\xampp\mysql\bin\mysql.exe" -u root -p -e "SELECT 1"
```

Update `DB_USERNAME`/`DB_PASSWORD`, then:

```bat
C:\xampp\php\php.exe artisan config:clear
```

### `SQLSTATE[HY000] [2002] No connection could be made`

MariaDB is not running. Start **MySQL** in the XAMPP Control Panel, or run
`C:\xampp\mysql_start.bat` and leave its console window open. Verify the listener:

```bat
netstat -ano | findstr :3306
"C:\xampp\mysql\bin\mysql.exe" -u root -e "SELECT 1"
```

If it starts and immediately exits, inspect `C:\xampp\mysql\data\mysql_error.log`; the
database must be running before any page, migration or test can use the app.

### `Unknown database 'school_ms'` or `'school_ms_test'`

Create both databases:

```sql
CREATE DATABASE school_ms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE school_ms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Migration fails with a foreign key error (1452) or duplicate key

- Migrations must run in filename order; do not run a later migration manually first.
- `php artisan migrate:fresh` is safe **locally only**; it drops all tables.
- If a partial migration left tables behind, inspect `migrations` and `SHOW TABLES`,
  then fix the schema state rather than editing already-applied migration files.

### Tests fail to reset the database

`RefreshDatabase` needs DROP/CREATE/ALTER rights on `school_ms_test`. Grant them to the
test user, or run tests as a dedicated local user. Never point tests at `school_ms`.

### Key length / index errors on old MySQL

The schema uses utf8mb4. Use MySQL 5.7+ or MariaDB 10.4+ and InnoDB. Very old MySQL
versions may need a smaller `innodb_large_prefix`/index configuration; upgrading is
preferred.

### Live 500: `Unknown column 'admissions.user_id'`

The production database was imported from a schema dump that predates the applicant
portal migrations, so `/admissions` queries a column that does not exist yet.

Without shell access, import `database/sql/applicant_portal_migrations.sql` in
phpMyAdmin (backup first). It adds the three applicant columns to `admissions`, creates
`homework_submissions` and `student_leaves`, and records the three migrations.
Step 1 is one-time: if it reports `#1060 Duplicate column name 'user_id'`, phpMyAdmin
stops the import there. In that case import `database/sql/applicant_portal_migrations_continue.sql`
instead — it creates the two tables, records the migrations and verifies the columns.
With shell access, `php artisan migrate --force` does the same.

## 3. Login and access

### 419 Page Expired

The CSRF token or session expired. Clear cookies for the site, ensure `SESSION_DRIVER`
tables exist (`php artisan migrate`), and confirm the session domain/path. If you changed
`APP_KEY`, all old sessions are invalid by design.

This is expected when a login page stays open longer than `SESSION_LIFETIME` (120 minutes)
or when the same form is submitted twice. Laravel now shows a friendly "Your session has
expired" page with a link back to sign in; simply reload and sign in again.

Cookies are scoped per host, so `http://localhost/...` and `http://127.0.0.1:8000/...` keep
separate sessions. Pick one base URL (and matching `APP_URL`) for day-to-day use. If you
access the app through Apache at `http://localhost/dream/public`, set:

```env
APP_URL=http://localhost/dream/public
```

then run `php artisan config:clear`. Otherwise password-reset and notification links
generated from `APP_URL` point at the artisan server instead of Apache.

### Login returns to the form with "credentials do not match"

- Check the account is active and the password is correct.
- Demo accounts exist only after `php artisan migrate --seed` in `local`/`testing`.
- If an administrator reset the password, the new temporary password must be used and
  changed at first sign-in.
- Five failed attempts within a minute trigger throttling; wait for the retry window
  (429/validation message shows seconds).

### "Please choose a new password before continuing"

Administrator-created accounts (and accounts reset through user management) have
`must_change_password = true`. Web users are redirected to `/admin/profile/password`; API
clients receive `403 password_change_required`. Change the password in the web UI, then
request a new API token.

### 403 "Your account is not linked to a school"

Every account must have a `school_id`; the context is derived from it. Recreate the
account with a school. School assignment cannot be changed in the user edit screen.

### Student account receives 403 in `/admin`

This is expected. `/admin` is guarded by `EnsureStaffWorkspace` and only
`config('school.workspace_roles')` (school admin, principal, accountant, teacher) may
enter. Students sign in at `/student`.

### Unlinked student account is signed out of `/student`

The student role requires a linked student record. Student accounts must be created from
**Students → (student) → Student portal access**, never from **Users**; the user form now
rejects the student role. An unlinked account is refused at sign-in, and an existing session
is signed out back to the login form with an explanation instead of a 403 loop. The account's
edit screen also shows a warning: disable or delete it, create the student record, then
create portal access.

The seeded demo student (`student@edunest.test`) is linked and can sign in immediately.

### Teacher can open the dashboard but gets 403 elsewhere

This is expected: the seeded teacher has a limited permission set. Adjust the role matrix
as the School Admin if the role genuinely needs more permissions.

### Last administrator cannot be deactivated

The system protects the final active School Admin. Create or activate another
administrator first.

## 4. UI and assets

### Apache shows a file list at `http://localhost/dream/`

That is the project root, not the web root. The supported URL is
`http://localhost/dream/public/login` (or point a virtual host at the `public/` directory).
A file list means directory listings are on and the Laravel source — including `.env` if it
is named something guessable — is reachable from the browser. The shipped root `.htaccess`
disables listings and returns 403 for `.env`, `composer.*`, `artisan`, logs and SQL dumps;
it does not rewrite `/dream/` into `public/`, because Laravel cannot detect its base URL
behind that rewrite and every route then 404s. If the protection does not apply, enable
`mod_rewrite`/`mod_authz` and `AllowOverride All`. Verify with:

```bat
curl -I http://localhost/dream/.env
rem expect 403 or 404, never 200
```

### Every page 404s on a shared host, but the site root lists `app/`, `config/`, `docs/`

The whole project was uploaded inside `public_html`. Move the application outside the
document root (for example `~/dream_app`), publish only the contents of `public/` into
`public_html`, repoint the three `__DIR__.'/../'` requires in `public/index.php` (see
[INSTALLATION.md §5 "cPanel"](INSTALLATION.md)) and rotate the database password and
`APP_KEY` afterwards, because `.env` was web-reachable.

### The domain root says "403 Forbidden" but `/public/` responds

Same install, one step further: the listing is now blocked (good), but `public_html`
still holds the application instead of only `public/`, so there is no `index.php` at the
root and the server refuses to guess. `/public/` reaching the app proves the upload and
PHP are fine.

Then the usual second error appears on `/public/`:

```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)
```

That is the local XAMPP `.env` on a production server. Create the cPanel database and
user, import `database/exports/school_ms_production.sql`, set `DB_DATABASE`,
`DB_USERNAME` and `DB_PASSWORD`, then `php artisan config:clear`. With `APP_DEBUG=true`
the page also prints the database name, the failing SQL and absolute file paths to every
visitor — set `APP_DEBUG=false` while diagnosing anything that is not your own machine.

Finish with either layout described in
[INSTALLATION.md §5 "Already uploaded into `public_html`?"](INSTALLATION.md): move the app
to `~/dream_app` and publish the contents of `public/`, or uncomment the internal
rewrite in the root `.htaccess` and set `APP_URL` without the `/public` suffix.

### Unstyled pages or 404s for CSS/JS

Assets are served from `public/assets`, not a Vite build directory. Confirm
`public/assets/css/app.css` and `public/assets/vendor/...` exist and that the web server
document root is `public`. Do not run `npm run build`; it is not part of this delivery.

### Chart is missing

Confirm `public/assets/vendor/chartjs/chart.umd.min.js` loads (Network tab) and there are
no console errors. The dashboard chart is progressive enhancement; the page still works
without it.

### Bengali labels do not appear

- Use the topbar language switcher; the choice is stored in the session and on the user.
- Confirm `lang/bn.json` and `lang/bn/*.php` exist.
- Run `php artisan optimize:clear` after editing language files.
- Audit descriptions are stored in the language active when the event occurred; they do
  not retranslate retroactively.

### Logo/avatar uploads return 404 after upload

- Files live in `public/uploads`; ensure the directory is writable by the web user.
- Confirm `APP_URL` matches the URL you browse, especially behind a proxy.
- Do not add a `storage:link` dependency; `school_uploads` points at `public/uploads`.
- If uploads succeed but images 403, check web server rules for `/uploads`.

### Upload rejected as invalid

Only JPEG, PNG and WebP up to 2 MB are accepted (validated by real MIME, not extension).
Raise `upload_max_filesize` and `post_max_size` if the request is truncated, then retry.

### 500 `Class "finfo" not found` while uploading

The host has the `fileinfo` extension disabled, and Flysystem's stock local disk builds a
`finfo` instance as soon as an upload is written. Prefer enabling `fileinfo` (cPanel →
Select PHP Version → Extensions). If that is not possible, keep the `school_uploads` disk
on the `school_local` driver registered in `AppServiceProvider`; it supplies
`App\Support\ExtensionMimeTypeDetector`, which reads the image header and file extension
without `finfo`. Clear the config cache after changing `config/filesystems.php`.

### Sidebar/topbar layout looks broken on a phone

The admin shell is responsive; tables scroll horizontally on narrow viewports. Test at
360–430 px width. If a fixed header appears mid-page in a full-page screenshot, that is a
screenshot artifact, not a layout bug.

## 5. Caching and configuration

After changing `.env`, routes, views or config in a cached environment:

```bat
C:\xampp\php\php.exe artisan optimize:clear
```

Use `php artisan optimize` only for production deploys. A stale `bootstrap/cache/config.php`
can mask `.env` changes; `config:clear` removes it.

If views fail to compile:

```bat
C:\xampp\php\php.exe artisan view:clear
C:\xampp\php\php.exe artisan view:cache
```

Permission errors on `storage/` or `bootstrap/cache/`:

- Windows/XAMPP: ensure the Apache service user can write.
- Linux: `chown -R www-data:www-data storage bootstrap/cache`, directories `775`, files
  `664`.

## 6. Mail, notifications and queues

### Password reset email never arrives

Local default is `MAIL_MAILER=log`. Open `storage/logs/laravel.log` and search for the
reset URL. Configure SMTP in `.env` for real delivery. The UI intentionally shows the same
confirmation whether or not the email exists.

### Welcome notification missing

The notification is stored in the `notifications` table for the new user and appears in
their bell menu after sign-in. Email delivery depends on the notification channel and
mail configuration.

### Queued jobs do not run

Phase 1 does not require a worker. If you enable queued work, run:

```bat
C:\xampp\php\php.exe artisan queue:work --tries=3
```

and ensure the `jobs` table exists. In production use Supervisor/systemd and restart
workers after each deploy.

## 7. API

### 401 Unauthenticated

Send `Authorization: Bearer <token>` and `Accept: application/json`. Tokens are revoked on
logout, deactivation and password changes.

### 403 on an API call

The authenticated account must belong to the school. The server ignores any
`X-School-Id` header; there is no way to select another school. Check that the token
belongs to an active account with a `school_id`.

### 422 for a login that works on the web

Check the email is lowercase/trimmed, the account is active, and the password has not been
reset. API and web use the same credentials.

### 429 Too Many Requests

Login is limited to 5/min per email+IP; authenticated API traffic to 60/min per user.
Wait for `Retry-After` or clear the rate limiter in local development by restarting the
cache (`php artisan cache:clear`).

## 8. Tests

### `Tests: ... failed` after code changes

Run the focused test first:

```bat
C:\xampp\php\php.exe artisan test --filter=UserManagementTest
```

Check whether the failure is a real regression, a stale config cache
(`php artisan config:clear`), or leftover data. Tests use `RefreshDatabase`, so they should
not depend on seeded demo rows unless they seed explicitly.

### Tests are slow on first run

The first API login test performs bcrypt hashing; `phpunit.xml` sets `BCRYPT_ROUNDS=4`.
First-run database creation also costs a few seconds. Later tests reuse the migrated test
database.

### `php artisan test` cannot connect to MySQL

The test environment uses `school_ms_test` from `phpunit.xml`, regardless of `.env`.
Create it and grant privileges. Do not change the test suite to SQLite: the schema uses
MySQL-specific index names and decimal semantics that should be exercised for real.

## 9. Fresh local reset

Only when you can discard all local data:

```bat
C:\xampp\php\php.exe artisan optimize:clear
C:\xampp\php\php.exe artisan migrate:fresh --seed
```

This recreates the schema and demo data. It does **not** delete files in
`public/uploads`; remove those manually if you want a completely clean state.

## 10. Where to look

| Symptom | First place |
| --- | --- |
| 500 error | `storage/logs/laravel.log` |
| Wrong data visible | School scope, policy, `SchoolScope` |
| Login/CSRF issue | `sessions` table, `.env` session keys, cookies |
| Missing styles/images | `public/assets`, `public/uploads`, web server docroot |
| Failed tests | `school_ms_test`, `phpunit.xml`, recent migrations |
| Slow queries (future modules) | `DB::listen`, `EXPLAIN`, indexes in migrations |
