-- =====================================================================
-- EduNest / Dreamroots Millenium School
-- Fix for: POST /register -> 500
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'mobile_number'
--   Live schema: irvrzogs_dreamroots
--
-- Hand-written equivalent of the pending migration
--   2026_11_01_130000_update_users_and_students_for_student_auth
--
-- ---------------------------------------------------------------------
-- v3 -- WHAT CHANGED, AND WHY
--
-- You have now hit two dead-end errors from this file:
--   #1109 Unknown table 'users' in information_schema  (wrong phpMyAdmin node)
--   #1054 Unknown column 'mobile_number' in 'SELECT'   (1f, before the
--         column exists -- a comment telling you to skip it is not a fix)
--
-- Both are gone in this version.
--   * Every table is written as irvrzogs_dreamroots.<table>, so the
--     statement can only ever touch the live schema. Which node you are
--     clicked onto no longer matters.
--   * Every statement that could refer to something that may not exist yet
--     checks information_schema first and runs a SELECT notice instead.
--     Nothing in this file red-boxes because a column, an index or a
--     cleanup step is absent -- it tells you what it skipped and why.
--   * The unique-index adds in 3e now check for duplicate rows themselves
--     and print "BLOCKED: ..." instead of throwing error 1062 at you.
--
-- The only errors this file can still produce mean something is genuinely
-- wrong: an unknown schema (#1049), a read-only user (#1044/#1142), or a
-- key the host cannot index. See IF IT WENT WRONG at the bottom.
-- ---------------------------------------------------------------------
--
-- HOW TO USE IT
--   Work one SECTION at a time, top to bottom. Sections 2 and 3 read your
--   data, so do not paste the whole file in one go. Re-running any section
--   is safe: the guards make it a no-op if the work is already done.
--
--   Before anything else: back the database up.
--   cPanel -> Backups -> Download a MySQL Database Backup, or on the shell:
--   mysqldump -u <db_user> -p irvrzogs_dreamroots > before_mobile_auth.sql
--
-- Rehearsed three times against a clone of the dev database built under the
-- name irvrzogs_dreamroots: virgin-but-dirty, half-applied, and
-- already-complete. Zero unexpected errors in all three.
-- =====================================================================


-- =====================================================================
-- SECTION 0 - PROVE YOU ARE POINTED AT THE RIGHT DATABASE
-- =====================================================================

-- 0a. Which node is phpMyAdmin on? Allowed to say NULL or information_schema
--     now -- everything below names the schema itself.
SELECT DATABASE() AS node_you_are_clicked_on;

-- 0b. Read the live schema by name. "Unknown database" or "access denied"
--     here means STOP: the prefix in this file is wrong, or this MySQL user
--     cannot reach it. Nothing below has run yet.
SELECT COUNT(*) AS users_in_live_schema,
       SUM(deleted_at IS NOT NULL) AS soft_deleted
FROM irvrzogs_dreamroots.users;

-- 0c. It must see the students table too.
SELECT COUNT(*) AS students_in_live_schema
FROM irvrzogs_dreamroots.students;

-- 0d. You need write access or section 2 fails with #1142. Look for
--     UPDATE / ALTER / INSERT / CREATE / INDEX in the Grants line.
SHOW GRANTS FOR CURRENT_USER();


-- =====================================================================
-- SECTION 1 - LOOK AT THE DATA (read-only, always safe to run)
-- =====================================================================

-- 1a. Is the migration already recorded? A row here means artisan already
--     applied it and you can stop.
SELECT migration, batch
FROM irvrzogs_dreamroots.migrations
WHERE migration = '2026_11_01_130000_update_users_and_students_for_student_auth';

-- 1b. Which of the two new columns already exist -- a previous half-finished
--     run would have left users.mobile_number behind.
SELECT table_name, column_name, column_type
FROM information_schema.columns
WHERE table_schema = 'irvrzogs_dreamroots'
  AND column_name = 'mobile_number'
  AND table_name IN ('users', 'students');

-- 1c. Which of the three indexes already exist.
SELECT table_name, index_name
FROM information_schema.statistics
WHERE table_schema = 'irvrzogs_dreamroots'
  AND index_name IN ('users_phone_unique', 'users_mobile_number_unique',
                     'students_mobile_number_index')
GROUP BY table_name, index_name;

-- 1d. Blocking hazard 1: rows whose phone is an empty string. Two or more
--     of these would abort users_phone_unique.
SELECT COUNT(*) AS empty_phone_rows, GROUP_CONCAT(id) AS ids
FROM irvrzogs_dreamroots.users
WHERE phone = '';

-- 1e. Blocking hazard 2: active accounts sharing a phone number. Any row
--     here would abort users_phone_unique.
SELECT phone, COUNT(*) AS how_many, GROUP_CONCAT(id) AS user_ids
FROM irvrzogs_dreamroots.users
WHERE deleted_at IS NULL AND phone IS NOT NULL AND phone <> ''
GROUP BY phone
HAVING how_many > 1
ORDER BY how_many DESC;

-- 1f. Blocking hazard 3: active accounts sharing a mobile number.
--     Self-guarded -- reports "nothing to check" while the column is
--     absent, which is the normal case on a database that has never been
--     touched. This was the statement that threw #1054 at you.
SET @n1f := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'irvrzogs_dreamroots'
               AND table_name = 'users'
               AND column_name = 'mobile_number');
