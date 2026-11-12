# Security Guide

EduNest handles minors' records, guardian contact details and (from Phase 4) money. The
following controls are implemented and tested today, followed by the gates that must hold
as modules are added.

## 1. School ownership and isolation

- EduNest serves exactly one school. The `schools` table holds one row and every staff
  account has a non-null `school_id`.
- Every school-owned table carries `school_id`; `BelongsToSchool` auto-fills it and
  `SchoolScope` filters reads when a context is active.
- `ResolveSchoolContext` derives the context from `user.school_id` only. There is no
  session switch, `X-School-Id` header or form field that can select a school. An account
  without a school receives 403.
- Client-supplied `school_id` is never trusted for ownership. `UserService` resolves the
  school server-side; tests prove that a request carrying another school's id creates the
  account in the actor's school.
- Policies additionally compare `school_id` for school profile and user records, so a
  missing scope cannot silently expose another school's record.
- Global scopes are retained as defense-in-depth even though the UI has one school: bulk
  imports, API clients and future portal links must not be able to cross the boundary.

**Limitation:** isolation is enforced in application code, not by database row-level
security. Every new repository/service query on a school-owned model must retain the
scope; code review and cross-school feature tests are mandatory.

## 2. Authentication

| Control | Implementation |
| --- | --- |
| Password policy | Minimum 8 characters with letters and numbers (`Password::defaults`) |
| Password storage | Bcrypt (cost 12); passwords hidden from serialisation and audit |
| Login throttling | 5 failures per minute per email+IP, plus service-level tracking |
| Applicant registration | `POST /register` is the only self-service account creation; the role is hard-coded to `student` and the school comes from `SchoolContext`, so a request cannot mint staff or pick a school. `signup` limiter: 3/min and 15/day per IP, 5/hour per lowercased email |
| Application throttling | `admission-apply` limiter: 5/min per authenticated user (IP when anonymous); the applicant `user_id` is taken from the session, never the payload |
| Generic failures | Same message for unknown email, wrong password and inactive account |
| Password reset | Framework broker; generic response regardless of registration; 3/min throttle |
| Temporary passwords | Administrator-created accounts must change password before web/API use |
| Session security | Database sessions, HTTP-only cookies, SameSite=lax, secure cookies in production |
| API tokens | Sanctum hashed tokens; logout revokes the current token |
| Access revocation | Deactivation, deletion or admin password reset deletes tokens and sessions |
| Active accounts | `EnsureAccountIsActive` blocks every protected route and clears web sessions |
| Last-admin guard | The final active School Admin cannot be demoted, deactivated or deleted |
| Self-protection | Admins cannot edit their own admin record, change their own status or delete themselves |

The API returns `403 password_change_required` until the temporary password is changed
(that action is currently web-only). API login still issues a token, but every protected
endpoint refuses it.

## 3. Authorisation

- Permission catalogue is defined in `config/school.php`; roles are seeded from it.
  `school_admin` is the highest role; there is no platform/super-admin bypass.
- Targeted notifications require `notices.manage`. Selected account ids are validated
  against the actor's school, active status and the chosen audience (student/teacher/
  staff), the sender is excluded, and one request is capped at 500 recipients. The
  lock-screen copy is fixed and generic; the administrator's text stays in the inbox.
- `/admin` is wrapped in `EnsureStaffWorkspace`, which allows only
  `config('school.workspace_roles')` (`school_admin`, `principal`, `accountant`,
  `teacher`). A student account navigating there is returned to its own workspace; a
  JSON request receives `403`.
- `/admissions` is wrapped in `EnsureAdmissionApplicant`: only `student`-role accounts
  enter, and every record is re-read through the signed-in account, so another
  applicant's id or photo returns `404`. Staff are refused the same way as above.
- `/student` is wrapped in `EnsureStudentPortal`: the account must hold the `student`
  role **and** be linked to a `students` row. An applicant whose admission is still
  pending is sent to `/admissions` rather than signed out, so the account created at
  registration stays usable. Controllers never accept a student id from the URL; they
  resolve the student through the authenticated account, so one
  student cannot read another's invoices or receipts (covered by feature tests).
- The three workspace middlewares share `RedirectsToOwnWorkspace`, which keeps the
  refusal on the server (`403` for JSON, no controller executed, no record written for
  any caller) while a browser in the wrong workspace is sent to its own home instead of
  a dead-end error page.
- `UserPolicy` requires the permission **and** `RoleHierarchy::canManageUser()`, which
  enforces same school and strictly lower role level. A principal cannot edit another
  principal, an accountant, or a school admin.
- Creating users validates the target role through `RoleHierarchy::canAssignRole()`.
  Nobody can assign their own role or a higher one. User management only offers staff
  roles; student portal accounts are provisioned from a student record, so an account
  cannot be created that holds `student` without a linked student row.
