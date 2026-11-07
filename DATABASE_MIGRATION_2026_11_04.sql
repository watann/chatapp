-- November 4, 2026: approval-controlled registry lists.
-- Back up the live database before running. Run after the 2026-10-31 migration.
USE `chatapp`;

CREATE TABLE IF NOT EXISTS `registry_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(190) NOT NULL,
  `person_type` ENUM('student','teacher','staff') NOT NULL,
  `created_by_admin_id` INT UNSIGNED NOT NULL,
  `status` ENUM('draft','administration_review','returned_registry','manager_review','returned_administration','approved') NOT NULL DEFAULT 'draft',
  `administration_reviewed_by` INT UNSIGNED NULL,
  `administration_note` VARCHAR(1000) NULL,
  `manager_reviewed_by` INT UNSIGNED NULL,
  `manager_note` VARCHAR(1000) NULL,
  `approved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `registry_batches_status` (`status`,`created_at`),
  KEY `registry_batches_creator` (`created_by_admin_id`,`created_at`),
  CONSTRAINT `registry_batches_creator_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `registry_batches_administration_fk` FOREIGN KEY (`administration_reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `registry_batches_manager_fk` FOREIGN KEY (`manager_reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registry_batch_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `person_type` ENUM('student','teacher','staff') NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `identity_number` VARCHAR(100) NOT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_identity_unique` (`batch_id`,`identity_number`),
  CONSTRAINT `registry_batch_item_parent_fk` FOREIGN KEY (`batch_id`) REFERENCES `registry_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `registry_batch_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `actor_admin_id` INT UNSIGNED NOT NULL,
  `event` VARCHAR(60) NOT NULL,
  `note` VARCHAR(1000) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `batch_event_parent` (`batch_id`,`created_at`),
  CONSTRAINT `batch_event_batch_fk` FOREIGN KEY (`batch_id`) REFERENCES `registry_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batch_event_actor_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Registry department: Registry prepares lists, Administration reviews, Manager gives final approval.
-- Existing custom Registry departments are preserved.
INSERT INTO institution_departments(parent_id,code,name,department_type,responsibilities,is_active,sort_order)
SELECT root.id,'registry','Institution Registry','other','Prepares official student, teacher and staff registration lists for Administration review.',1,35
FROM institution_departments AS root
WHERE root.code='institution'
  AND NOT EXISTS (SELECT 1 FROM institution_departments WHERE code='registry');
