# Installation and Operations

This guide targets the delivered Phase 1 application. No Node.js or Vite build is
required: CSS, Bootstrap, Bootstrap Icons, Chart.js and fonts are vendored under
`public/assets`.

## 1. Requirements

| Component | Minimum | Tested |
| --- | --- | --- |
| PHP | 8.2 | 8.2.12 (XAMPP) |
| Composer | 2.x | 2.x |
| MySQL / MariaDB | MySQL 5.7 / MariaDB 10.4 | MariaDB 10.4.32 |
| PHP extensions | `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl` | XAMPP defaults |
| Web server | Apache/Nginx (or `php artisan serve` locally) | Apache |

The demo path below assumes `C:\xampp\htdocs\dream`. If `php` is not on your PATH, use
`C:\xampp\php\php.exe` (or `/c/xampp/php/php.exe` in Git Bash).

## 2. Local installation (XAMPP, Windows)

### 2.1 Place the code

Copy/clone the project to `C:\xampp\htdocs\dream`. The document root for production must
be the `public` directory, not the project root.

### 2.2 Install PHP dependencies

```bat
cd C:\xampp\htdocs\dream
composer install
```

If Composer is not installed globally, use `C:\xampp\php\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar install`.

### 2.3 Create the environment file

```bat
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

Edit `.env`:

```dotenv
APP_NAME="EduNest School Management"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Dhaka

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_ms
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log

DEMO_SEED_PASSWORD="Demo@1234"
```

`MAIL_MAILER=log` writes password reset and welcome mail to
`storage/logs/laravel.log`, which is convenient locally.

Choose one base URL and keep `APP_URL` in sync with it. `artisan serve` uses
`http://127.0.0.1:8000`; a plain XAMPP install that browses `htdocs/dream` uses
`http://localhost/dream/public`. Cookies are per-host, so switching between `localhost`
and `127.0.0.1` starts a separate session (an old form then shows 419 Page Expired).

### 2.4 Create databases

In phpMyAdmin or the MySQL console:

```sql
CREATE DATABASE school_ms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE school_ms_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

`school_ms_test` is used by PHPUnit. Both databases use InnoDB so foreign keys and row
locks behave correctly.

### 2.5 Migrate and seed

```bat
C:\xampp\php\php.exe artisan migrate --seed
```

This:

- creates every table for all planned phases;
- seeds roles and the permission matrix;
- in `local`/`testing` only, seeds one demo school with its staff/student users,
  academic data, attendance, fee invoices and a published First Term Examination
  with marks and report cards.

If migrations already ran and only demo data is missing:

```bat
C:\xampp\php\php.exe artisan db:seed
```

### 2.6 Run the application

```bat
C:\xampp\php\php.exe artisan serve
```

Open <http://127.0.0.1:8000>. Apache users may instead browse
`http://localhost/dream/public` with the virtual host configured as described in §5.

Uploaded logos/avatars are written to `public/uploads`; ensure the web server can write
there. No `storage:link` is required for these public assets.

The repository includes a root `.htaccess` as a safety net for XAMPP setups where
`htdocs/dream` itself is reachable from the browser. It disables directory listings and
denies `.env`, `composer.*`, `package*.json`, `artisan`, `phpunit.xml`, logs and SQL dumps.
It deliberately does **not** rewrite `http://localhost/dream/` into `public/`: when a
sub-directory install is internally rewritten, Laravel cannot derive the right base URL,
so `/dream/login` and every other route 404s while the assets still load. Browse the
`public/` path directly (`http://localhost/dream/public/`) or, better, point the document
root at `public/` as described in §5. An opt-in rewrite block is included as a comment in
the file; copy it only if you understand the base-URL consequence.

This fallback is not a substitute for a correct `DocumentRoot` — with the project root
exposed, source files remain readable even when the sensitive filenames above are blocked.

### 2.7 Run the tests

```bat
C:\xampp\php\php.exe artisan test
```

Tests use `school_ms_test`, `RefreshDatabase`, array cache/sessions, sync queues and the
array mail transport (`phpunit.xml`). The MySQL user must be able to create/drop tables in
that database. Current result: **112 tests / 586 assertions passing**.

## 3. Demo accounts (local only)

All demo accounts use `DEMO_SEED_PASSWORD` (`Demo@1234` by default). They exist only when
`APP_ENV` is `local` or `testing`; never use them in production.

| Email | Role |
| --- | --- |
| `admin@edunest.test` | School Admin |
| `principal@edunest.test` | Principal |
| `accountant@edunest.test` | Accountant |
| `teacher@edunest.test` | Teacher |
| `student@edunest.test` | Student portal (linked to the first demo student) |