SET @s1f := IF(@n1f = 0,
  'SELECT ''users.mobile_number does not exist yet - nothing to check'' AS skipped_1f',
  'SELECT mobile_number, COUNT(*) AS how_many, GROUP_CONCAT(id) AS user_ids FROM irvrzogs_dreamroots.users WHERE deleted_at IS NULL AND mobile_number IS NOT NULL AND CHAR_LENGTH(mobile_number) > 0 GROUP BY mobile_number HAVING how_many > 1 ORDER BY how_many DESC');
PREPARE stmt_1f FROM @s1f;
EXECUTE stmt_1f;
DEALLOCATE PREPARE stmt_1f;

-- 1g. The same duplicate check for soft-deleted accounts. Section 3c nulls
--     these out the way the migration does, so they are not a hazard.
SELECT phone, COUNT(*) AS how_many, GROUP_CONCAT(id) AS user_ids
FROM irvrzogs_dreamroots.users
WHERE deleted_at IS NOT NULL AND phone IS NOT NULL AND phone <> ''
GROUP BY phone
HAVING how_many > 1;

-- 1h. How many accounts have no email at all? Section 3a makes the email
--     column nullable, which is what lets a pupil register by phone.
SELECT COUNT(*) AS null_email_rows
FROM irvrzogs_dreamroots.users
WHERE email IS NULL;


-- =====================================================================
-- SECTION 2 - CLEAN THE DATA
--
-- 2a and 2b are harmless when nothing matches, so run both. 2c self-skips
-- while users.mobile_number does not exist.
--
-- All three keep the LOWEST id in each duplicate group and blank the newer
-- ones, because the older account is the one that has been signing in.
-- Read the id lists from 1e/1f first and confirm you are happy with the
-- survivor -- change "a.id > b.id" to "a.id < b.id" to let the newest win.
-- =====================================================================

-- 2a. Empty-string phone -> NULL. A unique index tolerates several NULLs;
--     it does not tolerate several ''.
UPDATE irvrzogs_dreamroots.users SET phone = NULL WHERE phone = '';

-- 2b. De-duplicate phone among active accounts.
UPDATE irvrzogs_dreamroots.users a
JOIN irvrzogs_dreamroots.users b
  ON a.phone = b.phone
 AND a.id > b.id
 AND a.deleted_at IS NULL
 AND b.deleted_at IS NULL
 AND a.phone IS NOT NULL
 AND a.phone <> ''
SET a.phone = NULL;

-- 2c. De-duplicate mobile_number among active accounts. Self-guarded.
SET @n2c := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'irvrzogs_dreamroots'
               AND table_name = 'users'
               AND column_name = 'mobile_number');
SET @s2c := IF(@n2c = 0,
  'SELECT ''users.mobile_number does not exist yet - nothing to clean'' AS skipped_2c',
  'UPDATE irvrzogs_dreamroots.users a JOIN irvrzogs_dreamroots.users b ON a.mobile_number = b.mobile_number AND a.id > b.id AND a.deleted_at IS NULL AND b.deleted_at IS NULL AND a.mobile_number IS NOT NULL AND CHAR_LENGTH(a.mobile_number) > 0 SET a.mobile_number = NULL');
