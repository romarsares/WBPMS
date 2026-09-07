-- ============================================================
-- WBPMS — Clean Database Script
-- Generated: 2026-09-07
--
-- Usage:
--   1. Create an empty database:
--        CREATE DATABASE wbpms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. Run this file:
--        mysql -u root -p wbpms < clean_database.sql
--
-- This script:
--   • Drops all existing WBPMS tables (safe re-run)
--   • Re-creates the full schema (all 16 migrations applied)
--   • Seeds the minimum required reference data:
--       role, request_type, contribution_policy_version,
--       sss_bracket, philhealth_rate, pagibig_rate,
--       holiday_calendar, job_position
--
-- Demo credentials (after running seeds separately via phinx):
--   Business Owner : owner    / owner-demo-pass
--   HR Head        : hrhead   / hrhead-demo-pass
--   Employee       : employee / employee-demo-pass
--
-- WARNING: Never use demo accounts in production.
-- ============================================================

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

-- ============================================================
-- SECTION 1: DROP ALL TABLES (reverse dependency order)
-- ============================================================

DROP TABLE IF EXISTS `cash_advance_repayment`;
DROP TABLE IF EXISTS `cash_advance_history`;
DROP TABLE IF EXISTS `cash_advance_request_detail`;
DROP TABLE IF EXISTS `deposit_slip`;
DROP TABLE IF EXISTS `disbursement_batch`;
DROP TABLE IF EXISTS `payslip`;
DROP TABLE IF EXISTS `contribution_record`;
DROP TABLE IF EXISTS `payroll_adjustment`;
DROP TABLE IF EXISTS `payroll_earnings`;
DROP TABLE IF EXISTS `deduction`;
DROP TABLE IF EXISTS `payroll`;
DROP TABLE IF EXISTS `payroll_run`;
DROP TABLE IF EXISTS `payroll_period`;
DROP TABLE IF EXISTS `payroll_policy_version`;
DROP TABLE IF EXISTS `sss_bracket`;
DROP TABLE IF EXISTS `philhealth_rate`;
DROP TABLE IF EXISTS `pagibig_rate`;
DROP TABLE IF EXISTS `contribution_policy_version`;
DROP TABLE IF EXISTS `leave_ledger`;
DROP TABLE IF EXISTS `leave_entitlement`;
DROP TABLE IF EXISTS `overtime_request_detail`;
DROP TABLE IF EXISTS `leave_request_detail`;
DROP TABLE IF EXISTS `request`;
DROP TABLE IF EXISTS `request_type`;
DROP TABLE IF EXISTS `attendance_punch`;
DROP TABLE IF EXISTS `attendance_adjustment`;
DROP TABLE IF EXISTS `attendance_policy_flag`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `biometric_punch`;
DROP TABLE IF EXISTS `attendance_import_batch`;
DROP TABLE IF EXISTS `employee_document`;
DROP TABLE IF EXISTS `employee_lifecycle_event`;
DROP TABLE IF EXISTS `employee_employment_episode`;
DROP TABLE IF EXISTS `employment_contract_review`;
DROP TABLE IF EXISTS `employee_schedule_assignment`;
DROP TABLE IF EXISTS `employee_biometric_enrollment`;
DROP TABLE IF EXISTS `employee_branch_assignment`;
DROP TABLE IF EXISTS `bank_details`;
DROP TABLE IF EXISTS `salary`;
DROP TABLE IF EXISTS `leave_request_detail`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `password_reset_challenge`;
DROP TABLE IF EXISTS `employee`;
DROP TABLE IF EXISTS `address`;
DROP TABLE IF EXISTS `barangay`;
DROP TABLE IF EXISTS `city`;
DROP TABLE IF EXISTS `province`;
DROP TABLE IF EXISTS `biometric_device_branch`;
DROP TABLE IF EXISTS `biometric_device`;
DROP TABLE IF EXISTS `attendance_site`;
DROP TABLE IF EXISTS `branch`;
DROP TABLE IF EXISTS `work_schedule`;
DROP TABLE IF EXISTS `holiday_calendar`;
DROP TABLE IF EXISTS `job_position`;
DROP TABLE IF EXISTS `role`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `phinxlog`;

-- ============================================================
-- SECTION 2: SCHEMA
-- ============================================================

