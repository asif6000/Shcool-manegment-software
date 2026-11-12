/*
 * DRMS production (irvrzogs_drms2026) — schema for Homework & Classwork.
 *
 * Why the live site returns 500 on /admin/homework:
 *   SQLSTATE[42S22] Unknown column 'category' in 'SELECT'
 * The merged screens query homework.category, but the live table predates the
 * migration. This file adds exactly what the code already expects. Nothing is
 * dropped, renamed or rewritten; every existing homework row becomes category
 * 'homework', which is what it is.
 *
 * Run it in cPanel -> phpMyAdmin -> SQL tab. The database does not have to be
 * selected first: every statement below names irvrzogs_drms2026 explicitly.
 *
 * Re-runnable: each ALTER is guarded by an information_schema check and runs
 * only when its change is missing, so a second run is a set of harmless
 * SELECTs. Written with PREPARE instead of ADD COLUMN IF NOT EXISTS so it
 * behaves the same on MariaDB and MySQL. No DELIMITER and no stored procedure
 * is needed - paste the whole file and press Go.
 */

/* -------------------------------------------------------------------------- */
/* 1. Before: read only. On a table that has not been migrated expect          */
/*    category_present = 0, due_date_nullable = 0, index_present = 0.          */
/* -------------------------------------------------------------------------- */

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
      AND COLUMN_NAME = 'category') AS category_present,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
      AND COLUMN_NAME = 'due_date' AND IS_NULLABLE = 'YES') AS due_date_nullable,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
      AND INDEX_NAME = 'homework_category_status_index') AS index_present,
  (SELECT COUNT(*) FROM irvrzogs_drms2026.`homework`) AS homework_rows;

/* -------------------------------------------------------------------------- */
/* 2. The two kinds of work in one table. 'homework' is the default, so every  */
/*    row that already exists is classified truthfully by the ALTER itself.    */
/* -------------------------------------------------------------------------- */

SET @has_category := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
    AND COLUMN_NAME = 'category'
);

SET @sql := IF(@has_category = 0,
  'ALTER TABLE `irvrzogs_drms2026`.`homework` ADD COLUMN `category` VARCHAR(16) NOT NULL DEFAULT ''homework'' AFTER `title`',
  'SELECT ''homework.category already present'' AS note');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

/* -------------------------------------------------------------------------- */
/* 3. One composite index serves both tabs of the teacher screen and the       */
/*    office monitor.                                                          */
/* -------------------------------------------------------------------------- */

SET @has_index := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
    AND INDEX_NAME = 'homework_category_status_index'
);

SET @sql := IF(@has_index = 0,
  'ALTER TABLE `irvrzogs_drms2026`.`homework` ADD INDEX `homework_category_status_index` (`school_id`, `category`, `status`)',
  'SELECT ''homework_category_status_index already present'' AS note');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

/* -------------------------------------------------------------------------- */
/* 4. Class work records what the class already did, so it has no deadline.    */
/* -------------------------------------------------------------------------- */

SET @due_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
    AND COLUMN_NAME = 'due_date'
  LIMIT 1
);

SET @sql := IF(COALESCE(@due_nullable, 'YES') = 'NO',
  'ALTER TABLE `irvrzogs_drms2026`.`homework` MODIFY COLUMN `due_date` DATE NULL DEFAULT NULL',
  'SELECT ''homework.due_date already nullable'' AS note');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

/* -------------------------------------------------------------------------- */
/* 5. The office screen is a read-only monitor, so it gets its own permission  */
/*    instead of the teacher's homework.view / homework.manage.                */
/* -------------------------------------------------------------------------- */

INSERT INTO irvrzogs_drms2026.`permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'homework.monitor', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM irvrzogs_drms2026.`permissions`
  WHERE `name` = 'homework.monitor' AND `guard_name` = 'web'
);

INSERT IGNORE INTO irvrzogs_drms2026.`role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM irvrzogs_drms2026.`permissions` p
CROSS JOIN irvrzogs_drms2026.`roles` r
WHERE p.`name` = 'homework.monitor' AND p.`guard_name` = 'web'
  AND r.`name` IN ('school_admin', 'principal') AND r.`guard_name` = 'web';

/* The permission list is cached in the database; clear it so the next request
   rebuilds it. No artisan runs on this host. */
DELETE FROM irvrzogs_drms2026.`cache`
WHERE `key` LIKE '%spatie.permission.cache%';

/* -------------------------------------------------------------------------- */
/* 6. After: read only. Expect category_present = 1, due_date_nullable = 1,    */
/*    index_present = 1, monitor_grants = 2, and every existing row counted    */
/*    as homework.                                                             */
/* -------------------------------------------------------------------------- */

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
      AND COLUMN_NAME = 'category') AS category_present,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
      AND COLUMN_NAME = 'due_date' AND IS_NULLABLE = 'YES') AS due_date_nullable,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026' AND TABLE_NAME = 'homework'
      AND INDEX_NAME = 'homework_category_status_index') AS index_present,
  (SELECT COUNT(*) FROM irvrzogs_drms2026.`role_has_permissions` rp
     JOIN irvrzogs_drms2026.`permissions` p ON p.id = rp.permission_id
     JOIN irvrzogs_drms2026.`roles` r       ON r.id = rp.role_id
    WHERE p.`name` = 'homework.monitor'
      AND r.`name` IN ('school_admin', 'principal')) AS monitor_grants,
  (SELECT COUNT(*) FROM irvrzogs_drms2026.`homework` WHERE `category` = 'homework') AS rows_homework,
  (SELECT COUNT(*) FROM irvrzogs_drms2026.`homework` WHERE `category` = 'classwork') AS rows_classwork;

/*
 * Rollback — only if the code is taken back as well. Keeping the column costs
 * nothing: older code never reads it and no row is lost.
 *
 * DELETE rp FROM irvrzogs_drms2026.`role_has_permissions` rp
 *   JOIN irvrzogs_drms2026.`permissions` p ON p.id = rp.permission_id
 * WHERE p.`name` = 'homework.monitor';
 * DELETE FROM irvrzogs_drms2026.`permissions` WHERE `name` = 'homework.monitor';
 * DELETE FROM irvrzogs_drms2026.`cache` WHERE `key` LIKE '%spatie.permission.cache%';
 * ALTER TABLE irvrzogs_drms2026.`homework` MODIFY COLUMN `due_date` DATE NOT NULL;
 * ALTER TABLE irvrzogs_drms2026.`homework`
 *   DROP INDEX `homework_category_status_index`,
 *   DROP COLUMN `category`;
 */
