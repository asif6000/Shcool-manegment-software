-- =============================================================================
-- Face Recognition Demo Configuration Data
-- Dream Roots Millennium School
-- Generated: 2026-11-09
-- Run this in phpMyAdmin to immediately load a complete working demo setup
-- =============================================================================

-- 1. Configure Demo Attendance Rules (08:30 AM to 03:00 PM, Sun-Thu, 15m Late threshold)
INSERT INTO `attendance_rules` (
  `school_id`,
  `name`,
  `start_time`,
  `end_time`,
  `late_threshold_minutes`,
  `very_late_threshold_minutes`,
  `window_hours`,
  `duplicate_interval_seconds`,
  `allow_holiday_attendance`,
  `skip_weekends`,
  `working_days`,
  `send_sms_on_present`,
  `send_sms_on_late`,
  `send_sms_to_guardian`,
  `max_checks_per_day`,
  `is_active`,
  `priority`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'Demo School Schedule & Face Attendance Rules',
  '08:30:00',
  '15:00:00',
  15,
  60,
  12.00,
  300,
  0,
  1,
  '[1, 2, 3, 4, 5]',
  1,
  1,
  1,
  10,
  1,
  1,
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  `start_time` = '08:30:00',
  `end_time` = '15:00:00',
  `late_threshold_minutes` = 15,
  `very_late_threshold_minutes` = 60,
  `working_days` = '[1, 2, 3, 4, 5]',
  `send_sms_on_present` = 1,
  `send_sms_on_late` = 1,
  `send_sms_to_guardian` = 1,
  `is_active` = 1,
  `updated_at` = NOW();

-- 2. Register Demo Face Recognition Terminal Device
-- Plain secret: demo_secret_face_2026_drms_main_gate
-- SHA-256 hash: 61ee0552aff5e1c4f655ca1b594e77e67c3937f6964cf4b86b4c36e8cb711a71
INSERT INTO `attendance_devices` (
  `id`,
  `school_id`,
  `name`,
  `kind`,
  `driver`,
  `serial`,
  `location`,
  `secret_hash`,
  `status`,
  `config`,
  `is_active`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  1,
  'Main Gate Face Terminal 01',
  'face',
  'hikvision',
  'HK-DSK1T671-DEMO01',
  'Main Campus Entrance Gate',
  '61ee0552aff5e1c4f655ca1b594e77e67c3937f6964cf4b86b4c36e8cb711a71',
  'connected',
  '{\"model\":\"Hikvision DS-K1T671MF\",\"firmware\":\"V3.2.30\",\"lan_ip\":\"192.168.1.120\",\"api_port\":80,\"protocol\":\"ISAPI/HTTPS\"}',
  1,
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  `name` = 'Main Gate Face Terminal 01',
  `kind` = 'face',
  `driver` = 'hikvision',
  `location` = 'Main Campus Entrance Gate',
  `secret_hash` = '61ee0552aff5e1c4f655ca1b594e77e67c3937f6964cf4b86b4c36e8cb711a71',
  `status` = 'connected',
  `is_active` = 1,
  `updated_at` = NOW();

-- 3. Enable System Settings (Students and Teachers)
INSERT INTO `settings` (`school_id`, `group_name`, `key`, `value`, `type`, `created_at`, `updated_at`)
VALUES
  (1, 'attendance', 'face_recognition_enabled', '1', 'boolean', NOW(), NOW()),
  (1, 'attendance', 'face_recognition_user_types', '[\"students\",\"teachers\",\"both\"]', 'json', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `updated_at` = NOW();

-- 4. Sample Student Face Device ID Mappings (If students exist)
INSERT IGNORE INTO `attendance_device_users` (`school_id`, `attendance_device_id`, `student_id`, `device_user_id`, `created_at`, `updated_at`)
SELECT 1, 1, `id`, CONCAT('100', `id`), NOW(), NOW()
FROM `students`
WHERE `school_id` = 1
LIMIT 5;