Seeded content: one school (EduNest Model School, ENS) with 28 students, 10 classes,
3 sessions, 6 teachers, 2 employees, fee invoices/receipts and a published First Term
Examination with marks and result snapshots. Audit activity is seeded for the dashboard.

To regenerate demo data from scratch:

```bat
C:\xampp\php\php.exe artisan migrate:fresh --seed
```

> This drops all data in `school_ms`. Never run it against a database you need to keep.

## 4. Creating the first production administrator

Do **not** ship demo accounts. After migrating and seeding permissions:

```bat
C:\xampp\php\php.exe artisan school:admin head@yourschool.edu.bd --name="Head Teacher"
```

The command:

- creates the single school row when none exists (prompts for name/code, or pass
  `--school-name` and `--school-code`);
- reuses the existing school on later runs;
- generates a random temporary password when `--password` is omitted and prints it once;
- assigns the `school_admin` role and forces a password change at first sign-in;
- refuses duplicate emails and writes an audit entry.

Create all other accounts through `Users` in the `/admin` workspace. The only
self-service screen is applicant registration at `/register`, which can create
`student` accounts and nothing else; there is no staff sign-up or school
provisioning screen.

## 5. Web server configuration

### Apache

Point the virtual host document root at `public`:

```apache
<VirtualHost *:80>
    ServerName edunest.local
    DocumentRoot "C:/xampp/htdocs/dream/public"

    <Directory "C:/xampp/htdocs/dream/public">
        AllowOverride All
        Require all granted
    </Directory>

    # Uploaded files must never execute as PHP
    <Directory "C:/xampp/htdocs/dream/public/uploads">
        php_admin_flag engine off
        <FilesMatch "\.(php|phtml|phar)$">
            Require all denied
        </FilesMatch>
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite` and restart Apache. Add `127.0.0.1 edunest.local` to the hosts file
if using a local domain.

### Nginx

