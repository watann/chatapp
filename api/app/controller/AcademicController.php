<?php
require_once __DIR__ . '/../helpers.php';

class AcademicController{
    private const OPTION_TYPES = ['student_department','teacher_certificate','teacher_specialization','staff_workplace'];

    public function defaultAcademicYear(){
        $y = (int)date('Y');
        if((int)date('n') < 8) $y--;
        return $y . '-' . ($y + 1);
    }

    public function listOptions($type, $activeOnly=true){
        if(!in_array($type, self::OPTION_TYPES, true)) throw new Exception(t('invalid_action'));
        $sql = "SELECT o.*,CONCAT_WS(' ',h.first_name,h.middle_name,h.last_name) AS head_teacher_name,h.issued_id AS head_teacher_issued_id
                FROM institution_options o
                LEFT JOIN institution_registry h ON h.id=o.head_teacher_registry_id
                WHERE o.option_type=?" . ($activeOnly ? " AND o.is_active=1" : "") . " ORDER BY o.sort_order ASC,o.label ASC,o.id ASC";
        $stmt = db()->prepare($sql);
        $stmt->bind_param('s', $type);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getOption($id, $type=null, $activeOnly=false){
        $id=(int)$id;
        if($id<=0) return null;
        $sql="SELECT * FROM institution_options WHERE id=?";
        $types='i'; $args=[$id];
        if($type!==null){
            if(!in_array($type,self::OPTION_TYPES,true)) return null;
            $sql.=" AND option_type=?";
            $types.='s'; $args[]=$type;
        }
        if($activeOnly) $sql.=" AND is_active=1";
        $sql.=" LIMIT 1";
        $stmt=db()->prepare($sql);
        $stmt->bind_param($types, ...$args);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function addOption($type,$label,$adminId){
        if(!in_array($type,self::OPTION_TYPES,true)) throw new Exception(t('invalid_action'));
        $label=trim((string)$label);
        if($label==='' || mb_strlen($label)>190) throw new Exception(t('option_name_required'));
        $adminId=(int)$adminId;
        $check=db()->prepare("SELECT id,is_active FROM institution_options WHERE option_type=? AND label=? LIMIT 1");
        $check->bind_param('ss',$type,$label); $check->execute();
        $row=$check->get_result()->fetch_assoc();
        if($row){
            $id=(int)$row['id'];
            if(!(int)$row['is_active']){
                $u=db()->prepare("UPDATE institution_options SET is_active=1,updated_at=NOW() WHERE id=?");
                $u->bind_param('i',$id); $u->execute();
                return $id;
            }
            throw new Exception(t('option_already_exists'));
        }
        $stmt=db()->prepare("INSERT INTO institution_options(option_type,label,is_active,sort_order,created_by_admin_id,created_at,updated_at) VALUES(?,?,1,0,?,NOW(),NOW())");
        $stmt->bind_param('ssi',$type,$label,$adminId); $stmt->execute();
        return (int)$stmt->insert_id;
    }

    public function renameStudyDepartment($optionId,$label){
        $optionId=(int)$optionId; $label=trim((string)$label);
        if($label==='' || mb_strlen($label)>190) throw new Exception(t('option_name_required'));
        $old=$this->getOption($optionId,'student_department',false);
        if(!$old) throw new Exception(t('invalid_department'));
        $conn=db(); $conn->begin_transaction();
        try{
            $dup=$conn->prepare("SELECT id FROM institution_options WHERE option_type='student_department' AND label=? AND id<>? LIMIT 1");
            $dup->bind_param('si',$label,$optionId);$dup->execute();
            if($dup->get_result()->fetch_assoc()) throw new Exception(t('option_already_exists'));
            $stmt=$conn->prepare("UPDATE institution_options SET label=?,updated_at=NOW() WHERE id=? AND option_type='student_department'");
            $stmt->bind_param('si',$label,$optionId); $stmt->execute();
            $sync=$conn->prepare('UPDATE institution_registry SET student_department=? WHERE student_department_option_id=?');
            $sync->bind_param('si',$label,$optionId); $sync->execute();
            $conn->commit();
        }catch(Throwable $e){$conn->rollback();throw $e;}
    }

    public function setAcademicDepartmentHead($optionId,$teacherRegistryId){
        $optionId=(int)$optionId; $teacherRegistryId=(int)$teacherRegistryId;
        $option=$this->getOption($optionId,'student_department',false);
        if(!$option) throw new Exception(t('invalid_department'));
        if($teacherRegistryId<=0){
            $stmt=db()->prepare("UPDATE institution_options SET head_teacher_registry_id=NULL,updated_at=NOW() WHERE id=? AND option_type='student_department'");
            $stmt->bind_param('i',$optionId); $stmt->execute();
            return;
        }
        $stmt=db()->prepare("SELECT id FROM institution_registry WHERE id=? AND person_type='teacher' AND status<>'suspended' LIMIT 1");
        $stmt->bind_param('i',$teacherRegistryId); $stmt->execute();
        if(!$stmt->get_result()->fetch_assoc()) throw new Exception(t('teacher_not_found'));
        $used=db()->prepare("SELECT id,label FROM institution_options WHERE option_type='student_department' AND head_teacher_registry_id=? AND id<>? LIMIT 1");
        $used->bind_param('ii',$teacherRegistryId,$optionId); $used->execute();
        if($used->get_result()->fetch_assoc()) throw new Exception(t('teacher_already_study_head'));
        $u=db()->prepare("UPDATE institution_options SET head_teacher_registry_id=?,updated_at=NOW() WHERE id=? AND option_type='student_department'");
        $u->bind_param('ii',$teacherRegistryId,$optionId); $u->execute();
    }

    public function toggleOption($id){
        $id=(int)$id;
        $row=$this->getOption($id);
        if(!$row) throw new Exception(t('invalid_action'));
        $active=(int)$row['is_active'] ? 0 : 1;
        $stmt=db()->prepare("UPDATE institution_options SET is_active=?,updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ii',$active,$id); $stmt->execute();
    }

    public function ensureGroup($departmentOptionId,$stage,$groupCode,$academicYear,$adminId){
        $departmentOptionId=(int)$departmentOptionId;
        $stage=(int)$stage;
        $groupCode=strtoupper(trim((string)$groupCode));
        $academicYear=trim((string)$academicYear);
        $adminId=(int)$adminId;
        if(!$this->getOption($departmentOptionId,'student_department',true)) throw new Exception(t('invalid_department'));
        if($stage<1 || $stage>4) throw new Exception(t('invalid_stage'));
        if($groupCode==='' || mb_strlen($groupCode)>20) throw new Exception(t('group_code_required'));
        if(!preg_match('/^\d{4}-\d{4}$/',$academicYear)) throw new Exception(t('invalid_academic_year'));
        $parts=explode('-',$academicYear);
        if((int)$parts[1] !== (int)$parts[0]+1) throw new Exception(t('invalid_academic_year'));

        $stmt=db()->prepare("SELECT id,is_active FROM academic_groups WHERE department_option_id=? AND stage=? AND group_code=? AND academic_year=? LIMIT 1");
        $stmt->bind_param('iiss',$departmentOptionId,$stage,$groupCode,$academicYear);
        $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
        if($row){
            if(!(int)$row['is_active']) throw new Exception(t('academic_group_archived'));
            return (int)$row['id'];
        }

        $stmt=db()->prepare("INSERT INTO academic_groups(department_option_id,stage,group_code,academic_year,is_active,created_by_admin_id,created_at,updated_at) VALUES(?,?,?,?,1,?,NOW(),NOW())");
        $stmt->bind_param('iissi',$departmentOptionId,$stage,$groupCode,$academicYear,$adminId);
        $stmt->execute();
        return (int)$stmt->insert_id;
    }

    public function createGroup($departmentOptionId,$stage,$groupCode,$academicYear,$adminId){
        return $this->ensureGroup($departmentOptionId,$stage,$groupCode,$academicYear,$adminId);
    }

    public function listGroups(){
        $sql="SELECT g.*,o.label AS department_label,
              (SELECT COUNT(*) FROM institution_registry r WHERE r.academic_group_id=g.id AND r.person_type='student' AND r.student_academic_status='active' AND r.status<>'suspended') AS student_count,
              (SELECT COUNT(*) FROM academic_group_teachers gt JOIN institution_registry tr ON tr.id=gt.teacher_registry_id WHERE gt.group_id=g.id AND tr.status<>'suspended') AS teacher_count
              FROM academic_groups g
              JOIN institution_options o ON o.id=g.department_option_id
              ORDER BY g.academic_year DESC,o.label ASC,g.stage ASC,g.group_code ASC";
        return db()->query($sql);
    }

    public function getGroup($groupId){
        $groupId=(int)$groupId;
        $stmt=db()->prepare("SELECT g.*,o.label AS department_label FROM academic_groups g JOIN institution_options o ON o.id=g.department_option_id WHERE g.id=? LIMIT 1");
        $stmt->bind_param('i',$groupId); $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function listTeachers(){
        return db()->query("SELECT r.id,r.issued_id,r.first_name,r.middle_name,r.last_name,r.status,
            COALESCE(cert.label,r.teacher_certificate) AS certificate_label,
            COALESCE(o.label,r.teacher_specialization) AS specialization_label,
            u.unique_id,u.email
            FROM institution_registry r
            LEFT JOIN institution_options cert ON cert.id=r.teacher_certificate_option_id
            LEFT JOIN institution_options o ON o.id=r.teacher_specialization_option_id
            LEFT JOIN users u ON u.registry_id=r.id
            WHERE r.person_type='teacher' AND r.status<>'suspended'
            ORDER BY r.first_name,r.middle_name,r.last_name");
    }

    public function teachersForGroup($groupId){
        $groupId=(int)$groupId;
        $stmt=db()->prepare("SELECT gt.*,r.first_name,r.middle_name,r.last_name,r.issued_id,
            COALESCE(cert.label,r.teacher_certificate) AS certificate_label,
            COALESCE(o.label,r.teacher_specialization) AS specialization_label,u.unique_id,u.email
            FROM academic_group_teachers gt
            JOIN institution_registry r ON r.id=gt.teacher_registry_id
            LEFT JOIN institution_options cert ON cert.id=r.teacher_certificate_option_id
            LEFT JOIN institution_options o ON o.id=r.teacher_specialization_option_id
            LEFT JOIN users u ON u.registry_id=r.id
            WHERE gt.group_id=?
            ORDER BY r.first_name,r.middle_name,r.last_name");
        $stmt->bind_param('i',$groupId); $stmt->execute();
        return $stmt->get_result();
    }

    public function assignTeacher($groupId,$teacherRegistryId,$adminId){
        $groupId=(int)$groupId; $teacherRegistryId=(int)$teacherRegistryId; $adminId=(int)$adminId;
        $group=$this->getGroup($groupId);
        if(!$group || !(int)$group['is_active']) throw new Exception(t('academic_group_not_found'));
        $stmt=db()->prepare("SELECT id FROM institution_registry WHERE id=? AND person_type='teacher' AND status<>'suspended' LIMIT 1");
        $stmt->bind_param('i',$teacherRegistryId); $stmt->execute();
        if(!$stmt->get_result()->num_rows) throw new Exception(t('teacher_not_found'));
        $stmt=db()->prepare("INSERT IGNORE INTO academic_group_teachers(group_id,teacher_registry_id,assigned_by_admin_id,created_at) VALUES(?,?,?,NOW())");
        $stmt->bind_param('iii',$groupId,$teacherRegistryId,$adminId); $stmt->execute();
    }

    public function unassignTeacher($groupId,$teacherRegistryId){
        $groupId=(int)$groupId; $teacherRegistryId=(int)$teacherRegistryId;
        $stmt=db()->prepare("DELETE FROM academic_group_teachers WHERE group_id=? AND teacher_registry_id=?");
        $stmt->bind_param('ii',$groupId,$teacherRegistryId); $stmt->execute();
    }

    public function timetable($groupId){
        $groupId=(int)$groupId;
        $stmt=db()->prepare("SELECT tt.*,r.first_name,r.middle_name,r.last_name
            FROM academic_timetable tt
            LEFT JOIN institution_registry r ON r.id=tt.teacher_registry_id
            WHERE tt.group_id=?
            ORDER BY tt.day_of_week ASC,tt.start_time ASC,tt.sort_order ASC,tt.id ASC");
        $stmt->bind_param('i',$groupId); $stmt->execute();
        return $stmt->get_result();
    }

    public function addTimetable($groupId,$day,$start,$end,$subject,$teacherRegistryId,$room,$adminId){
        $groupId=(int)$groupId; $day=(int)$day; $teacherRegistryId=(int)$teacherRegistryId; $adminId=(int)$adminId;
        $subject=trim((string)$subject); $room=trim((string)$room); $start=trim((string)$start); $end=trim((string)$end);
        $group=$this->getGroup($groupId);
        if(!$group || !(int)$group['is_active']) throw new Exception(t('academic_group_not_found'));
        $validTime=function($value){ $d=DateTime::createFromFormat('H:i',$value); return $d && $d->format('H:i')===$value; };
        if($day<1 || $day>7 || !$validTime($start) || !$validTime($end) || $start >= $end) throw new Exception(t('invalid_timetable_time'));
        if($subject==='' || $room==='' || mb_strlen($subject)>190 || mb_strlen($room)>100) throw new Exception(t('required_fields'));
        if($teacherRegistryId>0){
            $stmt=db()->prepare("SELECT gt.id FROM academic_group_teachers gt JOIN institution_registry r ON r.id=gt.teacher_registry_id WHERE gt.group_id=? AND gt.teacher_registry_id=? AND r.status<>'suspended' LIMIT 1");
            $stmt->bind_param('ii',$groupId,$teacherRegistryId); $stmt->execute();
            if(!$stmt->get_result()->num_rows) throw new Exception(t('teacher_must_be_assigned'));
        }else{
            $teacherRegistryId=null;
        }
        // Half-open interval overlap: existing.start < new.end AND existing.end > new.start.
        $stmt=db()->prepare("SELECT id FROM academic_timetable WHERE group_id=? AND day_of_week=? AND start_time<? AND end_time>? LIMIT 1");
        $stmt->bind_param('iiss',$groupId,$day,$end,$start); $stmt->execute();
        if($stmt->get_result()->num_rows) throw new Exception(t('group_timetable_conflict'));
        if($teacherRegistryId!==null){
            $stmt=db()->prepare("SELECT tt.id FROM academic_timetable tt JOIN academic_groups g ON g.id=tt.group_id WHERE tt.teacher_registry_id=? AND tt.day_of_week=? AND tt.start_time<? AND tt.end_time>? AND g.is_active=1 LIMIT 1");
            $stmt->bind_param('iiss',$teacherRegistryId,$day,$end,$start); $stmt->execute();
            if($stmt->get_result()->num_rows) throw new Exception(t('teacher_timetable_conflict'));
        }
        $stmt=db()->prepare("SELECT tt.id FROM academic_timetable tt JOIN academic_groups g ON g.id=tt.group_id WHERE tt.day_of_week=? AND LOWER(TRIM(tt.room))=LOWER(?) AND tt.start_time<? AND tt.end_time>? AND g.is_active=1 LIMIT 1");
        $stmt->bind_param('isss',$day,$room,$end,$start); $stmt->execute();
        if($stmt->get_result()->num_rows) throw new Exception(t('room_timetable_conflict'));
        $stmt=db()->prepare("INSERT INTO academic_timetable(group_id,day_of_week,start_time,end_time,subject,teacher_registry_id,room,sort_order,created_by_admin_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,0,?,NOW(),NOW())");
        $stmt->bind_param('iisssisi',$groupId,$day,$start,$end,$subject,$teacherRegistryId,$room,$adminId);
        $stmt->execute();
    }

    public function deleteTimetable($groupId,$id){
        $groupId=(int)$groupId; $id=(int)$id;
        $stmt=db()->prepare("DELETE FROM academic_timetable WHERE id=? AND group_id=?");
        $stmt->bind_param('ii',$id,$groupId); $stmt->execute();
    }

    public function listStudents(){
        $sql="SELECT r.*,o.label AS department_label,g.group_code,g.academic_year,g.stage AS group_stage,
              u.unique_id,u.email
              FROM institution_registry r
              LEFT JOIN institution_options o ON o.id=r.student_department_option_id
              LEFT JOIN academic_groups g ON g.id=r.academic_group_id
              LEFT JOIN users u ON u.registry_id=r.id
              WHERE r.person_type='student'
              ORDER BY (r.student_academic_status='active') DESC,o.label ASC,r.student_stage ASC,g.group_code ASC,r.first_name ASC";
        return db()->query($sql);
    }

    public function progressStudent($registryId,$decision,$academicYear,$groupCode,$manualStage,$adminId,$note=''){
        $registryId=(int)$registryId; $adminId=(int)$adminId; $manualStage=(int)$manualStage;
        $academicYear=trim((string)$academicYear); $groupCode=strtoupper(trim((string)$groupCode));
        if(!in_array($decision,['pass','stay','assign'],true)) throw new Exception(t('invalid_action'));
        if(!preg_match('/^\d{4}-\d{4}$/',$academicYear)) throw new Exception(t('invalid_academic_year'));
        $yearParts=explode('-',$academicYear); if((int)$yearParts[1] !== (int)$yearParts[0]+1) throw new Exception(t('invalid_academic_year'));
        $conn=db(); $conn->begin_transaction();
        try{
            $stmt=$conn->prepare("SELECT r.*,g.academic_year AS current_academic_year,g.group_code AS current_group_code FROM institution_registry r LEFT JOIN academic_groups g ON g.id=r.academic_group_id WHERE r.id=? AND r.person_type='student' FOR UPDATE");
            $stmt->bind_param('i',$registryId); $stmt->execute(); $student=$stmt->get_result()->fetch_assoc();
            if(!$student || ($student['status']??'')==='suspended') throw new Exception(t('student_not_found'));
            if(($student['student_academic_status']??'active')!=='active') throw new Exception(t('student_not_active'));
            $dup=$conn->prepare("SELECT id FROM student_progressions WHERE student_registry_id=? AND academic_year=? LIMIT 1 FOR UPDATE");
            $dup->bind_param('is',$registryId,$academicYear); $dup->execute(); if($dup->get_result()->num_rows) throw new Exception(t('student_progress_already_recorded'));
            $currentYear=(string)($student['current_academic_year']??'');
            if(in_array($decision,['pass','stay'],true) && preg_match('/^(\d{4})-(\d{4})$/',$currentYear,$m)){
                $expected=((int)$m[1]+1).'-'.((int)$m[2]+1);
                if($academicYear!==$expected) throw new Exception(t('progress_year_must_be_next'));
            }
            $oldStage=max(1,(int)($student['student_stage'] ?? 1)); $oldGroup=(int)($student['academic_group_id'] ?? 0);
            $newStage=$oldStage; $newGroup=null; $newStatus='active';
            if($decision==='pass'){
                if($oldStage>=4){ $newStage=4; $newStatus='graduated'; $newGroup=null; }
                else $newStage=$oldStage+1;
            }elseif($decision==='assign'){
                if($manualStage<1 || $manualStage>4) throw new Exception(t('invalid_stage'));
                $newStage=$manualStage;
            }
            if($newStatus==='active'){
                $dept=(int)($student['student_department_option_id'] ?? 0); if($dept<=0) throw new Exception(t('invalid_department'));
                if($groupCode==='') throw new Exception(t('group_code_required'));
                $newGroup=$this->ensureGroup($dept,$newStage,$groupCode,$academicYear,$adminId);
            }
            if($newGroup===null){
                $stmt=$conn->prepare("UPDATE institution_registry SET student_stage=?,student_academic_status=?,academic_group_id=NULL,updated_at=NOW() WHERE id=?"); $stmt->bind_param('isi',$newStage,$newStatus,$registryId);
            }else{
                $stmt=$conn->prepare("UPDATE institution_registry SET student_stage=?,student_academic_status=?,academic_group_id=?,updated_at=NOW() WHERE id=?"); $stmt->bind_param('isii',$newStage,$newStatus,$newGroup,$registryId);
            }
            $stmt->execute(); $oldGroupNullable=$oldGroup>0?$oldGroup:null; $newGroupNullable=$newGroup ?: null; $note=mb_substr(trim((string)$note),0,500);
            $stmt=$conn->prepare("INSERT INTO student_progressions(student_registry_id,academic_year,decision,old_stage,new_stage,old_group_id,new_group_id,note,admin_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->bind_param('issiiiisi',$registryId,$academicYear,$decision,$oldStage,$newStage,$oldGroupNullable,$newGroupNullable,$note,$adminId); $stmt->execute();
            $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }

    public function groupsForCurrentUser(){
        require_auth();
        $user=current_user();
        if(!$user || empty($user['registry_id'])) return [];
        $rid=(int)$user['registry_id'];
        if(($user['role']??'')==='student'){
            $stmt=db()->prepare("SELECT g.*,o.label AS department_label,'student' AS academic_role
                FROM institution_registry r JOIN academic_groups g ON g.id=r.academic_group_id
                JOIN institution_options o ON o.id=g.department_option_id
                WHERE r.id=? AND r.person_type='student' AND r.status<>'suspended' AND r.student_academic_status='active' AND g.is_active=1");
            $stmt->bind_param('i',$rid); $stmt->execute();
        }elseif(($user['role']??'')==='teacher'){
            $stmt=db()->prepare("SELECT g.*,o.label AS department_label,'teacher' AS academic_role
                FROM academic_group_teachers gt JOIN academic_groups g ON g.id=gt.group_id
                JOIN institution_options o ON o.id=g.department_option_id
                WHERE gt.teacher_registry_id=? AND g.is_active=1 AND EXISTS (SELECT 1 FROM institution_registry tr WHERE tr.id=gt.teacher_registry_id AND tr.status<>'suspended') ORDER BY g.academic_year DESC,o.label,g.stage,g.group_code");
            $stmt->bind_param('i',$rid); $stmt->execute();
        }else{
            return [];
        }
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function accessContext($groupId){
        require_auth();
        $group=$this->getGroup($groupId);
        if(!$group || !(int)$group['is_active']) return null;
        $user=current_user();
        if(!$user || empty($user['registry_id'])) return null;
        $rid=(int)$user['registry_id'];
        if(($user['role']??'')==='student'){
            $stmt=db()->prepare("SELECT id FROM institution_registry WHERE id=? AND person_type='student' AND status<>'suspended' AND academic_group_id=? AND student_academic_status='active' LIMIT 1");
            $stmt->bind_param('ii',$rid,$groupId); $stmt->execute();
            if($stmt->get_result()->num_rows) return ['role'=>'student','registry_id'=>$rid,'group'=>$group,'user'=>$user];
        }
        if(($user['role']??'')==='teacher'){
            $stmt=db()->prepare("SELECT gt.id FROM academic_group_teachers gt JOIN institution_registry r ON r.id=gt.teacher_registry_id WHERE gt.group_id=? AND gt.teacher_registry_id=? AND r.status<>'suspended' LIMIT 1");
            $stmt->bind_param('ii',$groupId,$rid); $stmt->execute();
            if($stmt->get_result()->num_rows) return ['role'=>'teacher','registry_id'=>$rid,'group'=>$group,'user'=>$user];
        }
        return null;
    }

    public function visiblePosts($groupId,$ctx){
        $groupId=(int)$groupId;
        if(($ctx['role']??'')==='teacher'){
            $rid=(int)$ctx['registry_id'];
            $stmt=db()->prepare("SELECT ap.*,r.first_name,r.middle_name,r.last_name,u.img
                FROM academic_posts ap JOIN institution_registry r ON r.id=ap.teacher_registry_id
                LEFT JOIN users u ON u.registry_id=r.id
                WHERE ap.group_id=? AND ap.teacher_registry_id=? ORDER BY ap.created_at DESC,ap.id DESC");
            $stmt->bind_param('ii',$groupId,$rid);
        }else{
            $stmt=db()->prepare("SELECT ap.*,r.first_name,r.middle_name,r.last_name,u.img
                FROM academic_posts ap JOIN institution_registry r ON r.id=ap.teacher_registry_id
                LEFT JOIN users u ON u.registry_id=r.id
                WHERE ap.group_id=? ORDER BY ap.created_at DESC,ap.id DESC");
            $stmt->bind_param('i',$groupId);
        }
        $stmt->execute(); return $stmt->get_result();
    }

    public function commentsForPost($postId){
        $postId=(int)$postId;
        $stmt=db()->prepare("SELECT c.*,r.first_name,r.middle_name,r.last_name,u.img
            FROM academic_post_comments c JOIN institution_registry r ON r.id=c.student_registry_id
            LEFT JOIN users u ON u.registry_id=r.id
            WHERE c.post_id=? ORDER BY c.created_at ASC,c.id ASC");
        $stmt->bind_param('i',$postId); $stmt->execute();
        return $stmt->get_result();
    }

    public function academicMediaPost($postId){
        require_auth();
        $postId=(int)$postId;
        $stmt=db()->prepare("SELECT id,group_id,teacher_registry_id,media_path FROM academic_posts WHERE id=? LIMIT 1");
        $stmt->bind_param('i',$postId); $stmt->execute();
        $post=$stmt->get_result()->fetch_assoc();
        if(!$post || empty($post['media_path'])) return null;
        $ctx=$this->accessContext((int)$post['group_id']);
        if(!$ctx) return null;
        if($ctx['role']==='teacher' && (int)$ctx['registry_id']!==(int)$post['teacher_registry_id']) return null;
        return $post;
    }

    public function createAcademicPost($groupId,$content,$file){
        $ctx=$this->accessContext($groupId);
        if(!$ctx || $ctx['role']!=='teacher') throw new Exception(t('teachers_only_post'));
        $content=trim((string)$content); if(mb_strlen($content)>5000) throw new Exception(t('post_too_long'));
        $media=null;
        try{
            $media=upload_academic_media($file); if($content==='' && !$media) throw new Exception(t('content_required'));
            $rid=(int)$ctx['registry_id']; $stmt=db()->prepare("INSERT INTO academic_posts(group_id,teacher_registry_id,content,media_path,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())");
            $stmt->bind_param('iiss',$groupId,$rid,$content,$media); $stmt->execute();
        }catch(Throwable $e){ if($media) delete_academic_media($media); throw $e; }
    }

    public function deleteAcademicPost($groupId,$postId){
        $ctx=$this->accessContext($groupId);
        if(!$ctx || $ctx['role']!=='teacher') throw new Exception(t('not_allowed'));
        $rid=(int)$ctx['registry_id']; $postId=(int)$postId;
        $stmt=db()->prepare("SELECT media_path FROM academic_posts WHERE id=? AND group_id=? AND teacher_registry_id=? LIMIT 1");
        $stmt->bind_param('iii',$postId,$groupId,$rid); $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc();
        if(!$row) throw new Exception(t('not_allowed'));
        $stmt=db()->prepare("DELETE FROM academic_posts WHERE id=? AND group_id=? AND teacher_registry_id=?");
        $stmt->bind_param('iii',$postId,$groupId,$rid); $stmt->execute();
        if(!empty($row['media_path'])) delete_academic_media($row['media_path']);
    }

    public function addAcademicComment($groupId,$postId,$comment){
        $ctx=$this->accessContext($groupId);
        if(!$ctx || $ctx['role']!=='student') throw new Exception(t('students_only_comment'));
        $comment=trim((string)$comment); $postId=(int)$postId;
        if($comment==='' || mb_strlen($comment)>1500) throw new Exception(t('comment_too_long'));
        $stmt=db()->prepare("SELECT id FROM academic_posts WHERE id=? AND group_id=? LIMIT 1");
        $stmt->bind_param('ii',$postId,$groupId); $stmt->execute();
        if(!$stmt->get_result()->num_rows) throw new Exception(t('invalid_action'));
        $rid=(int)$ctx['registry_id'];
        $stmt=db()->prepare("INSERT INTO academic_post_comments(post_id,student_registry_id,comment,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())");
        $stmt->bind_param('iis',$postId,$rid,$comment); $stmt->execute();
    }

    public function toggleGroupActive($groupId){
        $group=$this->getGroup($groupId); if(!$group) throw new Exception(t('academic_group_not_found'));
        $active=(int)$group['is_active']?0:1; $stmt=db()->prepare("UPDATE academic_groups SET is_active=?,updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ii',$active,$groupId); $stmt->execute();
    }

    public function adminPostsForGroup($groupId){
        $groupId=(int)$groupId; $stmt=db()->prepare("SELECT ap.*,r.first_name,r.middle_name,r.last_name FROM academic_posts ap JOIN institution_registry r ON r.id=ap.teacher_registry_id WHERE ap.group_id=? ORDER BY ap.created_at DESC,ap.id DESC");
        $stmt->bind_param('i',$groupId); $stmt->execute(); return $stmt->get_result();
    }

    public function adminDeleteAcademicPost($groupId,$postId){
        $groupId=(int)$groupId; $postId=(int)$postId;
        $stmt=db()->prepare("SELECT media_path FROM academic_posts WHERE id=? AND group_id=? LIMIT 1"); $stmt->bind_param('ii',$postId,$groupId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
        if(!$row) throw new Exception(t('invalid_action')); $d=db()->prepare("DELETE FROM academic_posts WHERE id=? AND group_id=?"); $d->bind_param('ii',$postId,$groupId); $d->execute();
        if(!empty($row['media_path'])) delete_academic_media($row['media_path']);
    }

}
