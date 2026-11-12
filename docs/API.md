# API v1 Reference

Base URL: `http://127.0.0.1:8000/api/v1`

Implemented surface: authentication, profile lookup, dashboard, school profile, user
management, notifications and push/device subscriptions. The public website additionally
exposes one unauthenticated JSON read route, `GET /website/live` (section 8). Further
module endpoints are
added phase by phase (see [PHASES.md](PHASES.md)); do not assume unimplemented URLs
exist. Web push setup and the lock-screen content rules live in
[NOTIFICATIONS.md](NOTIFICATIONS.md).

## 1. Conventions

- JSON request and response bodies (`Content-Type: application/json`).
- Bearer tokens from Laravel Sanctum: `Authorization: Bearer <token>`.
- Dates are ISO 8601 (`2026-10-26T14:30:00+06:00`); calendar fields use `YYYY-MM-DD`.
- All data belongs to the single school. The school is resolved from the bearer
  token's account; there is no `X-School-Id` header or `school_id` request field.
- Client-supplied `school_id` never decides ownership; user creation always uses the
  actor's school.
- Responses never include passwords, reset tokens or other secrets.

## 2. Response envelope

Success:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

Paginated success adds `meta`:

```json
{
  "success": true,
  "message": "Users retrieved.",
  "data": [],
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3,
      "from": 1,
      "to": 15
    },
    "links": {
      "first": "...?page=1",
      "last": "...?page=3",
      "prev": null,
      "next": "...?page=2"
    }
  }
}
```