- Role permission edits are restricted to the School Admin and cannot elevate a role
  above their own level.
- UI hides edit/status/reset/delete controls that policy would reject, including the
  actor's own row.

Route model binding may resolve a record from another school before the policy check, so
cross-school access returns 403 rather than 404. This is tested; do not turn it into a
data leak by rendering partial details in error pages.

## 4. Input handling and common web risks

| Risk | Control |
| --- | --- |
| SQL injection | Eloquent/query-builder bindings; the only raw aggregate (`MONTH(...)`) uses fixed SQL and bound values |
| Mass assignment | Narrow `$fillable`; `school_id`, `must_change_password` and role fields are not fillable; services assign them explicitly |
| XSS | Blade auto-escaping; `@json` for chart data; no unescaped user content in views |
| CSRF | `@csrf` on web forms; web mutations are POST/PUT/PATCH/DELETE; API uses bearer tokens |
| IDOR | Policy + school scope on every user/school/student/staff endpoint; the student portal resolves records through the signed-in student's relations; feature tests cover cross-school and cross-student access |
| Open redirect | Redirects target named routes, not user-supplied URLs |
| Unsafe file upload | MIME verified server-side against JPEG/PNG/WebP, size limited to 2 MB, UUID filename, profile directory enforced, deletion restricted to the profile path |
| Header injection | Framework response construction; file download names are not user-controlled |
| Clickjacking | `X-Frame-Options: SAMEORIGIN` |
| MIME sniffing | `X-Content-Type-Options: nosniff` |
| Referrer leakage | `Referrer-Policy: strict-origin-when-cross-origin` |
| Browser feature abuse | `Permissions-Policy` disables camera, microphone, geolocation |
| Transport | HTTPS redirect + HSTS for secure production requests; secure session cookies required in production |

Uploaded logos, avatars and student/teacher photos are stored on the public disk with
UUID names. The web server **must** deny PHP execution under `public/uploads` (see
[INSTALLATION.md](INSTALLATION.md)). The application checks MIME and never trusts the
client extension.

## 5. Audit trail

`AuditLogger` records:

- `login`, `logout`, `login_failed` (with redacted payloads);
- `user.created`, `user.updated`, `user.status_changed`, `user.deleted`;
- `password_reset_requested`, `password_changed`;
- `profile.updated`;
- `role.permissions_updated`;
- `school.updated`;
- academic, admission, student, staff, leave, salary and attendance events.

Each entry stores actor, school, event, target type/id, description, before/after values,
IP and user agent. Password, confirmation, token, secret and authorization keys are
recursively replaced with `[REDACTED]`.

Audit entries are append-only by application convention; no update or delete route
exists. In high-assurance deployments, restrict `UPDATE`/`DELETE` on `audit_logs` at the
database level or stream entries to an append-only store.

## 6. Financial security (fees enabled)

These are mandatory before any fee or accounting endpoint is exposed:

