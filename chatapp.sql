CREATE DATABASE IF NOT EXISTS `chatapp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `chatapp`;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `registry_batch_events`;
DROP TABLE IF EXISTS `registry_batch_items`;
DROP TABLE IF EXISTS `registry_batches`;
DROP TABLE IF EXISTS `administrative_audit_log`;
DROP TABLE IF EXISTS `finance_transactions`;
DROP TABLE IF EXISTS `department_requests`;
DROP TABLE IF EXISTS `academic_grade_audit`;
DROP TABLE IF EXISTS `academic_exam_grades`;
DROP TABLE IF EXISTS `academic_exams`;
DROP TABLE IF EXISTS `academic_post_comments`;
DROP TABLE IF EXISTS `academic_posts`;
DROP TABLE IF EXISTS `academic_timetable`;
DROP TABLE IF EXISTS `academic_group_teachers`;
DROP TABLE IF EXISTS `student_progressions`;
DROP TABLE IF EXISTS `academic_groups`;
DROP TABLE IF EXISTS `institution_options`;
DROP TABLE IF EXISTS `call_signals`;
DROP TABLE IF EXISTS `call_sessions`;
DROP TABLE IF EXISTS `post_moderation_actions`;
DROP TABLE IF EXISTS `post_word_alerts`;
DROP TABLE IF EXISTS `post_reports`;
DROP TABLE IF EXISTS `moderation_words`;
DROP TABLE IF EXISTS `post_likes`;
DROP TABLE IF EXISTS `post_comments`;
DROP TABLE IF EXISTS `stories`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `friendships`;
DROP TABLE IF EXISTS `group_messages`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `chat_groups`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `user_blocks`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `institution_registry`;
DROP TABLE IF EXISTS `institution_departments`;
DROP TABLE IF EXISTS `admin_users`;
SET FOREIGN_KEY_CHECKS=1;


CREATE TABLE `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(190) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `admin_role` ENUM('super_admin','department_head','department_staff') NOT NULL DEFAULT 'department_staff',
  `department_id` INT UNSIGNED NULL,
  `registry_id` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_email` (`email`),
  KEY `admin_role_lookup` (`admin_role`,`is_active`),
  UNIQUE KEY `admin_registry_unique` (`registry_id`),
  KEY `admin_department_lookup` (`department_id`,`admin_role`,`is_active`),
  KEY `admin_created_by` (`created_by`),
  CONSTRAINT `admin_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `institution_options` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `option_type` ENUM('student_department','teacher_certificate','teacher_specialization','staff_workplace') NOT NULL,
  `label` VARCHAR(190) NOT NULL,
  `head_teacher_registry_id` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_by_admin_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institution_option_unique` (`option_type`,`label`),
  KEY `institution_option_active` (`option_type`,`is_active`,`sort_order`),
  UNIQUE KEY `institution_option_head_teacher` (`head_teacher_registry_id`),
  CONSTRAINT `institution_option_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `institution_departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(190) NOT NULL,
  `department_type` ENUM('root','academic_affairs','finance','administration','student_affairs','human_resources','it','other') NOT NULL DEFAULT 'other',
  `responsibilities` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code_unique` (`code`),
  KEY `department_parent` (`parent_id`,`sort_order`),
  CONSTRAINT `department_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `institution_departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `institution_departments` (`id`,`parent_id`,`code`,`name`,`department_type`,`responsibilities`,`is_active`,`sort_order`) VALUES
(1,NULL,'institution','Institution','root','Overall institutional governance and executive management.',1,0),
(2,1,'academic_affairs','Academic Affairs','academic_affairs','Owns academic groups, timetable coordination, teacher academic assignments and grade review.',1,10),
(3,1,'finance','Finance','finance','Owns institutional receipts, expenses, financial references, approved disbursements and balance control.',1,20),
(4,1,'administration','Institution Administration','administration','Owns official administration, institutional registry governance and cross-department administrative coordination.',1,30),
(5,1,'student_affairs','Student Affairs','student_affairs','Owns student records, student progression, student status and student-service administration.',1,40),
(6,1,'human_resources','Human Resources','human_resources','Owns staff and teacher registry administration, workforce records and departmental staffing governance.',1,50),
(7,1,'it','Information Technology','it','Owns institutional systems, technical operations, platform support and information-technology services.',1,60),
(8,1,'registry','Institution Registry','other','Prepares official student, teacher and staff registration lists for Administration review.',1,35);