-- ------------------------------------------------------------
-- role
-- ------------------------------------------------------------
CREATE TABLE `role` (
  `role_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'BusinessOwner | HRHead | Employee',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Application roles: BusinessOwner, HRHead, Employee';

-- ------------------------------------------------------------
-- sessions
-- ------------------------------------------------------------
CREATE TABLE `sessions` (
  `session_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PHP session_id()',
  `expires_at` bigint unsigned NOT NULL COMMENT 'Unix timestamp; application enforces idle+absolute expiry',
  `data` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serialized session payload',
  PRIMARY KEY (`session_id`),
  KEY `idx_sessions_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PHP session storage; managed by DatabaseSessionHandler';

-- ------------------------------------------------------------
-- audit_logs
-- ------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL COMMENT 'NULL for failed-login and pre-auth events',
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_performed` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_affected` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `attempted_identifier` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_user_action_at` (`user_id`,`action_at`),
  KEY `idx_audit_event_action_at` (`event_type`,`action_at`),
  KEY `idx_audit_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable audit trail; user_id NULL for unauthenticated events';

-- ------------------------------------------------------------
-- branch
-- ------------------------------------------------------------
CREATE TABLE `branch` (
  `branch_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Inactive','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `uq_branch_code` (`branch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurable organizational branches';

-- ------------------------------------------------------------
-- attendance_site
-- ------------------------------------------------------------
CREATE TABLE `attendance_site` (
  `site_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `site_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Manila',
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`site_id`),
  UNIQUE KEY `uq_site_code` (`site_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Physical locations where biometric devices are installed';

-- ------------------------------------------------------------
-- biometric_device
-- ------------------------------------------------------------
CREATE TABLE `biometric_device` (
  `device_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` bigint unsigned NOT NULL,
  `device_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LDE_XLS_DAILY_LOG_V1',
  `timezone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Manila',
  `status` enum('Active','Inactive','Retired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `installed_at` date DEFAULT NULL,
  `retired_at` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_id`),
  UNIQUE KEY `uq_device_code` (`device_code`),
  UNIQUE KEY `uq_device_serial` (`serial_number`),
  KEY `site_id` (`site_id`),
  CONSTRAINT `biometric_device_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `attendance_site` (`site_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registered biometric devices; one device may cover multiple branches';

-- ------------------------------------------------------------
-- biometric_device_branch
-- ------------------------------------------------------------
CREATE TABLE `biometric_device_branch` (
  `device_branch_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`device_branch_id`),
  UNIQUE KEY `uq_dbb_device_branch_from` (`device_id`,`branch_id`,`effective_from`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `biometric_device_branch_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `biometric_device` (`device_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `biometric_device_branch_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_dbb_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Effective-dated coverage: which branches a device serves';

-- ------------------------------------------------------------
-- province / city / barangay / address
-- ------------------------------------------------------------
CREATE TABLE `province` (
  `province_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `province_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`province_id`),
  UNIQUE KEY `uq_province_name` (`province_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `city` (
  `city_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `city_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`city_id`),
  KEY `idx_city_name` (`city_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `barangay` (
  `barangay_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `barangay_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`barangay_id`),
  KEY `idx_barangay_name` (`barangay_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `address` (
  `address_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `street` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_id` bigint unsigned DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `barangay_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`address_id`),
  KEY `province_id` (`province_id`),
  KEY `city_id` (`city_id`),
  KEY `barangay_id` (`barangay_id`),
  CONSTRAINT `address_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `province` (`province_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `address_ibfk_2` FOREIGN KEY (`city_id`) REFERENCES `city` (`city_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `address_ibfk_3` FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`barangay_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Flat address structure; all geography references optional';

-- ------------------------------------------------------------
-- employee
-- ------------------------------------------------------------
CREATE TABLE `employee` (
  `employee_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Immutable after creation',
  `employee_type` enum('Regular','Contractual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_initial` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `hire_date` date NOT NULL,
  `id_picture` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path in storage/private/',
  `address_id` bigint unsigned DEFAULT NULL,
  `status` enum('Active','Inactive','Separated','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `philhealth_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagibig_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tin_number` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sss_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Employee SSS ID number (optional)',
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_review_date` date DEFAULT NULL COMMENT 'Required for Contractual; populated at creation',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employee_number` (`employee_number`),
  UNIQUE KEY `uq_employee_email` (`email`),
  KEY `address_id` (`address_id`),
  CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`address_id`) REFERENCES `address` (`address_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Core employee record; employee_number is immutable after creation';

-- ------------------------------------------------------------
-- users  (employee FK added after employee table exists)
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `user_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned DEFAULT NULL COMMENT 'NULL for non-payroll users (e.g. Business Owner)',
  `role_id` bigint unsigned NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_email` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `requires_password_change` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Force password change on next login',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_account_email` (`account_email`),
  UNIQUE KEY `uq_users_employee_id` (`employee_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System accounts; employee_id nullable for the Business Owner';

-- ------------------------------------------------------------
-- password_reset_challenge
-- ------------------------------------------------------------
CREATE TABLE `password_reset_challenge` (
  `challenge_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `otp_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts_remaining` tinyint unsigned NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`challenge_id`),
  KEY `idx_prc_user_expires` (`user_id`,`expires_at`),
  CONSTRAINT `password_reset_challenge_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Hashed, expiring, single-use OTP challenges for password recovery';

-- ------------------------------------------------------------
-- audit_logs FK (deferred after users)
-- ------------------------------------------------------------
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ------------------------------------------------------------
-- employment_contract_review
-- ------------------------------------------------------------
CREATE TABLE `employment_contract_review` (
  `review_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `review_due_date` date NOT NULL,
  `outcome` enum('Regularized','Renewed','Separated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_date` date NOT NULL,
  `next_review_date` date DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `notes` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uq_ecr_employee_effective` (`employee_id`,`effective_date`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `employment_contract_review_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employment_contract_review_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contractual employee review outcomes';

-- ------------------------------------------------------------
-- employee_branch_assignment
-- ------------------------------------------------------------
CREATE TABLE `employee_branch_assignment` (
  `branch_assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `transfer_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transferred_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_assignment_id`),
  UNIQUE KEY `uq_eba_employee_from` (`employee_id`,`effective_from`),
  KEY `branch_id` (`branch_id`),
  KEY `transferred_by` (`transferred_by`),
  CONSTRAINT `employee_branch_assignment_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_branch_assignment_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_branch_assignment_ibfk_3` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_eba_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Effective-dated branch membership; one assignment per employee per date';

-- ------------------------------------------------------------
-- employee_biometric_enrollment
-- ------------------------------------------------------------
CREATE TABLE `employee_biometric_enrollment` (
  `enrollment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `device_employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`enrollment_id`),
  UNIQUE KEY `uq_ebe_device_code_from` (`device_id`,`device_employee_code`,`effective_from`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `employee_biometric_enrollment_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_biometric_enrollment_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `biometric_device` (`device_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_ebe_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maps device enrollment code to employee; preserves leading zeroes';

-- ------------------------------------------------------------
-- work_schedule
-- ------------------------------------------------------------
CREATE TABLE `work_schedule` (
  `schedule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schedule_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `working_days` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rest_days` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `break_minutes` smallint unsigned NOT NULL DEFAULT '60',
  `grace_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `overtime_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `break_start_time` time DEFAULT NULL,
  `break_end_time` time DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `work_start_time` time NOT NULL,
  `work_end_time` time NOT NULL,
  `standard_minutes` smallint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('Active','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `uq_ws_name` (`schedule_name`),
  CONSTRAINT `chk_ws_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee work schedule; working_days and rest_days stored as JSON arrays';

-- ------------------------------------------------------------
-- employee_schedule_assignment
-- ------------------------------------------------------------
CREATE TABLE `employee_schedule_assignment` (
  `assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `schedule_id` bigint unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('Active','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uq_esa_employee_from` (`employee_id`,`effective_from`),
  KEY `schedule_id` (`schedule_id`),
  CONSTRAINT `employee_schedule_assignment_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_schedule_assignment_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `work_schedule` (`schedule_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_esa_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links an employee to a reusable work_schedule';

-- ------------------------------------------------------------
-- holiday_calendar
-- ------------------------------------------------------------
CREATE TABLE `holiday_calendar` (
  `holiday_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holiday_date` date NOT NULL,
  `description` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `holiday_type` enum('Regular','Special') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pay_multiplier` decimal(4,2) NOT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`holiday_id`),
  UNIQUE KEY `uq_holiday_date` (`holiday_date`),
  CONSTRAINT `chk_holiday_multiplier` CHECK (
    ((`holiday_type` = 'Regular') AND (`pay_multiplier` = 2.00))
    OR ((`holiday_type` = 'Special') AND (`pay_multiplier` = 1.30))
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Public holidays; Regular=200%, Special=130% pay multiplier';

-- ------------------------------------------------------------
-- job_position
-- ------------------------------------------------------------
CREATE TABLE `job_position` (
  `position_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`position_id`),
  UNIQUE KEY `uq_position_title` (`position_title`),
  KEY `idx_position_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Managed list of job titles/positions for the employee form dropdown';

-- ------------------------------------------------------------
-- bank_details
-- ------------------------------------------------------------
CREATE TABLE `bank_details` (
  `bank_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `bank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDO',
  `account_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bank_id`),
  UNIQUE KEY `uq_bank_name_acct_from` (`bank_name`,`account_number`,`effective_from`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `bank_details_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_bank_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Effective-dated bank accounts; one active BDO account per employee';

-- ------------------------------------------------------------
-- salary
-- ------------------------------------------------------------
CREATE TABLE `salary` (
  `salary_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `daily_rate` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('Active','Archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`salary_id`),
  UNIQUE KEY `uq_salary_employee_from` (`employee_id`,`effective_from`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `salary_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `salary_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_salary_period` CHECK ((`effective_to` IS NULL OR `effective_to` > `effective_from`)),
  CONSTRAINT `chk_salary_rate` CHECK (`daily_rate` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Effective-dated daily rate per employee; new row for every rate change';

-- ------------------------------------------------------------
-- request_type / request
-- ------------------------------------------------------------
CREATE TABLE `request_type` (
  `request_type_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type_name` enum('Leave','Overtime','CashAdvance') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Active','Inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_type_id`),
  UNIQUE KEY `uq_request_type_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lookup for Leave, Overtime, CashAdvance request categories';

CREATE TABLE `request` (
  `request_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `request_type_id` bigint unsigned NOT NULL,
  `reason` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `submitted_at` datetime NOT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_by` bigint unsigned DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_request_employee` (`employee_id`),
  KEY `request_type_id` (`request_type_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `archived_by` (`archived_by`),
  CONSTRAINT `request_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `request_ibfk_2` FOREIGN KEY (`request_type_id`) REFERENCES `request_type` (`request_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `request_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `request_ibfk_4` FOREIGN KEY (`archived_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee request header; exactly one detail row per request_type';

CREATE TABLE `leave_request_detail` (
  `request_id` bigint unsigned NOT NULL,
  `leave_type` enum('Sick') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` decimal(5,2) NOT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `leave_request_detail_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_lrd_dates` CHECK (`end_date` >= `start_date` AND `days_requested` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `overtime_request_detail` (
  `request_id` bigint unsigned NOT NULL,
  `overtime_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `requested_minutes` smallint unsigned NOT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `overtime_request_detail_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_ord_minutes` CHECK (`requested_minutes` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_advance_request_detail` (
  `request_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  PRIMARY KEY (`request_id`),
  CONSTRAINT `cash_advance_request_detail_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_card_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- leave_entitlement / leave_ledger
-- ------------------------------------------------------------
CREATE TABLE `leave_entitlement` (
  `entitlement_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `leave_type` enum('Sick') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `leave_year` smallint unsigned NOT NULL,
  `entitled_days` decimal(5,2) NOT NULL DEFAULT '4.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entitlement_id`),
  UNIQUE KEY `uq_le_employee_type_year` (`employee_id`,`leave_type`,`leave_year`),
  CONSTRAINT `leave_entitlement_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Annual sick leave allocation; 4 days default';

CREATE TABLE `leave_ledger` (
  `entry_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entitlement_id` bigint unsigned NOT NULL,
  `request_id` bigint unsigned DEFAULT NULL,
  `entry_type` enum('Grant','Usage','Adjustment','Reversal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `days_delta` decimal(5,2) NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  UNIQUE KEY `uq_ll_request_id` (`request_id`),
  KEY `entitlement_id` (`entitlement_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `leave_ledger_ibfk_1` FOREIGN KEY (`entitlement_id`) REFERENCES `leave_entitlement` (`entitlement_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `leave_ledger_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `leave_ledger_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only ledger; running balance = SUM(days_delta) per entitlement';

-- ------------------------------------------------------------
-- attendance_import_batch / biometric_punch / attendance
-- ------------------------------------------------------------
CREATE TABLE `attendance_import_batch` (
  `import_batch_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_checksum` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_year` smallint unsigned NOT NULL,
  `source_month` tinyint unsigned NOT NULL,
  `parser_version` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LDE_XLS_DAILY_LOG_V1',
  `status` enum('Processing','Draft','Approved','Completed','Rejected','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Processing',
  `records_parsed` int unsigned NOT NULL DEFAULT '0',
  `records_matched` int unsigned NOT NULL DEFAULT '0',
  `records_unmatched` int unsigned NOT NULL DEFAULT '0',
  `duplicates_skipped` int unsigned NOT NULL DEFAULT '0',
  `incomplete_days` int unsigned NOT NULL DEFAULT '0',
  `multi_punch_days` int unsigned NOT NULL DEFAULT '0',
  `uploaded_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `cancelled_by` bigint unsigned DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`import_batch_id`),
  UNIQUE KEY `uq_batch_checksum` (`file_checksum`),
  KEY `device_id` (`device_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `approved_by` (`approved_by`),
  KEY `cancelled_by` (`cancelled_by`),
  CONSTRAINT `attendance_import_batch_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `biometric_device` (`device_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_import_batch_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_import_batch_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `attendance_import_batch_ibfk_4` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_batch_month` CHECK (`source_month` BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One record per uploaded .xls file; SHA-256 prevents duplicate imports';

CREATE TABLE `biometric_punch` (
  `punch_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned DEFAULT NULL,
  `branch_assignment_id` bigint unsigned DEFAULT NULL,
  `device_employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_local_at` datetime NOT NULL,
  `punched_at_utc` datetime NOT NULL,
  `punch_type` enum('In','Out','Unknown') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_transaction_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_status` enum('matched','unmatched','coverage_exception','duplicate') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_user_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_employee_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_record` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_workbook_row` int unsigned NOT NULL,
  `source_date_column` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`punch_id`),
  UNIQUE KEY `uq_punch_device_code_local_at` (`device_id`,`device_employee_code`,`source_local_at`),
  KEY `idx_punch_batch` (`import_batch_id`),
  KEY `idx_punch_employee` (`employee_id`),
  KEY `branch_assignment_id` (`branch_assignment_id`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `biometric_punch_ibfk_1` FOREIGN KEY (`import_batch_id`) REFERENCES `attendance_import_batch` (`import_batch_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `biometric_punch_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `biometric_device` (`device_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `biometric_punch_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `biometric_punch_ibfk_4` FOREIGN KEY (`branch_assignment_id`) REFERENCES `employee_branch_assignment` (`branch_assignment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `biometric_punch_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable raw punch; all time tokens from the workbook are preserved';

CREATE TABLE `attendance` (
  `attendance_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `branch_assignment_id` bigint unsigned NOT NULL,
  `schedule_id` bigint unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `late_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `undertime_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `overtime_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `status` enum('Complete','Incomplete','ReviewRequired','Approved','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Complete',
  `source` enum('xls_import','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `import_batch_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance_employee_date` (`employee_id`,`attendance_date`),
  KEY `branch_assignment_id` (`branch_assignment_id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `import_batch_id` (`import_batch_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`branch_assignment_id`) REFERENCES `employee_branch_assignment` (`branch_assignment_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`schedule_id`) REFERENCES `work_schedule` (`schedule_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`import_batch_id`) REFERENCES `attendance_import_batch` (`import_batch_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily attendance; unique (employee_id, attendance_date)';

CREATE TABLE `attendance_punch` (
  `attendance_id` bigint unsigned NOT NULL,
  `punch_id` bigint unsigned NOT NULL,
  `evidence_role` enum('TimeIn','Intermediate','TimeOut','Unclassified') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`,`punch_id`),
  UNIQUE KEY `uq_ap_punch_id` (`punch_id`),
  CONSTRAINT `attendance_punch_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_punch_ibfk_2` FOREIGN KEY (`punch_id`) REFERENCES `biometric_punch` (`punch_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Links each attendance record to its supporting biometric_punch rows';

CREATE TABLE `attendance_adjustment` (
  `adjustment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attendance_id` bigint unsigned NOT NULL,
  `adjusted_by` bigint unsigned DEFAULT NULL,
  `old_time_in` time DEFAULT NULL,
  `new_time_in` time DEFAULT NULL,
  `old_time_out` time DEFAULT NULL,
  `new_time_out` time DEFAULT NULL,
  `old_overtime_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `new_overtime_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `adjustment_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adjustment_id`),
  KEY `attendance_id` (`attendance_id`),
  KEY `adjusted_by` (`adjusted_by`),
  CONSTRAINT `attendance_adjustment_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_adjustment_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only log of manual attendance corrections';

CREATE TABLE `attendance_policy_flag` (
  `flag_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `flag_type` enum('ConsecutiveLate','TardinessMemoCap','TwoWeekAbsence','ConsecutiveAWOL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `triggering_date` date NOT NULL,
  `status` enum('Pending','Reviewed','Closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `action_taken` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`flag_id`),
  UNIQUE KEY `uq_apf_employee_type_date` (`employee_id`,`flag_type`,`triggering_date`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `attendance_policy_flag_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `attendance_policy_flag_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Attendance threshold flags for HR review';

-- ------------------------------------------------------------
-- contribution_policy_version / sss_bracket / philhealth_rate / pagibig_rate
-- ------------------------------------------------------------
CREATE TABLE `contribution_policy_version` (
  `contribution_policy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `policy_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `demo_only` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Draft','Approved','Retired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`contribution_policy_id`),
  UNIQUE KEY `uq_cpv_code_version` (`policy_code`,`version`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `contribution_policy_version_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Versioned government contribution policy';

CREATE TABLE `sss_bracket` (
  `bracket_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contribution_policy_id` bigint unsigned NOT NULL,
  `salary_from` decimal(12,2) NOT NULL,
  `salary_to` decimal(12,2) DEFAULT NULL,
  `employee_share` decimal(12,2) NOT NULL,
  `employer_share` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bracket_id`),
  UNIQUE KEY `uq_sss_policy_from` (`contribution_policy_id`,`salary_from`),
  CONSTRAINT `sss_bracket_ibfk_1` FOREIGN KEY (`contribution_policy_id`) REFERENCES `contribution_policy_version` (`contribution_policy_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SSS salary brackets; salary_to NULL means open upper bound';

CREATE TABLE `philhealth_rate` (
  `rate_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contribution_policy_id` bigint unsigned NOT NULL,
  `rate_decimal` decimal(7,6) NOT NULL,
  `basis_floor` decimal(12,2) DEFAULT NULL,
  `basis_ceiling` decimal(12,2) DEFAULT NULL,
  `employee_share_decimal` decimal(7,6) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uq_philhealth_policy` (`contribution_policy_id`),
  CONSTRAINT `philhealth_rate_ibfk_1` FOREIGN KEY (`contribution_policy_id`) REFERENCES `contribution_policy_version` (`contribution_policy_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PhilHealth rate; 4% total split 50/50';

CREATE TABLE `pagibig_rate` (
  `rate_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contribution_policy_id` bigint unsigned NOT NULL,
  `rate_decimal` decimal(7,6) DEFAULT NULL,
  `basis_ceiling` decimal(12,2) DEFAULT NULL,
  `employee_fixed_amount` decimal(12,2) DEFAULT NULL,
  `employer_fixed_amount` decimal(12,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uq_pagibig_policy` (`contribution_policy_id`),
  CONSTRAINT `pagibig_rate_ibfk_1` FOREIGN KEY (`contribution_policy_id`) REFERENCES `contribution_policy_version` (`contribution_policy_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_pagibig_mode` CHECK (
    (`rate_decimal` IS NOT NULL AND `basis_ceiling` IS NOT NULL)
    OR (`employee_fixed_amount` IS NOT NULL AND `employer_fixed_amount` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pag-IBIG rate; ₱200 employee + ₱200 employer fixed';

-- ------------------------------------------------------------
-- payroll_policy_version / payroll_period / payroll_run / payroll
-- ------------------------------------------------------------
CREATE TABLE `payroll_policy_version` (
  `policy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `policy_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `eemr_days_per_year` smallint unsigned NOT NULL DEFAULT '313',
  `annual_tax_threshold` decimal(12,2) NOT NULL DEFAULT '250000.00',
  `late_rate_per_minute` decimal(12,2) NOT NULL DEFAULT '1.00',
  `rounding_mode` enum('HalfUp') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'HalfUp',
  `demo_only` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('Draft','Approved','Retired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`policy_id`),
  UNIQUE KEY `uq_ppv_code_version` (`policy_code`,`version`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `payroll_policy_version_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Versioned payroll rules';

CREATE TABLE `payroll_period` (
  `payroll_period_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `pay_date` date NOT NULL,
  `status` enum('Open','AttendanceClosed','Disbursed','Closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
  `cutoff_pattern` enum('LegacyFridayThursday','SundayFriday') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SundayFriday',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payroll_period_id`),
  UNIQUE KEY `uq_period_start` (`period_start`),
  UNIQUE KEY `uq_period_pay_date` (`pay_date`),
  CONSTRAINT `chk_period_cutoff_pattern` CHECK (
    ((`cutoff_pattern` = 'LegacyFridayThursday') AND DAYOFWEEK(`period_start`)=6 AND DAYOFWEEK(`period_end`)=5 AND (TO_DAYS(`period_end`)-TO_DAYS(`period_start`))=6 AND `pay_date`=(`period_end` + INTERVAL 1 DAY))
    OR ((`cutoff_pattern` = 'SundayFriday') AND DAYOFWEEK(`period_start`)=1 AND DAYOFWEEK(`period_end`)=6 AND (TO_DAYS(`period_end`)-TO_DAYS(`period_start`))=5 AND `pay_date`=`period_end`)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Weekly payroll window';

CREATE TABLE `payroll_run` (
  `payroll_run_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_period_id` bigint unsigned NOT NULL,
  `branch_id` bigint unsigned NOT NULL,
  `payroll_policy_id` bigint unsigned NOT NULL,
  `status` enum('Draft','Computed','PendingOwnerApproval','Approved','Returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `gross_pay` decimal(15,2) DEFAULT NULL,
  `total_deductions` decimal(15,2) DEFAULT NULL,
  `net_pay` decimal(15,2) DEFAULT NULL,
  `computed_by` bigint unsigned DEFAULT NULL,
  `computed_at` datetime DEFAULT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `return_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lock_version` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`payroll_run_id`),
  UNIQUE KEY `uq_run_period_branch` (`payroll_period_id`,`branch_id`),
  UNIQUE KEY `uq_run_id_period` (`payroll_run_id`,`payroll_period_id`),
  KEY `branch_id` (`branch_id`),
  KEY `payroll_policy_id` (`payroll_policy_id`),
  KEY `computed_by` (`computed_by`),
  KEY `submitted_by` (`submitted_by`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `payroll_run_ibfk_1` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_period` (`payroll_period_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_run_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branch` (`branch_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_run_ibfk_3` FOREIGN KEY (`payroll_policy_id`) REFERENCES `payroll_policy_version` (`policy_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_run_ibfk_4` FOREIGN KEY (`computed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `payroll_run_ibfk_5` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `payroll_run_ibfk_6` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_run_returned_reason` CHECK (`status` <> 'Returned' OR `return_reason` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Branch-scoped payroll run';

CREATE TABLE `payroll` (
  `payroll_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` bigint unsigned NOT NULL,
  `payroll_period_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `branch_assignment_id` bigint unsigned NOT NULL,
  `salary_id` bigint unsigned NOT NULL,
  `daily_rate_snapshot` decimal(12,2) NOT NULL,
  `gross_pay` decimal(12,2) NOT NULL,
  `total_deductions` decimal(12,2) NOT NULL,
  `net_pay` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payroll_id`),
  UNIQUE KEY `uq_payroll_period_employee` (`payroll_period_id`,`employee_id`),
  KEY `employee_id` (`employee_id`),
  KEY `branch_assignment_id` (`branch_assignment_id`),
  KEY `salary_id` (`salary_id`),
  KEY `fk_payroll_run_period` (`payroll_run_id`,`payroll_period_id`),
  CONSTRAINT `fk_payroll_run_period` FOREIGN KEY (`payroll_run_id`,`payroll_period_id`) REFERENCES `payroll_run` (`payroll_run_id`,`payroll_period_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`branch_assignment_id`) REFERENCES `employee_branch_assignment` (`branch_assignment_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_ibfk_3` FOREIGN KEY (`salary_id`) REFERENCES `salary` (`salary_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_ibfk_4` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_period` (`payroll_period_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee payroll detail; approved rows are immutable';

CREATE TABLE `payroll_earnings` (
  `earning_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `earning_type` enum('Basic','Overtime','RegularHoliday','SpecialHoliday','ThirteenthMonth','ManualAdjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_attendance_id` bigint unsigned DEFAULT NULL,
  `source_request_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `unit_rate` decimal(12,4) NOT NULL,
  `multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `amount` decimal(12,2) NOT NULL,
  `calculation_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`earning_id`),
  KEY `payroll_id` (`payroll_id`),
  KEY `source_attendance_id` (`source_attendance_id`),
  KEY `source_request_id` (`source_request_id`),
  CONSTRAINT `payroll_earnings_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_earnings_ibfk_2` FOREIGN KEY (`source_attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `payroll_earnings_ibfk_3` FOREIGN KEY (`source_request_id`) REFERENCES `request` (`request_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itemized earning components with calculation evidence';

CREATE TABLE `deduction` (
  `deduction_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `deduction_type` enum('Late','Undertime','CashAdvance','SSS','PhilHealth','PagIBIG','IncomeTax','ManualAdjustment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_attendance_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `unit_rate` decimal(12,4) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `calculation_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`deduction_id`),
  KEY `payroll_id` (`payroll_id`),
  KEY `source_attendance_id` (`source_attendance_id`),
  CONSTRAINT `deduction_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `deduction_ibfk_2` FOREIGN KEY (`source_attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itemized deduction lines including government contribution employee shares';

CREATE TABLE `contribution_record` (
  `contribution_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `deduction_id` bigint unsigned NOT NULL,
  `contribution_policy_id` bigint unsigned NOT NULL,
  `contribution_type` enum('SSS','PhilHealth','PagIBIG') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `eemr_basis` decimal(12,2) NOT NULL,
  `employee_share` decimal(12,2) NOT NULL,
  `employer_share` decimal(12,2) NOT NULL,
  `deduction_date` date NOT NULL,
  `calculation_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('Computed','Locked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Computed',
  `locked_at` datetime DEFAULT NULL,
  `locked_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`contribution_id`),
  UNIQUE KEY `uq_cr_payroll_type` (`payroll_id`,`contribution_type`),
  UNIQUE KEY `uq_cr_deduction_id` (`deduction_id`),
  KEY `contribution_policy_id` (`contribution_policy_id`),
  KEY `locked_by` (`locked_by`),
  CONSTRAINT `contribution_record_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `contribution_record_ibfk_2` FOREIGN KEY (`deduction_id`) REFERENCES `deduction` (`deduction_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `contribution_record_ibfk_3` FOREIGN KEY (`contribution_policy_id`) REFERENCES `contribution_policy_version` (`contribution_policy_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `contribution_record_ibfk_4` FOREIGN KEY (`locked_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Government contribution with employer share; unique per payroll×program';

CREATE TABLE `payroll_adjustment` (
  `adjustment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` bigint unsigned NOT NULL,
  `payroll_id` bigint unsigned NOT NULL,
  `adjustment_type` enum('earning','deduction') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `earning_id` bigint unsigned DEFAULT NULL,
  `deduction_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjusted_by` bigint unsigned NOT NULL,
  `adjusted_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adjustment_id`),
  KEY `idx_payroll_adjustment_payroll` (`payroll_id`),
  KEY `payroll_run_id` (`payroll_run_id`),
  KEY `earning_id` (`earning_id`),
  KEY `deduction_id` (`deduction_id`),
  KEY `adjusted_by` (`adjusted_by`),
  CONSTRAINT `payroll_adjustment_ibfk_1` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_run` (`payroll_run_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_adjustment_ibfk_2` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_adjustment_ibfk_3` FOREIGN KEY (`earning_id`) REFERENCES `payroll_earnings` (`earning_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_adjustment_ibfk_4` FOREIGN KEY (`deduction_id`) REFERENCES `deduction` (`deduction_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payroll_adjustment_ibfk_5` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only HR manual payroll earnings and deductions';

-- ------------------------------------------------------------
-- payslip
-- ------------------------------------------------------------
CREATE TABLE `payslip` (
  `payslip_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `issue_date` date NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `generated_at` datetime NOT NULL,
  `content_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`payslip_id`),
  UNIQUE KEY `uq_payslip_payroll_id` (`payroll_id`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `payslip_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `payslip_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Digital payslip generated after payroll approval; one per payroll row';

-- ------------------------------------------------------------
-- disbursement_batch / deposit_slip
-- ------------------------------------------------------------
CREATE TABLE `disbursement_batch` (
  `batch_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_period_id` bigint unsigned NOT NULL,
  `bank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BDO',
  `cheque_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cheque_total` decimal(15,2) NOT NULL,
  `status` enum('Prepared','Issued','Reconciled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Prepared',
  `prepared_by` bigint unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `uq_disbursement_period` (`payroll_period_id`),
  UNIQUE KEY `uq_disbursement_cheque` (`cheque_number`),
  KEY `prepared_by` (`prepared_by`),
  CONSTRAINT `disbursement_batch_ibfk_1` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_period` (`payroll_period_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `disbursement_batch_ibfk_2` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One BDO cheque per payroll period';

CREATE TABLE `deposit_slip` (
  `slip_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint unsigned NOT NULL,
  `payroll_id` bigint unsigned NOT NULL,
  `bank_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('Pending','Prepared','Deposited') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `prepared_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`slip_id`),
  UNIQUE KEY `uq_slip_payroll_id` (`payroll_id`),
  KEY `batch_id` (`batch_id`),
  KEY `bank_id` (`bank_id`),
  CONSTRAINT `deposit_slip_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `disbursement_batch` (`batch_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `deposit_slip_ibfk_2` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `deposit_slip_ibfk_3` FOREIGN KEY (`bank_id`) REFERENCES `bank_details` (`bank_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_slip_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual employee deposit slips for the printable BDO preparation list';

-- ------------------------------------------------------------
-- cash_advance_history / cash_advance_repayment
-- ------------------------------------------------------------
CREATE TABLE `cash_advance_history` (
  `history_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `original_amount` decimal(12,2) NOT NULL,
  `remaining_balance` decimal(12,2) NOT NULL,
  `status` enum('Active','Paid','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `approved_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  UNIQUE KEY `uq_cah_request_id` (`request_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `cash_advance_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `request` (`request_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `cash_advance_history_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_cah_balance` CHECK (`remaining_balance` >= 0 AND `remaining_balance` <= `original_amount` AND `original_amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One active obligation per approved cash advance request';

CREATE TABLE `cash_advance_repayment` (
  `repayment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `history_id` bigint unsigned NOT NULL,
  `payroll_id` bigint unsigned NOT NULL,
  `deduction_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`repayment_id`),
  UNIQUE KEY `uq_car_history_payroll` (`history_id`,`payroll_id`),
  UNIQUE KEY `uq_car_deduction_id` (`deduction_id`),
  KEY `payroll_id` (`payroll_id`),
  CONSTRAINT `cash_advance_repayment_ibfk_1` FOREIGN KEY (`history_id`) REFERENCES `cash_advance_history` (`history_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `cash_advance_repayment_ibfk_2` FOREIGN KEY (`payroll_id`) REFERENCES `payroll` (`payroll_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `cash_advance_repayment_ibfk_3` FOREIGN KEY (`deduction_id`) REFERENCES `deduction` (`deduction_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_car_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Weekly repayment deductions linked to payroll and deduction rows';

-- ------------------------------------------------------------
-- employee_lifecycle_event / employee_employment_episode
-- ------------------------------------------------------------
CREATE TABLE `employee_lifecycle_event` (
  `event_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `event_type` enum('Archive','Rehire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_date` date NOT NULL,
  `reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acted_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_lifecycle_employee_date` (`employee_id`,`effective_date`),
  KEY `acted_by` (`acted_by`),
  CONSTRAINT `employee_lifecycle_event_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_lifecycle_event_ibfk_2` FOREIGN KEY (`acted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Append-only audit trail for archive and rehire decisions';

CREATE TABLE `employee_employment_episode` (
  `episode_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `started_on` date NOT NULL,
  `ended_on` date DEFAULT NULL,
  `start_reason` enum('Hire','Rehire') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`episode_id`),
  UNIQUE KEY `uq_episode_employee_start` (`employee_id`,`started_on`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `employee_employment_episode_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_employment_episode_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_episode_dates` CHECK (`ended_on` IS NULL OR `ended_on` >= `started_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One row per continuous employment period for an employee';

-- ------------------------------------------------------------
-- employee_document
-- ------------------------------------------------------------
CREATE TABLE `employee_document` (
  `document_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `document_type` enum('IDPhoto','EmploymentContract','GovernmentID','TaxForm','BankProof','SeparationDocument','RehireDocument','Other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Other',
  `document_label` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(260) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `byte_size` int unsigned NOT NULL,
  `sha256` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Current','Superseded','Archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Current',
  `replaces_document_id` bigint unsigned DEFAULT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_id`),
  KEY `idx_employee_document_employee` (`employee_id`),
  KEY `idx_employee_document_status` (`status`),
  KEY `idx_employee_document_sha256` (`sha256`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `employee_document_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_document_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `employee_document_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Scanned employee documents — files stored in storage/private/';

-- ------------------------------------------------------------
-- phinxlog  (Phinx migration version tracking)
-- ------------------------------------------------------------
CREATE TABLE `phinxlog` (
  `version` bigint NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECTION 3: REFERENCE / SEED DATA
-- ============================================================

-- role
INSERT INTO `role` (`role_id`, `role_name`) VALUES
  (1, 'BusinessOwner'),
  (2, 'HRHead'),
  (3, 'Employee');

-- request_type
INSERT INTO `request_type` (`request_type_id`, `type_name`, `status`) VALUES
  (1, 'Leave',        'Active'),
  (2, 'Overtime',     'Active'),
  (3, 'CashAdvance',  'Active');

-- contribution_policy_version  (demo fixture — mark demo_only=1)
INSERT INTO `contribution_policy_version`
  (`contribution_policy_id`, `policy_code`, `version`, `effective_from`, `demo_only`, `status`)
VALUES
  (1, 'DEMO-CONTRIB', 'V1', '2024-01-01', 1, 'Approved');

-- sss_bracket  (demo: single bracket EEMR ≥ ₱11,000)
INSERT INTO `sss_bracket`
  (`bracket_id`, `contribution_policy_id`, `salary_from`, `salary_to`, `employee_share`, `employer_share`, `effective_from`)
VALUES
  (1, 1, 11000.00, NULL, 540.00, 1140.00, '2024-01-01');

-- philhealth_rate  (demo: 4% total, 2% employee share)
INSERT INTO `philhealth_rate`
  (`rate_id`, `contribution_policy_id`, `rate_decimal`, `employee_share_decimal`)
VALUES
  (1, 1, 0.040000, 0.020000);

-- pagibig_rate  (demo: ₱200 employee + ₱200 employer fixed)
INSERT INTO `pagibig_rate`
  (`rate_id`, `contribution_policy_id`, `employee_fixed_amount`, `employer_fixed_amount`)
VALUES
  (1, 1, 200.00, 200.00);

-- job_position
INSERT INTO `job_position` (`position_id`, `position_title`, `department`, `status`, `sort_order`) VALUES
  (1,  'Business Owner',      'Management',  'Active', 1),
  (2,  'Store In-charge',     'Management',  'Active', 2),
  (3,  'HR Head',             'Admin',       'Active', 3),
  (4,  'Secretary',           'Admin',       'Active', 4),
  (5,  'Accounting Staff',    'Admin',       'Active', 5),
  (6,  'Sales Clerk',         'Sales',       'Active', 10),
  (7,  'Cashier',             'Sales',       'Active', 11),
  (8,  'Checker',             'Operations',  'Active', 12),
  (9,  'Encoder',             'Operations',  'Active', 13),
  (10, 'Warehouse In-charge', 'Warehouse',   'Active', 20),
  (11, 'Driver',              'Logistics',   'Active', 21),
  (12, 'Laborer',             'Warehouse',   'Active', 22);

-- holiday_calendar  (2026 Philippine public holidays — update annually)
INSERT INTO `holiday_calendar` (`holiday_date`, `description`, `holiday_type`, `pay_multiplier`, `status`) VALUES
  ('2026-01-01', 'New Year\'s Day',               'Regular', 2.00, 'Active'),
  ('2026-04-02', 'Maundy Thursday',               'Regular', 2.00, 'Active'),
  ('2026-04-03', 'Good Friday',                   'Regular', 2.00, 'Active'),
  ('2026-04-04', 'Black Saturday',                'Special', 1.30, 'Active'),
  ('2026-04-09', 'Day of Valor (Araw ng Kagitingan)', 'Regular', 2.00, 'Active'),
  ('2026-05-01', 'Labor Day',                     'Regular', 2.00, 'Active'),
  ('2026-06-12', 'Independence Day',              'Regular', 2.00, 'Active'),
  ('2026-08-31', 'Ninoy Aquino Day',              'Regular', 2.00, 'Active'),
  ('2026-11-01', 'All Saints\' Day',              'Special', 1.30, 'Active'),
  ('2026-11-02', 'All Souls\' Day',               'Special', 1.30, 'Active'),
  ('2026-11-30', 'Bonifacio Day',                 'Regular', 2.00, 'Active'),
  ('2026-12-08', 'Feast of the Immaculate Conception', 'Special', 1.30, 'Active'),
  ('2026-12-24', 'Christmas Eve',                 'Special', 1.30, 'Active'),
  ('2026-12-25', 'Christmas Day',                 'Regular', 2.00, 'Active'),
  ('2026-12-30', 'Rizal Day',                     'Regular', 2.00, 'Active'),
  ('2026-12-31', 'New Year\'s Eve',               'Special', 1.30, 'Active');

-- ============================================================
-- RESTORE SESSION VARIABLES
-- ============================================================
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- End of clean_database.sql
