-- ============================================================================
-- ONE-TIME support script: restore admin login for
--   suyad21122015@gmail.com
--
-- Temporary password after running this script:  DreamRoots@2026
-- The account is forced to choose a new password at first sign-in.
-- Delete this file from the server after it has been used.
--
-- What it does (safe, repeatable):
--   1. Creates the single `schools` row when the database has none, so the
--      account can pass the "linked to a school" check.
--   2. Shows the current state of that account (read-only).
--   3. Ensures the `school_admin` role exists and holds every permission.
--   4. Creates the account if it is missing.
--   5. Clears soft-delete, re-activates it, links it to the first school and
--      resets the password to the temporary one above.
--   6. Assigns `school_admin` if it is missing.
--   7. Verifies the result (read-only).
--
-- No attendance, student, fee or other school data is touched.
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @email := 'suyad21122015@gmail.com';
SET @temp_hash := '$2y$12$o.jVl610m8b.XcwbcU9.iOhVQQMaNlk5gyDTsYRh6MUfk4SySA0Tq'; -- DreamRoots@2026

-- ---------------------------------------------------------------------------
-- 0. Ensure the single-school record exists (safe to re-run)
--    Without a `schools` row the account cannot pass the school-context check.
-- ---------------------------------------------------------------------------
INSERT INTO `schools`
    (`id`, `name`, `short_name`, `code`, `slug`, `timezone`, `currency`,
     `locale`, `is_active`, `created_at`, `updated_at`)
SELECT 1, 'Dream Roots Millenium School', 'DRMS', 'DRMS',
       'dream-roots-millenium-school', 'Asia/Dhaka', 'BDT', 'en', 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `schools`);

SET @school := (SELECT `id` FROM `schools` ORDER BY `id` LIMIT 1);

-- ---------------------------------------------------------------------------
-- 1. Current state (read-only)
-- ---------------------------------------------------------------------------
SELECT 'account' AS section, `id`, `name`, `email`, `school_id`, `is_active`,
       `must_change_password`, `deleted_at`, `last_login_at`
FROM `users`
WHERE `email` = @email COLLATE utf8mb4_unicode_ci;

SELECT 'schools' AS section, `id`, `name` FROM `schools` ORDER BY `id`;

SELECT 'admin accounts' AS section, u.`id`, u.`email`, u.`is_active`
FROM `users` u
JOIN `model_has_roles` mr ON mr.`model_id` = u.`id` AND mr.`model_type` LIKE '%User'
JOIN `roles` r ON r.`id` = mr.`role_id`
WHERE r.`name` = 'school_admin'
ORDER BY u.`id`;

-- ---------------------------------------------------------------------------
-- 2. Make sure the role and its permissions exist
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'school_admin', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `roles` WHERE `name` = 'school_admin' AND `guard_name` = 'web'
);

INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
CROSS JOIN `roles` r
WHERE r.`name` = 'school_admin'
  AND r.`guard_name` = 'web'
  AND p.`guard_name` = 'web';

-- ---------------------------------------------------------------------------
-- 3. Create the account only if it does not exist
-- ---------------------------------------------------------------------------
INSERT INTO `users`
    (`school_id`, `name`, `email`, `email_verified_at`, `password`,
     `is_active`, `must_change_password`, `created_at`, `updated_at`)
SELECT @school, 'School Administrator', @email, NOW(), @temp_hash,
       1, 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = @email COLLATE utf8mb4_unicode_ci);

-- ---------------------------------------------------------------------------
-- 4. Repair state: active, linked to the school, fresh temporary password
-- ---------------------------------------------------------------------------
UPDATE `users`
SET `school_id` = COALESCE(`school_id`, @school),
    `password` = @temp_hash,
    `is_active` = 1,
    `must_change_password` = 1,
    `deleted_at` = NULL,
    `email_verified_at` = COALESCE(`email_verified_at`, NOW()),
    `updated_at` = NOW()
WHERE `email` = @email COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Assign school_admin (no duplicate rows)
--    model_type is copied from an existing role row when one exists, so the
--    script does not depend on backslash escaping.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`)
SELECT r.`id`,
       COALESCE(
           (SELECT mr.`model_type` FROM `model_has_roles` mr LIMIT 1),
           CONCAT('App', CHAR(92), 'Models', CHAR(92), 'User')
       ),
       u.`id`
FROM `roles` r
JOIN `users` u ON u.`email` = @email COLLATE utf8mb4_unicode_ci
WHERE r.`name` = 'school_admin'
  AND r.`guard_name` = 'web';

-- ---------------------------------------------------------------------------
-- 6. Verify (read-only)
-- ---------------------------------------------------------------------------
SELECT 'verify account' AS section, u.`id`, u.`name`, u.`email`, u.`school_id`,
       u.`is_active`, u.`must_change_password`, u.`deleted_at`
FROM `users` u
WHERE u.`email` = @email COLLATE utf8mb4_unicode_ci;

SELECT 'verify role' AS section, r.`name` AS role_name
FROM `model_has_roles` mr
JOIN `roles` r ON r.`id` = mr.`role_id`
JOIN `users` u ON u.`id` = mr.`model_id`
WHERE u.`email` = @email COLLATE utf8mb4_unicode_ci
  AND mr.`model_type` LIKE '%User';

SELECT 'verify permissions' AS section, COUNT(*) AS granted_permissions
FROM `role_has_permissions` rp
JOIN `roles` r ON r.`id` = rp.`role_id`
WHERE r.`name` = 'school_admin' AND r.`guard_name` = 'web';
