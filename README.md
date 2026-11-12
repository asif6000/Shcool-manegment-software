# EduNest School Management

A single-school management system for Bangladesh, built with Laravel 12, MySQL,
Blade and Bootstrap 5. One installation serves exactly one school: there are no
tenants, subscriptions, school-switcher or platform-level accounts.

> **Status:** Phases 1–3, the public website pages, the student portal, the staff
> fees workflow, the exam/results workflow, admin notices with the live website feed,
> notifications/web push and the admission application desk are implemented, seeded
> and verified (188 tests / 1071 assertions against MySQL). Fee
> setup screens (types/structures/discounts), expenses/payroll/cashbook screens and
> the Reports module are **not wired yet**.
> Later modules are documented in [docs/PHASES.md](docs/PHASES.md) and are not
> delivered features.

## Highlights

- **One school, one workspace.** All staff accounts belong to the single school
  row; the school is resolved from the authenticated account, never from a
  client-supplied id.
- **Public Bengali-first website**: a school landing page at `/` (identity, contact,
  quick actions) plus public notice board, result search, teacher list, class routine
  and the admission application desk. The landing page reads `/website/live` and
  re-polls every 20 seconds, so the counts and the notice list a school admin
  publishes appear on the website without a reload. Sign-in CTAs
  only — no pricing, parent portal or tenant claims.
- **Admission applications need an account**: applicants register at `/register`,
  sign in, then fill the form under `/admissions`. Every application gets a number
  (`APP-2026-0001`), stays pending until the office decides, and the applicant tracks
  the decision — and the admission number — from their own dashboard. Registration is
  the only public self-service flow in the app and always creates a `student` account.
- **Notice board the admin actually controls**: `/admin/notices` composes, schedules,
  pins and publishes notices, and publishing gives each person in the audience one
  inbox item (title plus an excerpt of a notice the school already wrote for the
  public board); browser push, when it is enabled, carries the title and generic
  wording only.
- **Role hierarchy and permissions** (School Admin, Principal, Accountant,
  Teacher, Student) with last-administrator protection. Staff roles share the
  `/admin` workspace; students get a separate `/student` portal.
- **Secure authentication**: session login, Sanctum API tokens, applicant signup
  (password-hashed, rate-limited, always a `student` account), password reset,
  forced temporary-password change, active-account enforcement, throttling and
  audit.
- **User management** scoped to the school, with self-protection and audit
  trails.
- **School profile** with logo upload, contact/location data, locale, currency
  and timezone.
- **Academics**: sessions, classes, sections, subjects, curriculum and holidays.
- **Admissions and students**: applications from both the applicant portal and the
  office walk-in desk, guardians, enrollments and CSV export. Approval links the
  applicant's account to the new student record and opens the portal.
- **Teachers and staff**: profiles, leave requests/approval and salary
  structures.
- **Attendance**: bulk student marking, monthly reports, CSV export and staff
  attendance.
- **Fees & payments** under `/admin/fees`: invoice generation per session/class,
  outstanding dues, discounts, payment collection, immutable printable receipts,
  controlled reversals and CSV export.
- **Exams & results** under `/admin/exams`: exam scheduling, per-class subject
  papers, grade scales, mark/absence entry, tabulation, tie-aware merit
  positions, publish/unpublish with immutable snapshots, printable report cards
  and CSV export. Students see only their own published results in the portal.
- **Responsive staff workspace** under `/admin`: split-screen login, collapsible
  sidebar, dashboard cards, admission chart, recent activity, searchable tables
  and accessible forms.
- **Notifications & web push**: one database-backed pipeline feeds the in-app inbox,
  browser lock-screen notifications through VAPID web push (queued, retried,
  idempotent per subscription) and exposes device tokens to the mobile API. Push text
  never carries names, marks, GPA or amounts — it links to an authenticated page.
- **English and Bengali** interface (`lang/bn.json`, `lang/bn/*.php`).
- **Versioned REST API** at `/api/v1` with a consistent envelope and pagination.
- **No Node/Vite runtime**: Bootstrap, icons, Chart.js and fonts are vendored.

## Stack

| Layer | Technology |
| --- | --- |
| Runtime | PHP 8.2+ |
| Framework | Laravel 12 |
| Database | MySQL 5.7+ / MariaDB 10.4+ |
| Views | Blade + Bootstrap 5.3 + Bootstrap Icons + Chart.js |
| API | Laravel Sanctum 4 |
| RBAC | spatie/laravel-permission 6 |
| Tests | PHPUnit 11 (MySQL test database) |

## Quick start (XAMPP)

