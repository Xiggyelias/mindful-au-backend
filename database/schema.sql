-- Africa University Counseling Management System
-- MySQL Database Schema
-- Generated from Laravel Migrations

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+02:00";

-- Create database (uncomment if needed)
-- CREATE DATABASE IF NOT EXISTS `counseling_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `counseling_db`;

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_last_seen_at_index` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `profiles`
CREATE TABLE IF NOT EXISTS `profiles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `anonymous_mode` tinyint(1) NOT NULL DEFAULT 0,
  `peer_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profiles_user_id_unique` (`user_id`),
  KEY `profiles_user_id_index` (`user_id`),
  KEY `profiles_peer_available_index` (`peer_available`),
  CONSTRAINT `profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `roles`
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `description` text,
  `color` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL DEFAULT 0,
  `permissions` json,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `notification_types`
CREATE TABLE IF NOT EXISTS `notification_types` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `description` text,
  `color` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `default_template` text,
  `channels` json,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `session_types`
CREATE TABLE IF NOT EXISTS `session_types` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `description` text,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `max_duration_minutes` int(11) DEFAULT NULL,
  `capabilities` json,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_types_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `session_statuses`
CREATE TABLE IF NOT EXISTS `session_statuses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `description` text,
  `color` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_terminal` tinyint(1) NOT NULL DEFAULT 0,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_statuses_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `ai_models`
CREATE TABLE IF NOT EXISTS `ai_models` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `max_tokens` int(11) DEFAULT NULL,
  `cost_per_input_token` decimal(10,8) DEFAULT NULL,
  `cost_per_output_token` decimal(10,8) DEFAULT NULL,
  `capabilities` json,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_models_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `ai_reports`
