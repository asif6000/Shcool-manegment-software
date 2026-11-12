# Notifications, Web Push and Expo devices

Phase 8 adds one notification pipeline with three delivery paths: the in-app inbox
(database notifications), browser Web Push (VAPID) and mobile device tokens (Expo push).
Everything is rendered from the same persisted record, so a user who ignores a browser
push still sees the item in the inbox.

## 1. Data model

| Table | Purpose |
| --- | --- |
| `notifications` | Laravel database notifications plus `category`, `dedupe_key` and `school_id`. `id` is a UUID. |
| `notification_preferences` | Per user, per `category` × `channel` on/off switch (`expo`, `webpush`). |
| `push_subscriptions` | Browser Web Push subscriptions: `endpoint`, `endpoint_hash`, `public_key`, `auth_token`, `content_encoding`, `is_active`, failure and usage counters. |
| `device_push_tokens` | Expo/mobile tokens: `platform`, `token`, `token_hash`, `device_name`, `app_version`, `is_active`, `revoked_at`. |
| `push_delivery_logs` | One row per (notification, subscription, channel): status `queued`/`sent`/`skipped`/`failed`/`invalid`, `attempts`, `response_code`, sanitised error. |

Sensitive columns (`auth_token`, `token`) are encrypted at rest by model casts.
`endpoint_hash` and `token_hash` are SHA-256 digests used for lookups and uniqueness —
the raw values are never indexed.

`dedupe_key` is unique. Re-firing the same school event for the same recipient is a
no-op instead of a duplicate inbox entry (for example re-marking the same attendance day).

## 2. Sending pipeline

```
domain service  ->  SchoolEventNotifier  ->  NotificationService::send()
                                                 |
                                                 +-> dedupe check (unique key)
                                                 +-> persist notifications row
                                                 +-> if webpush.enabled: SendWebPushNotification job (afterCommit)
```

`App\Notifications\SchoolEventNotification` carries a title, message, icon, url, category,
`dedupe_key` and — separately — `push_title` / `push_body`. The push variants are stored in
the notification payload so the queued job can deliver a lock-screen-safe message without
changing what the authenticated inbox shows.

`SendWebPushNotification` (`tries = 3`, `backoff = [10, 60, 300]`):

1. Returns immediately when `WEBPUSH_ENABLED=false`, when the notification no longer
   exists, when it belongs to a different user, or when the recipient's preference for
   that category is off.
2. Loads the recipient's active subscriptions (capped by `WEBPUSH_MAX_SUBSCRIPTIONS`).
3. Creates or reuses the delivery log row, so a retried job never re-sends a `sent`
   or `invalid` delivery.
4. Maps the push-service answer: `201` → `sent`, `410` → `invalid` + subscription
   deactivated, `400` → `failed` (permanent, no retry), `429`/`5xx`/transport error →
   `failed` + rethrow so the queue retries with backoff.

Run a worker wherever push is enabled:

```bash
php artisan queue:work --tries=3 --timeout=90
```

## 3. Lock-screen safety rules

Push text is visible on a locked device, so it must never contain personal or financial
detail. The pipeline enforces this by construction:

- Attendance pushes say *"You were marked absent on 02 Oct 2026"* — no student name in the
  guardian message, and no other student's identity.
- Fee pushes never include an amount; they link to the invoice page.
- Result pushes never include marks, GPA or position; they link to the result page.
- Notice pushes use the fixed line *"A new notice has been published. Open it to read
  the details."* �?" the notice text is only in the in-app inbox copy.
- A targeted administrator message always uses *"New message from the school office"* /
  *"Sign in to read the message."* on the lock screen. The administrator's title and body
  exist only in the in-app inbox.
- Routine pushes say only that the class routine changed; they name no subject, teacher
  or room. Classwork pushes say only that new classwork was shared.
