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