INSERT INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`) VALUES
('teacher_specialization','Technology — Network',1,10,NULL),
('teacher_specialization','Technology — Programming',1,20,NULL),
('teacher_specialization','Technology — Cybersecurity',1,30,NULL),
('teacher_specialization','Technology — AI',1,40,NULL),
('teacher_specialization','Technology — Graphic Design',1,50,NULL),
('teacher_specialization','Mathematics',1,60,NULL),
('teacher_specialization','Physics',1,70,NULL);
INSERT INTO `institution_options` (`option_type`,`label`,`is_active`,`sort_order`,`created_by_admin_id`) VALUES
('teacher_certificate','Diploma',1,10,NULL),
('teacher_certificate','Bachelor',1,20,NULL),
('teacher_certificate','Master',1,30,NULL),
('teacher_certificate','PhD',1,40,NULL);


CREATE TABLE `academic_groups` (
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

CREATE TABLE `institution_registry` (
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
  `teacher_certificate` VARCHAR(190) NULL,
  `teacher_specialization` VARCHAR(190) NULL,
  `student_department_option_id` INT UNSIGNED NULL,
  `staff_workplace_option_id` INT UNSIGNED NULL,
  `teacher_certificate_option_id` INT UNSIGNED NULL,
  `teacher_specialization_option_id` INT UNSIGNED NULL,
  `student_stage` TINYINT UNSIGNED NULL,
  `student_academic_status` ENUM('active','graduated','inactive') NULL,
  `academic_group_id` INT UNSIGNED NULL,
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
  KEY `registry_student_department_option` (`student_department_option_id`),
  KEY `registry_staff_workplace_option` (`staff_workplace_option_id`),
  KEY `registry_teacher_certificate_option` (`teacher_certificate_option_id`),
  KEY `registry_teacher_specialization_option` (`teacher_specialization_option_id`),
  KEY `registry_academic_group` (`academic_group_id`),
  KEY `registry_student_progress` (`person_type`,`student_academic_status`,`student_stage`),
  CONSTRAINT `registry_admin_fk` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `registry_student_department_option_fk` FOREIGN KEY (`student_department_option_id`) REFERENCES `institution_options` (`id`) ON DELETE SET NULL,
  CONSTRAINT `registry_staff_workplace_option_fk` FOREIGN KEY (`staff_workplace_option_id`) REFERENCES `institution_options` (`id`) ON DELETE SET NULL,
  CONSTRAINT `registry_teacher_certificate_option_fk` FOREIGN KEY (`teacher_certificate_option_id`) REFERENCES `institution_options` (`id`) ON DELETE SET NULL,
  CONSTRAINT `registry_teacher_specialization_option_fk` FOREIGN KEY (`teacher_specialization_option_id`) REFERENCES `institution_options` (`id`) ON DELETE SET NULL,
  CONSTRAINT `registry_academic_group_fk` FOREIGN KEY (`academic_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `institution_options`
  ADD CONSTRAINT `institution_option_head_teacher_fk` FOREIGN KEY (`head_teacher_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL;

ALTER TABLE `admin_users`
  ADD CONSTRAINT `admin_department_fk` FOREIGN KEY (`department_id`) REFERENCES `institution_departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_registry_fk` FOREIGN KEY (`registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL;

CREATE TABLE `department_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT UNSIGNED NOT NULL,
  `requester_admin_id` INT UNSIGNED NOT NULL,
  `request_type` VARCHAR(80) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `details` VARCHAR(3000) NOT NULL DEFAULT '',
  `target_registry_id` BIGINT UNSIGNED NULL,
  `target_admin_user_id` INT UNSIGNED NULL,
  `amount` DECIMAL(14,2) NULL,
  `payload_json` LONGTEXT NULL,
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

CREATE TABLE `finance_transactions` (
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

CREATE TABLE `administrative_audit_log` (
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


CREATE TABLE `users` (
  `user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unique_id` INT UNSIGNED NOT NULL,
  `fname` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) NOT NULL DEFAULT '',
  `lname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `img` VARCHAR(255) NOT NULL DEFAULT 'assets/img/default-avatar.svg',
  `status` VARCHAR(50) NOT NULL DEFAULT 'Offline now',
  `last_seen_at` DATETIME NULL,
  `language` ENUM('ku','en','ar','de','fa') NOT NULL DEFAULT 'ku',
  `role` ENUM('student','teacher','staff') NOT NULL DEFAULT 'student',
  `bio` TEXT NULL,
  `theme_mode` ENUM('light','dark','system') NOT NULL DEFAULT 'light',
  `accent_color` ENUM('blue','green','purple','orange','rose') NOT NULL DEFAULT 'blue',
  `ui_density` ENUM('comfortable','compact') NOT NULL DEFAULT 'comfortable',
  `registry_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `users_registry_id` (`registry_id`),
  KEY `status` (`status`),
  KEY `role` (`role`),
  CONSTRAINT `users_registry_fk` FOREIGN KEY (`registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `msg_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `incoming_msg_id` INT UNSIGNED NOT NULL,
  `outgoing_msg_id` INT UNSIGNED NOT NULL,
  `msg` TEXT NOT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `attachment_name` VARCHAR(255) NULL,
  `attachment_mime` VARCHAR(120) NULL,
  `attachment_size` INT UNSIGNED NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`msg_id`),
  KEY `conversation_lookup` (`outgoing_msg_id`, `incoming_msg_id`, `created_at`),
  KEY `incoming_lookup` (`incoming_msg_id`),
  KEY `unread_lookup` (`incoming_msg_id`,`read_at`,`msg_id`),
  CONSTRAINT `messages_incoming_fk` FOREIGN KEY (`incoming_msg_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `messages_outgoing_fk` FOREIGN KEY (`outgoing_msg_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `friendships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_one` INT UNSIGNED NOT NULL,
  `user_two` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `friend_pair` (`user_one`,`user_two`),
  KEY `friend_user_two` (`user_two`),
  KEY `friend_requested_by` (`requested_by`),
  KEY `friend_status` (`status`),
  CONSTRAINT `friend_user_one_fk` FOREIGN KEY (`user_one`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `friend_user_two_fk` FOREIGN KEY (`user_two`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `friend_requested_by_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `user_blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_id` INT UNSIGNED NOT NULL,
  `blocked_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_block_unique` (`blocker_id`,`blocked_id`),
  KEY `user_blocked_lookup` (`blocked_id`),
  CONSTRAINT `block_blocker_fk` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `block_blocked_fk` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `media_path` VARCHAR(255) NULL,
  `visibility` ENUM('public','friends') NOT NULL DEFAULT 'friends',
  `shared_post_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_feed` (`visibility`,`created_at`),
  KEY `post_user` (`user_unique_id`,`created_at`),
  KEY `shared_post_lookup` (`shared_post_id`),
  UNIQUE KEY `user_shared_once` (`user_unique_id`,`shared_post_id`),
  CONSTRAINT `posts_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `posts_shared_fk` FOREIGN KEY (`shared_post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_likes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_user_like` (`post_id`,`user_unique_id`),
  KEY `like_user` (`user_unique_id`),
  CONSTRAINT `likes_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `likes_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `post_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `comment_post` (`post_id`,`created_at`),
  KEY `comment_user` (`user_unique_id`),
  CONSTRAINT `comments_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `media_path` VARCHAR(255) NULL,
  `visibility` ENUM('public','friends') NOT NULL DEFAULT 'friends',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `story_feed` (`visibility`,`expires_at`,`created_at`),
  KEY `story_user` (`user_unique_id`,`expires_at`),
  CONSTRAINT `stories_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `updated_at` (`updated_at`),
  CONSTRAINT `groups_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `user_unique_id` INT UNSIGNED NOT NULL,
  `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`, `user_unique_id`),
  KEY `user_unique_id` (`user_unique_id`),
  CONSTRAINT `group_members_group_fk` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_fk` FOREIGN KEY (`user_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `sender_unique_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `attachment_name` VARCHAR(255) NULL,
  `attachment_mime` VARCHAR(120) NULL,
  `attachment_size` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_lookup` (`group_id`, `created_at`),
  KEY `sender_lookup` (`sender_unique_id`),
  CONSTRAINT `group_messages_group_fk` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_messages_user_fk` FOREIGN KEY (`sender_unique_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_group_teachers` (
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

CREATE TABLE `student_progressions` (
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
  UNIQUE KEY `student_progress_year_unique` (`student_registry_id`,`academic_year`),
  KEY `student_progress_history` (`student_registry_id`,`created_at`),
  CONSTRAINT `student_progress_registry_fk` FOREIGN KEY (`student_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_progress_old_group_fk` FOREIGN KEY (`old_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_progress_new_group_fk` FOREIGN KEY (`new_group_id`) REFERENCES `academic_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `student_progress_admin_fk` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_posts` (
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

CREATE TABLE `academic_post_comments` (
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

CREATE TABLE `academic_timetable` (
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


CREATE TABLE `academic_exams` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `teacher_registry_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `exam_date` DATE NOT NULL,
  `max_score` DECIMAL(7,2) NOT NULL DEFAULT 100.00,
  `status` ENUM('draft','submitted','published','returned') NOT NULL DEFAULT 'draft',
  `teacher_note` VARCHAR(1000) NULL,
  `review_note` VARCHAR(1000) NULL,
  `submitted_at` DATETIME NULL,
  `reviewed_by_admin_id` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exam_group_status` (`group_id`,`status`,`exam_date`),
  KEY `exam_teacher` (`teacher_registry_id`,`created_at`),
  KEY `exam_review_admin` (`reviewed_by_admin_id`),
  CONSTRAINT `exam_group_fk` FOREIGN KEY (`group_id`) REFERENCES `academic_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_teacher_fk` FOREIGN KEY (`teacher_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `exam_review_admin_fk` FOREIGN KEY (`reviewed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_exam_grades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id` BIGINT UNSIGNED NOT NULL,
  `student_registry_id` BIGINT UNSIGNED NOT NULL,
  `teacher_score` DECIMAL(7,2) NOT NULL,
  `final_score` DECIMAL(7,2) NOT NULL,
  `teacher_note` VARCHAR(500) NULL,
  `admin_note` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exam_student_unique` (`exam_id`,`student_registry_id`),
  KEY `grade_student_lookup` (`student_registry_id`,`exam_id`),
  CONSTRAINT `grade_exam_fk` FOREIGN KEY (`exam_id`) REFERENCES `academic_exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_student_fk` FOREIGN KEY (`student_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `academic_grade_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `exam_id` BIGINT UNSIGNED NOT NULL,
  `grade_id` BIGINT UNSIGNED NULL,
  `actor_type` ENUM('teacher','admin') NOT NULL,
  `actor_registry_id` BIGINT UNSIGNED NULL,
  `actor_admin_id` INT UNSIGNED NULL,
  `action` ENUM('create','update','submit','review_edit','publish','return') NOT NULL,
  `old_score` DECIMAL(7,2) NULL,
  `new_score` DECIMAL(7,2) NULL,
  `note` VARCHAR(1000) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grade_audit_exam` (`exam_id`,`created_at`),
  CONSTRAINT `grade_audit_exam_fk` FOREIGN KEY (`exam_id`) REFERENCES `academic_exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grade_audit_grade_fk` FOREIGN KEY (`grade_id`) REFERENCES `academic_exam_grades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grade_audit_registry_fk` FOREIGN KEY (`actor_registry_id`) REFERENCES `institution_registry` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grade_audit_admin_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `call_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `caller_id` INT UNSIGNED NOT NULL,
  `callee_id` INT UNSIGNED NOT NULL,
  `call_type` ENUM('voice','video') NOT NULL,
  `status` ENUM('ringing','accepted','declined','ended','missed') NOT NULL DEFAULT 'ringing',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `answered_at` DATETIME NULL,
  `last_activity_at` DATETIME NULL,
  `ended_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `call_incoming` (`callee_id`,`status`,`created_at`),
  KEY `call_outgoing` (`caller_id`,`status`,`created_at`),
  KEY `call_activity_lookup` (`status`,`last_activity_at`),
  CONSTRAINT `call_caller_fk` FOREIGN KEY (`caller_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE,
  CONSTRAINT `call_callee_fk` FOREIGN KEY (`callee_id`) REFERENCES `users` (`unique_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `call_signals` (
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


-- New-install post moderation schema (2026-11-07).
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
