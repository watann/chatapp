USE `chatapp`;

-- ============================================================
-- 2026-10-30 logic, organization hierarchy, privacy and grades
-- Run once on an existing installation after making a backup.
-- It is written to be safely re-runnable on the upgraded schema.
-- ============================================================

-- Presence state
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_seen_at` DATETIME NULL AFTER `status`;
UPDATE `users` SET `last_seen_at`=COALESCE(`last_seen_at`,`updated_at`,`created_at`,NOW()) WHERE `last_seen_at` IS NULL;

-- Blocking/privacy
CREATE TABLE IF NOT EXISTS `user_blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_id` INT UNSIGNED NOT NULL,
  `blocked_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_block_unique` (`blocker_id`,`blocked_id`),
  KEY `blocked_lookup` (`blocked_id`,`blocker_id`),
  CONSTRAINT `user_block_blocker_fk` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `user_block_blocked_fk` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Full institution structure
CREATE TABLE IF NOT EXISTS `institution_departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `department_type` ENUM('root','academic_affairs','finance','administration','student_affairs','human_resources','it','other') NOT NULL DEFAULT 'other',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code_unique` (`code`),
  KEY `department_parent` (`parent_id`,`sort_order`),
  CONSTRAINT `department_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `institution_departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `institution_departments` (`id`,`parent_id`,`code`,`name`,`department_type`,`is_active`,`sort_order`) VALUES
(1,NULL,'institution','Institution','root',1,0),
(2,1,'academic_affairs','Academic Affairs','academic_affairs',1,10),
(3,1,'finance','Finance','finance',1,20),
(4,1,'administration','Institution Administration','administration',1,30),
(5,1,'student_affairs','Student Affairs','student_affairs',1,40),
(6,1,'human_resources','Human Resources','human_resources',1,50),
(7,1,'it','Information Technology','it',1,60);

-- Expand old generic admin model, migrate generic admins to department staff,
-- then remove the obsolete generic role.
ALTER TABLE `admin_users` MODIFY COLUMN `admin_role` ENUM('super_admin','admin','department_head','department_staff') NOT NULL DEFAULT 'department_staff';
UPDATE `admin_users` SET `admin_role`='department_staff', `is_active`=0 WHERE `admin_role`='admin';
ALTER TABLE `admin_users` MODIFY COLUMN `admin_role` ENUM('super_admin','department_head','department_staff') NOT NULL DEFAULT 'department_staff';
ALTER TABLE `admin_users`
  ADD COLUMN IF NOT EXISTS `department_id` INT UNSIGNED NULL AFTER `admin_role`,
  ADD COLUMN IF NOT EXISTS `registry_id` BIGINT UNSIGNED NULL AFTER `department_id`;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='admin_users' AND index_name='admin_registry_unique');
SET @sql := IF(@idx=0,'ALTER TABLE admin_users ADD UNIQUE KEY admin_registry_unique (registry_id)','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='admin_users' AND index_name='admin_department_lookup');
SET @sql := IF(@idx=0,'ALTER TABLE admin_users ADD KEY admin_department_lookup (department_id,admin_role,is_active)','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='admin_users' AND constraint_name='admin_department_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0,'ALTER TABLE admin_users ADD CONSTRAINT admin_department_fk FOREIGN KEY (department_id) REFERENCES institution_departments(id) ON DELETE SET NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='admin_users' AND constraint_name='admin_registry_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0,'ALTER TABLE admin_users ADD CONSTRAINT admin_registry_fk FOREIGN KEY (registry_id) REFERENCES institution_registry(id) ON DELETE SET NULL','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Student progression must be idempotent per student/academic year.
DELETE sp1 FROM `student_progressions` sp1
JOIN `student_progressions` sp2 ON sp2.student_registry_id=sp1.student_registry_id AND sp2.academic_year=sp1.academic_year AND sp2.id<sp1.id;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='student_progressions' AND index_name='student_progress_unique_year');
SET @sql := IF(@idx=0,'ALTER TABLE student_progressions ADD UNIQUE KEY student_progress_unique_year (student_registry_id,academic_year)','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Teacher -> Academic Affairs -> Student exam-grade workflow.
CREATE TABLE IF NOT EXISTS `academic_exams` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `teacher_registry_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `exam_date` DATE NOT NULL,
  `max_score` DECIMAL(8,2) NOT NULL,
  `teacher_note` VARCHAR(1000) NULL,
  `status` ENUM('draft','submitted','published','returned') NOT NULL DEFAULT 'draft',
  `submitted_at` DATETIME NULL,
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_note` VARCHAR(1000) NULL,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_teacher_lookup` (`teacher_registry_id`,`group_id`,`status`,`exam_date`),
  KEY `exam_review_queue` (`status`,`submitted_at`),
  CONSTRAINT `exam_group_fk` FOREIGN KEY (`group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_teacher_fk` FOREIGN KEY (`teacher_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exam_reviewer_fk` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_exam_grades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id` BIGINT UNSIGNED NOT NULL,
  `student_registry_id` BIGINT UNSIGNED NOT NULL,
  `teacher_score` DECIMAL(8,2) NOT NULL,
  `final_score` DECIMAL(8,2) NOT NULL,
  `teacher_note` VARCHAR(500) NULL,
  `admin_note` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_student_unique` (`exam_id`,`student_registry_id`),
  KEY `student_grade_lookup` (`student_registry_id`,`exam_id`),
  CONSTRAINT `grade_exam_fk` FOREIGN KEY (`exam_id`) REFERENCES `academic_exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_student_fk` FOREIGN KEY (`student_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_grade_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id` BIGINT UNSIGNED NOT NULL,
  `grade_id` BIGINT UNSIGNED NULL,
  `actor_type` ENUM('teacher','admin') NOT NULL,
  `actor_registry_id` BIGINT UNSIGNED NULL,
  `actor_admin_id` INT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,
  `old_score` DECIMAL(8,2) NULL,
  `new_score` DECIMAL(8,2) NULL,
  `note` VARCHAR(1000) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grade_audit_exam` (`exam_id`,`created_at`),
  CONSTRAINT `grade_audit_exam_fk` FOREIGN KEY (`exam_id`) REFERENCES `academic_exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_audit_grade_fk` FOREIGN KEY (`grade_id`) REFERENCES `academic_exam_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grade_audit_registry_fk` FOREIGN KEY (`actor_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grade_audit_admin_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stale accepted calls can now expire instead of leaving a user permanently busy.
ALTER TABLE `call_sessions` ADD COLUMN IF NOT EXISTS `last_activity_at` DATETIME NULL AFTER `ended_at`;
UPDATE `call_sessions` SET `last_activity_at`=COALESCE(`last_activity_at`,`answered_at`,`created_at`) WHERE `last_activity_at` IS NULL;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='call_sessions' AND index_name='call_activity_lookup');
SET @sql := IF(@idx=0,'ALTER TABLE call_sessions ADD KEY call_activity_lookup (status,last_activity_at)','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- Professional institution workflow, approvals and finance
-- ============================================================
ALTER TABLE `institution_departments`
  ADD COLUMN IF NOT EXISTS `responsibilities` TEXT NULL AFTER `department_type`;

UPDATE `institution_departments` SET `responsibilities`='Owns academic groups, timetable coordination, teacher academic assignments and grade review.' WHERE `code`='academic_affairs' AND (`responsibilities` IS NULL OR `responsibilities`='');
UPDATE `institution_departments` SET `responsibilities`='Owns institutional receipts, expenses, financial references, approved disbursements and balance control.' WHERE `code`='finance' AND (`responsibilities` IS NULL OR `responsibilities`='');
UPDATE `institution_departments` SET `responsibilities`='Owns official administration, institutional registry governance and cross-department administrative coordination.' WHERE `code`='administration' AND (`responsibilities` IS NULL OR `responsibilities`='');
UPDATE `institution_departments` SET `responsibilities`='Owns student records, student progression, student status and student-service administration.' WHERE `code`='student_affairs' AND (`responsibilities` IS NULL OR `responsibilities`='');
UPDATE `institution_departments` SET `responsibilities`='Owns staff and teacher registry administration, workforce records and departmental staffing governance.' WHERE `code`='human_resources' AND (`responsibilities` IS NULL OR `responsibilities`='');
UPDATE `institution_departments` SET `responsibilities`='Owns institutional systems, technical operations, platform support and information-technology services.' WHERE `code`='it' AND (`responsibilities` IS NULL OR `responsibilities`='');

CREATE TABLE IF NOT EXISTS `department_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED NOT NULL,
  `requester_admin_id` INT UNSIGNED NOT NULL,
  `request_type` ENUM('staff_requisition','staff_add','staff_remove','resource','procurement','budget','other') NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `details` VARCHAR(3000) NOT NULL DEFAULT '',
  `target_registry_id` BIGINT UNSIGNED NULL,
  `target_admin_user_id` INT UNSIGNED NULL,
  `amount` DECIMAL(14,2) NULL,
  `status` ENUM('pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_note` VARCHAR(1000) NULL,
  `executed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_request_queue` (`status`,`created_at`),
  KEY `department_request_department` (`department_id`,`status`,`created_at`),
  KEY `department_request_requester` (`requester_admin_id`,`created_at`),
  KEY `department_request_target_registry` (`target_registry_id`),
  KEY `department_request_target_admin` (`target_admin_user_id`),
  CONSTRAINT `department_request_department_fk` FOREIGN KEY (`department_id`) REFERENCES `institution_departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `department_request_requester_fk` FOREIGN KEY (`requester_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `department_request_target_registry_fk` FOREIGN KEY (`target_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL,
  CONSTRAINT `department_request_target_admin_fk` FOREIGN KEY (`target_admin_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `department_request_reviewer_fk` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finance_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_request_id` BIGINT UNSIGNED NULL,
  `transaction_type` ENUM('income','expense') NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `party_name` VARCHAR(190) NOT NULL,
  `reference_no` VARCHAR(100) NULL,
  `description` VARCHAR(2000) NOT NULL DEFAULT '',
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by_admin_id` INT UNSIGNED NOT NULL,
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_note` VARCHAR(1000) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `finance_request_lookup` (`department_request_id`,`status`),
  KEY `finance_status_date` (`status`,`transaction_date`,`id`),
  KEY `finance_type_date` (`transaction_type`,`transaction_date`,`status`),
  KEY `finance_submitter` (`submitted_by_admin_id`,`created_at`),
  CONSTRAINT `finance_request_fk` FOREIGN KEY (`department_request_id`) REFERENCES `department_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `finance_submitter_fk` FOREIGN KEY (`submitted_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `finance_reviewer_fk` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `administrative_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_admin_id` INT UNSIGNED NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL,
  `entity_id` BIGINT UNSIGNED NOT NULL,
  `details` VARCHAR(2000) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_entity_lookup` (`entity_type`,`entity_id`,`created_at`),
  KEY `audit_actor_lookup` (`actor_admin_id`,`created_at`),
  CONSTRAINT `audit_actor_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `department_requests` MODIFY COLUMN `request_type` ENUM('staff_requisition','staff_add','staff_remove','resource','procurement','budget','other') NOT NULL;

-- If an early preview of this upgrade created a one-to-one unique request link,
-- replace it with a status-aware non-unique lookup so rejected finance entries
-- do not permanently block a corrected resubmission.
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='finance_transactions' AND index_name='finance_request_unique');
SET @sql := IF(@idx>0,'ALTER TABLE finance_transactions DROP INDEX finance_request_unique','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='finance_transactions' AND index_name='finance_request_lookup');
SET @sql := IF(@idx=0,'ALTER TABLE finance_transactions ADD KEY finance_request_lookup (department_request_id,status)','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
