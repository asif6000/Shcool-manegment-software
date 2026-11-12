/*
 * EduNest production class-videos table — MariaDB-safe, one paste, re-runnable.
 *
 * The new "Class videos" screen (admin posts a class-wise lesson video, either
 * as a YouTube link or as an uploaded file, and optionally shows it on the
 * public landing page) reads and writes a table that does not exist on the live
 * database yet. Until this runs, GET /admin/class-videos and the landing page
 * both fail with:
 *   Base table or view not found: 1146  Table '...class_videos' doesn't exist
 *
 * One table only: class_videos          (migration 2026_10_31_100000)
 * No existing table is touched. Nothing here drops, alters, deletes or
 * rewrites any row of any live table.
 *
 * FOREIGN KEY clauses are deliberately left out of the CREATE TABLE: hand-run
 * key clauses are the statements that fail most often on cPanel (#150/#1452)
 * and the app joins by id, not by database constraints, so an oddly ordered or
 * partly empty live database cannot abort the install. Optional foreign keys
 * are parked in the block at the very end.
 *
 * MariaDB accepts IF NOT EXISTS on CREATE TABLE, so the whole body can be
 * pasted again after a partial run without stopping. Block comments only, so a
 * paste that loses its newlines still parses.
 */

CREATE TABLE IF NOT EXISTS `class_videos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NULL,
  `subject_id` bigint(20) unsigned NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NULL,
  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `youtube_url` varchar(300) COLLATE utf8mb4_unicode_ci NULL,
  `youtube_id` varchar(16) COLLATE utf8mb4_unicode_ci NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
  `file_name` varchar(160) COLLATE utf8mb4_unicode_ci NULL,
  `file_size` int(10) unsigned NULL,
  `show_on_website` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_videos_academic_session_id_foreign` (`academic_session_id`),
  KEY `class_videos_class_id_foreign` (`class_id`),
  KEY `class_videos_section_id_foreign` (`section_id`),
  KEY `class_videos_subject_id_foreign` (`subject_id`),
  KEY `class_videos_created_by_foreign` (`created_by`),
  KEY `class_videos_website_index` (`school_id`,`show_on_website`,`created_at`),
  KEY `class_videos_period_index` (`school_id`,`class_id`,`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* Read-only checks: run these last and compare against what you expect.        */
/* The table must have 19 columns and the seven keys listed above.               */
/* -------------------------------------------------------------------------- */

SHOW TABLES LIKE 'class_videos';

SHOW COLUMNS FROM `class_videos`;

SHOW INDEX FROM `class_videos`;

SELECT
  (SELECT COUNT(*) FROM `class_videos`) AS video_rows,
  (SELECT COUNT(*) FROM `class_videos` WHERE `show_on_website` = 1) AS website_rows,
  (SELECT COUNT(*) FROM `class_videos` WHERE `source` = 'youtube') AS youtube_rows,
  (SELECT COUNT(*) FROM `class_videos` WHERE `source` = 'upload') AS upload_rows;

/* -------------------------------------------------------------------------- */
/* Optional: the database-level links the migrated schema carries. The screen    */
/* works without them. Apply one statement at a time, only after the table       */
/* above exists; if the host complains, leave them out.                          */
/*
ALTER TABLE `class_videos`
  ADD CONSTRAINT `class_videos_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `class_videos_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  ADD CONSTRAINT `class_videos_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `class_videos_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `class_videos_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `class_videos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
*/

/* -------------------------------------------------------------------------- */
/* Optional: record the migration so a later `artisan migrate` on the live       */
/* server is a no-op instead of a duplicate-table error. Run it only if you       */
/* normally keep the `migrations` table up to date by hand. The WHERE NOT EXISTS   */
/* guard makes it safe to paste twice.                                            */
/*
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_31_100000_create_class_videos_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_31_100000_create_class_videos_table');

SELECT COUNT(*) AS recorded FROM `migrations`
WHERE `migration` = '2026_10_31_100000_create_class_videos_table';
*/