PREPARE stmt_2c FROM @s2c;
EXECUTE stmt_2c;
DEALLOCATE PREPARE stmt_2c;

-- 2d. Proof that you are clear to continue: the first two must return 0.
SELECT COUNT(*) AS still_empty
FROM irvrzogs_dreamroots.users
WHERE phone = '';

SELECT COUNT(*) AS still_dupe_phones
FROM (
  SELECT phone FROM irvrzogs_dreamroots.users
  WHERE deleted_at IS NULL AND phone IS NOT NULL AND phone <> ''
  GROUP BY phone HAVING COUNT(*) > 1
) d;

SET @n2d := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'irvrzogs_dreamroots'
               AND table_name = 'users'
               AND column_name = 'mobile_number');
SET @s2d := IF(@n2d = 0,
  'SELECT 0 AS still_dupe_mobiles -- column absent, so nothing can collide',
  'SELECT COUNT(*) AS still_dupe_mobiles FROM (SELECT mobile_number FROM irvrzogs_dreamroots.users WHERE deleted_at IS NULL AND mobile_number IS NOT NULL AND CHAR_LENGTH(mobile_number) > 0 GROUP BY mobile_number HAVING COUNT(*) > 1) d');
PREPARE stmt_2d FROM @s2d;
EXECUTE stmt_2d;
DEALLOCATE PREPARE stmt_2d;


-- =====================================================================
-- SECTION 3 - BUILD THE SCHEMA
--
-- The migration, in the migration's own order. Each step checks whether
-- its own object is already there and prints a notice instead of failing,
-- so you can paste the whole section whatever state the database is in.
-- =====================================================================

-- 3a. users.email becomes nullable. Without this, a pupil who registers
--     with only a phone number cannot be inserted.
SET @n3a := (SELECT IS_NULLABLE FROM information_schema.columns
             WHERE table_schema = 'irvrzogs_dreamroots'
               AND table_name = 'users'
               AND column_name = 'email');
SET @s3a := IF(@n3a = 'YES',
  'SELECT ''users.email is already nullable'' AS skipped_3a',
  'ALTER TABLE irvrzogs_dreamroots.users MODIFY email VARCHAR(255) NULL DEFAULT NULL');
PREPARE stmt_3a FROM @s3a;
EXECUTE stmt_3a;
DEALLOCATE PREPARE stmt_3a;

-- 3b. users.mobile_number
SET @n3b := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'irvrzogs_dreamroots'
               AND table_name = 'users'
               AND column_name = 'mobile_number');
SET @s3b := IF(@n3b > 0,
  'SELECT ''users.mobile_number already exists'' AS skipped_3b',
  'ALTER TABLE irvrzogs_dreamroots.users ADD COLUMN mobile_number VARCHAR(30) NULL AFTER phone');
PREPARE stmt_3b FROM @s3b;
EXECUTE stmt_3b;
DEALLOCATE PREPARE stmt_3b;

-- 3c. Soft-deleted accounts lose their contact numbers, exactly as the
--     migration does. This frees any phone a deleted user was hoarding.
UPDATE irvrzogs_dreamroots.users
SET phone = NULL, mobile_number = NULL
WHERE deleted_at IS NOT NULL;

-- 3d. Carry each active account's phone across as its mobile_number.
UPDATE irvrzogs_dreamroots.users
SET mobile_number = phone
WHERE deleted_at IS NULL
  AND phone IS NOT NULL AND phone <> ''
  AND mobile_number IS NULL;

-- 3e. The two unique indexes. Checks for duplicates first and prints
--     BLOCKED instead of throwing 1062.
SET @n3e1 := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = 'irvrzogs_dreamroots'
                AND table_name = 'users'
                AND index_name = 'users_phone_unique');
SET @d3e1 := (SELECT COUNT(*) FROM (SELECT phone FROM irvrzogs_dreamroots.users
              WHERE phone IS NOT NULL AND phone <> '' AND deleted_at IS NULL
              GROUP BY phone HAVING COUNT(*) > 1) d);