CREATE TABLE IF NOT EXISTS `ai_reports` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` enum('weekly_heatmap','monthly_trend','risk_assessment','counselor_burnout') NOT NULL,
  `status` enum('pending','generating','ready','failed') NOT NULL DEFAULT 'ready',
  `summary` text,
  `data` json,
  `file_path` varchar(255) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `activity_logs`
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text,
  `type` enum('auth','session','alert','system') NOT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  `user_agent` text,
  `metadata` json,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_type_index` (`type`),
  KEY `activity_logs_created_at_index` (`created_at`),
  KEY `activity_logs_user_id_created_at_index` (`user_id`,`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `diagnostic_questionnaires`
CREATE TABLE IF NOT EXISTS `diagnostic_questionnaires` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `questions` json NOT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `diagnostics`
CREATE TABLE IF NOT EXISTS `diagnostics` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `responses` json NOT NULL,
  `total_score` int(11) NOT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL,
  `category_scores` json NOT NULL,
  `ai_recommendations` json NOT NULL,
  `insights` text,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `anonymous_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diagnostics_anonymous_id_unique` (`anonymous_id`),
  KEY `diagnostics_student_id_index` (`student_id`),
  KEY `diagnostics_risk_level_index` (`risk_level`),
  KEY `diagnostics_created_at_index` (`created_at`),
  CONSTRAINT `diagnostics_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `system_settings`
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` json,
  `category` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `institution_accounts`
CREATE TABLE IF NOT EXISTS `institution_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `role` enum('student','staff','counselor','peer_counselor','admin') NOT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `full_name` varchar(255) DEFAULT NULL,
  `id_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institution_accounts_email_unique` (`email`),
  KEY `institution_accounts_role_is_active_index` (`role`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `user_roles`
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('admin','counselor','peer_counselor','student') NOT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_user_id_role_unique` (`user_id`,`role`),
  KEY `user_roles_user_id_role_id_index` (`user_id`,`role_id`),
  CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `counseling_sessions`
CREATE TABLE IF NOT EXISTS `counseling_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `counselor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `peer_counselor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assigned_role` varchar(32) DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `anonymous_id` varchar(32) DEFAULT NULL,
  `identity_revealed_at` timestamp NULL DEFAULT NULL,
  `identity_revealed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('pending','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  `session_status_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_type` enum('chat','video','voice') NOT NULL DEFAULT 'chat',
  `session_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ai_summary` text DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `counseling_sessions_student_id_foreign` (`student_id`),
  KEY `counseling_sessions_counselor_id_foreign` (`counselor_id`),
  KEY `counseling_sessions_peer_counselor_id_foreign` (`peer_counselor_id`),
  KEY `counseling_sessions_assigned_by_foreign` (`assigned_by`),
  UNIQUE KEY `counseling_sessions_anonymous_id_unique` (`anonymous_id`),
  KEY `counseling_sessions_identity_revealed_by_foreign` (`identity_revealed_by`),
  KEY `counseling_sessions_student_id_session_status_id_index` (`student_id`,`session_status_id`),
  KEY `counseling_sessions_counselor_id_session_status_id_index` (`counselor_id`,`session_status_id`),
  KEY `counseling_sessions_peer_counselor_status_idx` (`peer_counselor_id`,`status`),
  KEY `counseling_sessions_assigned_role_status_idx` (`assigned_role`,`status`),
  KEY `counseling_sessions_anonymous_status_idx` (`is_anonymous`,`status`),
  KEY `counseling_sessions_started_at_index` (`started_at`),
  KEY `counseling_sessions_ended_at_index` (`ended_at`),
  KEY `idx_sessions_counselor_chat_open` (`counselor_id`,`session_type`,`status`,`updated_at`,`id`),
  KEY `idx_sessions_peer_chat_open` (`peer_counselor_id`,`assigned_role`,`session_type`,`status`,`updated_at`,`id`),
  KEY `idx_sessions_student_chat_open` (`student_id`,`session_type`,`status`,`updated_at`,`id`),
  CONSTRAINT `counseling_sessions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `counseling_sessions_counselor_id_foreign` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_sessions_peer_counselor_id_foreign` FOREIGN KEY (`peer_counselor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_sessions_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_sessions_identity_revealed_by_foreign` FOREIGN KEY (`identity_revealed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `counseling_sessions_session_type_id_foreign` FOREIGN KEY (`session_type_id`) REFERENCES `session_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `counseling_sessions_session_status_id_foreign` FOREIGN KEY (`session_status_id`) REFERENCES `session_statuses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `messages`
CREATE TABLE IF NOT EXISTS `messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_id` bigint(20) UNSIGNED DEFAULT NULL,
  `content` text NOT NULL,
  `message_type` enum('text','voice','file','ai') NOT NULL DEFAULT 'text',
  `file_url` varchar(255) DEFAULT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 1,
  `seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_session_id_foreign` (`session_id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_recipient_id_foreign` (`recipient_id`),
  KEY `messages_session_id_sender_id_index` (`session_id`,`sender_id`),
  KEY `messages_created_at_index` (`created_at`),
  KEY `messages_session_id_id_index` (`session_id`,`id`),
  KEY `messages_session_id_created_at_index` (`session_id`,`created_at`),
  KEY `messages_session_recipient_seen_idx` (`session_id`,`recipient_id`,`seen_at`),
  CONSTRAINT `messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `counseling_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_recipient_id_foreign` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `appointments`
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `counselor_id` bigint(20) UNSIGNED NOT NULL,
  `scheduled_at` timestamp NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `status` enum('scheduled','confirmed','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_student_id_foreign` (`student_id`),
  KEY `appointments_counselor_id_foreign` (`counselor_id`),
  KEY `appointments_student_id_counselor_id_index` (`student_id`,`counselor_id`),
  KEY `appointments_scheduled_at_status_index` (`scheduled_at`,`status`),
  KEY `idx_appointments_counselor_scheduled_status` (`counselor_id`,`scheduled_at`,`status`),
  CONSTRAINT `appointments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_counselor_id_foreign` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `ai_diagnostics`
CREATE TABLE IF NOT EXISTS `ai_diagnostics` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stress_level` int(11) DEFAULT NULL COMMENT '0-100',
  `anxiety_level` int(11) DEFAULT NULL COMMENT '0-100',
  `depression_level` int(11) DEFAULT NULL COMMENT '0-100',
  `mood` varchar(255) DEFAULT NULL,
  `risk_level` enum('low','medium','high','critical') DEFAULT NULL,
  `insights` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_diagnostics_student_id_foreign` (`student_id`),
  KEY `ai_diagnostics_session_id_foreign` (`session_id`),
  KEY `ai_diagnostics_student_id_created_at_index` (`student_id`,`created_at`),
  KEY `ai_diagnostics_risk_level_index` (`risk_level`),
  KEY `idx_ai_diagnostics_created_risk_student` (`created_at`,`risk_level`,`student_id`),
  CONSTRAINT `ai_diagnostics_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_diagnostics_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `counseling_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `counselor_wellness_logs`
CREATE TABLE IF NOT EXISTS `counselor_wellness_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `counselor_id` bigint(20) UNSIGNED NOT NULL,
  `mood_score` int(11) DEFAULT NULL COMMENT '0-100',
  `stress_level` int(11) DEFAULT NULL COMMENT '0-100',
  `burnout_index` int(11) DEFAULT NULL COMMENT '0-100',
  `recommendations` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `check_in_answers` json DEFAULT NULL,
  `check_in_version` varchar(40) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `counselor_wellness_logs_counselor_id_foreign` (`counselor_id`),
  KEY `counselor_wellness_logs_counselor_id_created_at_index` (`counselor_id`,`created_at`),
  CONSTRAINT `counselor_wellness_logs_counselor_id_foreign` FOREIGN KEY (`counselor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `panic_logs`
CREATE TABLE IF NOT EXISTS `panic_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `panic_logs_student_id_foreign` (`student_id`),
  KEY `panic_logs_resolved_by_foreign` (`resolved_by`),
  KEY `panic_logs_student_id_created_at_index` (`student_id`,`created_at`),
  KEY `panic_logs_location_index` (`location`),
  CONSTRAINT `panic_logs_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `panic_logs_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `student_mood_logs`
CREATE TABLE IF NOT EXISTS `student_mood_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `mood` enum('great','okay','low','stressed','tired') NOT NULL,
  `logged_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_mood_logs_student_id_logged_on_unique` (`student_id`,`logged_on`),
  KEY `student_mood_logs_student_id_created_at_index` (`student_id`,`created_at`),
  CONSTRAINT `student_mood_logs_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `notifications`
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','error','panic') NOT NULL DEFAULT 'info',
  `read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_foreign` (`user_id`),
  KEY `notifications_user_id_notification_type_id_index` (`user_id`,`notification_type_id`),
  KEY `notifications_read_created_at_index` (`read`,`created_at`),
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_notification_type_id_foreign` FOREIGN KEY (`notification_type_id`) REFERENCES `notification_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `chat_conversations`
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ai_model_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `model` varchar(255) NOT NULL DEFAULT 'nvidia/nemotron-nano-9b-v2:free',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_conversations_user_id_is_active_index` (`user_id`,`is_active`),
  KEY `chat_conversations_ai_model_id_index` (`ai_model_id`),
  KEY `chat_conversations_last_message_at_index` (`last_message_at`),
  CONSTRAINT `chat_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_conversations_ai_model_id_foreign` FOREIGN KEY (`ai_model_id`) REFERENCES `ai_models` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `chat_messages`
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_messages_conversation_id_role_index` (`conversation_id`,`role`),
  KEY `chat_messages_created_at_index` (`created_at`),
  CONSTRAINT `chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `message_metadata`
CREATE TABLE IF NOT EXISTS `message_metadata` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','decimal','json') NOT NULL DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_metadata_message_id_key_unique` (`message_id`,`key`),
  CONSTRAINT `message_metadata_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `peer_assignments`
CREATE TABLE IF NOT EXISTS `peer_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `peer_counselor_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('active','escalated','reassigned','closed') NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unassigned_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `peer_assignments_peer_counselor_id_status_index` (`peer_counselor_id`,`status`),
  KEY `peer_assignments_session_id_status_index` (`session_id`,`status`),
  CONSTRAINT `peer_assignments_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `counseling_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `peer_assignments_peer_counselor_id_foreign` FOREIGN KEY (`peer_counselor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `peer_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `escalations`
CREATE TABLE IF NOT EXISTS `escalations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `escalated_by` bigint(20) UNSIGNED NOT NULL,
  `escalated_to` bigint(20) UNSIGNED DEFAULT NULL,
  `escalation_type` enum('peer_to_counselor','urgent_flag','panic') NOT NULL DEFAULT 'peer_to_counselor',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'high',
  `reason` text DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `escalations_escalated_by_created_at_index` (`escalated_by`,`created_at`),
  KEY `escalations_escalation_type_created_at_index` (`escalation_type`,`created_at`),
  CONSTRAINT `escalations_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `counseling_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `escalations_escalated_by_foreign` FOREIGN KEY (`escalated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `escalations_escalated_to_foreign` FOREIGN KEY (`escalated_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `login_logs`
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `auth_method` enum('password','google') NOT NULL DEFAULT 'password',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_logs_email_created_at_index` (`email`,`created_at`),
  KEY `login_logs_success_created_at_index` (`success`,`created_at`),
  CONSTRAINT `login_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

