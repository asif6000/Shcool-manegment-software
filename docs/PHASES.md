# Implementation Phases

The database schema for every planned module is migrated. Business logic is delivered
phase by phase; a table existing does **not** mean its workflow works. This page records
what is verified today and what remains.

## Status at a glance

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Foundation: auth, RBAC, single-school scoping, users, school profile, audit | **Delivered and tested** |
| 2 | Academic setup, admissions, students, staff, leave, salary structures | **Delivered and tested** |
| 3 | Attendance (students and staff) | **Delivered and tested** |
| 3c | Student portal: account linking, attendance, fees, receipts, profile | **Delivered and tested** |
| 3b | Timetable, homework, notices | Schema only |
| 4 | Fees, receipts, accounting, payroll | Fee invoices, payments, receipts, dues and reversals delivered; setup/accounting screens pending |
| 5 | Examinations, grading, results | **Delivered and tested** |
| 6 | Reporting, API breadth, website management | Landing page and public notice/result/teacher/routine/admission pages delivered, plus the live website feed (Phase 8b); reporting and content management not started |
| 7 | Integrations, backups, hardening, production rehearsal | Operations docs only |
| 8 | Notifications, web push and device subscriptions | **Delivered and tested** (VAPID web push only; no Expo/React Native client shipped) |
| 8b | Admin notices and live website feed | **Delivered and tested** (composer, audience push, `/website/live` polled by the landing page) |
| 9 | Student signup and the admission application desk | **Delivered and tested** (applicant account, authenticated application, admin decision, portal opens on approval) |

## Module map

| Module | Phase | Status |
| --- | --- | --- |
| Dashboard | 1 | Implemented |
| Admission Management | 2 / 9 | Implemented (office walk-in desk + authenticated applicant desk, approve/reject, admission number) |
| Student Management | 2 | Implemented |
| Teacher & Staff | 2 | Implemented (profiles, leave, salary structures) |
| Class & Academic | 2 | Implemented |
| Attendance | 3 | Implemented (student + staff) |
| Fees Management | 4 | Implemented (invoices, payments, receipts, dues, reversals; setup UI pending) |
| Accounts & Cashbook | 4 | Services + tests; staff screens pending |
| Examination | 5 | Implemented (scheduling, papers, grade scales, marks) |
| Result Management | 5 | Implemented (tabulation, positions, publish snapshots, report cards) |
| Routine & Timetable | 3b | Schema only |
| Online Classes | 6 | Schema only |
| Homework & LMS | 3b | Schema only |
| Student Portal | 3c | Implemented (dashboard, attendance, fees, results, profile) |
| Admission Application Desk | 9 | Implemented (applicant signup, `/admissions` workspace, application tracking, decision notification) |
| Parent Portal | — | Out of scope for this single-school build |
| Notice & SMS | 3b / 7 / 8b | Implemented (composer, scheduling, pinning, audience push, public board, live website feed); SMS pending |
| Library | 6 | Schema only |
| Transport | 6 | Schema only |
| HR & Payroll | 4 | Salary structures delivered; payroll run pending |
| Inventory & Assets | 6 | Schema only |
| Certificates & ID Cards | 6 | Schema only |
| Events & Activities | 6 | Schema only |
| Office Management | 6 | Schema only |
| Reports & Analytics | 6 | Core CSV exports only |
| Website Management | 6 | Public pages (landing, notices, result search, teachers, routines, admission application) delivered; content management not started |
| Notifications & Web Push | 8 | Implemented (database feed, VAPID browser push, device tokens; SMS/native client pending) |
| User & Permissions | 1 | Implemented |
| Settings | 1 / 6 | School profile implemented; module settings pending |

---

## Phase 1 — Foundation (delivered)

### Scope

- Single-school model: one `schools` row, school derived from the authenticated account.
- Authentication (web session + Sanctum API tokens), password reset, forced password
  change, active-account enforcement, rate limiting.
- Roles and permissions with hierarchy and last-administrator protection.
- Staff workspace under `/admin`, guarded by `EnsureStaffWorkspace`; guardian and student
  roles are rejected.
- User management scoped to the school, with audit trail and notifications.
- Dashboard (stats, admission chart, recent activity, quick actions).
- Public single-school landing page at `/` with school identity/contact details,
  aggregate active counts and staff/student sign-in CTAs. Authenticated visitors are
  redirected to their workspace; there is no public registration, pricing or parent
  portal.
