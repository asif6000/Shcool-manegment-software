# Attendance

One engine writes every student attendance row, whichever method produced it.
This document describes the methods, their honest states, the data model, the
device contract, the reporting policy, and what is still required before a
biometric method can be called operational.

## 1. Methods

| Method | State today | Entry point |
| --- | --- | --- |
| Manual register | Ready, built in | `/admin/attendance/students` |
| ID card QR | Ready, built in | `/admin/attendance/qr` |
| Fingerprint | **Not configured** | `/admin/attendance/methods` |
| Face recognition | **Not configured** | `/admin/attendance/methods` |

No fingerprint or face manufacturer, SDK, API or protocol has been selected.
`config/attendance.php` therefore ships with an empty adapter map, and the
ingestion endpoint answers `503 not_configured` for every terminal until a real
adapter class is registered and verified against hardware.

The methods page derives its badges from server configuration and terminal
heartbeats. A status is never typed by hand:

- **Ready** — an adapter is registered for that kind and the terminal
  authenticated within `devices.heartbeat_minutes`.
- **Not configured** — no adapter is registered for that kind, or no terminal
  has been added yet.
- **Disconnected** — an adapter exists but the terminal has not checked in, or
  it was disabled.
- **Error** — the last recorded device error.

## 2. Shared rules

Every write goes through `App\Services\StudentAttendanceService`:

- One row per student per day (`unique school_id, student_id, attendance_date`).
  Re-saving a register updates rows; it never duplicates them.
- Status is one of `present`, `absent`, `late`, `leave`, `excused`.
- `source` records the method (`manual`, `qr`, `fingerprint`, `face`) and
  `source_ref` holds the card or device event reference.
- The day must fall inside the academic session, and a holiday needs an
  explicit override. A recorded holiday row needs the override flag.
- Teachers may only open and mark class/section pairs that appear on their
  timetable (`class_routines`). `school_admin` and `principal` are unrestricted
  (`attendance.unrestricted_roles`).
- Staff edits are audited (`student_attendance.updated`,
  `student_attendance.corrected`, `student_attendance.marked`,
  `student_attendance.scanned`, `student_attendance.device_event`).
- Changing a row that was produced by QR or a device (or one already corrected)
  requires a correction reason of at least five characters. The row is then
  locked (`locked_at`): no later QR scan or device punch can overwrite it.
- An absent student and their linked guardians receive the existing
  absence notification, once per absence.

## 3. Percentage policy

`App\Services\AttendancePolicy` is the single implementation used by the staff
report, the CSV export and the student portal.

- A **countable day** is a calendar day that is not a weekly holiday and not a
  recorded holiday.
- Weekly holidays default to Sunday–Thursday working days
  (`working_days = [1,2,3,4,5]` using a Saturday-first index, so Friday and
  Saturday are closed).
- `leave` and `excused` are counted separately and removed from the denominator.
- Attendance % = `(present + late) ÷ (countable days − leave − excused)`,
  rounded to one decimal.

Rows recorded on weekends or holidays remain visible for history but are
excluded from the rate and reported as "excluded" rows on the report.

## 4. ID cards (QR)

- A card token is `atn1_` followed by 64 hex characters from
  `random_bytes(32)`. Only its SHA-256 hash is stored.
- No student name, student ID or other personal data is inside the QR payload.
- The token is shown once, on the printable card. Reissuing revokes the old card
  immediately; revoking a lost card stops it working at once.
- Scans are verified server-side: token shape, active card, current enrollment,
  academic session, holiday, working day and the actor's class scope. The client
  never sends a student ID or a success flag.
- `POST /admin/attendance/qr/scan` is rate limited (`attendance-scan`, 60/min).
- Camera scanning uses the vendored `jsQR` library, warns when the page is not
  served over HTTPS, and always offers manual code entry.

## 5. Device contract (fingerprint / face)

The endpoint is vendor-neutral. A device posts punches; it never posts a
student ID, a template or an image.