SET @s3e1 := IF(@n3e1 > 0,
  'SELECT ''users_phone_unique already present'' AS skipped_3e1',
  IF(@d3e1 > 0,
    'SELECT ''BLOCKED: duplicate phones remain. Run 2b, re-check 1e, then run 3e again.'' AS blocked_3e1',
    'ALTER TABLE irvrzogs_dreamroots.users ADD UNIQUE users_phone_unique (phone)'));
PREPARE stmt_3e1 FROM @s3e1;
EXECUTE stmt_3e1;
DEALLOCATE PREPARE stmt_3e1;

SET @n3e2 := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = 'irvrzogs_dreamroots'
                AND table_name = 'users'
                AND index_name = 'users_mobile_number_unique');
SET @d3e2 := (SELECT COUNT(*) FROM (SELECT mobile_number FROM irvrzogs_dreamroots.users
              WHERE mobile_number IS NOT NULL AND CHAR_LENGTH(mobile_number) > 0
                AND deleted_at IS NULL
              GROUP BY mobile_number HAVING COUNT(*) > 1) d);
SET @s3e2 := IF(@n3e2 > 0,
  'SELECT ''users_mobile_number_unique already present'' AS skipped_3e2',
  IF(@d3e2 > 0,
    'SELECT ''BLOCKED: duplicate mobile numbers remain. Run 2c, then run 3e again.'' AS blocked_3e2',
    'ALTER TABLE irvrzogs_dreamroots.users ADD UNIQUE users_mobile_number_unique (mobile_number)'));
PREPARE stmt_3e2 FROM @s3e2;
EXECUTE stmt_3e2;
DEALLOCATE PREPARE stmt_3e2;

-- 3f. The students side: a column and a plain (non-unique) index, so there
--     is no duplicate-entry risk here.
SET @n3f1 := (SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = 'irvrzogs_dreamroots'
                AND table_name = 'students'
                AND column_name = 'mobile_number');
SET @s3f1 := IF(@n3f1 > 0,
  'SELECT ''students.mobile_number already exists'' AS skipped_3f1',
  'ALTER TABLE irvrzogs_dreamroots.students ADD COLUMN mobile_number VARCHAR(30) NULL AFTER phone');
PREPARE stmt_3f1 FROM @s3f1;
EXECUTE stmt_3f1;
DEALLOCATE PREPARE stmt_3f1;

UPDATE irvrzogs_dreamroots.students
SET mobile_number = phone
WHERE phone IS NOT NULL AND phone <> '' AND mobile_number IS NULL;

SET @n3f2 := (SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = 'irvrzogs_dreamroots'
                AND table_name = 'students'
                AND index_name = 'students_mobile_number_index');
SET @s3f2 := IF(@n3f2 > 0,
  'SELECT ''students_mobile_number_index already present'' AS skipped_3f2',
  'ALTER TABLE irvrzogs_dreamroots.students ADD INDEX students_mobile_number_index (mobile_number)');
PREPARE stmt_3f2 FROM @s3f2;
EXECUTE stmt_3f2;
DEALLOCATE PREPARE stmt_3f2;


-- =====================================================================
-- SECTION 4 - TELL LARAVEL THE MIGRATION HAS RUN
--
-- 4b self-skips if the row is already there, so run both lines.
-- =====================================================================

-- 4a. Check.
SELECT COUNT(*) AS already_recorded
FROM irvrzogs_dreamroots.migrations
WHERE migration = '2026_11_01_130000_update_users_and_students_for_student_auth';

-- 4b. Insert, only if it is not recorded.
SET @n4b := (SELECT COUNT(*) FROM irvrzogs_dreamroots.migrations
             WHERE migration = '2026_11_01_130000_update_users_and_students_for_student_auth');
SET @s4b := IF(@n4b > 0,
  'SELECT ''migration already recorded in migrations table'' AS skipped_4b',
  'INSERT INTO irvrzogs_dreamroots.migrations (migration, batch) SELECT ''2026_11_01_130000_update_users_and_students_for_student_auth'', MAX(batch) + 1 FROM irvrzogs_dreamroots.migrations');
PREPARE stmt_4b FROM @s4b;
EXECUTE stmt_4b;
DEALLOCATE PREPARE stmt_4b;


