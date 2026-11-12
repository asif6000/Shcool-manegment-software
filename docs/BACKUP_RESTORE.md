# Backup and Restore

The MySQL database is the source of truth. Uploaded logos/avatars and the `.env`
(especially `APP_KEY`) are also required for a complete recovery. Code is recovered from
the repository at the deployed commit.

## 1. What to back up

| Asset | Location | Why |
| --- | --- | --- |
| Database | MySQL `school_ms` | All school, user, audit and future financial records |
| Uploads | `public/uploads/` | School logos and user avatars (student/staff photos when added) |
| Environment | `.env` | Database credentials, `APP_KEY`, mail/SMS/gateway secrets |
| Code version | Git commit/tag or release archive | Exact application version matching the schema |
| Logs (optional) | `storage/logs/` | Incident investigation; short retention |

`APP_KEY` is critical: losing it invalidates encrypted sessions/cookies and any
encrypted columns added later. Store it in the same encrypted backup set, separately from
the database dump, and in a password manager.

## 2. Backup targets

Recommended starting point for a school deployment:

- **RPO:** 24 hours or better (daily full backup; more often during fee collection).
- **RTO:** 1–2 hours for a single-school installation.
- **Retention:** 14 daily, 8 weekly, 12 monthly, kept off the application server.
- **Storage:** encrypted, access-controlled, with at least one off-site copy.

During admission or fee-collection peaks, take an additional dump before bulk operations
and before every deployment.

## 3. Database dump

### Windows / XAMPP

```bat
set BACKUP_DIR=D:\backups\edunest
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

"C:\xampp\mysql\bin\mysqldump.exe" ^
  -u root ^
  --single-transaction --quick --routines --triggers ^
  --default-character-set=utf8mb4 --no-tablespaces ^
  school_ms > "%BACKUP_DIR%\school_ms_2026-10-26.sql"
```

Notes:

- `--single-transaction` gives a consistent InnoDB snapshot without locking the app.
- `--routines --triggers` preserves objects even though Phase 1 defines none; future
  phases may add them.
- Avoid putting the password on the command line. Create a restricted option file
  (e.g. `C:\secure\edunest.cnf`) and pass `--defaults-extra-file=...`.
- Schedule the command with Task Scheduler (daily, run whether the user is logged in or
  not) and have the task write a status line to a log.

### Linux

```bash
STAMP=$(date +%F_%H%M)
mysqldump --defaults-extra-file=/etc/edunest/backup.cnf \
  --single-transaction --quick --routines --triggers \
  --default-character-set=utf8mb4 --no-tablespaces \
  school_ms | gzip > /var/backups/edunest/school_ms_${STAMP}.sql.gz
```

Cron example (02:15 daily):

```
15 2 * * * /usr/bin/bash /usr/local/bin/edunest-backup.sh >> /var/log/edunest-backup.log 2>&1
```

### Uploads

Windows:

```bat
robocopy "C:\xampp\htdocs\dream\public\uploads" "D:\backups\edunest\uploads" /MIR /R:2 /W:5
```

Linux:

```bash
tar -czf /var/backups/edunest/uploads_${STAMP}.tar.gz -C /var/www/edunest/public uploads
```

Mirroring with `/MIR` deletes files removed at the source. That is usually desired, but
verify the backup destination before scheduling it.

## 4. Protect the backups

- Encrypt at rest (BitLocker/LUKS volume or 7-Zip AES-256/GPG).
- Restrict access to backup operators; dumps contain student/guardian PII and password
  hashes.
- Never commit dumps, `.env` or uploads to Git.
- Copy at least one generation off-site (encrypted object storage or offline media).
- Record each run in `backup_logs` when the Phase 7 backup command is introduced
  (`filename`, `disk`, `size_bytes`, `status`, `notes`).

## 5. Verify a backup

A backup is not valid until restored.