```
POST /api/v1/attendance/events
X-Device-Id: <attendance_devices.id>
X-Device-Secret: <secret shown once at registration>
Content-Type: application/json

{
  "events": [
    {
      "external_ref": "device-event-10021",
      "user_id": "1001",
      "occurred_at": "2026-10-28T08:12:00+06:00",
      "meta": { "terminal": "main-gate" }
    }
  ]
}
```

Rules enforced by `AttendanceDeviceEventController`:

- HTTPS is required outside local/testing.
- Invalid or rotated credentials receive `401`.
- A kind with no registered adapter receives `503 not_configured`; no event is
  stored.
- The payload is validated (1–100 events, `external_ref`, `user_id`,
  `occurred_at`, optional scalar metadata).
- `school_id` is taken from the authenticated device; the body cannot select a
  school.
- A device user ID must be mapped to a student on that device. Unmapped IDs are
  stored as `rejected / unknown_device_user`; the server never guesses a match.
- Each event is idempotent on `(school_id, source, external_ref)`. A provider
  retry returns the first decision and never creates a second attendance row.
- Punches dated more than `devices.max_event_age_hours` (72) in the past or
  `devices.max_clock_skew_minutes` (15) in the future are refused.
- A punch may only mark `present`. It never overwrites a locked row, a `leave`
  or `excused` row, or a row that already counts as attended.
- Every event is stored in `attendance_events` with its decision and reason, so
  a missing mark can be explained on the methods page.
- Metadata keys that look like biometric payloads (image, template, embedding,
  vector, fingerprint, face, …) are dropped before storage.
- `POST /api/v1/attendance/events` is limited to 120 requests/minute per device.

Response:

```json
{
  "status": "accepted",
  "device": { "id": 3, "kind": "fingerprint" },
  "received": 1,
  "results": [
    {
      "external_ref": "device-event-10021",
      "status": "applied",
      "reason": null,
      "message": "Attendance marked for Ayesha Rahman.",
      "student": { "student_id": "ST-0007", "name": "Ayesha Rahman" }
    }
  ]
}
```

`status` per event is `applied`, `duplicate`, `skipped` (locked or protected
status) or `rejected` (holiday, non-working day, future date, stale event,
clock skew, unknown user, out of scope, not enrolled).

## 6. Device registry

`/admin/attendance/methods` (permission `attendance.devices.manage`) allows staff
to:

- register a terminal kind and name; the plain secret is shown once, only its
  SHA-256 hash is stored;
- rotate the secret (the old one stops working immediately);
- enable or disable a terminal;
- map a device user ID to a student of the same school, and remove stale
  mappings.

```php
// config/attendance.php
'drivers' => [
    'fingerprint' => env('ATTENDANCE_FINGERPRINT_DRIVER'),
    'face' => env('ATTENDANCE_FACE_DRIVER'),
],
'adapters' => [], // driver name => App\Services\Attendance\DeviceAdapter class
```

Environment variables:

| Variable | Purpose | Default |
| --- | --- | --- |
| `ATTENDANCE_FINGERPRINT_DRIVER` | Driver name for the fingerprint adapter | empty |
| `ATTENDANCE_FACE_DRIVER` | Driver name for the face adapter | empty |

Setting a driver name without adding a class to `attendance.devices.adapters`
still leaves the method **not configured**, which is intentional.

## 7. What is needed before a driver is written

Obtain the following from the manufacturer. Guessing any of these would produce
an integration that looks connected but is not.

1. Exact device model, firmware and whether it pushes events or must be polled.
2. Transport and protocol: HTTPS webhook, TCP socket, MQTT, SFTP export or SDK.
3. Authentication method and how request signatures are computed and verified.
4. Event payload schema with a stable event identifier for idempotency.
5. How device user IDs are created, exported and kept in sync.
6. Clock and timezone behaviour, NTP support, maximum acceptable delay.
7. Whether templates are stored on the device/vendor, and for how long.
8. Consent and retention terms for face templates, including minors.
9. Sandbox device access and a support contact.

An adapter then implements
`App\Services\Attendance\DeviceAdapter` (`driver`, `isConfigured`, `pull`,
`health`) and is registered in `attendance.devices.adapters`.

## 8. Face recognition safeguards