-- =====================================================================
-- SECTION 5 - VERIFY
-- =====================================================================

-- 5a. The exact query that produced the 500. Self-guarded, so on an
--     un-migrated database it reports the column is missing rather than
--     throwing the same #1054 the live site threw.
SET @n5a := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'irvrzogs_dreamroots'
               AND table_name = 'users'
               AND column_name = 'mobile_number');
SET @s5a := IF(@n5a = 0,
  'SELECT ''users.mobile_number still missing - section 3b did not run'' AS blocked_5a',
  'SELECT COUNT(*) AS aggregate FROM irvrzogs_dreamroots.users WHERE mobile_number = ''01810902817'' AND deleted_at IS NULL');
PREPARE stmt_5a FROM @s5a;
EXECUTE stmt_5a;
DEALLOCATE PREPARE stmt_5a;

-- 5b. Final shape: both columns present, all three indexes present.
SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = 'irvrzogs_dreamroots'
  AND column_name = 'mobile_number'
  AND table_name IN ('users', 'students');

SELECT table_name, index_name
FROM information_schema.statistics
WHERE table_schema = 'irvrzogs_dreamroots'
  AND index_name IN ('users_phone_unique', 'users_mobile_number_unique',
                     'students_mobile_number_index')
GROUP BY table_name, index_name;

-- 5c. users.email really is nullable now.
SELECT column_name, is_nullable
FROM information_schema.columns
WHERE table_schema = 'irvrzogs_dreamroots'
  AND table_name = 'users' AND column_name = 'email';

-- 5d. A roll-up of the whole job. Six "1" values means done.
SELECT
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = 'irvrzogs_dreamroots' AND table_name = 'users'
      AND column_name = 'mobile_number')              AS users_column,
  (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = 'irvrzogs_dreamroots' AND table_name = 'students'
      AND column_name = 'mobile_number')              AS students_column,
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = 'irvrzogs_dreamroots' AND table_name = 'users'
      AND index_name = 'users_phone_unique') > 0      AS phone_unique,
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = 'irvrzogs_dreamroots' AND table_name = 'users'
      AND index_name = 'users_mobile_number_unique') > 0 AS mobile_unique,
  (SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = 'irvrzogs_dreamroots' AND table_name = 'students'
      AND index_name = 'students_mobile_number_index') > 0 AS students_index,
  (SELECT COUNT(*) FROM irvrzogs_dreamroots.migrations
    WHERE migration = '2026_11_01_130000_update_users_and_students_for_student_auth') AS recorded;

-- 5e. Last, and the only proof that counts: open
--     https://dreamrootsmilleniumschool.com/register in a browser and
--     submit a real pupil sign-up. The fatal should be gone and the
--     account should land on the pending-approval list.


-- =====================================================================
-- IF IT WENT WRONG
--
-- * "BLOCKED: duplicate phones remain" / "duplicate mobile numbers remain"
--   Section 3e did its job: it refused to add an index that would have
--   failed. Run 2a and 2b (and 2c if it reported mobile numbers), re-check
--   with 1e/1f, then run 3e again.
--
-- * "skipped_..." in any result
--   Normal. That object already exists, or the column it would have looked
--   at has not been created yet. Nothing went wrong.
--
-- * "#1049 Unknown database 'irvrzogs_dreamroots'"
--   The live schema is named differently from what Laravel reported. List
--   them with SHOW DATABASES; then find-and-replace the prefix in this file
--   before going further.
--
-- * "#1044" or "#1142" access denied / command denied for user
--   That phpMyAdmin login cannot write to that schema. Use the cPanel
--   database owner, or attach the user under
--   cPanel -> MySQL Databases -> Add User To Database.
--
-- * "Specified key was too long; max key length is 767 bytes"
--   Your live users.phone is wider than this build's varchar(30) and the
--   host cannot index it. Tell me and I will hand you the narrowing
--   statement; do not guess at it.
--
-- * Do NOT run "php artisan migrate:rollback" to undo this. The
--   migration's down() restores email as NOT NULL, which fails outright if
--   any account has a null email (see 1h) and would take the site down
--   harder.
-- =====================================================================