- All invoice/payment/ledger mutations inside `DB::transaction`.
- Row locks (`lockForUpdate`) on invoice and payment rows during allocation.
- Idempotency keys unique per school; replay returns the original receipt.
- Invoice arithmetic and allocation caps exactly as documented in
  [DATABASE.md §4](DATABASE.md#4-financial-invariants-mandatory-for-phase-4-services).
- Immutable receipt snapshot + SHA-256 hash; no update/delete path.
- No silent deletion of posted financial rows; reversal records actor, time and reason.
- Reversals create compensating cash entries and recompute affected invoices.
- Every financial action is permission-gated and audited with before/after values.
- Gateway callbacks verify signatures and are idempotent; amounts/currency are
  re-validated server-side.
- Receipt and report access is scoped and permission-checked; students can only open
  receipts that belong to their own linked student record.

Fee, receipt and cash-ledger services are implemented, and the staff fee workflow is
enabled under `/admin/fees` with permission gates, school-scoped route binding,
transactional mutations and audit. `FeeBillingTest` and `FeeStaffUiTest` cover
idempotency, reversals, receipts, permission denial, export gating and cross-school
404s. A dedicated concurrent-load test and payroll/expense reconciliation remain
before the accounting phase is called complete.

## 7. Academic result integrity (exams enabled)

- Marks are written only for students with an active enrollment in the exam's
  session and class; scores outside the paper's full marks are rejected.
- Publishing result data requires `results.publish`; mark entry requires
  `marks.enter`; viewing tabulation and report cards requires `results.view`.
- Publication runs in a transaction, locks the exam row, verifies that every
  enrolled student has a mark or an absence for every paper, and then stores an
  immutable JSON snapshot plus the computed GPA, grade and position.
- Published exams, papers and marks are locked; corrections require an explicit
  unpublish, which is audited. Snapshots are retained when results are
  unpublished so the historical record is not lost.
- Positions are computed per class with deterministic tie handling; no
  client-supplied totals, GPA or positions are accepted.
- Students can only read their own published results through the linked student
  record; draft results and other students' records return 404.
- Result CSV exports are permission-gated and neutralise spreadsheet formulas.
- Publish, unpublish, mark saves and grade-scale changes are written to the audit
  trail. `ExamManagementTest` covers these boundaries.

## 8. Secrets and configuration

- Secrets live in `.env`; `.env` is not committed and must be excluded from web access.
- `APP_KEY` must be generated per environment and backed up securely (encrypted values
  depend on it).
- Demo credentials are seeded only in `local`/`testing` and must never reach production.
- Mail, SMS and payment credentials belong in environment/secret storage, never in code
  or database fixtures.
- Rotate credentials after any suspected exposure; revoke API tokens and sessions for
  affected users.

## 9. Privacy and data protection

- Student and guardian data is school-scoped and permission-gated; the student portal
  exposes only the signed-in student's own record. Public endpoints do not expose it.
- The public landing page is the only unauthenticated content. It renders the school's
  own name, motto, address, phone/email/website and four aggregate counts (active
  students, teachers, classes and subjects). It never selects student/teacher names,
  guardians, results or financial records, and authenticated visitors are redirected
  before counts are loaded. Keep this allow-list intact when editing the view.
- Do not log request bodies containing passwords or guardian PII; the audit logger
  redacts secrets, but application logs should remain at warning level in production.
- Backups contain personal data: encrypt them, restrict access, and define a retention
  period.
- Student, teacher and applicant photos are currently on the public disk behind
  unguessable UUID filenames. Each one is *served* through an ownership-checked route
  (`/admissions/{id}/photo`, `/admin/students/{id}/photo`, `/student/photo`) that adds
  `nosniff` and `private` caching, but a direct `public/uploads/...` URL still resolves
  if the filename is known. Move them to a private disk with authorised, signed
  delivery before treating photo access as sensitive; a UUID is not an access control
  for children's photos.
- Provide a documented process for correcting or removing guardian/student data in line
  with school policy and applicable law.

## 10. Known limitations

| Limitation | Impact | Mitigation / plan |
| --- | --- | --- |
| No MFA/2FA | Stolen passwords can be reused | Add TOTP for admin roles in Phase 7 |
| Web-only password change | Temporary-password API users are blocked | Use web first; add API password endpoint with re-authentication |
| No email verification gate | Accounts created by admins are pre-verified by design | Acceptable for closed provisioning; applicant accounts from `/register` are `student`-role only and grant access to nothing but their own applications until the school approves one |
| No password history/expiry | Users can reuse old passwords | Add policy in hardening |
| Public photo/logo uploads | Images are publicly readable by URL | Deny PHP execution; private storage planned for student/staff photos |
| No antivirus scan | Malicious image payloads possible | Validate MIME, disable execution, add scanning if available |
| No CSP header | Reduced XSS defence-in-depth | Add a tested CSP in Phase 7 |
| Application-level isolation | A missed scope could expose another school's record | Kept `school_id` scope, policies and cross-school tests as defense-in-depth |
| Audit not DB-immutable | A DB superuser could alter rows | Restrict DB privileges; ship logs offsite |
| 403 on cross-school IDs | Reveals that a record id exists | Accepted trade-off; avoid sequential public ids in future APIs if needed |
| Rate limits per cache node | Multi-node limits not global without shared cache | Use Redis for shared limits at scale |

## 11. Production security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS only
- [ ] Secure, HTTP-only, SameSite session cookies; database/Redis sessions
- [ ] Strong unique database credentials; least-privilege DB user
- [ ] Demo accounts removed; demo password unused
- [ ] PHP execution denied in `public/uploads`; directory listing disabled
- [ ] Web root is `public/`; `.env`, `storage`, `database` unreachable
- [ ] XAMPP/Apache `DocumentRoot` points at `public/`, not the project root (the root
      `.htaccess` is only a defence-in-depth fallback; verify `/.env` returns 403/404)
- [ ] Real mail/SMS/gateway secrets stored outside version control
- [ ] Failed-login and API throttles verified
- [ ] School-scope and workspace-role tests re-run after any access change
- [x] Financial idempotency/locking/reversal tests pass before enabling fees (98-test suite)
- [x] Exam publish/unpublish, mark-bound and cross-school tests pass before enabling results (112-test suite)
- [ ] Backups encrypted, tested for restore, retention documented
- [ ] Audit log retention and offsite copy configured
- [ ] Dependency audit (`composer audit`) and framework updates scheduled

## 12. Reporting a vulnerability

Document a private security contact for the deployment (for example
`security@your-domain`). Do not disclose student or guardian data in reports; use
synthetic examples and include reproduction steps, affected endpoints and impact.
