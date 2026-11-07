-- Apply to an EXISTING database using phpMyAdmin > SQL, after taking a backup.
-- Does not delete or modify existing records/tables. Requires MySQL/MariaDB with InnoDB.
CREATE TABLE IF NOT EXISTS `post_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `reporter_user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `status` ENUM('pending','dismissed') NOT NULL DEFAULT 'pending',
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_report_once` (`post_id`,`reporter_user_id`),
  KEY `post_reports_pending` (`status`,`created_at`),
  CONSTRAINT `post_report_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_report_reporter_fk` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `post_report_reviewer_fk` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moderation_words` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `word` VARCHAR(100) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by_admin_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `moderation_word_unique` (`word`),
  CONSTRAINT `moderation_word_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_word_alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `matched_words` TEXT NOT NULL,
  `status` ENUM('pending','dismissed') NOT NULL DEFAULT 'pending',
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_word_alert_unique` (`post_id`),
  KEY `post_word_alert_pending` (`status`,`updated_at`),
  CONSTRAINT `post_word_alert_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_word_alert_reviewer_fk` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_moderation_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `post_author_user_id` INT UNSIGNED NOT NULL,
  `actor_admin_id` INT UNSIGNED NULL,
  `action_type` ENUM('delete_reported','delete_flagged','dismiss_reports','dismiss_flag') NOT NULL,
  `post_excerpt` VARCHAR(500) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_moderation_action_post` (`post_id`,`created_at`),
  CONSTRAINT `post_moderation_actor_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