```bat
cd C:\xampp\htdocs\dream
composer install
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

Create `school_ms` and `school_ms_test` (utf8mb4), set the database credentials in
`.env`, then:

```bat
C:\xampp\php\php.exe artisan migrate --seed
C:\xampp\php\php.exe artisan serve
```

Open <http://127.0.0.1:8000> for the public landing page, then sign in at
`/login`; all staff screens live under `/admin`. If you browse through Apache,
point the document root at `public/` and use <http://localhost/dream/public/>:
the shipped root `.htaccess` only blocks `.env`, logs, dumps and source files,
because Laravel cannot reliably detect its base URL when a sub-directory install
is rewritten into `public/`. Full requirements, Apache/Nginx/cPanel configuration
and the production checklist are in [docs/INSTALLATION.md](docs/INSTALLATION.md).

Run the tests:

```bat
C:\xampp\php\php.exe artisan test
```

## Demo accounts (local only)

Password for all demo accounts: `Demo@1234` (configurable via
`DEMO_SEED_PASSWORD`). Seeded only when `APP_ENV` is `local` or `testing`.

| Email | Role |
| --- | --- |
| `admin@edunest.test` | School Admin |
| `principal@edunest.test` | Principal |
| `accountant@edunest.test` | Accountant |
| `teacher@edunest.test` | Teacher |
| `student@edunest.test` | Student portal (linked to the first demo student) |

Never use these credentials or enable demo seeding in production. For a fresh
production install, create the first School Admin with:

```bat
C:\xampp\php\php.exe artisan school:admin head@yourschool.edu.bd --name="Head Teacher"
```

The command creates the single school row when needed, prints a temporary password
once, and forces a change at first sign-in. See
[docs/INSTALLATION.md](docs/INSTALLATION.md#4-creating-the-first-production-administrator).

## What is implemented

- Public website: landing page at `/` (school identity, contact details, aggregate
  counts, quick actions), `/notices` notice board, `/results` guest result search,
  `/teachers` and `/routines`. The landing page reads `/website/live` and
  the notice list and counts a school admin publishes appear without a reload.
  Its admission buttons send a visitor to `/register`, not to a form they cannot
  submit.
- Admission application desk (authenticated): `/register` creates a `student`
  account, then `/admissions` holds the applicant workspace — new application form,
  list of own applications, detail page with the decision and a password change page.
  Submitting allocates an `APP-YYYY-NNNN` number, one application stays open at a
  time, a rejected applicant may apply again, and approval hands the account a
  student record plus `ADM-YYYY-NNNN`. Staff never see applicant screens and
  applicants never see another person's record.
- Notices: `/admin/notices` composer with audience (everyone, students, guardians,
  teachers, staff), academic session, scheduling, expiry, pinning, publish/unpublish
  and soft delete; publishing notifies the audience once each through the
  notification pipeline.
- Login, logout, forgot/reset password, forced password change.
- Dashboard with live school counts, month-over-month growth, 12-month admission
  chart and recent audit activity.
- User list/search/filter/pagination, create, edit, activate/deactivate, password
  reset link and soft delete.
- Role/permission matrix editable by the School Admin (never above their own
  level).
- School profile and edit screens with logo upload.
- Audit trail with event/date filters and before/after details.
- Notifications inbox with browser web-push opt-in, profile and security pages.
- Global workspace search for authorised users.
- Academic setup: sessions (one current), classes, sections, subjects,
  curriculum and holidays.
- Admissions: create, edit, approve (creates student + enrollment atomically)
  and reject with reason.
- Students: directory, profile, photo, guardians, enrollments, CSV export.
- Staff: teachers, employees, leave types/requests/approval, salary structures.
- Attendance: student bulk marking and monthly report/CSV; staff daily marking.
- Fees: invoice generation, dues, discounts, payment collection, receipts,
  reversals and CSV export (fee-type/structure/discount setup screens pending).
- Exams: schedule create/edit, subject papers, grade-scale editing, mark/absence
  entry, tabulation with positions, publish/unpublish snapshots, report cards
  and CSV export.
- Student portal: dashboard, attendance, fees/receipts, published results and
  profile.
- API v1: auth, me/logout, dashboard, school show/update, user
  CRUD/status/reset-link.
- Local demo data for one school.

## Planned modules (not yet delivered)

The full database schema exists for these areas. Screens, routes, policies and
tests are added phase by phase; until then they are not linked in the UI.

| Module | Status |
| --- | --- |
| Dashboard | Implemented |
| Admission Management | Implemented |
| Student Management | Implemented |
| Teacher & Staff | Implemented (profiles, leave, salary structures) |
| Class & Academic | Implemented |
| Attendance | Implemented (student + staff) |
| Fees Management | Implemented (invoices, payments, receipts, dues, reversals; setup screens pending) |
| Accounts & Cashbook | Schema + services, no screens/routes |
| Examination | Implemented (scheduling, papers, grade scales, marks) |
| Result Management | Implemented (tabulation, positions, publish snapshots, report cards) |
| Routine & Timetable | Public routine page (empty until data exists); admin editing pending |
| Online Classes | Schema only |
| Homework & LMS | Schema only |
| Student Portal | Implemented (dashboard, attendance, fees, results, profile) |
| Parent Portal | Out of scope for this single-school build |
| Notice & SMS | Admin composer, scheduling, audience push, public board and the live website feed implemented; SMS pending |
| Library | Schema only |
| Transport | Schema only |
| HR & Payroll | Salary structures implemented; payroll run pending |
| Inventory & Assets | Schema only |
| Certificates & ID Cards | Schema only |
| Events & Activities | Schema only |
| Office Management | Schema only |
| Reports & Analytics | Core CSV exports; broader reporting pending |
| Website Management | Public landing, notices, result search, teachers, routines and admission application implemented, with `/website/live` feeding the landing page; content management not started |
| Notifications & Web Push | Implemented (inbox, VAPID browser push, device tokens, notice publishing to an audience); SMS and native client pending |
| User & Permissions | Implemented |
| Settings | School profile implemented; module settings pending |

## Documentation

| Document | Contents |
| --- | --- |
| [Architecture](docs/ARCHITECTURE.md) | Layers, single-school context, RBAC, request lifecycle, decisions |
| [Database](docs/DATABASE.md) | Conventions, table catalogue, Mermaid ER diagrams, financial invariants |
| [Phases](docs/PHASES.md) | Delivered scope and the roadmap with acceptance criteria |
| [API v1](docs/API.md) | Authentication, envelope, endpoints, samples, error codes |
| [Notifications](docs/NOTIFICATIONS.md) | Notification model, push pipeline, VAPID setup, lock-screen content rules, device API |
| [Attendance](docs/ATTENDANCE.md) | Unified attendance engine, manual/QR methods, device contract, percentage policy, biometric integration status |
| [Installation](docs/INSTALLATION.md) | Local setup, demo data, web server config, production checklist |
| [Security](docs/SECURITY.md) | Threat controls, audit, financial security gates, known limitations |
| [Backup & restore](docs/BACKUP_RESTORE.md) | Dumps, uploads, verification, recovery drills |
| [Troubleshooting](docs/TROUBLESHOOTING.md) | Common setup, login, asset, test and API issues |

## Project structure

```
app/
  Http/Controllers/          Web and Api/V1 controllers
  Http/Middleware/           Account status, school context, staff workspace, student portal, applicant desk, locale, headers, password gate
  Http/Requests/             Validated, normalised input
  Models/Concerns/           BelongsToSchool (scope + auto-fill)
  Models/Scopes/             SchoolScope
  Policies/                  UserPolicy, SchoolPolicy
  Services/                  Authentication, User, School, Dashboard, academic/student/staff/attendance, fees, exams/results, student portal
  Support/                   SchoolContext, RoleHierarchy, AuditLogger, ApiResponse, FileUploadService