Failure:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  },
  "code": "validation_error"
}
```

`errors` is `{}` when there are no field errors.

### Error codes and statuses

| HTTP | `code` | Meaning |
| --- | --- | --- |
| 401 | `unauthenticated` | Missing/expired token |
| 403 | `forbidden` | Policy or role hierarchy denied the action |
| 403 | `password_change_required` | Account still uses a temporary password |
| 404 | `not_found` | Route/model not found |
| 422 | `validation_error` | Invalid payload or business validation |
| 429 | `rate_limited` | Throttle exceeded (`Retry-After` header set) |

Authorisation failures for records in another school return **403**, not 404, because the
record is located before the policy check. This is intentional and covered by tests.

## 3. Rate limits

| Limiter | Limit | Key |
| --- | --- | --- |
| `api-login` (login) | 5/min | lowercase email + IP |
| `api` (all authenticated endpoints) | 60/min | user id, falling back to IP |

Exceeding a limit returns 429 with the standard envelope and `Retry-After`.

## 4. Authentication

### POST `/auth/login`

Public. Returns a personal access token. Inactive accounts and bad credentials both
produce a 422 credential error, without disclosing which condition failed.

Request:

```json
{
  "email": "admin@edunest.test",
  "password": "Demo@1234",
  "device_name": "office-laptop"
}
```

Response `200`:

```json
{
  "success": true,
  "message": "Signed in successfully.",
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "user": {
      "id": 2,
      "name": "Nusrat Jahan",
      "email": "admin@edunest.test",
      "phone": "01711000001",
      "is_active": true,
      "must_change_password": false,
      "locale": null,
      "avatar_url": null,
      "last_login_at": "2026-10-26T14:30:00+06:00",
      "roles": ["school_admin"],
      "school": {
        "id": 1,
        "name": "EduNest Model School",
        "code": "ENS",
        "currency": "BDT",
        "timezone": "Asia/Dhaka"
      },
      "created_at": "2026-10-26T12:00:00+06:00"
    }
  }
}
```

A successful login updates `last_login_at`, writes a `login` audit event and clears the
failure counter. A failed login records the attempt and increments the limiter.

### GET `/auth/me`

Returns the authenticated user with roles and school (same `UserResource`).

### POST `/auth/logout`

Revokes the **current** token and returns `{"success": true, "message": "Signed out successfully.", "data": null}`.
Subsequent requests with that token return 401.

### Temporary-password accounts

A user created by an administrator has `must_change_password = true`. Login succeeds,
but every protected endpoint returns:

```json
{
  "success": false,
  "message": "Please choose a new password before continuing.",
  "errors": {},
  "code": "password_change_required"
}
```

The password change itself is currently a web action (`/admin/profile/password`). API
clients should complete the first sign-in in the web UI. This guard is covered by a
feature test.

## 5. Single-school resolution

EduNest serves one school. Every authenticated request derives its school from
`user.school_id` inside `ResolveSchoolContext`. An account without a school receives
`403` with `code: forbidden`. There is no header, query parameter or body field that can
select another school.

## 6. Endpoints

| Method | Path | Permission | Notes |
| --- | --- | --- | --- |
| POST | `/auth/login` | public | Rate limited 5/min |
| POST | `/auth/logout` | token | Revokes current token |
| GET | `/auth/me` | token | Current user |
| GET | `/dashboard` | `dashboard.view` | Scoped stats, chart, activity |
| GET | `/school` | `school.view` | Current school + counts |
| PUT | `/school` | `school.update` | Multipart allowed for logo |
| GET | `/users` | `users.view` | Paginated, filtered |
| POST | `/users` | `users.create` | Creates temporary-password account |
| GET | `/users/{user}` | `users.view` + policy | 403 across schools |
| PUT | `/users/{user}` | `users.update` + hierarchy | Role-aware |
| DELETE | `/users/{user}` | `users.delete` + hierarchy | Soft delete; revokes access |
| PATCH | `/users/{user}/status` | `users.update` + hierarchy | Toggles active flag |
| POST | `/users/{user}/reset-link` | `users.reset-password` + hierarchy | Sends reset mail |
| POST | `/attendance/events` | device secret headers | Fingerprint/face ingestion; `503` while not configured |

### POST `/attendance/events`

Device endpoint, separate from the user API. It does **not** use Sanctum or the
response envelope above. A terminal authenticates with `X-Device-Id` and
`X-Device-Secret` headers (issued once on `/admin/attendance/methods`) and posts
neutral punch records:

```json
{
  "events": [
    {
      "external_ref": "device-event-10021",
      "user_id": "1001",
      "occurred_at": "2026-10-28T08:12:00+06:00"
    }
  ]
}
```

The response lists a result per event (`applied`, `duplicate`, `skipped`,
`rejected`). Events are idempotent on `external_ref`, the school comes from the
device, and no biometric template or image is accepted or stored. Until a real
adapter is registered, every request returns `503` with
`{"status":"not_configured"}`. See `docs/ATTENDANCE.md` for the full contract.

### GET `/dashboard`

Response `data`:

```json
{
  "school": { "id": 1, "name": "EduNest Model School", "code": "ENS" },
  "current_session": {
    "id": 3,
    "name": "2026",
    "start_date": "2026-01-01",
    "end_date": "2026-12-31"
  },
  "stats": { "students": 28, "teachers": 6, "classes": 10, "staff": 2 },
  "growth": { "students": 12.5, "teachers": 0.0 },
  "chart": {
    "labels": ["Jan", "Feb", "..."],
    "current": [0, 3, 5, "..."],
    "previous": [0, 2, 4, "..."],
    "currentYear": 2026,
    "previousYear": 2025
  },
  "activity": [
    {
      "id": 10,
      "event": "school.updated",
      "description": "School profile was updated",
      "actor": "Nusrat Jahan",
      "created_at": "2026-10-26T12:30:00+06:00"
    }
  ]
}
```

### GET `/school`

Returns `SchoolResource` with `counts` (`users`, `students`, `teachers`). The school is
always the authenticated account's school.

### PUT `/school`

Accepts JSON or multipart form data. Fields:

| Field | Rules |
| --- | --- |
| `name` | required, max 160 |
| `short_name` | nullable, max 60 |
| `code` | required, alpha-dash, max 30, unique |
| `eiin` | nullable, max 20, unique |
| `email`, `phone`, `phone_alt` | nullable, max 255/30/30 |
| `website` | nullable URL, max 255 |
| `address` | nullable, max 500 |
| `division`, `district`, `upazila` | nullable, max 60 |
| `postcode` | nullable, max 10 |
| `motto` | nullable, max 255 |
| `established_year` | nullable integer, 1800–next year |
| `timezone` | required, valid timezone |
| `currency` | required, 3 letters |
| `locale` | required, `en` or `bn` |
| `logo` | image `jpg/jpeg/png/webp`, max 2048 KB |

Updates are audited with old/new values.

### GET `/users`

Query parameters:

| Name | Values | Default |
| --- | --- | --- |
| `search` | name, email or phone fragment | — |
| `role` | role name | all |
| `status` | `active`, `inactive` | all |
| `sort` | `name`, `email`, `created_at`, `last_login_at` | `created_at` |
| `direction` | `asc`, `desc` | `desc` |
| `page` | integer | 1 |

Page size is 15. Every actor only sees their own school's users. Response uses the
paginated envelope; each item is a `UserResource`.

### POST `/users`

```json
{
  "name": "New Teacher",
  "email": "teacher2@edunest.test",
  "phone": "01700000000",
  "role": "teacher",
  "password": "Secret@123",
  "password_confirmation": "Secret@123",
  "is_active": true
}
```

- `role` must exist and be strictly below the actor's level.
- The account is always created in the actor's school; `school_id` is not accepted.
- The account is created with `must_change_password = true`, receives a welcome
  notification and an audit entry.
- Response `201` with the created `UserResource`.

### PUT `/users/{user}`

Same fields as create, except `password` is optional. Supplying a password revokes the
target user's tokens and sessions and forces a change at next sign-in. Self-editing is
rejected; use the web profile.

Role changes enforce the hierarchy. The last active school administrator cannot be
demoted or deactivated.

### PATCH `/users/{user}/status`

No body. Toggles `is_active`. Deactivation immediately revokes tokens and deletes
database sessions. Self-status changes are rejected.

### POST `/users/{user}/reset-link`

No body. Sends the framework password reset mail. The response is
`{"success": true, "message": "Password reset link sent.", "data": null}`. The action is
audited. Reset link throttling is enforced by the web reset flow.

### DELETE `/users/{user}`

Soft deletes the account after revoking access, retaining the audit trail. The last
active administrator guards apply. Returns `data: null`.

### Notifications and devices

Full details, payload rules and the VAPID/queue setup live in
[NOTIFICATIONS.md](NOTIFICATIONS.md).

| Method | URI | Notes |
| --- | --- | --- |
| GET | `/notifications` | Paginated inbox of the token's account. `unread`, `category`, `per_page` (1–50). |
| GET | `/notifications/unread-count` | `{"count": n}`. |
| POST | `/notifications/{id}/read` | Owner only; `404` for anyone else's row. |
| POST | `/notifications/read-all` | Only the caller's unread rows. |
| GET | `/notifications/preferences` | Category × channel matrix; missing rows default to enabled. |
| PUT | `/notifications/preferences` | Body `{"preferences":[{"category":"fees","channel":"webpush","enabled":false}]}`. |
| POST | `/notifications/devices` | Register an Expo token (`platform` android/ios, `token`, optional `device_name`, `app_version`). Re-registering an existing token returns `200` with the same id and moves it to the caller. |
| DELETE | `/notifications/devices/{id}` | Revokes the caller's device; `404` otherwise. |

Notification items are serialised as
`{id, category, title, message, icon, url, is_read, read_at, created_at}`. The recipient is
always taken from the bearer token; a `user_id` in the request body is ignored.

## 7. Examples

```bash
# Sign in
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edunest.test","password":"Demo@1234","device_name":"curl"}'