Face is not enabled. Before it can be, the school needs written guardian
consent and a lawful basis for processing a minor's biometric data, a documented
retention period with a deletion path, liveness checks where the hardware
supports them, restricted access, and a manual or QR fallback for every student.
Templates stay with the provider or terminal; this application stores only the
event outcome and an external reference, never a template or image.

## 9. Data model

| Table | Purpose |
| --- | --- |
| `student_attendances` | One row per student/day; `source`, `source_ref`, `locked_at`, `correction_reason`, `corrected_by`, `corrected_at` |
| `student_attendance_cards` | Hashed QR card tokens, issuance and revocation |
| `attendance_devices` | Terminal registry, hashed secret, status, last seen/sync, last error |
| `attendance_device_users` | Per-device mapping from device user ID to student |
| `attendance_events` | Idempotent log of every QR scan and device punch with its decision |

Migrations:

```
2026_10_28_130000_add_method_columns_to_student_attendances
2026_10_28_130100_create_attendance_devices_table
2026_10_28_130200_create_attendance_events_table
2026_10_28_130300_create_student_attendance_cards_table
2026_10_28_130400_create_attendance_device_users_table
```

All are additive. No existing attendance row is rewritten by the migrations;
existing rows default to `source = manual`.

## 10. Permissions

| Permission | Grants |
| --- | --- |
| `attendance.view` | Open the register, report and methods overview |
| `attendance.mark` | Save a register and scan cards |
| `attendance.report` | Monthly report and CSV export |
| `attendance.qr.manage` | Issue, print and revoke ID cards |
| `attendance.devices.manage` | Register terminals, rotate secrets, map users |

`school_admin` receives every permission; `principal` receives the attendance
set; `teacher` receives `view`, `mark` and `report` scoped by timetable.

## 11. Rollback

Run `php artisan migrate:rollback --step=5` to drop the four new tables and the
added columns. Rolling back removes device credentials, card tokens and the
event log; attendance rows themselves remain, but the `source` distinction and
correction metadata are lost. Export `attendance_events` first if the history is
needed.

Code rollback: remove the `/attendance/methods` and `/attendance/qr` routes,
`AttendanceMethodController`, `AttendanceQrController`,
`AttendanceDeviceEventController`, the new services/models, the new views and
the API route. No existing route or controller was renamed, so the manual
register and staff attendance keep working without them.

### Upgrading production without terminal access

`database/sql/attendance_migrations.sql` is the phpMyAdmin-importable equivalent
of the five migrations plus device permissions and the `migrations` bookkeeping
rows. It avoids `information_schema` and prepared statements because some cPanel
database users are denied access to `information_schema` (#1044); existence
checks use `SHOW` statements instead. The four tables use `IF NOT EXISTS` and
permission/migration rows use `NOT EXISTS`, so only the `student_attendances`
`ALTER` is non-repeatable: if it reports `#1060 Duplicate column name 'source'`,
that step is already applied — skip it and run the rest. Take a full export
first, import the file, and check the verification output at the bottom. Never
run `migrate:fresh`, `db:seed` or a data reset on production. If shell access is
available, `php artisan migrate --force` remains the preferred path.

## 12. Tests

- `tests/Feature/AttendanceTest.php` — bulk marking, re-marking, holidays,
  session bounds, cross-school denial, teacher timetable scope, correction
  reason and lock, percentage policy (leave/excused/weekend/holiday), report and
  CSV, staff attendance.
- `tests/Feature/AttendanceQrTest.php` — issuance and hashing, one-time print,
  scan/duplicate/idempotency, revoked and unknown cards, reissue invalidation,
  cross-school cards, teacher scope, manual fallback, school isolation.
- `tests/Feature/AttendanceDeviceTest.php` — NOT_CONFIGURED refusal, credential
  and rotation checks, mapped punch, retry idempotency, delayed and skewed
  events, locked/leave protection, unmapped user, payload validation, face
  staying unconfigured, honest dashboard states, mapping uniqueness and
  cross-school denial.

The device tests use `Tests\Fixtures\Adapters\FakeFingerprintAdapter`, a clearly
labelled test double. They exercise the ingestion pipeline only; they are not,
and must not be presented as, tests against real biometric hardware.
