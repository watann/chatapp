USE `chatapp`;

-- This migration upgrades either the older chat app or the previous social version.
-- Back up the database first and run this file once.

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `language` VARCHAR(5) NOT NULL DEFAULT 'ku' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `role` VARCHAR(20) NOT NULL DEFAULT 'student' AFTER `language`,
  ADD COLUMN IF NOT EXISTS `bio` TEXT NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `theme_mode` VARCHAR(20) NOT NULL DEFAULT 'light' AFTER `bio`,
  ADD COLUMN IF NOT EXISTS `accent_color` VARCHAR(20) NOT NULL DEFAULT 'blue' AFTER `theme_mode`,
  ADD COLUMN IF NOT EXISTS `ui_density` VARCHAR(20) NOT NULL DEFAULT 'comfortable' AFTER `accent_color`,
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `users`
  MODIFY COLUMN `language` ENUM('ku','en','ar','de','fa') NOT NULL DEFAULT 'ku',
  MODIFY COLUMN `role` ENUM('student','teacher','staff') NOT NULL DEFAULT 'student',
  MODIFY COLUMN `theme_mode` ENUM('light','dark','system') NOT NULL DEFAULT 'light',
  MODIFY COLUMN `accent_color` ENUM('blue','green','purple','orange','rose') NOT NULL DEFAULT 'blue',
  MODIFY COLUMN `ui_density` ENUM('comfortable','compact') NOT NULL DEFAULT 'comfortable';

ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS `friendships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_one` INT UNSIGNED NOT NULL,
  `user_two` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `friend_pair` (`user_one`,`user_two`), KEY `friend_user_two` (`user_two`), KEY `friend_requested_by` (`requested_by`), KEY `friend_status` (`status`),
  CONSTRAINT `friend_user_one_fk` FOREIGN KEY (`user_one`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `friend_user_two_fk` FOREIGN KEY (`user_two`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `friend_requested_by_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `media_path` VARCHAR(255) NULL,
  `visibility` ENUM('public','friends') NOT NULL DEFAULT 'friends',
  `shared_post_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `post_feed` (`visibility`,`created_at`), KEY `post_user` (`user_unique_id`,`created_at`), KEY `shared_post_lookup` (`shared_post_id`), UNIQUE KEY `user_shared_once` (`user_unique_id`,`shared_post_id`),
  CONSTRAINT `posts_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `posts_shared_fk` FOREIGN KEY (`shared_post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `posts` ADD COLUMN IF NOT EXISTS `shared_post_id` INT UNSIGNED NULL AFTER `visibility`;

SET @idx_shared := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='posts' AND index_name='shared_post_lookup');
SET @sql := IF(@idx_shared=0, 'ALTER TABLE posts ADD KEY shared_post_lookup (shared_post_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx_unique_share := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='posts' AND index_name='user_shared_once');
SET @sql := IF(@idx_unique_share=0, 'ALTER TABLE posts ADD UNIQUE KEY user_shared_once (user_unique_id,shared_post_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_shared := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='posts' AND constraint_name='posts_shared_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk_shared=0, 'ALTER TABLE posts ADD CONSTRAINT posts_shared_fk FOREIGN KEY (shared_post_id) REFERENCES posts(id) ON DELETE CASCADE', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `post_likes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `post_user_like` (`post_id`,`user_unique_id`), KEY `like_user` (`user_unique_id`),
  CONSTRAINT `likes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `likes_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `comment_post` (`post_id`,`created_at`), KEY `comment_user` (`user_unique_id`),
  CONSTRAINT `comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `media_path` VARCHAR(255) NULL,
  `visibility` ENUM('public','friends') NOT NULL DEFAULT 'friends',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`), KEY `story_feed` (`visibility`,`expires_at`,`created_at`), KEY `story_user` (`user_unique_id`,`expires_at`),
  CONSTRAINT `stories_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `stories` ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

CREATE TABLE IF NOT EXISTS `chat_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `created_by` (`created_by`), KEY `updated_at` (`updated_at`),
  CONSTRAINT `groups_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `group_user` (`group_id`,`user_unique_id`), KEY `user_unique_id` (`user_unique_id`),
  CONSTRAINT `group_members_group_fk` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `sender_unique_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `group_lookup` (`group_id`,`created_at`), KEY `sender_lookup` (`sender_unique_id`),
  CONSTRAINT `group_messages_group_fk` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_messages_user_fk` FOREIGN KEY (`sender_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat attachments and unread tracking
ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `attachment_path` VARCHAR(255) NULL AFTER `msg`,
  ADD COLUMN IF NOT EXISTS `attachment_name` VARCHAR(255) NULL AFTER `attachment_path`,
  ADD COLUMN IF NOT EXISTS `attachment_mime` VARCHAR(120) NULL AFTER `attachment_name`,
  ADD COLUMN IF NOT EXISTS `attachment_size` INT UNSIGNED NULL AFTER `attachment_mime`,
  ADD COLUMN IF NOT EXISTS `read_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `attachment_size`;

-- On first add, existing rows receive the timestamp default; future messages may be explicitly inserted as unread (NULL).
ALTER TABLE `messages` MODIFY COLUMN `read_at` DATETIME NULL DEFAULT NULL;

ALTER TABLE `group_messages`
  ADD COLUMN IF NOT EXISTS `attachment_path` VARCHAR(255) NULL AFTER `message`,
  ADD COLUMN IF NOT EXISTS `attachment_name` VARCHAR(255) NULL AFTER `attachment_path`,
  ADD COLUMN IF NOT EXISTS `attachment_mime` VARCHAR(120) NULL AFTER `attachment_name`,
  ADD COLUMN IF NOT EXISTS `attachment_size` INT UNSIGNED NULL AFTER `attachment_mime`;

CREATE TABLE IF NOT EXISTS `call_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `caller_id` INT UNSIGNED NOT NULL,
  `callee_id` INT UNSIGNED NOT NULL,
  `call_type` ENUM('voice','video') NOT NULL,
  `status` ENUM('ringing','accepted','declined','ended','missed') NOT NULL DEFAULT 'ringing',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `answered_at` DATETIME NULL,
  `ended_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `call_incoming` (`callee_id`,`status`,`created_at`),
  KEY `call_outgoing` (`caller_id`,`status`,`created_at`),
  CONSTRAINT `call_caller_fk` FOREIGN KEY (`caller_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `call_callee_fk` FOREIGN KEY (`callee_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `call_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` INT UNSIGNED NOT NULL,
  `recipient_id` INT UNSIGNED NOT NULL,
  `signal_type` ENUM('offer','answer','candidate') NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `signal_poll` (`call_id`,`recipient_id`,`id`),
  CONSTRAINT `signal_call_fk` FOREIGN KEY (`call_id`) REFERENCES `call_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `signal_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `signal_recipient_fk` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional performance index for global unread-message polling (MariaDB/MySQL variants that support IF NOT EXISTS on indexes).
ALTER TABLE `messages` ADD INDEX IF NOT EXISTS `unread_lookup` (`incoming_msg_id`,`read_at`,`msg_id`);

-- Administration and institution pre-registration system.
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(190) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `admin_role` ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_email` (`email`),
  KEY `admin_role_lookup` (`admin_role`,`is_active`),
  KEY `admin_created_by` (`created_by`),
  CONSTRAINT `admin_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `institution_registry` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `issued_id` VARCHAR(40) NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `birth_date` DATE NOT NULL,
  `gender` ENUM('male','female','other') NOT NULL,
  `person_type` ENUM('student','teacher','staff') NOT NULL,
  `student_department` VARCHAR(190) NULL,
  `staff_workplace` VARCHAR(190) NULL,
  `teacher_specialization` ENUM('technology_network','technology_programming','technology_cybersecurity','technology_ai','technology_graphic_design','mathematics','physics') NULL,
  `identity_number` VARCHAR(100) NOT NULL,
  `status` ENUM('available','registered','suspended') NOT NULL DEFAULT 'available',
  `created_by_admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `registry_issued_id` (`issued_id`),
  UNIQUE KEY `registry_identity_number` (`identity_number`),
  KEY `registry_person_type` (`person_type`,`status`),
  KEY `registry_created_by` (`created_by_admin_id`),
  CONSTRAINT `registry_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `middle_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `fname`,
  ADD COLUMN IF NOT EXISTS `registry_id` BIGINT UNSIGNED NULL AFTER `ui_density`;

SET @idx_user_registry := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='users_registry_id');
SET @sql := IF(@idx_user_registry=0, 'ALTER TABLE users ADD UNIQUE KEY users_registry_id (registry_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk_user_registry := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='users' AND constraint_name='users_registry_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk_user_registry=0, 'ALTER TABLE users ADD CONSTRAINT users_registry_fk FOREIGN KEY (registry_id) REFERENCES institution_registry(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ============================================================
-- Academic administration upgrade: dynamic official options,
-- student progression, official academic groups and timetable.
-- Safe to run after the previous migration section.
-- ============================================================

CREATE TABLE IF NOT EXISTS `institution_options` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `option_type` ENUM('student_department','teacher_certificate','teacher_specialization','staff_workplace') NOT NULL,
  `label` VARCHAR(190) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by_admin_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institution_option_unique` (`option_type`,`label`),
  KEY `institution_option_active` (`option_type`,`is_active`,`sort_order`),
  CONSTRAINT `institution_option_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `institution_options` MODIFY COLUMN `option_type` ENUM('student_department','teacher_certificate','teacher_specialization','staff_workplace') NOT NULL;

-- The old teacher field was an ENUM. Convert it to text so future Super Admin options are dynamic.
ALTER TABLE `institution_registry`
  MODIFY COLUMN `teacher_specialization` VARCHAR(190) NULL;

UPDATE `institution_registry` SET `teacher_specialization` =
  CASE `teacher_specialization`
    WHEN 'technology_network' THEN 'Technology — Network'
    WHEN 'technology_programming' THEN 'Technology — Programming'
    WHEN 'technology_cybersecurity' THEN 'Technology — Cybersecurity'
    WHEN 'technology_ai' THEN 'Technology — AI'
    WHEN 'technology_graphic_design' THEN 'Technology — Graphic Design'
    WHEN 'mathematics' THEN 'Mathematics'
    WHEN 'physics' THEN 'Physics'
    ELSE `teacher_specialization`
  END
WHERE `teacher_specialization` IS NOT NULL;

INSERT IGNORE INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`) VALUES
('teacher_specialization','Technology — Network',1,10,NULL),
('teacher_specialization','Technology — Programming',1,20,NULL),
('teacher_specialization','Technology — Cybersecurity',1,30,NULL),
('teacher_specialization','Technology — AI',1,40,NULL),
('teacher_specialization','Technology — Graphic Design',1,50,NULL),
('teacher_specialization','Mathematics',1,60,NULL),
('teacher_specialization','Physics',1,70,NULL);

INSERT IGNORE INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`)
SELECT 'student_department', TRIM(`student_department`), 1, 0, NULL
FROM `institution_registry`
WHERE `student_department` IS NOT NULL AND TRIM(`student_department`) <> ''
GROUP BY TRIM(`student_department`);

INSERT IGNORE INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`)
SELECT 'staff_workplace', TRIM(`staff_workplace`), 1, 0, NULL
FROM `institution_registry`
WHERE `staff_workplace` IS NOT NULL AND TRIM(`staff_workplace`) <> ''
GROUP BY TRIM(`staff_workplace`);

INSERT IGNORE INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`)
SELECT 'teacher_specialization', TRIM(`teacher_specialization`), 1, 0, NULL
FROM `institution_registry`
WHERE `teacher_specialization` IS NOT NULL AND TRIM(`teacher_specialization`) <> ''
GROUP BY TRIM(`teacher_specialization`);

CREATE TABLE IF NOT EXISTS `academic_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_option_id` INT UNSIGNED NOT NULL,
  `stage` TINYINT UNSIGNED NOT NULL,
  `group_code` VARCHAR(20) NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by_admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_group_unique` (`department_option_id`,`stage`,`group_code`,`academic_year`),
  KEY `academic_group_lookup` (`academic_year`,`department_option_id`,`stage`,`group_code`),
  CONSTRAINT `academic_group_department_fk` FOREIGN KEY (`department_option_id`) REFERENCES `institution_options` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `academic_group_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `institution_registry`
  ADD COLUMN IF NOT EXISTS `teacher_certificate` VARCHAR(190) NULL AFTER `staff_workplace`,
  ADD COLUMN IF NOT EXISTS `student_department_option_id` INT UNSIGNED NULL AFTER `teacher_specialization`,
  ADD COLUMN IF NOT EXISTS `staff_workplace_option_id` INT UNSIGNED NULL AFTER `student_department_option_id`,
  ADD COLUMN IF NOT EXISTS `teacher_certificate_option_id` INT UNSIGNED NULL AFTER `staff_workplace_option_id`,
  ADD COLUMN IF NOT EXISTS `teacher_specialization_option_id` INT UNSIGNED NULL AFTER `teacher_certificate_option_id`,
  ADD COLUMN IF NOT EXISTS `student_stage` TINYINT UNSIGNED NULL AFTER `teacher_specialization_option_id`,
  ADD COLUMN IF NOT EXISTS `student_academic_status` VARCHAR(20) NULL AFTER `student_stage`,
  ADD COLUMN IF NOT EXISTS `academic_group_id` INT UNSIGNED NULL AFTER `student_academic_status`;

ALTER TABLE `institution_registry`
  MODIFY COLUMN `student_academic_status` ENUM('active','graduated','inactive') NULL;

UPDATE `institution_registry` r
JOIN `institution_options` o ON o.option_type='student_department' AND o.label=TRIM(r.student_department)
SET r.student_department_option_id=o.id
WHERE r.person_type='student' AND r.student_department_option_id IS NULL AND r.student_department IS NOT NULL;

UPDATE `institution_registry` r
JOIN `institution_options` o ON o.option_type='staff_workplace' AND o.label=TRIM(r.staff_workplace)
SET r.staff_workplace_option_id=o.id
WHERE r.person_type='staff' AND r.staff_workplace_option_id IS NULL AND r.staff_workplace IS NOT NULL;

UPDATE `institution_registry` r
JOIN `institution_options` o ON o.option_type='teacher_specialization' AND o.label=TRIM(r.teacher_specialization)
SET r.teacher_specialization_option_id=o.id
WHERE r.person_type='teacher' AND r.teacher_specialization_option_id IS NULL AND r.teacher_specialization IS NOT NULL;

UPDATE `institution_registry`
SET `student_stage`=COALESCE(`student_stage`,1),
    `student_academic_status`=COALESCE(`student_academic_status`,'active')
WHERE `person_type`='student';

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_registry' AND index_name='registry_student_department_option');
SET @sql := IF(@idx=0, 'ALTER TABLE institution_registry ADD KEY registry_student_department_option (student_department_option_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_registry' AND index_name='registry_staff_workplace_option');
SET @sql := IF(@idx=0, 'ALTER TABLE institution_registry ADD KEY registry_staff_workplace_option (staff_workplace_option_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_registry' AND index_name='registry_teacher_certificate_option');
SET @sql := IF(@idx=0, 'ALTER TABLE institution_registry ADD KEY registry_teacher_certificate_option (teacher_certificate_option_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_registry' AND index_name='registry_teacher_specialization_option');
SET @sql := IF(@idx=0, 'ALTER TABLE institution_registry ADD KEY registry_teacher_specialization_option (teacher_specialization_option_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_registry' AND index_name='registry_academic_group');
SET @sql := IF(@idx=0, 'ALTER TABLE institution_registry ADD KEY registry_academic_group (academic_group_id)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_registry' AND index_name='registry_student_progress');
SET @sql := IF(@idx=0, 'ALTER TABLE institution_registry ADD KEY registry_student_progress (person_type,student_academic_status,student_stage)', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='institution_registry' AND constraint_name='registry_student_department_option_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0, 'ALTER TABLE institution_registry ADD CONSTRAINT registry_student_department_option_fk FOREIGN KEY (student_department_option_id) REFERENCES institution_options(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='institution_registry' AND constraint_name='registry_staff_workplace_option_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0, 'ALTER TABLE institution_registry ADD CONSTRAINT registry_staff_workplace_option_fk FOREIGN KEY (staff_workplace_option_id) REFERENCES institution_options(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='institution_registry' AND constraint_name='registry_teacher_certificate_option_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0, 'ALTER TABLE institution_registry ADD CONSTRAINT registry_teacher_certificate_option_fk FOREIGN KEY (teacher_certificate_option_id) REFERENCES institution_options(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='institution_registry' AND constraint_name='registry_teacher_specialization_option_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0, 'ALTER TABLE institution_registry ADD CONSTRAINT registry_teacher_specialization_option_fk FOREIGN KEY (teacher_specialization_option_id) REFERENCES institution_options(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='institution_registry' AND constraint_name='registry_academic_group_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0, 'ALTER TABLE institution_registry ADD CONSTRAINT registry_academic_group_fk FOREIGN KEY (academic_group_id) REFERENCES academic_groups(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `academic_group_teachers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `teacher_registry_id` BIGINT UNSIGNED NOT NULL,
  `assigned_by_admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_group_teacher_unique` (`group_id`,`teacher_registry_id`),
  KEY `academic_teacher_registry` (`teacher_registry_id`),
  CONSTRAINT `academic_group_teacher_group_fk` FOREIGN KEY (`group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_group_teacher_registry_fk` FOREIGN KEY (`teacher_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_group_teacher_admin_fk` FOREIGN KEY (`assigned_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_progressions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_registry_id` BIGINT UNSIGNED NOT NULL,
  `academic_year` VARCHAR(20) NOT NULL,
  `decision` ENUM('pass','stay','assign') NOT NULL,
  `old_stage` TINYINT UNSIGNED NOT NULL,
  `new_stage` TINYINT UNSIGNED NOT NULL,
  `old_group_id` INT UNSIGNED NULL,
  `new_group_id` INT UNSIGNED NULL,
  `note` VARCHAR(500) NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_progress_history` (`student_registry_id`,`created_at`),
  CONSTRAINT `student_progress_registry_fk` FOREIGN KEY (`student_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_progress_old_group_fk` FOREIGN KEY (`old_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_progress_new_group_fk` FOREIGN KEY (`new_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_progress_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `teacher_registry_id` BIGINT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `media_path` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `academic_post_group` (`group_id`,`created_at`),
  KEY `academic_post_teacher` (`teacher_registry_id`,`group_id`,`created_at`),
  CONSTRAINT `academic_post_group_fk` FOREIGN KEY (`group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_post_teacher_fk` FOREIGN KEY (`teacher_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_post_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `student_registry_id` BIGINT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `academic_comment_post` (`post_id`,`created_at`),
  KEY `academic_comment_student` (`student_registry_id`),
  CONSTRAINT `academic_comment_post_fk` FOREIGN KEY (`post_id`) REFERENCES `academic_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_comment_student_fk` FOREIGN KEY (`student_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_timetable` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `teacher_registry_id` BIGINT UNSIGNED NULL,
  `room` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by_admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `academic_timetable_group` (`group_id`,`day_of_week`,`start_time`),
  KEY `academic_timetable_teacher` (`teacher_registry_id`),
  CONSTRAINT `academic_timetable_group_fk` FOREIGN KEY (`group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `academic_timetable_teacher_fk` FOREIGN KEY (`teacher_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_timetable_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- ChatApp simplification / unified manager approval upgrade
-- 2026-10-31
-- Run once after backing up the database.

USE `chatapp`;

-- A study department can have one official teacher as its academic head.
SET @col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='institution_options' AND column_name='head_teacher_registry_id');
SET @sql := IF(@col=0, 'ALTER TABLE institution_options ADD COLUMN head_teacher_registry_id BIGINT UNSIGNED NULL AFTER label', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_non_unique := (SELECT MIN(NON_UNIQUE) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_options' AND index_name='institution_option_head_teacher');
SET @sql := IF(@idx_non_unique=1, 'ALTER TABLE institution_options DROP INDEX institution_option_head_teacher', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='institution_options' AND index_name='institution_option_head_teacher' AND NON_UNIQUE=0);
SET @sql := IF(@idx=0, 'ALTER TABLE institution_options ADD UNIQUE KEY institution_option_head_teacher (head_teacher_registry_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='institution_options' AND constraint_name='institution_option_head_teacher_fk' AND constraint_type='FOREIGN KEY');
SET @sql := IF(@fk=0, 'ALTER TABLE institution_options ADD CONSTRAINT institution_option_head_teacher_fk FOREIGN KEY (head_teacher_registry_id) REFERENCES institution_registry(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Department requests now also carry structured administrative changes.
ALTER TABLE `department_requests` MODIFY COLUMN `request_type` VARCHAR(80) NOT NULL;
SET @col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='department_requests' AND column_name='payload_json');
SET @sql := IF(@col=0, 'ALTER TABLE department_requests ADD COLUMN payload_json LONGTEXT NULL AFTER amount', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Make teacher certificate selection usable on a new/older installation without manual seed work.
INSERT IGNORE INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`) VALUES
('teacher_certificate','Diploma',1,10,NULL),
('teacher_certificate','Bachelor',1,20,NULL),
('teacher_certificate','Master',1,30,NULL),
('teacher_certificate','PhD',1,40,NULL);
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