- Localisation (English/Bengali), responsive admin shell, security headers.

### Delivered artefacts

- Middleware: `EnsureAccountIsActive`, `ResolveSchoolContext`, `EnsurePasswordIsChanged`,
  `EnsureStaffWorkspace`, `SetLocale`, `SecurityHeaders`.
- `SchoolContext` (resolved from `user.school_id`), `SchoolScope`, `BelongsToSchool`.
- `RoleHierarchy`, `UserPolicy`, `SchoolPolicy`, `AuditLogger`, `FileUploadService`,
  `ApiResponse`.
- Services: `AuthenticationService`, `UserService`, `SchoolService`, `DashboardService`.
- Web screens: public landing page (`LandingController`), split-screen login,
  forgot/reset password, dashboard, users list/create/edit, roles matrix, school
  profile/edit, audit trail, notifications, profile, password change, global search.
- API v1: auth, me, logout, dashboard, school show/update, user CRUD, status, reset link.
- Seeders: roles/permissions always; one-school demo data locally.
- Tests: login, scoping, workspace gating, user hierarchy and API.

---

## Phase 2 — Academic setup and people records (delivered)

- Sessions with exactly one current session; classes, sections, subjects, curriculum and
  holidays.
- Admission pipeline: create/edit, approve (student + enrollment in one transaction) and
  reject with reason.
- Student directory/profile/photo, guardians, enrollments, duplicate roll-number
  validation, CSV export.
- Teacher and employee profiles with account linkage; leave types and requests with
  approval; effective-dated salary structures with the "one active structure" rule.

Not delivered from the original phase scope: private student/teacher photo storage
(currently public disk with UUID names) and student API endpoints.

---

## Phase 3 — Attendance (delivered)

- Bulk student marking per class/section/date, re-marking updates existing rows.
- Holiday override requirement, session-date validation, monthly report and CSV export.
- Staff daily attendance with duplicate protection and holiday handling.
- Permission gates for administrators, teachers and accountants.

---

## Phase 3d — Smart attendance (delivered)

- One attendance engine behind every method; `source`, `source_ref`, correction
  reason and `locked_at` on `student_attendances`.
- Manual register with search, bulk statuses, timetable-derived teacher scope,
  audited corrections and a correction lock that QR/device events cannot undo.
- ID card QR: random hashed tokens, one-time print, issue/reissue/revoke, camera
  scanning with manual fallback, server-side card/session/scope checks and rate
  limiting.
- Device registry with hashed secrets, rotation, enable/disable and per-device
  student mapping.
- Vendor-neutral ingestion endpoint `POST /api/v1/attendance/events` with
  idempotency, clock-skew/stale rejection and no biometric payload storage.
- Fingerprint and face recognition remain **NOT_CONFIGURED**: no manufacturer,
  adapter or hardware exists yet, and the endpoint refuses traffic until one is
  registered and verified. The methods page reports Ready only from a
  registered adapter plus an authenticated heartbeat.
- One percentage policy shared by the report, CSV export and student portal,
  including leave, excused, weekends and holidays.

---

## Phase 3c — Student portal (delivered)

- One portal login per student, provisioned by staff from the student profile.
- `/student` dashboard: current enrollment, monthly/session attendance, fee balance,
  recent invoices and receipts.
- Attendance register with month navigation and status summary.
- Fee invoices, invoice detail and immutable receipt snapshots; read-only by design
  (no online payment yet).
- Profile and password change, including the first-login temporary-password gate.
- All records resolved through the signed-in student's own relations; staff are denied
  from `/student` and students from `/admin`.

### Acceptance tests

- Portal pages render the linked student's own data.
- Another student's invoice or receipt returns 404.
- Staff receive 403 in `/student`; students receive 403 in `/admin`.
- Student login redirects to `/student`; temporary passwords redirect to the portal
  password page.
- Staff can create, reset and disable portal access with audit entries.

---

## Phase 3b — Timetable, homework and notices (schema only)

Deliverables when implemented:

- Weekly class routines with section/teacher slot conflict detection.
- Homework with attachment and due dates.
- Notices with audience, pinning and expiry.
- Teacher assignment rules (only assigned classes/sections).

The tables exist; no routes, services or screens are wired.