- Every push carries a `url` that requires authentication; the data behind it is fetched
  after sign-in. The notice link is the documented exception: a notice addressed to
  `all`, `students` or `guardians` links to its public page (`/notices/{id}`), while an
  internal notice (`teachers`, `staff`) links to `/admin/notices`, because its public
  page would 404 by design.

Tests assert the absence of names, amounts and grades in `push_title` / `push_body`.

## 4. VAPID keys

Generate once, per environment:

```bash
php artisan webpush:vapid
```

Write the result into `.env`:

```dotenv
WEBPUSH_ENABLED=true
VAPID_SUBJECT="mailto:admin@example.edu"
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
WEBPUSH_TTL=86400
WEBPUSH_TIMEOUT=30
WEBPUSH_QUEUE=default
WEBPUSH_MAX_SUBSCRIPTIONS=10
```

The private key stays in `.env`. Never commit it, never print it, never copy it into a
config cache that is served over HTTP. `WebPushSender::enabled()` returns false when
either key is missing, and the job then logs a `skipped`/`not_configured` delivery
instead of throwing.

Service workers and the browser push API require a secure context: `localhost` is fine
for development, everything else must be HTTPS.

## 5. Browser subscription flow

`public/sw.js` is registered on authenticated pages. The bell dropdown offers push
enablement, which:

1. Calls `Notification.requestPermission()`.
2. Gets `serviceWorkerRegistration.pushManager.subscribe()` with the exported VAPID
   application public key.
3. POSTs `{endpoint, keys:{p256dh, auth}}` to `POST /notifications/push/subscribe`
   (throttled by `throttle:push-subscribe`).
4. The server stores the subscription against **the session user only** — the request
   body has no user field, and a re-subscribed endpoint follows the newly signed-in
   account.

`DELETE /notifications/push/unsubscribe` deactivates only the caller's subscription.
Per-user subscription count is capped by `WEBPUSH_MAX_SUBSCRIPTIONS`.

Endpoints are validated as absolute HTTPS URLs outside local development, and both keys
are required with `content_encoding` limited to `aes128gcm` or `aesgcm`.

## 6. HTTP surface

### Web (session, staff and student layouts)

| Method | URI | Notes |
| --- | --- | --- |
| GET | `/notifications` | Inbox, paginated, unread filter. |
| GET | `/notifications/feed` | JSON for the bell dropdown: latest 10 items + `unread_count`. |
| POST | `/notifications/{notification}/read` | Owner only; `404` otherwise. |
| POST | `/notifications/read-all` | Affects the caller's inbox only. |
| PUT | `/notifications/preferences` | Category × channel matrix update. |
| POST | `/notifications/push/subscribe` | Register a browser subscription. |
| DELETE | `/notifications/push/unsubscribe` | Deactivate the caller's subscription for this endpoint. |
| GET | `/admin/notifications/compose` | Staff compose screen (`notices.manage`). |
| POST | `/admin/notifications/compose` | Send a targeted campaign; the form carries a one-time `campaign_id` for dedupe. |

### API v1 (Sanctum bearer token)

| Method | URI | Notes |
| --- | --- | --- |
| GET | `/api/v1/notifications` | Paginated inbox for the token's account. |
| GET | `/api/v1/notifications/unread-count` | `{ "count": n }`. |
| POST | `/api/v1/notifications/{id}/read` | Owner only. |
| POST | `/api/v1/notifications/read-all` | Owner only. |
| GET | `/api/v1/notifications/preferences` | Current matrix, defaults to enabled. |
| PUT | `/api/v1/notifications/preferences` | Validates category and channel. |
| POST | `/api/v1/notifications/devices` | Register an Expo token (`android`/`ios`). |
| DELETE | `/api/v1/notifications/devices/{device}` | Revoke; owner only. |

Every endpoint derives the recipient from the authenticated account. A client-supplied
`user_id` is never read, and cross-account access returns `404` rather than `403` so that
existence is not leaked.

### Expo device tokens

`POST /api/v1/notifications/devices` body:

```json
{
  "platform": "android",
  "token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]",
  "device_name": "Pixel 8 Pro",
  "app_version": "1.4.2"
}
```

The token is validated against the Expo push-token shape, hashed for lookup, and stored
encrypted. Re-registering an existing token moves it to the new account and returns
`200` with the same id (a person who signs out and a colleague who signs in on the same
phone should not accumulate rows). Revoking sets `is_active = false` and `revoked_at`.

Registering a device does **not** send anything from this repository: no Expo project,
SDK or React Native code is included. A future mobile client calls these endpoints and
forwards tokens to its own Expo push gateway. The `expo` channel preference exists so
that client can honour per-category settings from the same matrix.

## 7. School events that notify today

| Event | Recipients | Category | Dedupe key |
| --- | --- | --- | --- |
| Student marked absent | the student account and each linked guardian | `attendance` | `attendance.absent:{student}:{date}:{user}` |
| Fee payment recorded | the student account | `fees` | `fee.payment:{payment}:{user}` |
| Fee reminder (scheduled) | the student account and each linked guardian | `fees` | `fee.reminder.{student or guardian}:{invoice}:{stage}:{due_date}:{user}` |
| Exam results published | students with a published result for that exam | `exams` | `exam.published:{exam}:{student}:{published_at}:{user}` |
| Targeted administrator message | selected audience or specific students, teachers or staff | `general` | `targeted:{campaign_id}:{user}` |
| Classwork shared | active students enrolled in the class/section for that session | `classwork` | `classwork.shared:{material}:{user}` |
| Class routine changed | active students in the slot's class/section and the assigned teacher | `routine` | `routine.{created or updated or deleted}:{slot}:{signature}:{user}` |
| Notice published | the notice audience — everyone, or the accounts linked to a student, guardian, teacher or employee record | `notices` | `notice.published:{notice}:{user}` |

Notice publishing is opt-in per save (`notify_audience`), never runs for a notice whose
`published_at` is still in the future, and excludes the account that published it.

### Targeted administrator messages

`/admin/notifications/compose` is available to accounts with `notices.manage` (School
Admin and Principal). The sender chooses students, teachers or staff, then either the
whole audience or specific active accounts. Cross-school and inactive account ids are
rejected/ignored, the sender is excluded, and a single request is capped at 500
recipients. The hidden `campaign_id` makes a double-submitted form idempotent through the
normal `dedupe_key` unique index.

The administrator's title and body are stored in the in-app inbox. The browser push is
deliberately generic, so sending sensitive text through this form cannot leak it onto a
lock screen.

### Fee reminders

`php artisan fees:send-reminders --days=3` notifies the student account and linked
guardians about open invoices due within three days or already overdue. It sends no
amount, and the `stage` (`due` or `overdue`) plus the due date are part of the dedupe key,
so running it daily never duplicates the same reminder. The scheduler entry runs it at
08:00 daily; use `--dry-run` to inspect the matched invoices without notifying anyone.

### Routine and classwork changes

Creating, updating or deleting a timetable slot notifies the students enrolled in that
class/section for the slot's session and the assigned teacher. A new classwork entry
notifies the students in that class/section; ordinary study materials do not. Both use
the `routine` and `classwork` preference categories.

Inactive accounts are skipped, guardians only receive events for students linked to them
in the same school, and one account that is both student and guardian receives a single
notification per event.

## 8. Testing

```bash
php artisan test --filter='Notification|Push|SchoolEvent|Routine|Classwork|FeeReminder'
```

`PushNotificationJobTest` binds a fake `WebPushSender`, so no HTTP request leaves the
test run. Transport failures are simulated by throwing from the fake, and the assertions
cover the masked error text (`https://…` is replaced with `[url]` before logging).

`TargetedNotificationTest`, `FeeReminderNotificationTest`, `RoutineNotificationTest` and
`ClassworkNotificationTest` cover audience scoping, cross-school rejection, idempotent
campaigns, reminder dedupe and the absence of private detail in push copy.