# List users in the authenticated school
curl -s "http://127.0.0.1:8000/api/v1/users?status=active&sort=name&direction=asc" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# Fetch the school profile
curl -s http://127.0.0.1:8000/api/v1/school \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"

# Toggle account status
curl -s -X PATCH http://127.0.0.1:8000/api/v1/users/9/status \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN"
```

## 8. Planned endpoint families

Phase 2+: `/students`, `/admissions`, `/enrollments`, `/teachers`,
`/employees`, `/academic-sessions`, `/classes`, `/sections`, `/subjects`, `/holidays`;
Phase 3: `/attendance`, `/routines`, `/homework`, `/notices`; Phase 4: `/invoices`,
`/payments`, `/receipts`, `/dues`, `/expenses`, `/cash-transactions`; Phase 5: `/exams`,
`/marks`, `/results`; Phase 6: `/reports`. They will reuse the envelope, pagination,
policies and school scoping defined here.

The exam/results workflow (exams, papers, grade scale, marks, tabulation, publish
snapshots and report cards) is implemented as Blade screens under `/admin/exams` and
`/student/results`; its `/exams`, `/marks` and `/results` API families remain planned.

The admission application desk (registration, applicant workspace, submit/track and the
office decision) is implemented as Blade screens under `/register`, `/admissions` and
`/admin/admissions` only. There is no token-authenticated route for it: those pages are
session-guarded, and an `Accept: application/json` request to them returns `401` when
signed out and `403` for an account from another workspace. An `/admissions` API family
would need its own signup/verification design before it can be exposed.

Notices are Blade screens at `/admin/notices` plus one unauthenticated JSON read route,
`GET /website/live`, which the landing page polls every 20 seconds. It returns only
aggregate counts (`students`, `teachers`, `classes`, `subjects`) and up to four website
audiences. It is throttled by IP (`website-live`, 60/min) and is not part of the API v1
envelope.

The student portal is rendered Blade under `/student` and uses the web session; it has
no API surface in v1, and no parent/guardian portal is planned.

Financial write endpoints will additionally require an `Idempotency-Key` (or body
`idempotency_key`) and return the original receipt on replay. The staff fee
workflow (invoices, dues, payments, receipts, reversals) is currently Blade-only
under `/admin/fees`; the `/invoices`, `/payments`, `/receipts` and `/dues` API
families remain planned.