```nginx
server {
    listen 80;
    server_name edunest.example.com;
    root /var/www/edunest/public;

    index index.php;
    charset utf-8;

    add_header X-Content-Type-Options nosniff;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ^~ /uploads/ {
        location ~ \.php$ { deny all; }
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Never expose the project root, `.env`, `storage` or `database` directories.

### cPanel (shared hosting, LiteSpeed/Apache + MySQL)

Upload the project **one level above** `public_html`, then publish only `public/`:

1. cPanel → File Manager → home directory → create `dream_app` and upload the whole
   project into it (`app`, `bootstrap`, `config`, `database`, `lang`, `resources`,
   `routes`, `storage`, `vendor`, `artisan`, `composer.json`, `.env`, …).
2. Copy the **contents** of `dream_app/public` into `public_html` (including the hidden
   `.htaccess`). `public_html` must contain `index.php`, `assets/`, `favicon.svg` and
   `.htaccess` — not a `public/` folder and not the Laravel source.
3. Edit `public_html/index.php` and point the three requires at the application folder:

   ```php
   if (file_exists($maintenance = __DIR__.'/../dream_app/storage/framework/maintenance.php')) {
   require __DIR__.'/../dream_app/vendor/autoload.php';
   $app = require_once __DIR__.'/../bootstrap/app.php';   // becomes ../dream_app/bootstrap/app.php
   ```

4. cPanel → MySQL® Databases → create a database, a user and a strong password, then add
   the user to the database with **All Privileges**. Import
   `database/exports/school_ms_production.sql` (schema + roles/permissions only, no mock
   data) with cPanel → phpMyAdmin → Import.
5. Edit `dream_app/.env`:

   ```dotenv
   APP_NAME="Dream Roots Millenium School"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://example.edu
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=cpaneluser_schoolms
   DB_USERNAME=cpaneluser_school
   DB_PASSWORD=strong-password
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
   ```

6. cPanel → Terminal (or SSH) and run:

   ```bash
   cd ~/dream_app
   php artisan key:generate --force
   php artisan storage:link
   php artisan migrate --force          # no-op after the SQL import; verifies pending work
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan school:admin             # creates the first school and administrator
   ```

7. If Terminal is unavailable, `key:generate` can be run by adding a one-off route, but
   the safer alternative is to generate a key locally (`php artisan key:generate --show`)
   and paste it into `.env` as `APP_KEY=base64:...`.

8. Shared hosting has no Supervisor, so drain the database queue from cron instead of a
   persistent worker (cPanel → Cron Jobs, every minute):

   ```bash
   cd ~/dream_app && php artisan queue:work database --stop-when-empty --tries=3 --timeout=90 >> storage/logs/queue.log 2>&1
   ```

   Without a worker, in-app notifications still appear immediately; only browser push is
   delayed. Also add the scheduler line from §6 if you want the `queue:work` safety net
   and future backup jobs.

9. Web push needs HTTPS plus VAPID keys:

   ```bash
   cd ~/dream_app
   php artisan webpush:vapid      # prints the four lines to paste into .env
   ```

   Copy the printed `WEBPUSH_ENABLED=true`, `VAPID_SUBJECT=mailto:admin@example.edu`,
   `VAPID_PUBLIC_KEY=…` and `VAPID_PRIVATE_KEY=…` into `dream_app/.env`, then rebuild the
   caches with `php artisan config:cache`. The command does not write `.env` itself.
   Rotating the keys later invalidates every existing browser subscription. Never copy the
   private key out of `.env`, paste it into a chat, or commit it. Browsers refuse to
   subscribe over plain HTTP, so the feature stays off until the certificate is installed.
   See [NOTIFICATIONS.md](NOTIFICATIONS.md).

10. The installable app is served by `/manifest.webmanifest` (with `/manifest.json` as an
    alias) and `/sw.js`. Install links appear in the public header and landing hero, and
    each browser gets matching instructions (Android Chrome, iPhone/iPad Safari, desktop
    Chrome/Edge/Safari). Service workers and installation require HTTPS outside
    `localhost`. Bump both `PWA_VERSION` in `.env` and `SW_VERSION` in `public/sw.js` for
    every production release so installed clients receive the update prompt.

Do **not** leave the application inside `public_html`. Directory listings of `app/`,
`config/` or `database/` mean the source and `.env` are reachable from the web; if that
ever happened, rotate the database password and delete the uploaded copy. The root
`.htaccess` shipped with the project only blocks listings and sensitive files — it does
not rewrite URLs, because a rewrite from a sub-folder breaks Laravel's base-path
detection.

### Already uploaded into `public_html`? How to read the symptoms

| Request | Meaning |
| --- | --- |
| `/` → **403 Forbidden** | `public_html` has no `index.php` and listing is blocked, so the app is sitting one level too deep. Not a crash — the protection is working. |
| `/` → **directory listing** of `app/`, `config/`, `database/` | The whole source is web-reachable. Rotate the database password and fix the layout before anything else. |
| `/public/` → **500** with `Access denied for user 'root'@'localhost' (using password: NO)` | The app is running; `.env` still holds the local XAMPP credentials. Set the cPanel `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` (step 5) and rebuild the config cache. |
| `/public/` → **404** | `index.php` is missing from `public_html/public`, or the upload skipped hidden files. |

Two ways to finish, in order of preference:

1. **Move the app out** (steps 1–3 above): `~/dream_app` for the source, the *contents*
   of its `public/` in `public_html`. No rewrite rules, clean URLs, nothing above the
   document root is web-reachable.
2. **Leave it and rewrite `/` into `public/`.** Uncomment the block at the end of the
   project's root `.htaccess`:

   ```apache
   RewriteEngine On
   RewriteRule ^$ public/index.php [L]
   RewriteRule ^((?!public/).*)$ public/$1 [L,NC]
   ```

   LiteSpeed and Apache both accept this internal rewrite. Then set
   `APP_URL=https://yourdomain` (no `/public`) so generated URLs and asset links match
   the address visitors see, and run `php artisan config:clear` afterwards.

If the document root cannot be changed at all, point the domain (cPanel → Domains →
Document Root) at `public_html/public`, or create a subdomain whose root is that folder.

## 6. Queue, scheduler and cache

- Database notifications are written synchronously, so the in-app inbox works without a
  worker. Browser web push is queued (`SendWebPushNotification` on the default queue):
  with `QUEUE_CONNECTION=database` the notification stays in the queue table until a
  worker drains it, so start one whenever push is enabled. SMS/mail jobs will use the same
  database queue.
- Start a worker when queues are used:

  ```bat
  C:\xampp\php\php.exe artisan queue:work --tries=3 --timeout=90
  ```

  In production run it under Supervisor/systemd and restart on deploy.

- Add the scheduler cron (fee reminders and future backup jobs use it):

  ```
  * * * * * cd /var/www/edunest && php artisan schedule:run >> /dev/null 2>&1
  ```

  The scheduler runs `fees:send-reminders --days=3` daily at 08:00. Test it manually with
  `php artisan fees:send-reminders --days=3 --dry-run` before enabling the cron.

- Cache, sessions and queues use database tables in local/demo. Redis is recommended for
  higher concurrency, but is not required to run Phase 1.

- After changing configuration, views or routes in production:

  ```bash
  php artisan optimize
  ```

  Clear with `php artisan optimize:clear` during debugging.

## 7. Production checklist

### Environment

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] `APP_KEY` generated and backed up; never commit `.env`
- [ ] A dedicated MySQL user with only the required privileges (no `GRANT`, no `DROP`
      in steady state if your backup/migration process allows it)
- [ ] `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`,
      `SESSION_SAME_SITE=lax`, `SESSION_ENCRYPT=true` if sessions contain sensitive data