config/school.php            Roles, workspace roles, permissions, uploads, locales, demo policy
database/migrations/         Complete schema for all planned modules
database/seeders/            Permissions always; one-school demo data locally only
docs/                        Architecture, database, API, operations
lang/                        English framework strings + Bengali translations
public/assets/               Vendored front-end dependencies
resources/views/             Layout, partials, pages
tests/Feature/               Auth, school scoping, users, academics, students, staff, attendance, fees, exams, portal, API
```

## Security notes

- School-owned records carry `school_id` and are filtered through the school
  scope; the context is derived server-side from `user.school_id`.
- Client-supplied `school_id`/`user_id` never decides ownership; user creation
  always uses the actor's school.
- Three workspaces, each enforced server-side by middleware: `/admin` for staff roles
  only, `/student` for accounts with a student record, `/admissions` for student-role
  accounts. The wrong workspace never renders content — a browser is sent back to its
  own home and a JSON caller gets `403`. Applicants can only read their own
  applications, and every portal query is resolved through the authenticated
  student's own relation (never a client-supplied id).
- Temporary-password accounts are blocked from the API until the password is
  changed.
- Deactivation, deletion and administrative password reset revoke API tokens and
  web sessions.
- Passwords and tokens are redacted from audit payloads.
- Uploads are MIME-validated, size-limited and stored with UUID names; PHP
  execution must be disabled in `public/uploads` on the web server.
- The only self-service flow is admission signup at `/register`, and it can only ever
  create a `student` account — never staff. There is no SaaS sign-up, provisioning
  or school-switcher.

See [docs/SECURITY.md](docs/SECURITY.md) for the complete control set and known
limitations (no MFA yet, public logo/photo storage).

## Verified results

- `php artisan test` — **188 passed, 1071 assertions** (MySQL `school_ms_test`).
- `php artisan route:list --except-vendor` — all staff routes under `/admin`.
- Demo seeding and browser verification are run after schema changes; see
  [docs/PHASES.md](docs/PHASES.md) for the latest recorded results.