1. Create a scratch database:
   ```sql
   CREATE DATABASE school_ms_verify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import the dump:
   ```bat
   "C:\xampp\mysql\bin\mysql.exe" -u root school_ms_verify < "D:\backups\edunest\school_ms_2026-10-26.sql"
   ```
3. Check core tables and counts:
   ```sql
   SELECT COUNT(*) FROM schools;
   SELECT COUNT(*) FROM users;
   SELECT COUNT(*) FROM students;
   SELECT COUNT(*) FROM audit_logs;
   ```
4. Optionally point a staging `.env` at the scratch database and sign in with a known
   test account.
5. Drop the scratch database when finished.

Also test the uploads archive by extracting it to a temporary directory and confirming
recent files are present.

## 6. Restore procedure

> Restoring overwrites live data. Confirm the chosen backup and take a safety dump of the
> current database first.

1. Enable maintenance mode:
   ```bat
   C:\xampp\php\php.exe artisan down --secret="restore-2026-10-26"
   ```
2. Take a safety dump of the current database.
3. Restore the database. For an in-place restore:
   ```bat
   "C:\xampp\mysql\bin\mysql.exe" -u root -e "DROP DATABASE school_ms; CREATE DATABASE school_ms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   "C:\xampp\mysql\bin\mysql.exe" -u root school_ms < "D:\backups\edunest\school_ms_2026-10-26.sql"
   ```
   For a safer cutover, restore into `school_ms_restored`, verify it, then change
   `DB_DATABASE` in `.env` and keep the old database until acceptance.
4. Restore uploads to `public/uploads` with the correct ownership/permissions.
5. Restore the matching `.env` if the server was rebuilt. Keep the original `APP_KEY`
   associated with that database; otherwise encrypted cookies and encrypted columns will
   not decrypt. When in doubt, clear sessions after restore:
   ```sql
   DELETE FROM sessions;
   ```
6. Clear stale caches:
   ```bat
   C:\xampp\php\php.exe artisan optimize:clear
   ```
7. Confirm schema state:
   ```bat
   C:\xampp\php\php.exe artisan migrate:status
   ```
   The restored schema should match the deployed code. Do not run new migrations until
   the restored database is verified.
8. Bring the app up:
   ```bat
   C:\xampp\php\php.exe artisan up
   ```
9. Smoke-test: platform login, school login, dashboard counts, user list, school profile,
   audit trail, logo/avatar display, API login and school-scoped user list.

## 7. Restore scenarios

| Scenario | Action |
| --- | --- |
| Accidental user/school edit | Restore the database to a scratch instance, export the affected rows, reconcile manually; avoid a full overwrite if possible |
| Corrupted uploads | Restore the latest uploads archive; database paths remain valid if the same UUID files are recovered |
| Lost `APP_KEY` | If no encrypted columns exist, generate a new key and clear sessions; otherwise data encrypted with the old key is unrecoverable |
| Failed deployment | Restore the pre-deploy dump, deploy the previous commit, clear caches, restart workers |
| Lost server | Rebuild from repository + `.env` backup + database dump + uploads archive |
| Ransomware/compromise | Isolate the server, restore from an offline encrypted copy, rotate all credentials and `APP_KEY`, review audit logs |

## 8. Restore acceptance checklist

- [ ] Import completed without errors
- [ ] `schools`, `users`, `students`, `fee_invoices` (when present) counts match the backup
- [ ] Roles and permissions present; a School Admin can sign in
- [ ] The single `schools` row and its users/students are intact
- [ ] Audit trail visible and recent entries present
- [ ] Logos/avatars load
- [ ] Uploads directory not PHP-executable
- [ ] API login works; cross-school access denied
- [ ] Queue workers restarted; scheduler running
- [ ] Backups resume on schedule
- [ ] Restore time and result recorded

## 9. Things that are not backed up

- Cache and failed jobs (disposable; queues should be drained before major restores).
- Session rows (users sign in again; delete after restore).
- Demo data (regenerable with `migrate:fresh --seed` locally only).
- External gateway/SMS provider history (retain provider reports separately).