- [ ] `LOG_LEVEL=warning`; logs shipped/rotated
- [ ] Real SMTP/API mail credentials; `MAIL_FROM_ADDRESS` verified
- [ ] `DEMO_SEED_PASSWORD` unset/irrelevant; demo seeding is disabled outside
      `local`/`testing` by `DatabaseSeeder`
- [ ] Remove/rename all demo accounts

### Application

- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=RolePermissionSeeder --force`
- [ ] `php artisan school:admin ...` for the first School Admin
- [ ] `php artisan optimize` (config, routes, views, events)
- [ ] `storage/` and `bootstrap/cache/` writable by the web user; `public/uploads/`
      writable but **never** PHP-executable
- [ ] HTTPS enforced; HSTS is emitted automatically for secure production requests
- [ ] Trusted proxies configured if behind a load balancer
      (`$middleware->trustProxies(at: [...])` in `bootstrap/app.php`)
- [ ] Upload limit at least 4 MB (`upload_max_filesize`, `post_max_size`) to match the
      2 MB image validation
- [ ] Backups scheduled and a restore drill completed
- [ ] Error pages and logs verified with debug off
- [ ] Queue worker and scheduler running if enabled
- [ ] Security headers present (X-Frame-Options, nosniff, Referrer-Policy,
      Permissions-Policy)
- [ ] Rate limits and login throttling verified in staging
- [ ] School-scope tests and `/admin` role-gate tests re-run after any access change

### Before enabling later phases

- Move student/teacher photos to a private disk with authorised delivery.
- Add idempotency and locking tests for every financial endpoint before exposure.
- Configure SMS/payment gateway secrets only in `.env`/secret storage, with webhook
  signature verification and replay protection.

## 8. Environment reference

| Key | Purpose | Production guidance |
| --- | --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_KEY` | Core app identity | `production`, `false`, HTTPS URL, generated key |
| `APP_TIMEZONE` | Default timezone | `Asia/Dhaka` |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Default language | `en`, fallback `en`; users may choose `bn` |
| `DB_*` | MySQL connection | Dedicated user, strong password, private network |
| `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | Session security | database, true, lax |
| `CACHE_STORE`, `QUEUE_CONNECTION` | Runtime infrastructure | database/Redis; run workers if queued |
| `MAIL_*` | Password reset and notifications | Real SMTP/API provider |
| `LOG_CHANNEL`, `LOG_LEVEL` | Diagnostics | stack/single, warning |
| `WEBPUSH_*` | Browser web push (enabled, TTL, timeout, queue, max subscriptions per user) | Keep `WEBPUSH_ENABLED=false` until VAPID keys exist and the site is HTTPS |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | Push service identity | Generate with `php artisan webpush:vapid`; the private key stays server-side only — never commit, paste or email it |
| `DEMO_SEED_PASSWORD` | Local demo only | Remove/ignore |

## 9. Upgrade and rollback

For a code update:

1. Take a database and uploads backup.
2. Put the app in maintenance mode: `php artisan down --secret="..."`.
3. Deploy code, `composer install --no-dev --optimize-autoloader`.
4. Run `php artisan migrate --force`.
5. `php artisan optimize`, restart queue workers.
6. `php artisan up`, smoke-test login, dashboard, users and school profile.

If a release must be rolled back, restore the matching database backup first; do not run
`migrate:rollback` blindly in production because later phases add dependent tables.

### Releasing the admission application desk

This release adds one migration and one new public surface. Beyond the steps above:

- `2026_10_28_000100_add_applicant_fields_to_admissions_table` only adds nullable
  columns (`user_id`, `photo_path`, `rejection_reason`) and two indexes, so existing
  walk-in admissions are untouched and no back-fill is needed. It must run **before**
  the new code is served, otherwise every applicant page fails on the missing
  `admissions.user_id` column.
- `public/uploads/admissions/` is created on the first applicant photo upload
  (`FileUploadService` makes the directory), and the existing rule that `public/uploads`
  must not execute PHP covers it — no extra web-server change.
- The guest endpoint `POST /admissions/apply` no longer exists. Applications now arrive
  only from a signed-in applicant or from `/admin/admissions`. Any bookmark, button or
  script still posting to the old route gets a redirect to sign-in instead of a stored
  record, so check the landing page links after deploying.
- Smoke-test the whole loop, not just the pages: `/register` → sign in → submit an
  application → `APP-YYYY-NNNN` appears → approve in `/admin/admissions/{id}` → the
  applicant can open `/student` and sees `ADM-YYYY-NNNN`.
- Confirm an applicant cannot read another applicant: `GET /admissions/{otherId}` must
  404, and `GET /admissions/{id}/photo` for someone else's application must 404 too.