---

## Phase 4 — Fees, receipts and accounting (fees workflow delivered)

### Delivered

- Fee types, class/session fee structures and demo billing data.
- Permission-gated staff screens under `/admin/fees`:
  - invoice list with summary cards, search, session/class/status filters and CSV export;
  - invoice generation for a class/section with selectable fee types and period;
  - invoice detail with items, totals, discounts, payments and printable view;
  - outstanding-dues worklist with overdue markers;
  - discount apply/remove while an invoice has no payments;
  - cancellation of unpaid invoices (paid invoices must be reversed first);
  - payment collection with method, reference, backdating limit and idempotency key;
  - immutable receipt snapshot with unique receipt number, hash and print view;
  - controlled payment reversal with a reason and compensating cash entry.
- Payment collection with allocation, partial payments and multiple methods.
- Cash-ledger posting for every payment, with controlled reversals.
- Permission gates: `fees.view`, `fees.manage`, `fees.collect`, `fees.reverse`,
  `fees.export`. Principal is read-only; accountant and school admin can operate.
- Feature tests for generation, idempotent collection, snapshot receipts, reversals,
  permission denial, export gating and cross-school access denial.

### Remaining

- Fee setup UI for fee types, frequencies, class/session structures and discounts.
- Dues ageing buckets and a per-student ledger view.
- Expenses, salary payments/payroll runs, cashbook and reconciliation screens.
- API: invoices, payments, receipts, dues, expenses and cash transactions.
- A true concurrent-request test for invoice locking under load.

### Mandatory controls

