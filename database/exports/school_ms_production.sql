-- =====================================================================
-- EduNest School Management - Production database schema (MySQL/MariaDB)
-- Exported: 2026-11-04
--
-- Contents: full table structure for every migration through
-- 2026_11_04_140000, plus roles/permissions and the migrations ledger.
-- NO demo / mock data (no schools, users, students, fees, etc.).
--
-- cPanel import steps:
--   1. cPanel > MySQL Databases: create DB + user, assign ALL PRIVILEGES.
--   2. phpMyAdmin > select the DB > Import > choose this file > Go.
--   3. Point the app .env to this DB, then create the first school/admin:
--        php artisan school:admin admin@yourschool.com \
--            --name="School Admin" --password="ChangeMe@1234" \
--            --school-name="Your School Name" --school-code="YSN"
--      (use cPanel > Terminal, run from the app root)
--   4. The admin password must be changed at first sign-in.
-- =====================================================================

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: school_ms_fresh
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_sessions`
--

DROP TABLE IF EXISTS `academic_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'upcoming',
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_sessions_school_name_unique` (`school_id`,`name`),
  KEY `academic_sessions_school_id_is_current_index` (`school_id`,`is_current`),
  KEY `academic_sessions_school_id_status_index` (`school_id`,`status`),
  CONSTRAINT `academic_sessions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admissions`
--

DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `applied_class_id` bigint(20) unsigned NOT NULL,
  `application_no` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `date_of_birth` date NOT NULL,
  `guardian_name` varchar(120) NOT NULL,
  `guardian_phone` varchar(30) NOT NULL,
  `guardian_email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `previous_school` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admissions_school_application_unique` (`school_id`,`application_no`),
  KEY `admissions_academic_session_id_foreign` (`academic_session_id`),
  KEY `admissions_applied_class_id_foreign` (`applied_class_id`),
  KEY `admissions_student_id_foreign` (`student_id`),
  KEY `admissions_reviewed_by_foreign` (`reviewed_by`),
  KEY `admissions_school_id_status_index` (`school_id`,`status`),
  KEY `admissions_session_status_index` (`school_id`,`academic_session_id`,`status`),
  KEY `admissions_user_id_foreign` (`user_id`),
  KEY `admissions_school_user_index` (`school_id`,`user_id`),
  KEY `admissions_applicant_session_index` (`school_id`,`user_id`,`academic_session_id`),
  CONSTRAINT `admissions_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `admissions_applied_class_id_foreign` FOREIGN KEY (`applied_class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `admissions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `admissions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance_device_users`
--

DROP TABLE IF EXISTS `attendance_device_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_device_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `attendance_device_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `device_user_id` varchar(191) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_device_users_user_unique` (`attendance_device_id`,`device_user_id`),
  UNIQUE KEY `attendance_device_users_student_unique` (`attendance_device_id`,`student_id`),
  KEY `attendance_device_users_school_id_foreign` (`school_id`),
  KEY `attendance_device_users_student_id_foreign` (`student_id`),
  KEY `attendance_device_users_created_by_foreign` (`created_by`),
  CONSTRAINT `attendance_device_users_attendance_device_id_foreign` FOREIGN KEY (`attendance_device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_device_users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_device_users_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `attendance_device_users_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance_devices`
--

DROP TABLE IF EXISTS `attendance_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `driver` varchar(255) DEFAULT NULL,
  `serial` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `secret_hash` varchar(64) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'not_configured',
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_devices_kind_status_index` (`school_id`,`kind`,`status`),
  KEY `attendance_devices_serial_index` (`school_id`,`serial`),
  CONSTRAINT `attendance_devices_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance_events`
--

DROP TABLE IF EXISTS `attendance_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `attendance_device_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `student_attendance_id` bigint(20) unsigned DEFAULT NULL,
  `source` varchar(20) NOT NULL,
  `external_ref` varchar(191) NOT NULL,
  `device_user_id` varchar(255) DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `received_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_events_external_unique` (`school_id`,`source`,`external_ref`),
  KEY `attendance_events_attendance_device_id_foreign` (`attendance_device_id`),
  KEY `attendance_events_student_id_foreign` (`student_id`),
  KEY `attendance_events_student_attendance_id_foreign` (`student_attendance_id`),
  KEY `attendance_events_status_time_index` (`school_id`,`status`,`occurred_at`),
  KEY `attendance_events_student_time_index` (`school_id`,`student_id`,`occurred_at`),
  CONSTRAINT `attendance_events_attendance_device_id_foreign` FOREIGN KEY (`attendance_device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_events_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `attendance_events_student_attendance_id_foreign` FOREIGN KEY (`student_attendance_id`) REFERENCES `student_attendances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_events_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `attendance_submissions`
--

DROP TABLE IF EXISTS `attendance_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'submitted',
  `student_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `present_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `absent_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `late_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `leave_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `excused_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `unmarked_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `revision_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_correction_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_submissions_academic_session_id_foreign` (`academic_session_id`),
  KEY `attendance_submissions_class_id_foreign` (`class_id`),
  KEY `attendance_submissions_section_id_foreign` (`section_id`),
  KEY `attendance_submissions_submitted_by_foreign` (`submitted_by`),
  KEY `attendance_submissions_reviewed_by_foreign` (`reviewed_by`),
  KEY `attendance_submissions_register_index` (`school_id`,`academic_session_id`,`class_id`,`section_id`,`attendance_date`),
  KEY `attendance_submissions_queue_index` (`school_id`,`status`,`attendance_date`),
  CONSTRAINT `attendance_submissions_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `attendance_submissions_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `attendance_submissions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_submissions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `attendance_submissions_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `attendance_submissions_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event` varchar(50) NOT NULL,
  `auditable_type` varchar(255) DEFAULT NULL,
  `auditable_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_school_id_created_at_index` (`school_id`,`created_at`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_event_created_at_index` (`event`,`created_at`),
  CONSTRAINT `audit_logs_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `backup_logs`
--

DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `disk` varchar(40) NOT NULL DEFAULT 'local',
  `size_bytes` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backup_logs_created_by_foreign` (`created_by`),
  KEY `backup_logs_school_date_index` (`school_id`,`created_at`),
  CONSTRAINT `backup_logs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `backup_logs_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `bank_name` varchar(120) NOT NULL,
  `account_no` varchar(40) NOT NULL,
  `branch` varchar(120) DEFAULT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_accounts_school_account_unique` (`school_id`,`account_no`),
  CONSTRAINT `bank_accounts_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cash_transactions`
--

DROP TABLE IF EXISTS `cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `type` varchar(20) NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'cash',
  `amount` decimal(14,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `source_type` varchar(255) DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `reconciled_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_transactions_reversal_of_id_foreign` (`reversal_of_id`),
  KEY `cash_transactions_created_by_foreign` (`created_by`),
  KEY `cash_transactions_reconciled_by_foreign` (`reconciled_by`),
  KEY `cash_transactions_date_type_index` (`school_id`,`transaction_date`,`type`),
  KEY `cash_transactions_source_index` (`source_type`,`source_id`),
  KEY `cash_transactions_reconciled_index` (`school_id`,`reconciled_at`),
  KEY `cash_transactions_bank_account_id_foreign` (`bank_account_id`),
  KEY `cash_transactions_channel_date_index` (`school_id`,`channel`,`transaction_date`),
  CONSTRAINT `cash_transactions_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_transactions_reconciled_by_foreign` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_transactions_reversal_of_id_foreign` FOREIGN KEY (`reversal_of_id`) REFERENCES `cash_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_transactions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `class_routines`
--

DROP TABLE IF EXISTS `class_routines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_routines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `day_of_week` tinyint(3) unsigned NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(40) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_routines_slot_unique` (`section_id`,`day_of_week`,`start_time`),
  KEY `class_routines_academic_session_id_foreign` (`academic_session_id`),
  KEY `class_routines_class_id_foreign` (`class_id`),
  KEY `class_routines_subject_id_foreign` (`subject_id`),
  KEY `class_routines_teacher_id_foreign` (`teacher_id`),
  KEY `class_routines_teacher_day_index` (`school_id`,`teacher_id`,`day_of_week`),
  KEY `class_routines_section_day_index` (`school_id`,`class_id`,`section_id`,`day_of_week`),
  CONSTRAINT `class_routines_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `class_routines_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `class_routines_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `class_routines_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `class_routines_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `class_routines_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `class_subjects`
--

DROP TABLE IF EXISTS `class_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `full_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `pass_marks` decimal(6,2) NOT NULL DEFAULT 33.00,
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_subjects_unique` (`school_id`,`academic_session_id`,`class_id`,`subject_id`),
  KEY `class_subjects_academic_session_id_foreign` (`academic_session_id`),
  KEY `class_subjects_class_id_foreign` (`class_id`),
  KEY `class_subjects_subject_id_foreign` (`subject_id`),
  KEY `class_subjects_class_session_index` (`school_id`,`class_id`,`academic_session_id`),
  CONSTRAINT `class_subjects_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `class_subjects_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `class_subjects_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `class_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `class_videos`
--

DROP TABLE IF EXISTS `class_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_videos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `source` varchar(16) NOT NULL,
  `youtube_url` varchar(300) DEFAULT NULL,
  `youtube_id` varchar(16) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(160) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `show_on_website` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
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
  KEY `class_videos_period_index` (`school_id`,`class_id`,`section_id`),
  CONSTRAINT `class_videos_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `class_videos_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `class_videos_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `class_videos_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `class_videos_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `class_videos_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `level` smallint(5) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classes_school_name_unique` (`school_id`,`name`),
  KEY `classes_school_id_level_is_active_index` (`school_id`,`level`,`is_active`),
  CONSTRAINT `classes_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `device_push_tokens`
--

DROP TABLE IF EXISTS `device_push_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `device_push_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `platform` varchar(20) NOT NULL,
  `token` text NOT NULL,
  `token_hash` char(64) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `app_version` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_push_tokens_hash_unique` (`token_hash`),
  KEY `device_push_tokens_user_active_index` (`user_id`,`is_active`),
  CONSTRAINT `device_push_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_code` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `designation` varchar(80) NOT NULL,
  `department` varchar(80) DEFAULT NULL,
  `joining_date` date NOT NULL,
  `employment_type` varchar(20) NOT NULL DEFAULT 'full_time',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_school_employee_code_unique` (`school_id`,`employee_code`),
  UNIQUE KEY `employees_user_id_unique` (`user_id`),
  KEY `employees_school_id_status_index` (`school_id`,`status`),
  KEY `employees_school_id_phone_index` (`school_id`,`phone`),
  CONSTRAINT `employees_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `roll_no` int(10) unsigned DEFAULT NULL,
  `enrolled_on` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollments_student_session_unique` (`school_id`,`student_id`,`academic_session_id`),
  UNIQUE KEY `enrollments_roll_unique` (`school_id`,`academic_session_id`,`class_id`,`section_id`,`roll_no`),
  KEY `enrollments_student_id_foreign` (`student_id`),
  KEY `enrollments_academic_session_id_foreign` (`academic_session_id`),
  KEY `enrollments_class_id_foreign` (`class_id`),
  KEY `enrollments_section_id_foreign` (`section_id`),
  KEY `enrollments_class_status_index` (`school_id`,`class_id`,`section_id`,`status`),
  CONSTRAINT `enrollments_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `enrollments_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `enrollments_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `enrollments_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `enrollments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exam_marks`
--

DROP TABLE IF EXISTS `exam_marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_marks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `exam_subject_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `marks_obtained` decimal(6,2) DEFAULT NULL,
  `is_absent` tinyint(1) NOT NULL DEFAULT 0,
  `grade_letter` varchar(5) DEFAULT NULL,
  `grade_point` decimal(3,2) DEFAULT NULL,
  `remarks` varchar(160) DEFAULT NULL,
  `entered_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_marks_unique` (`exam_subject_id`,`student_id`),
  KEY `exam_marks_student_id_foreign` (`student_id`),
  KEY `exam_marks_entered_by_foreign` (`entered_by`),
  KEY `exam_marks_student_index` (`school_id`,`student_id`),
  CONSTRAINT `exam_marks_entered_by_foreign` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exam_marks_exam_subject_id_foreign` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_marks_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `exam_marks_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exam_subjects`
--

DROP TABLE IF EXISTS `exam_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `exam_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `exam_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `full_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `pass_marks` decimal(6,2) NOT NULL DEFAULT 33.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_subjects_unique` (`exam_id`,`class_id`,`subject_id`),
  KEY `exam_subjects_class_id_foreign` (`class_id`),
  KEY `exam_subjects_subject_id_foreign` (`subject_id`),
  KEY `exam_subjects_exam_class_index` (`school_id`,`exam_id`,`class_id`),
  CONSTRAINT `exam_subjects_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `exam_subjects_exam_id_foreign` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_subjects_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `exam_subjects_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `exam_type` varchar(30) NOT NULL DEFAULT 'term',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `description` text DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exams_session_name_unique` (`school_id`,`academic_session_id`,`name`),
  KEY `exams_academic_session_id_foreign` (`academic_session_id`),
  KEY `exams_created_by_foreign` (`created_by`),
  KEY `exams_status_date_index` (`school_id`,`status`,`start_date`),
  CONSTRAINT `exams_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `exams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exams_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_categories_school_code_unique` (`school_id`,`code`),
  CONSTRAINT `expense_categories_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `expense_category_id` bigint(20) unsigned NOT NULL,
  `expense_no` varchar(30) NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `paid_to` varchar(120) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_school_no_unique` (`school_id`,`expense_no`),
  KEY `expenses_expense_category_id_foreign` (`expense_category_id`),
  KEY `expenses_approved_by_foreign` (`approved_by`),
  KEY `expenses_created_by_foreign` (`created_by`),
  KEY `expenses_cancelled_by_foreign` (`cancelled_by`),
  KEY `expenses_date_status_index` (`school_id`,`expense_date`,`status`),
  KEY `expenses_category_index` (`school_id`,`expense_category_id`),
  KEY `expenses_bank_account_id_foreign` (`bank_account_id`),
  CONSTRAINT `expenses_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `expenses_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_discounts`
--

DROP TABLE IF EXISTS `fee_discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_discounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'fixed',
  `frequency` varchar(20) NOT NULL DEFAULT 'one_time',
  `scope` varchar(20) NOT NULL DEFAULT 'all',
  `value` decimal(12,2) NOT NULL,
  `max_amount` decimal(14,2) DEFAULT NULL,
  `applies_to_fee_type_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `auto_apply` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `class_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_discounts_school_code_unique` (`school_id`,`code`),
  KEY `fee_discounts_applies_to_fee_type_id_foreign` (`applies_to_fee_type_id`),
  KEY `fee_discounts_class_id_foreign` (`class_id`),
  KEY `fee_discounts_student_id_foreign` (`student_id`),
  KEY `fee_discounts_created_by_foreign` (`created_by`),
  KEY `fee_discounts_updated_by_foreign` (`updated_by`),
  KEY `fee_discounts_active_index` (`school_id`,`is_active`,`starts_on`,`ends_on`),
  CONSTRAINT `fee_discounts_applies_to_fee_type_id_foreign` FOREIGN KEY (`applies_to_fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_discounts_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_discounts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_discounts_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `fee_discounts_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_discounts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_invoice_discounts`
--

DROP TABLE IF EXISTS `fee_invoice_discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_invoice_discounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_invoice_id` bigint(20) unsigned NOT NULL,
  `fee_discount_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `type` varchar(20) NOT NULL,
  `frequency` varchar(20) DEFAULT NULL,
  `scope` varchar(20) DEFAULT NULL,
  `value` decimal(12,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `base_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(14,2) DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'applied',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `fee_type_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(30) DEFAULT NULL,
  `rejected_by` bigint(20) unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fee_invoice_discounts_fee_invoice_id_foreign` (`fee_invoice_id`),
  KEY `fee_invoice_discounts_fee_discount_id_foreign` (`fee_discount_id`),
  KEY `fee_invoice_discounts_invoice_index` (`school_id`,`fee_invoice_id`),
  KEY `fee_invoice_discounts_requested_by_foreign` (`requested_by`),
  KEY `fee_invoice_discounts_approved_by_foreign` (`approved_by`),
  KEY `fee_invoice_discounts_status_index` (`school_id`,`status`),
  KEY `fee_invoice_discounts_fee_type_id_foreign` (`fee_type_id`),
  KEY `fee_invoice_discounts_rejected_by_foreign` (`rejected_by`),
  KEY `fee_invoice_discounts_cancelled_by_foreign` (`cancelled_by`),
  KEY `fee_invoice_discounts_created_index` (`school_id`,`status`,`created_at`),
  CONSTRAINT `fee_invoice_discounts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_discounts_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_discounts_fee_discount_id_foreign` FOREIGN KEY (`fee_discount_id`) REFERENCES `fee_discounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_discounts_fee_invoice_id_foreign` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_invoice_discounts_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_discounts_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_discounts_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_discounts_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_invoice_items`
--

DROP TABLE IF EXISTS `fee_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_invoice_id` bigint(20) unsigned NOT NULL,
  `fee_type_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(160) NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `unit_amount` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fee_invoice_items_fee_invoice_id_foreign` (`fee_invoice_id`),
  KEY `fee_invoice_items_fee_type_id_foreign` (`fee_type_id`),
  KEY `fee_invoice_items_invoice_index` (`school_id`,`fee_invoice_id`),
  CONSTRAINT `fee_invoice_items_fee_invoice_id_foreign` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_invoice_items_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoice_items_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_invoices`
--

DROP TABLE IF EXISTS `fee_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_no` varchar(30) NOT NULL,
  `billing_period` varchar(20) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `configured_fee_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `approved_discount_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `final_payable_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `actual_collected_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `due_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `waived_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'issued',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `active_billing_key` varchar(96) CHARACTER SET ascii COLLATE ascii_general_ci GENERATED ALWAYS AS (case when `status` <> 'cancelled' and `billing_period` is not null then concat(`school_id`,':',`student_id`,':',`academic_session_id`,':',`billing_period`) end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_invoices_school_no_unique` (`school_id`,`invoice_no`),
  UNIQUE KEY `fee_invoices_active_billing_unique` (`active_billing_key`),
  KEY `fee_invoices_academic_session_id_foreign` (`academic_session_id`),
  KEY `fee_invoices_student_id_foreign` (`student_id`),
  KEY `fee_invoices_enrollment_id_foreign` (`enrollment_id`),
  KEY `fee_invoices_created_by_foreign` (`created_by`),
  KEY `fee_invoices_status_due_index` (`school_id`,`status`,`due_date`),
  KEY `fee_invoices_student_status_index` (`school_id`,`student_id`,`status`),
  KEY `fee_invoices_student_period_index` (`school_id`,`student_id`,`billing_period`),
  CONSTRAINT `fee_invoices_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `fee_invoices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_invoices_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`),
  CONSTRAINT `fee_invoices_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `fee_invoices_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_payment_allocations`
--

DROP TABLE IF EXISTS `fee_payment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_payment_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_payment_id` bigint(20) unsigned NOT NULL,
  `fee_invoice_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `waived_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_payment_allocations_unique` (`fee_payment_id`,`fee_invoice_id`),
  KEY `fee_payment_allocations_fee_invoice_id_foreign` (`fee_invoice_id`),
  KEY `fee_allocations_invoice_index` (`school_id`,`fee_invoice_id`),
  CONSTRAINT `fee_payment_allocations_fee_invoice_id_foreign` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`),
  CONSTRAINT `fee_payment_allocations_fee_payment_id_foreign` FOREIGN KEY (`fee_payment_id`) REFERENCES `fee_payments` (`id`),
  CONSTRAINT `fee_payment_allocations_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_payments`
--

DROP TABLE IF EXISTS `fee_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned DEFAULT NULL,
  `fee_type_id` bigint(20) unsigned DEFAULT NULL,
  `billing_month` varchar(7) DEFAULT NULL,
  `invoice_no` varchar(30) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'cash',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `received_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `verification_status` varchar(20) NOT NULL DEFAULT 'not_required',
  `idempotency_key` varchar(64) NOT NULL,
  `notes` text DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_payments_idempotency_unique` (`school_id`,`idempotency_key`),
  KEY `fee_payments_student_id_foreign` (`student_id`),
  KEY `fee_payments_received_by_foreign` (`received_by`),
  KEY `fee_payments_reversed_by_foreign` (`reversed_by`),
  KEY `fee_payments_student_date_index` (`school_id`,`student_id`,`paid_at`),
  KEY `fee_payments_status_date_index` (`school_id`,`status`,`paid_at`),
  KEY `fee_payments_verified_by_foreign` (`verified_by`),
  KEY `fee_payments_bank_account_id_foreign` (`bank_account_id`),
  KEY `fee_payments_verification_index` (`school_id`,`verification_status`),
  KEY `fee_payments_class_id_foreign` (`class_id`),
  KEY `fee_payments_fee_type_id_foreign` (`fee_type_id`),
  KEY `fee_payments_tracking_index` (`school_id`,`billing_month`,`class_id`,`fee_type_id`),
  CONSTRAINT `fee_payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_payments_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_payments_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_payments_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_payments_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_payments_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `fee_payments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `fee_payments_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_receipts`
--

DROP TABLE IF EXISTS `fee_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_payment_id` bigint(20) unsigned NOT NULL,
  `receipt_no` varchar(30) NOT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  `snapshot_hash` char(64) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_receipts_school_no_unique` (`school_id`,`receipt_no`),
  UNIQUE KEY `fee_receipts_fee_payment_id_unique` (`fee_payment_id`),
  KEY `fee_receipts_issued_by_foreign` (`issued_by`),
  CONSTRAINT `fee_receipts_fee_payment_id_foreign` FOREIGN KEY (`fee_payment_id`) REFERENCES `fee_payments` (`id`),
  CONSTRAINT `fee_receipts_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_receipts_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_structures`
--

DROP TABLE IF EXISTS `fee_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `fee_type_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_day` tinyint(3) unsigned DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_structures_unique` (`school_id`,`academic_session_id`,`class_id`,`fee_type_id`),
  KEY `fee_structures_academic_session_id_foreign` (`academic_session_id`),
  KEY `fee_structures_class_id_foreign` (`class_id`),
  KEY `fee_structures_fee_type_id_foreign` (`fee_type_id`),
  KEY `fee_structures_class_index` (`school_id`,`class_id`,`is_active`),
  CONSTRAINT `fee_structures_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `fee_structures_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `fee_structures_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`),
  CONSTRAINT `fee_structures_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_student_assignments`
--

DROP TABLE IF EXISTS `fee_student_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_student_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_type_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `class_id` bigint(20) unsigned DEFAULT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `batch` varchar(36) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `active_assignment_key` varchar(96) CHARACTER SET ascii COLLATE ascii_general_ci GENERATED ALWAYS AS (case when `is_active` = 1 then concat(`school_id`,':',`student_id`,':',`fee_type_id`,':',`academic_session_id`) end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_assignments_active_unique` (`active_assignment_key`),
  KEY `fee_student_assignments_fee_type_id_foreign` (`fee_type_id`),
  KEY `fee_student_assignments_academic_session_id_foreign` (`academic_session_id`),
  KEY `fee_student_assignments_student_id_foreign` (`student_id`),
  KEY `fee_student_assignments_enrollment_id_foreign` (`enrollment_id`),
  KEY `fee_student_assignments_class_id_foreign` (`class_id`),
  KEY `fee_student_assignments_section_id_foreign` (`section_id`),
  KEY `fee_student_assignments_created_by_foreign` (`created_by`),
  KEY `fee_assignments_student_index` (`school_id`,`academic_session_id`,`student_id`),
  KEY `fee_assignments_batch_index` (`school_id`,`batch`),
  CONSTRAINT `fee_student_assignments_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `fee_student_assignments_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`),
  CONSTRAINT `fee_student_assignments_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `fee_student_assignments_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fee_types`
--

DROP TABLE IF EXISTS `fee_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(40) DEFAULT NULL,
  `frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_types_school_code_unique` (`school_id`,`code`),
  UNIQUE KEY `fee_types_school_name_unique` (`school_id`,`name`),
  CONSTRAINT `fee_types_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grade_scale_items`
--

DROP TABLE IF EXISTS `grade_scale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_scale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `grade_scale_id` bigint(20) unsigned NOT NULL,
  `grade_letter` varchar(5) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL,
  `remarks` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_scale_items_letter_unique` (`grade_scale_id`,`grade_letter`),
  KEY `grade_scale_items_school_id_foreign` (`school_id`),
  KEY `grade_scale_items_range_index` (`grade_scale_id`,`min_percentage`),
  CONSTRAINT `grade_scale_items_grade_scale_id_foreign` FOREIGN KEY (`grade_scale_id`) REFERENCES `grade_scales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_scale_items_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `grade_scales`
--

DROP TABLE IF EXISTS `grade_scales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grade_scales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_scales_school_name_unique` (`school_id`,`name`),
  KEY `grade_scales_school_id_is_default_index` (`school_id`,`is_default`),
  CONSTRAINT `grade_scales_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guardians`
--

DROP TABLE IF EXISTS `guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guardians` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `relation` varchar(20) NOT NULL DEFAULT 'guardian',
  `phone` varchar(30) NOT NULL,
  `alt_phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `occupation` varchar(80) DEFAULT NULL,
  `nid` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guardians_user_id_foreign` (`user_id`),
  KEY `guardians_school_id_phone_index` (`school_id`,`phone`),
  KEY `guardians_school_id_email_index` (`school_id`,`email`),
  CONSTRAINT `guardians_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `guardians_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'public',
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `holidays_academic_session_id_foreign` (`academic_session_id`),
  KEY `holidays_school_dates_index` (`school_id`,`start_date`,`end_date`),
  CONSTRAINT `holidays_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `holidays_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homework`
--

DROP TABLE IF EXISTS `homework`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homework` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `instructions` text DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'published',
  `published_at` timestamp NULL DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `homework_academic_session_id_foreign` (`academic_session_id`),
  KEY `homework_class_id_foreign` (`class_id`),
  KEY `homework_section_id_foreign` (`section_id`),
  KEY `homework_subject_id_foreign` (`subject_id`),
  KEY `homework_teacher_id_foreign` (`teacher_id`),
  KEY `homework_created_by_foreign` (`created_by`),
  KEY `homework_class_due_index` (`school_id`,`class_id`,`section_id`,`due_date`),
  KEY `homework_status_due_index` (`school_id`,`status`,`due_date`),
  CONSTRAINT `homework_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `homework_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `homework_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `homework_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `homework_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `homework_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `homework_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `homework_submissions`
--

DROP TABLE IF EXISTS `homework_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `homework_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `homework_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'submitted',
  `text_answer` text DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(160) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `resubmitted_at` timestamp NULL DEFAULT NULL,
  `marks` decimal(6,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `homework_submissions_student_unique` (`homework_id`,`student_id`),
  KEY `homework_submissions_student_id_foreign` (`student_id`),
  KEY `homework_submissions_enrollment_id_foreign` (`enrollment_id`),
  KEY `homework_submissions_reviewed_by_foreign` (`reviewed_by`),
  KEY `homework_submissions_student_index` (`school_id`,`student_id`,`submitted_at`),
  KEY `homework_submissions_status_index` (`homework_id`,`status`),
  CONSTRAINT `homework_submissions_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `homework_submissions_homework_id_foreign` FOREIGN KEY (`homework_id`) REFERENCES `homework` (`id`) ON DELETE CASCADE,
  CONSTRAINT `homework_submissions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `homework_submissions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `homework_submissions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `max_days_per_year` smallint(5) unsigned DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_types_school_name_unique` (`school_id`,`name`),
  UNIQUE KEY `leave_types_school_code_unique` (`school_id`,`code`),
  CONSTRAINT `leave_types_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lesson_materials`
--

DROP TABLE IF EXISTS `lesson_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'document',
  `title` varchar(160) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(160) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_materials_lesson_kind_index` (`lesson_id`,`kind`),
  KEY `lesson_materials_school_index` (`school_id`,`lesson_id`),
  CONSTRAINT `lesson_materials_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_materials_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lessons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `chapter_number` varchar(20) DEFAULT NULL,
  `chapter_name` varchar(160) DEFAULT NULL,
  `lesson_number` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `video_url` varchar(300) DEFAULT NULL,
  `youtube_id` varchar(16) DEFAULT NULL,
  `reference_links` text DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lessons_academic_session_id_foreign` (`academic_session_id`),
  KEY `lessons_class_id_foreign` (`class_id`),
  KEY `lessons_section_id_foreign` (`section_id`),
  KEY `lessons_subject_id_foreign` (`subject_id`),
  KEY `lessons_teacher_id_foreign` (`teacher_id`),
  KEY `lessons_created_by_foreign` (`created_by`),
  KEY `lessons_period_status_index` (`school_id`,`status`,`class_id`,`section_id`),
  KEY `lessons_subject_index` (`school_id`,`subject_id`),
  KEY `lessons_teacher_index` (`school_id`,`teacher_id`),
  KEY `lessons_recent_index` (`school_id`,`updated_at`),
  CONSTRAINT `lessons_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `lessons_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `lessons_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lessons_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `lessons_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `lessons_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `lessons_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mark_submissions`
--

DROP TABLE IF EXISTS `mark_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mark_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `exam_id` bigint(20) unsigned NOT NULL,
  `exam_subject_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'submitted',
  `enrolled_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `entered_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `absent_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `unmarked_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `submitted_by` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `revision_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_correction_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mark_submissions_exam_id_foreign` (`exam_id`),
  KEY `mark_submissions_section_id_foreign` (`section_id`),
  KEY `mark_submissions_submitted_by_foreign` (`submitted_by`),
  KEY `mark_submissions_reviewed_by_foreign` (`reviewed_by`),
  KEY `mark_submissions_sheet_index` (`exam_subject_id`,`section_id`),
  KEY `mark_submissions_queue_index` (`school_id`,`status`,`submitted_at`),
  CONSTRAINT `mark_submissions_exam_id_foreign` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mark_submissions_exam_subject_id_foreign` FOREIGN KEY (`exam_subject_id`) REFERENCES `exam_subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mark_submissions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mark_submissions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `mark_submissions_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `mark_submissions_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `body` text NOT NULL,
  `audience` varchar(20) NOT NULL DEFAULT 'all',
  `published_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notices_academic_session_id_foreign` (`academic_session_id`),
  KEY `notices_created_by_foreign` (`created_by`),
  KEY `notices_audience_published_index` (`school_id`,`audience`,`published_at`),
  CONSTRAINT `notices_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notices_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_preferences`
--

DROP TABLE IF EXISTS `notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `category` varchar(40) NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'webpush',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_unique` (`user_id`,`category`,`channel`),
  KEY `notification_preferences_channel_index` (`user_id`,`channel`),
  CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `category` varchar(40) DEFAULT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `dedupe_key` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notifications_dedupe_unique` (`dedupe_key`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_unread_index` (`notifiable_type`,`notifiable_id`,`read_at`),
  KEY `notifications_category_index` (`notifiable_type`,`notifiable_id`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_gateway_transactions`
--

DROP TABLE IF EXISTS `payment_gateway_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_gateway_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `payment_key` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `fee_invoice_id` bigint(20) unsigned DEFAULT NULL,
  `fee_payment_id` bigint(20) unsigned DEFAULT NULL,
  `gateway` varchar(40) NOT NULL,
  `gateway_transaction_id` varchar(120) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'BDT',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_transactions_unique` (`gateway`,`gateway_transaction_id`),
  UNIQUE KEY `gateway_transactions_payment_key_unique` (`school_id`,`payment_key`),
  KEY `payment_gateway_transactions_fee_invoice_id_foreign` (`fee_invoice_id`),
  KEY `payment_gateway_transactions_fee_payment_id_foreign` (`fee_payment_id`),
  KEY `gateway_transactions_status_index` (`school_id`,`status`),
  KEY `gateway_transactions_student_fk` (`student_id`),
  CONSTRAINT `gateway_transactions_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `payment_gateway_transactions_fee_invoice_id_foreign` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_gateway_transactions_fee_payment_id_foreign` FOREIGN KEY (`fee_payment_id`) REFERENCES `fee_payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_gateway_transactions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_delivery_logs`
--

DROP TABLE IF EXISTS `push_delivery_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_delivery_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` char(36) DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `push_subscription_id` bigint(20) unsigned DEFAULT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'webpush',
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `error_code` varchar(40) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_delivery_logs_unique` (`notification_id`,`push_subscription_id`,`channel`),
  KEY `push_delivery_logs_push_subscription_id_foreign` (`push_subscription_id`),
  KEY `push_delivery_logs_status_index` (`user_id`,`status`,`created_at`),
  CONSTRAINT `push_delivery_logs_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `push_delivery_logs_push_subscription_id_foreign` FOREIGN KEY (`push_subscription_id`) REFERENCES `push_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `push_delivery_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `public_key` text NOT NULL,
  `auth_token` text NOT NULL,
  `content_encoding` varchar(20) NOT NULL DEFAULT 'aes128gcm',
  `user_agent` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `last_failure_at` timestamp NULL DEFAULT NULL,
  `failure_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint_hash`),
  KEY `push_subscriptions_user_active_index` (`user_id`,`is_active`),
  CONSTRAINT `push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salary_payments`
--

DROP TABLE IF EXISTS `salary_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `salary_structure_id` bigint(20) unsigned DEFAULT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `salary_month` date NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL,
  `allowances_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductions_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(12,2) NOT NULL,
  `net_salary` decimal(12,2) NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `reference_no` varchar(80) DEFAULT NULL,
  `paid_on` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversed_by` bigint(20) unsigned DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_payments_teacher_month_unique` (`school_id`,`teacher_id`,`salary_month`),
  UNIQUE KEY `salary_payments_employee_month_unique` (`school_id`,`employee_id`,`salary_month`),
  KEY `salary_payments_salary_structure_id_foreign` (`salary_structure_id`),
  KEY `salary_payments_teacher_id_foreign` (`teacher_id`),
  KEY `salary_payments_employee_id_foreign` (`employee_id`),
  KEY `salary_payments_paid_by_foreign` (`paid_by`),
  KEY `salary_payments_reversed_by_foreign` (`reversed_by`),
  KEY `salary_payments_status_index` (`school_id`,`status`,`salary_month`),
  CONSTRAINT `salary_payments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `salary_payments_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_salary_structure_id_foreign` FOREIGN KEY (`salary_structure_id`) REFERENCES `salary_structures` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `salary_payments_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salary_structures`
--

DROP TABLE IF EXISTS `salary_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_structures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `basic_salary` decimal(12,2) NOT NULL,
  `house_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transport_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_structures_teacher_id_foreign` (`teacher_id`),
  KEY `salary_structures_employee_id_foreign` (`employee_id`),
  KEY `salary_structures_teacher_index` (`school_id`,`teacher_id`,`is_active`),
  KEY `salary_structures_employee_index` (`school_id`,`employee_id`,`is_active`),
  CONSTRAINT `salary_structures_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `salary_structures_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `salary_structures_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `code` varchar(30) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `eiin` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `phone_alt` varchar(30) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `division` varchar(60) DEFAULT NULL,
  `district` varchar(60) DEFAULT NULL,
  `upazila` varchar(60) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `motto` varchar(255) DEFAULT NULL,
  `established_year` smallint(5) unsigned DEFAULT NULL,
  `timezone` varchar(60) NOT NULL DEFAULT 'Asia/Dhaka',
  `currency` char(3) NOT NULL DEFAULT 'BDT',
  `locale` varchar(5) NOT NULL DEFAULT 'en',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schools_code_unique` (`code`),
  UNIQUE KEY `schools_slug_unique` (`slug`),
  UNIQUE KEY `schools_eiin_unique` (`eiin`),
  KEY `schools_is_active_name_index` (`is_active`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `name` varchar(30) NOT NULL,
  `capacity` smallint(5) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sections_class_name_unique` (`class_id`,`name`),
  KEY `sections_school_id_class_id_is_active_index` (`school_id`,`class_id`,`is_active`),
  CONSTRAINT `sections_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `sections_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `group_name` varchar(50) NOT NULL DEFAULT 'general',
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_school_key_unique` (`school_id`,`key`),
  KEY `settings_school_id_group_name_index` (`school_id`,`group_name`),
  CONSTRAINT `settings_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sms_logs`
--

DROP TABLE IF EXISTS `sms_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `phone` varchar(30) NOT NULL,
  `message` text NOT NULL,
  `provider` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response`)),
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sms_logs_user_id_foreign` (`user_id`),
  KEY `sms_logs_status_index` (`school_id`,`status`,`created_at`),
  CONSTRAINT `sms_logs_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `sms_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_attendances`
--

DROP TABLE IF EXISTS `staff_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `late_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL,
  `marked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_attendance_teacher_date_unique` (`school_id`,`teacher_id`,`attendance_date`),
  UNIQUE KEY `staff_attendance_employee_date_unique` (`school_id`,`employee_id`,`attendance_date`),
  KEY `staff_attendances_teacher_id_foreign` (`teacher_id`),
  KEY `staff_attendances_employee_id_foreign` (`employee_id`),
  KEY `staff_attendances_marked_by_foreign` (`marked_by`),
  KEY `staff_attendance_date_status_index` (`school_id`,`attendance_date`,`status`),
  CONSTRAINT `staff_attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `staff_attendances_marked_by_foreign` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_attendances_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `staff_attendances_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_leaves`
--

DROP TABLE IF EXISTS `staff_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_leaves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `leave_type_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,1) NOT NULL,
  `reason` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_leaves_leave_type_id_foreign` (`leave_type_id`),
  KEY `staff_leaves_teacher_id_foreign` (`teacher_id`),
  KEY `staff_leaves_employee_id_foreign` (`employee_id`),
  KEY `staff_leaves_approved_by_foreign` (`approved_by`),
  KEY `staff_leaves_status_index` (`school_id`,`status`,`start_date`),
  KEY `staff_leaves_teacher_index` (`school_id`,`teacher_id`),
  KEY `staff_leaves_employee_index` (`school_id`,`employee_id`),
  CONSTRAINT `staff_leaves_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_leaves_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `staff_leaves_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`),
  CONSTRAINT `staff_leaves_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `staff_leaves_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_attendance_cards`
--

DROP TABLE IF EXISTS `student_attendance_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_attendance_cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `issued_by` bigint(20) unsigned DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revoked_by` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoke_reason` varchar(255) DEFAULT NULL,
  `last_scanned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_attendance_cards_token_hash_unique` (`token_hash`),
  KEY `student_attendance_cards_student_id_foreign` (`student_id`),
  KEY `student_attendance_cards_issued_by_foreign` (`issued_by`),
  KEY `student_attendance_cards_revoked_by_foreign` (`revoked_by`),
  KEY `attendance_cards_student_active_index` (`school_id`,`student_id`,`revoked_at`),
  CONSTRAINT `student_attendance_cards_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_attendance_cards_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_attendance_cards_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `student_attendance_cards_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_attendances`
--

DROP TABLE IF EXISTS `student_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `source` varchar(20) NOT NULL DEFAULT 'manual',
  `source_ref` varchar(255) DEFAULT NULL,
  `late_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `correction_reason` varchar(255) DEFAULT NULL,
  `corrected_by` bigint(20) unsigned DEFAULT NULL,
  `corrected_at` timestamp NULL DEFAULT NULL,
  `marked_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_attendance_student_date_unique` (`school_id`,`student_id`,`attendance_date`),
  KEY `student_attendances_student_id_foreign` (`student_id`),
  KEY `student_attendances_enrollment_id_foreign` (`enrollment_id`),
  KEY `student_attendances_academic_session_id_foreign` (`academic_session_id`),
  KEY `student_attendances_class_id_foreign` (`class_id`),
  KEY `student_attendances_section_id_foreign` (`section_id`),
  KEY `student_attendances_marked_by_foreign` (`marked_by`),
  KEY `student_attendance_class_date_index` (`school_id`,`class_id`,`section_id`,`attendance_date`),
  KEY `student_attendance_status_date_index` (`school_id`,`status`,`attendance_date`),
  KEY `student_attendances_corrected_by_foreign` (`corrected_by`),
  KEY `student_attendance_source_date_index` (`school_id`,`source`,`attendance_date`),
  CONSTRAINT `student_attendances_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `student_attendances_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `student_attendances_corrected_by_foreign` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_attendances_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`),
  CONSTRAINT `student_attendances_marked_by_foreign` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_attendances_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `student_attendances_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `student_attendances_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_guardians`
--

DROP TABLE IF EXISTS `student_guardians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_guardians` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `guardian_id` bigint(20) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `is_emergency_contact` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_guardians_unique` (`student_id`,`guardian_id`),
  KEY `student_guardians_guardian_id_foreign` (`guardian_id`),
  KEY `student_guardians_school_id_guardian_id_index` (`school_id`,`guardian_id`),
  CONSTRAINT `student_guardians_guardian_id_foreign` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_guardians_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `student_guardians_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_leaves`
--

DROP TABLE IF EXISTS `student_leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_leaves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned DEFAULT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `leave_type` varchar(30) NOT NULL DEFAULT 'casual',
  `reason` text NOT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `decision_notes` text DEFAULT NULL,
  `decided_by` bigint(20) unsigned DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_leaves_student_id_foreign` (`student_id`),
  KEY `student_leaves_academic_session_id_foreign` (`academic_session_id`),
  KEY `student_leaves_decided_by_foreign` (`decided_by`),
  KEY `student_leaves_created_by_foreign` (`created_by`),
  KEY `student_leaves_student_date_index` (`school_id`,`student_id`,`from_date`),
  KEY `student_leaves_status_index` (`school_id`,`status`,`created_at`),
  CONSTRAINT `student_leaves_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_leaves_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_leaves_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_leaves_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `student_leaves_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_promotions`
--

DROP TABLE IF EXISTS `student_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `from_academic_session_id` bigint(20) unsigned NOT NULL,
  `to_academic_session_id` bigint(20) unsigned DEFAULT NULL,
  `from_enrollment_id` bigint(20) unsigned NOT NULL,
  `to_enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `from_class_id` bigint(20) unsigned NOT NULL,
  `from_section_id` bigint(20) unsigned DEFAULT NULL,
  `from_roll_no` int(10) unsigned DEFAULT NULL,
  `to_class_id` bigint(20) unsigned DEFAULT NULL,
  `to_section_id` bigint(20) unsigned DEFAULT NULL,
  `to_roll_no` int(10) unsigned DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `eligibility` varchar(20) DEFAULT NULL,
  `result_exam_id` bigint(20) unsigned DEFAULT NULL,
  `result_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_snapshot`)),
  `notes` text DEFAULT NULL,
  `promoted_by` bigint(20) unsigned DEFAULT NULL,
  `promoted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_promotions_student_from_session_unique` (`school_id`,`student_id`,`from_academic_session_id`),
  KEY `student_promotions_student_id_foreign` (`student_id`),
  KEY `student_promotions_from_academic_session_id_foreign` (`from_academic_session_id`),
  KEY `student_promotions_to_academic_session_id_foreign` (`to_academic_session_id`),
  KEY `student_promotions_from_enrollment_id_foreign` (`from_enrollment_id`),
  KEY `student_promotions_to_enrollment_id_foreign` (`to_enrollment_id`),
  KEY `student_promotions_from_class_id_foreign` (`from_class_id`),
  KEY `student_promotions_from_section_id_foreign` (`from_section_id`),
  KEY `student_promotions_to_class_id_foreign` (`to_class_id`),
  KEY `student_promotions_to_section_id_foreign` (`to_section_id`),
  KEY `student_promotions_result_exam_id_foreign` (`result_exam_id`),
  KEY `student_promotions_promoted_by_foreign` (`promoted_by`),
  KEY `student_promotions_action_date_index` (`school_id`,`action`,`promoted_at`),
  KEY `student_promotions_target_index` (`school_id`,`to_academic_session_id`,`to_class_id`,`to_section_id`),
  KEY `student_promotions_student_index` (`school_id`,`student_id`),
  CONSTRAINT `student_promotions_from_academic_session_id_foreign` FOREIGN KEY (`from_academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `student_promotions_from_class_id_foreign` FOREIGN KEY (`from_class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `student_promotions_from_enrollment_id_foreign` FOREIGN KEY (`from_enrollment_id`) REFERENCES `enrollments` (`id`),
  CONSTRAINT `student_promotions_from_section_id_foreign` FOREIGN KEY (`from_section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `student_promotions_promoted_by_foreign` FOREIGN KEY (`promoted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_promotions_result_exam_id_foreign` FOREIGN KEY (`result_exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_promotions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `student_promotions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `student_promotions_to_academic_session_id_foreign` FOREIGN KEY (`to_academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `student_promotions_to_class_id_foreign` FOREIGN KEY (`to_class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `student_promotions_to_enrollment_id_foreign` FOREIGN KEY (`to_enrollment_id`) REFERENCES `enrollments` (`id`),
  CONSTRAINT `student_promotions_to_section_id_foreign` FOREIGN KEY (`to_section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_results`
--

DROP TABLE IF EXISTS `student_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `exam_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `total_full_marks` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total_obtained` decimal(8,2) NOT NULL DEFAULT 0.00,
  `gpa` decimal(3,2) DEFAULT NULL,
  `grade_letter` varchar(5) DEFAULT NULL,
  `position` int(10) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot`)),
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_results_unique` (`exam_id`,`student_id`),
  KEY `student_results_student_id_foreign` (`student_id`),
  KEY `student_results_exam_status_index` (`school_id`,`exam_id`,`status`),
  CONSTRAINT `student_results_exam_id_foreign` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`),
  CONSTRAINT `student_results_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `student_results_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `student_id` varchar(30) NOT NULL,
  `admission_no` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `date_of_birth` date NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `religion` varchar(20) DEFAULT NULL,
  `nationality` varchar(40) NOT NULL DEFAULT 'Bangladeshi',
  `photo_path` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `admission_date` date NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `students_school_student_id_unique` (`school_id`,`student_id`),
  UNIQUE KEY `students_school_admission_no_unique` (`school_id`,`admission_no`),
  UNIQUE KEY `students_user_unique` (`user_id`),
  KEY `students_created_by_foreign` (`created_by`),
  KEY `students_school_id_status_index` (`school_id`,`status`),
  KEY `students_school_name_index` (`school_id`,`last_name`,`first_name`),
  KEY `students_school_user_index` (`school_id`,`user_id`),
  KEY `students_mobile_number_index` (`mobile_number`),
  CONSTRAINT `students_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `students_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `study_materials`
--

DROP TABLE IF EXISTS `study_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `study_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'published',
  `teacher_id` bigint(20) unsigned DEFAULT NULL,
  `lesson_id` bigint(20) unsigned DEFAULT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'material',
  `work_date` date DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(160) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `study_materials_academic_session_id_foreign` (`academic_session_id`),
  KEY `study_materials_class_id_foreign` (`class_id`),
  KEY `study_materials_section_id_foreign` (`section_id`),
  KEY `study_materials_subject_id_foreign` (`subject_id`),
  KEY `study_materials_created_by_foreign` (`created_by`),
  KEY `study_materials_period_index` (`school_id`,`class_id`,`section_id`,`subject_id`),
  KEY `study_materials_recent_index` (`school_id`,`created_at`),
  KEY `study_materials_kind_period_index` (`school_id`,`kind`,`class_id`),
  KEY `study_materials_teacher_id_foreign` (`teacher_id`),
  KEY `study_materials_lesson_id_foreign` (`lesson_id`),
  KEY `study_materials_status_period_index` (`school_id`,`status`,`class_id`),
  CONSTRAINT `study_materials_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `study_materials_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `study_materials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `study_materials_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `study_materials_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `study_materials_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `study_materials_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `study_materials_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'theory',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subjects_school_name_unique` (`school_id`,`name`),
  UNIQUE KEY `subjects_school_code_unique` (`school_id`,`code`),
  KEY `subjects_school_id_is_active_index` (`school_id`,`is_active`),
  CONSTRAINT `subjects_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teachers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_code` varchar(30) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(30) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `designation` varchar(80) NOT NULL DEFAULT 'Teacher',
  `joining_date` date NOT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `employment_type` varchar(20) NOT NULL DEFAULT 'full_time',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teachers_school_employee_code_unique` (`school_id`,`employee_code`),
  UNIQUE KEY `teachers_user_id_unique` (`user_id`),
  KEY `teachers_school_id_status_index` (`school_id`,`status`),
  KEY `teachers_school_id_phone_index` (`school_id`,`phone`),
  CONSTRAINT `teachers_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `teachers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `locale` varchar(5) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  UNIQUE KEY `users_mobile_number_unique` (`mobile_number`),
  KEY `users_school_id_is_active_index` (`school_id`,`is_active`),
  KEY `users_school_id_deleted_at_index` (`school_id`,`deleted_at`),
  CONSTRAINT `users_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vouchers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `voucher_no` varchar(30) NOT NULL,
  `type` varchar(20) NOT NULL,
  `voucher_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'cash',
  `bank_account_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `payee` varchar(120) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'posted',
  `cash_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_school_no_unique` (`school_id`,`voucher_no`),
  KEY `vouchers_bank_account_id_foreign` (`bank_account_id`),
  KEY `vouchers_cash_transaction_id_foreign` (`cash_transaction_id`),
  KEY `vouchers_created_by_foreign` (`created_by`),
  KEY `vouchers_cancelled_by_foreign` (`cancelled_by`),
  KEY `vouchers_date_type_index` (`school_id`,`voucher_date`,`type`),
  CONSTRAINT `vouchers_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_cash_transaction_id_foreign` FOREIGN KEY (`cash_transaction_id`) REFERENCES `cash_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 19:14:13
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: school_ms_fresh
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_schools_table',1),(2,'0001_01_01_000001_create_users_table',1),(3,'0001_01_01_000002_create_cache_table',1),(4,'0001_01_01_000003_create_jobs_table',1),(5,'2026_10_26_144947_create_personal_access_tokens_table',1),(6,'2026_10_26_144953_create_permission_tables',1),(7,'2026_10_26_150000_create_audit_and_notification_tables',1),(8,'2026_10_26_150100_create_academic_structure_tables',1),(9,'2026_10_26_150200_create_student_tables',1),(10,'2026_10_26_150300_create_staff_tables',1),(11,'2026_10_26_150400_create_student_attendance_table',1),(12,'2026_10_26_150500_create_timetable_homework_notice_tables',1),(13,'2026_10_26_150600_create_fee_tables',1),(14,'2026_10_26_150610_add_billing_period_to_fee_invoices',1),(15,'2026_10_26_150700_create_accounting_tables',1),(16,'2026_10_26_150800_create_exam_tables',1),(17,'2026_10_26_150900_create_integration_and_backup_tables',1),(18,'2026_10_26_151000_add_user_id_to_students_table',1),(19,'2026_10_27_000100_add_metadata_to_notifications_table',1),(20,'2026_10_27_000200_create_notification_preferences_table',1),(21,'2026_10_27_000300_create_push_subscriptions_table',1),(22,'2026_10_27_000400_create_push_delivery_logs_table',1),(23,'2026_10_27_000500_create_device_push_tokens_table',1),(24,'2026_10_28_000100_add_applicant_fields_to_admissions_table',1),(25,'2026_10_28_120000_create_homework_submissions_table',1),(26,'2026_10_28_120100_create_student_leaves_table',1),(27,'2026_10_28_130000_add_method_columns_to_student_attendances',1),(28,'2026_10_28_130100_create_attendance_devices_table',1),(29,'2026_10_28_130200_create_attendance_events_table',1),(30,'2026_10_28_130300_create_student_attendance_cards_table',1),(31,'2026_10_28_130400_create_attendance_device_users_table',1),(32,'2026_10_29_130000_add_fee_invoice_active_billing_unique_index',1),(33,'2026_10_29_140000_create_attendance_submissions_table',1),(34,'2026_10_29_140000_create_finance_operations_tables',1),(35,'2026_10_29_210000_create_mark_submissions_table',1),(36,'2026_10_29_220000_create_study_materials_table',1),(37,'2026_10_29_230000_add_status_to_homework_table',1),(38,'2026_10_29_230100_add_kind_to_study_materials_table',1),(39,'2026_10_30_100000_add_fee_settlement_waiver_columns',1),(40,'2026_10_30_140000_extend_fee_configuration',1),(41,'2026_10_30_150000_prepare_payment_gateway_transactions',1),(42,'2026_10_31_100000_create_class_videos_table',1),(43,'2026_10_31_120000_extend_fee_discount_management',1),(44,'2026_11_01_120000_create_lessons_table',1),(45,'2026_11_01_121000_create_lesson_materials_table',1),(46,'2026_11_01_130000_update_users_and_students_for_student_auth',1),(47,'2026_11_02_000000_create_student_promotions_table',1),(48,'2026_11_03_000000_drop_name_bn_columns',1),(49,'2026_11_04_100000_add_instructions_to_homework_table',1),(50,'2026_11_04_101000_add_status_and_teacher_to_study_materials_table',1),(51,'2026_11_04_120000_repair_study_material_status_columns',1),(52,'2026_11_04_130000_repair_fee_invoice_amount_columns',1),(53,'2026_11_04_140000_add_collection_tracking_to_fee_payments',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(2,'users.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(3,'users.create','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(4,'users.update','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(5,'users.delete','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(6,'users.reset-password','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(7,'roles.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(8,'school.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(9,'school.update','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(10,'academic.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(11,'academic.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(12,'students.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(13,'students.create','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(14,'students.update','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(15,'students.delete','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(16,'students.export','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(17,'admissions.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(18,'admissions.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(19,'staff.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(20,'staff.create','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(21,'staff.update','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(22,'staff.delete','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(23,'staff.export','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(24,'leave.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(25,'leave.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(26,'leave.apply','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(27,'salary.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(28,'attendance.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(29,'attendance.mark','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(30,'attendance.submit','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(31,'attendance.approve','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(32,'attendance.report','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(33,'attendance.qr.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(34,'attendance.devices.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(35,'fees.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(36,'fees.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(37,'fees.collect','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(38,'fees.collect.scoped','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(39,'fees.reverse','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(40,'fees.export','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(41,'fees.verify','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(42,'fees.discounts.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(43,'fees.discounts.approve','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(44,'expenses.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(45,'expenses.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(46,'expenses.approve','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(47,'expenses.approve.high','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(48,'payroll.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(49,'payroll.manage','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(50,'cash.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(51,'bank.view','web','2026-11-04 13:13:53','2026-11-04 13:13:53'),(52,'bank.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(53,'vouchers.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(54,'vouchers.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(55,'accounts.close','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(56,'accountants.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(57,'accountants.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(58,'exams.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(59,'exams.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(60,'marks.enter','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(61,'marks.submit','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(62,'marks.approve','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(63,'results.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(64,'results.publish','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(65,'reports.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(66,'reports.export','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(67,'lessons.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(68,'lessons.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(69,'homework.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(70,'homework.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(71,'materials.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(72,'materials.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(73,'videos.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(74,'videos.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(75,'notices.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(76,'notices.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(77,'timetable.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(78,'timetable.manage','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(79,'audit.view','web','2026-11-04 13:13:54','2026-11-04 13:13:54');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'school_admin','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(2,'principal','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(3,'accountant','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(4,'teacher','web','2026-11-04 13:13:54','2026-11-04 13:13:54'),(5,'student','web','2026-11-04 13:13:54','2026-11-04 13:13:54');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(2,1),(2,2),(3,1),(4,1),(5,1),(6,1),(7,1),(7,2),(8,1),(8,2),(8,3),(9,1),(9,2),(10,1),(10,2),(11,1),(11,2),(12,1),(12,2),(12,3),(13,1),(13,2),(14,1),(14,2),(15,1),(15,2),(16,1),(16,2),(16,3),(17,1),(17,2),(18,1),(18,2),(19,1),(19,2),(20,1),(20,2),(21,1),(21,2),(22,1),(23,1),(23,2),(24,1),(24,2),(25,1),(25,2),(26,1),(26,2),(26,4),(27,1),(27,2),(28,1),(28,2),(28,4),(29,1),(29,2),(29,4),(30,1),(30,4),(31,1),(31,2),(32,1),(32,2),(32,4),(33,1),(33,2),(34,1),(35,1),(35,2),(35,3),(36,1),(36,3),(37,3),(39,1),(39,3),(40,1),(40,3),(41,1),(41,2),(41,3),(42,1),(42,3),(43,1),(43,2),(44,1),(44,2),(44,3),(45,1),(45,2),(45,3),(46,1),(46,2),(46,3),(47,1),(47,2),(48,1),(48,2),(48,3),(49,1),(49,3),(50,1),(50,3),(51,1),(51,2),(51,3),(52,1),(52,2),(53,1),(53,2),(53,3),(54,1),(54,2),(54,3),(55,1),(55,2),(55,3),(56,1),(57,1),(58,1),(58,2),(59,1),(59,2),(60,1),(60,2),(60,4),(61,1),(61,4),(62,1),(62,2),(63,1),(63,2),(63,5),(64,1),(64,2),(65,1),(65,2),(65,3),(66,1),(66,2),(66,3),(67,2),(67,4),(68,2),(68,4),(69,4),(70,4),(71,4),(72,4),(73,1),(73,2),(74,1),(74,2),(75,1),(75,2),(75,4),(75,5),(76,1),(76,2),(77,1),(77,2),(77,4),(78,1),(79,1),(79,2),(79,3);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 19:14:13