- All mutations in `DB::transaction` with `lockForUpdate()` on invoice/payment rows.
- `idempotency_key` unique per school; duplicate submissions return the same result.
- Invoice math and allocation limits exactly as specified in
  [DATABASE.md §4](DATABASE.md#4-financial-invariants-mandatory-for-phase-4-services).
- Receipts insert-only; corrections via reversal.
- Every posting writes the matching cash ledger row; reconciliation never edits amounts.
- Financial endpoints are permission-gated and audited with before/after values.

### Acceptance tests

- Partial payment updates paid/due/status correctly. Covered by `FeeBillingTest`.
- Overpayment and over-allocation rejected without partial writes. Covered.
- Double-submit with the same idempotency key posts once. Covered.
- Reversal restores invoice dues and adds a reversing cash row. Covered.
- Receipt snapshot cannot be modified; audit contains actor and reason. Covered.
- Fee screens reject users without the relevant permission. Covered by `FeeStaffUiTest`.
- Cross-school invoice, payment and receipt ids return 404. Covered by `FeeStaffUiTest`.
- Concurrent payments serialise and cannot exceed the due. Pending a dedicated load test.
- Cash totals equal the sum of posted fee/salary/expense transactions. Pending payroll/expenses.

---

## Phase 5 — Examinations and results (delivered)

### Delivered

- Grade-scale management with a seeded Standard GPA-5 scale and validated
  continuous ranges (`GradeService`, `/admin/exams/grade-scale`).
- Permission-gated staff screens under `/admin/exams`:
  - exam list with search, session/status filters and summary cards;
  - exam create/edit/delete while the exam is a draft;
  - per-class subject papers with schedule, full marks and pass marks;
  - marks entry per class/paper/section, including absence and remarks;
  - tabulation per class with subject columns, totals, GPA, grade, positions
    and summary statistics;
  - publish/unpublish with completeness checks and immutable JSON snapshots;
  - printable report cards for staff preview and published results;
  - CSV export with permission gating and formula-injection protection.
- Automatic grade/GPA calculation; any F (or absence) makes the overall GPA
  0.00 with grade F.
- Tie-aware merit positions per class, deterministic on GPA, total and student
  id.
- Student portal results list and printable report card showing only the
  signed-in student's published results.
- Permission gates: `exams.view`, `exams.manage`, `marks.enter`, `results.view`,
  `results.publish`. Principal and school admin can manage and publish;
  teachers can enter marks and view results; accountants have no exam access.
- Published marks and papers are locked; corrections require unpublish first,
  and every publish/unpublish is audited.
- Feature tests in `tests/Feature/ExamManagementTest.php` cover permissions,
  paper scheduling, mark bounds, absence, GPA/F rules, completeness, tie
  positions, snapshot locking, unpublish, report cards, portal isolation,
  cross-school denial, CSV safety and grade-scale validation.

### Remaining

- Exam routine/timetable integration and hall/seat planning.
- Bulk mark import (CSV/Excel) and per-subject teacher assignment rules.
- Transcripts across sessions, certificates and promotion decisions.
- API: exams, marks, results and report-card data.

### Mandatory controls

- All mutations are school-scoped and run in transactions; publish locks the
  exam row, revalidates completeness and stores an immutable snapshot.
- Marks accept only enrolled students and only scores inside the paper total.
- Results are visible to students only after publication; draft data stays
  staff-only.
- Every publish, unpublish, mark save and grade-scale change is audited.

### Acceptance tests

- Marks cannot exceed full marks. Covered by `ExamManagementTest`.
- GPA matches the configured scale at boundaries; any F gives 0.00. Covered.
- Publishing requires every enrolled student to have a mark or absence. Covered.
- Publishing stores an immutable snapshot and assigns deterministic tie-aware
  positions. Covered.
- Unpublishing restores drafting and permits corrections. Covered.
- Every result endpoint is school-scoped; students only see their own published
  results. Covered.

---

## Phase 6 — Reporting and website (reporting and CMS not started)

### Modules

- Routine & Timetable, Homework & LMS, Online Classes.
- Staff-side fee/accounting screens, expenses, payroll runs and module settings.
- Notice & SMS, Library, Transport, Inventory & Assets, Certificates & ID Cards, Events &
  Activities, Office Management, Reports & Analytics, Website Management.
- The public single-school landing page is delivered; content editing/CMS remains.

### Reporting deliverables

- Report library: enrolment, attendance, fees/dues, expenses, payroll, results, audit and
  operational summaries.
- Shared server-side filters, pagination, print layouts and CSV exports.
- API Resources and endpoints for every implemented module.

### Acceptance tests

- A student can only see their own record (delivered and tested for the portal).
- Staff cannot enter `/student`; students cannot enter `/admin` (delivered and tested).
- Report totals match source records and respect filters.
- Exports enforce permissions, avoid formula injection and stream large datasets.
- Print layouts render without navigation and with school identity.

---

## Phase 7 — Integrations, backups and hardening

- SMS provider integration with queued jobs, retries and `sms_logs` status history.
- Online payment gateway: initiate/callback/verify, signature checks, idempotent posting
  through the payment service, reconciliation.
- Backup command + scheduler writing `backup_logs`, retention policy and restore drill.
- Security hardening: 2FA for privileged roles, session/device management, CSP,
  dependency and permission audits.
- Performance: query budgets, index review, caching hot dashboard aggregates.
- Deployment rehearsal with demo seeding disabled.

---

## Phase 8 — Notifications, web push and devices (delivered)

### Delivered

- One notification pipeline for in-app feed, email-agnostic database storage and web
  push: `App\Notifications\SchoolEventNotification` + `NotificationService`, with a
  `dedupe_key` so a repeated event never produces a second copy.
- Browser push via VAPID: `push_subscriptions` records, `webpush:vapid` key setup,
  `SendWebPushNotification` queued job (3 tries, 10s/60s/300s backoff) and a
  `push_delivery_logs` row per notification/subscription/channel that makes retries
  idempotent. A `410` from the push service deactivates the subscription; transient
  failures (`null`, `0`, `429`, `>= 500`) are rethrown so the queue retries them.
- Subscription endpoints for the web app (`POST`/`DELETE /notifications/push/subscribe`)
  and Sanctum endpoints for notifications and devices; every endpoint is authenticated
  and the owning user is always taken from the token, never from the request body.
- Lock-screen safety: push text carries no student names, marks, GPA, amounts or other
  sensitive detail, and links open authenticated pages only. Endpoints outside
  `local`/`testing` must be HTTPS.
- Tests: notification web/API suites, push job retry/deactivation/idempotency and the
  Expo-style device-token contract (device registration only — no Expo project, SDK or
  React Native code is shipped).

### Remaining

- Native mobile client and its FCM/APNs token handling (device API is ready for it).
- SMS provider channel, `sms_logs` history and queued sending (Phase 7).
- Notification preferences per role/user and an admin broadcast composer.

---

## Phase 8b — Admin notices and live website feed (delivered)

### Delivered

- Admin notice management at `/admin/notices` (`NoticeController` + `NoticeService`):
  create, edit, publish, unpublish, pin, schedule and soft-delete, guarded by
  `notices.view` / `notices.manage` and audited as `notice.created|updated|published|
  unpublished|pinned_toggled|deleted`.
- Audiences: `all`, `students`, `guardians`, `teachers`, `staff`. Only `all`,
  `students` and `guardians` are allowed on the public website, enforced by
  `Notice::scopePublic()` and `isVisibleOnWebsite()`.
- Publishing a notice notifies its audience once per person through the Phase 8
  pipeline (`SchoolEventNotifier::noticePublished()`, chunked by 100, deduped with
  `notice.published:{id}:{user}`), with the notice title as the in-app message and
  generic wording on the lock screen. Website-visible notices link to the public
  notice page; `teachers`/`staff` notices link to `/admin/notices`, since their
  public page 404s by design.
- `GET /website/live` returns the counts and the current notice list as JSON,
  rate-limited by IP (`website-live`, 60/min) and never exposing anything beyond
  public notice titles and aggregate counts.
- The landing page renders those counts in a stats band and the notice list in the
  "সর্বশেষ নোটিস" panel, then `assets/js/live.js` re-fetches the same endpoint every
  20s, pauses while the tab is hidden and stops after three consecutive failures.
  Rows are built with `createElement`/`textContent` only, so a school-authored title
  can never inject markup into the public page.
- Tests: `NoticeManagementTest` covers the draft → publish → website loop, the
  audience and expiry filters on the live feed, the permission boundary for teachers,
  one-notification-per-recipient deduping, unpublish/delete removal, scheduling, and
  cross-school isolation.

### Remaining

- SMS broadcast for notices (Phase 7 provider work).
- A scheduled publish worker — `published_at` in the future is honoured by the UI and
  the public scopes, but nothing promotes it automatically until the queue runs.

---

## Phase 9 — Student signup and the admission application desk (delivered)

### Scope

An applicant must hold an account before applying. Registration, sign-in, the form,
tracking and the office decision all reuse the Phase 1/2 authentication, RBAC,
school-context, audit and upload machinery — no parallel auth system was added.

### Delivered

- **Signup on the existing guard**: `GET/POST /register` (`RegisterController` →
  `ApplicantAccountService`) creates a `User` with the `student` role in the active
  school, hashes the password through the same policy
  (`min:8, letters + numbers`), signs the new account in, and writes a
  `user.registered` audit entry. It cannot create staff: the role is hard-coded, and
  `school_id` comes from `SchoolContext`, never the request.
- **Throttling**: limiter `signup` = 3/min and 15/day per IP plus 5/hour per lowercased
  email, so one address cannot be used to farm accounts.
- **Applicant workspace** `/admissions` behind
  `auth + active.account + school.context + password.changed + admission.applicant`
  (`ApplicantAdmissionController`): dashboard/list of own applications, new-application
  form, detail page, own photo, and its own password-change page and locale switcher.
  Guests are redirected to sign-in and JSON callers get `401`.
- **Application numbering and pending state**: each submission takes
  `APP-YYYY-NNNN` from `NumberSequence` inside the transaction and starts as `pending`.
  The applicant's own `user_id` is stamped server-side; a `user_id` in the payload is
  not readable, so nobody can file an application into someone else's account.
- **Duplicate prevention**, enforced in `AdmissionService::guardDuplicates()` with a
  `lockForUpdate()` re-read: one open (`pending`/`waitlisted`) application per account,
  and one per student per session/class. A blocked submission returns the field error
  naming the application still under review. After a rejection the applicant may apply
  again; after approval the applicant desk closes and `/student` opens.
- **Walk-in admissions are untouched**: staff still create applications at
  `/admin/admissions` with `user_id` null, and those rows are invisible to applicants.
- **Secure photo upload**: `admission_photo` profile (`admissions/`, JPEG/PNG/WebP,
  2 MB, real MIME sniffed from contents, dimensions checked, UUID filename) on the
  `school_uploads` disk, delivered only through
  `GET /admissions/{id}/photo` with `nosniff`, `private` caching and an ownership
  check — another applicant's id or photo returns `404`. On approval the file is moved
  into the student's `students/{schoolId}/…` path by `StudentService::adoptPhoto()`.
- **Rate limit on submissions**: limiter `admission-apply` = 5/min keyed to the user id
  (IP when anonymous), verified by a test that expects `429` on the sixth POST.
- **Privacy split on decisions**: `admissions.rejection_reason` is the only decision
  text the applicant ever sees; the internal `notes` column stays on the office screen.
  Rejection notifies the applicant and keeps the submitted data intact.
- **Approval is one transaction**: creates the student, adopts the photo, allocates
  `ADM-YYYY-NNNN`, links `students.user_id` to the applicant account, notifies them and
  writes a `student.account_linked` audit entry. The applicant's next sign-in lands in
  `/student`.
- **Workspace isolation**: `EnsureAdmissionApplicant`, `EnsureStudentPortal` and
  `EnsureStaffWorkspace` share `RedirectsToOwnWorkspace`, so an account in the wrong
  workspace is returned to its own home instead of a dead-end 403 page, and JSON
  requests still receive `403`.
- **Bangla**: every new applicant and office string is keyed in `lang/bn.json`
  (997 keys now), including the `:count application|:count applications` plural.
- **Tests**: `AdmissionApplicationTest` — 19 feature tests covering guest redirect,
  `401`, signup, weak/taken email, signup throttling, CSRF token, `APP-` numbering,
  ignored `user_id`, duplicate blocking and re-application, portal gating, staff `403`,
  cross-applicant `404`, walk-in isolation, approval linking, rejection privacy,
  unsafe-photo refusal, server-side field validation and submission throttling.

### Remaining

- No email/SMS receipt to the guardian when an application is submitted or decided —
  the applicant is notified in-app and by browser push only.
- Documents other than the photo (birth certificate, previous school report) are not
  accepted; the form takes one image.
- Applicants cannot edit a submitted application; the office must correct it. This is
  deliberate but there is no request-for-correction flow.
- No public search of application status by number — an applicant must sign in.

---

## Cross-phase definition of done

A phase is complete only when:

1. migrations run from scratch and roll back cleanly;
2. services enforce school ownership, permissions and transactions;
3. feature tests cover happy paths, authorisation boundaries and concurrency-sensitive
   rules;
4. `php artisan test` passes in full;
5. Blade views compile (`view:cache`) and key screens are browser-verified;
6. docs (API, database, security) are updated;
7. demo seed data still works and no demo credential is reachable in production.

## Verified runs

| Date | Scope | Result |
| --- | --- | --- |
| 2026-10-28 | Full PHPUnit suite after the admission application desk (MySQL `school_ms_test`) | **188 passed, 1071 assertions** (PHP 8.2.12, 2m20s) |
| 2026-10-28 | Laravel Pint over `app/Http/Middleware`, the new/edited tests | PASS — 12 files, no style violations |
| 2026-10-28 | `php artisan migrate` on the local dev database | `2026_10_28_000100_add_applicant_fields_to_admissions_table` applied; this was found **pending** — the first live submission died on `Unknown column 'admissions.user_id'` while the suite stayed green because tests use `school_ms_test`. Migrations must run on the dev database too, not only on the test one |
| 2026-10-28 | Browser golden path: `/register` → sign in → apply with a PNG photo → approve in `/admin/admissions/2` | `APP-2026-0001` created as `pending`; admin list showed the **Online application** badge and the **Submitted by** row; approval produced `STU-2026-0001` + `ADM-2026-0001`, moved the photo to `admin/students/29/photo`, and the applicant's next sign-in landed in `/student` |
| 2026-10-28 | Browser security checks as a guest | `GET /admissions/apply` redirected to `/login`; `Accept: application/json` on `/admissions/apply` and `/admissions` returned `401 Unauthenticated`; `GET /admissions/2/photo` returned `200` with `nosniff`, `private` caching and the UUID filename |
| 2026-10-28 | Browser duplicate and workspace checks | Second submission in the same session was refused with "Application APP-2026-0001 is still under review…"; an approved applicant opening `/admissions/apply` was sent to `/student`; admin login with a stale intended URL landed on `/admin/dashboard` instead of a 403 page |
| 2026-10-28 | Bangla rendering of the applicant workspace (`/register`, `/admissions`, `/admissions/apply`, detail page) and `lang/bn.json` | Every applicant-facing string rendered in Bangla; JSON valid at 997 keys. Found and fixed two gaps: the `:count application|:count applications` plural and `This area is for school staff accounts.` |
| 2026-10-27 | Full PHPUnit suite after the admin notices module and live website feed (MySQL `school_ms_test`) | **168 passed, 910 assertions** |
| 2026-10-27 | Laravel Pint over `app/` `tests/` `database/` | PASS — 274 files, no style violations |
| 2026-10-27 | Admin → website loop: publish in `/admin/notices`, then `/`, `/notices`, `/notices/{id}` and `/website/live`; unpublish and delete | Notice reached all four surfaces; the open landing page picked up the new notice and its date badge without a reload, and the feed returned `{"notices":[]}` after deletion |
| 2026-10-27 | Browser check of the landing page with `live.js?v=2` | `/website/live` fetched on load and again 20s later (HTTP 200 both times), no console errors, stats band rendered 28/6/10/7, "Last updated" hint revealed after the first poll |
| 2026-10-28 | Full PHPUnit suite after the smart attendance system (MySQL `school_ms_test`) | **238 passed, 1526 assertions** |
| 2026-10-28 | Browser check at `localhost/dream/public`: `/admin/attendance/methods`, `/admin/attendance/students?class_id=7`, `/admin/attendance/qr` | Methods page shows Manual/QR Ready and Fingerprint/Face Not configured; register renders the sticky save bar with correction reason; QR page renders scanner, manual fallback and HTTPS notice. Camera capture itself was not exercised (hidden browser viewport) |
| 2026-10-27 | Live probe of `dreamrootsmilleniumschool.com` — `/`, `/public/`, `/index.php`, `/login`, `/.env`, `/app/`, `storage/logs/laravel.log` | `/` 403 (listing blocked and no `index.php` in `public_html`), `/public/` 500 with `Access denied for user 'root'@'localhost' (using password: NO)` on the `sessions` query, sensitive paths 403 — PHP and the upload are fine; only the document root and the local `.env` database credentials are wrong. `APP_DEBUG` was also left on, which printed the database name and SQL to visitors |
| 2026-10-27 | Full PHPUnit suite after Phase 8 notifications, web push and public pages (MySQL `school_ms_test`) | **158 passed, 830 assertions** |
| 2026-10-27 | Laravel Pint over `app/` `tests/` `database/` (with the MySQL PHP path on `PATH`) | No style violations reported |
| 2026-10-27 | Browser check of the redesigned landing page plus `/notices` `/results` `/teachers` `/routines` `/admissions/apply` and a guest admission submit | All HTTP 200; admission stored as `school_id` of the active school and returned the application number; `/login` still the only sign-in route |
| 2026-10-27 | Public-file protection check while served from `localhost/dream/public` | `.env`, `storage/logs/laravel.log`, `composer.json` and the SQL export all return 403 |
| 2026-10-27 | Full PHPUnit suite after adding the public landing page (MySQL `school_ms_test`) | **115 passed, 607 assertions** |
| 2026-10-27 | Browser check: `/` at 1440px and 390px, mobile menu toggle, guest vs authenticated `/` | Landing shows live counts and contact details; no horizontal overflow at 390px; authenticated `/` redirects to the role workspace |
| 2026-10-26 | Full PHPUnit suite after exam/results workflow (MySQL `school_ms_test`) | **112 passed, 586 assertions** |
| 2026-10-26 | `migrate:fresh --seed` on a scratch database | 28 students seeded; exam published with 50 papers, 140 marks, 28 result snapshots |
| 2026-10-26 | Browser check: `/admin/exams` list, exam detail, locked marks sheet, tabulation, staff report card; `/student/results` list and report card | All HTTP 200; published result visible to the linked student only |
| 2026-10-26 | Full PHPUnit suite after fee staff UI (MySQL `school_ms_test`) | **98 passed, 484 assertions** |
| 2026-10-26 | Browser check: `/admin/fees` list, invoice detail, generation form, payment collection, receipt and reversal | All HTTP 200; payment/receipt/reversal completed end-to-end |
| 2026-10-26 | Full PHPUnit suite (MySQL `school_ms_test`) | **77 passed, 340 assertions** |
| 2026-10-26 | Browser check: login → `/admin/dashboard`, school profile, students, academic, admissions, staff, attendance (student/staff), users, roles, audit, notifications, profile, school edit | All HTTP 200, no console errors |
