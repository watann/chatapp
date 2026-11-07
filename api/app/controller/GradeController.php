<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AcademicController.php';
require_once __DIR__ . '/AdminController.php';

class GradeController{
    private function academic(){ return new AcademicController(); }

    private function normalizeScore($value,$max){
        if($value==='' || $value===null || !is_numeric($value)) throw new Exception(t('invalid_grade'));
        $score=round((float)$value,2); $max=(float)$max;
        if($score<0 || $score>$max) throw new Exception(t('invalid_grade'));
        return $score;
    }

    public function studentsForGroup($groupId){
        $groupId=(int)$groupId;
        $stmt=db()->prepare("SELECT r.id,r.issued_id,r.first_name,r.middle_name,r.last_name,u.email
            FROM institution_registry r LEFT JOIN users u ON u.registry_id=r.id
            WHERE r.person_type='student' AND r.academic_group_id=? AND r.student_academic_status='active' AND r.status<>'suspended'
            ORDER BY r.first_name,r.middle_name,r.last_name,r.id");
        $stmt->bind_param('i',$groupId); $stmt->execute(); return $stmt->get_result();
    }

    public function teacherExams($groupId,$ctx){
        if(!$ctx || ($ctx['role']??'')!=='teacher') return [];
        $groupId=(int)$groupId; $rid=(int)$ctx['registry_id'];
        $stmt=db()->prepare("SELECT e.*,(SELECT COUNT(*) FROM academic_exam_grades g WHERE g.exam_id=e.id) AS grade_count
            FROM academic_exams e WHERE e.group_id=? AND e.teacher_registry_id=? ORDER BY e.created_at DESC,e.id DESC");
        $stmt->bind_param('ii',$groupId,$rid); $stmt->execute(); return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getExam($examId){
        $examId=(int)$examId;
        $stmt=db()->prepare("SELECT e.*,ag.department_option_id,ag.stage,ag.group_code,ag.academic_year,o.label AS department_label,
            r.first_name,r.middle_name,r.last_name
            FROM academic_exams e
            JOIN academic_groups ag ON ag.id=e.group_id JOIN institution_options o ON o.id=ag.department_option_id
            JOIN institution_registry r ON r.id=e.teacher_registry_id
            WHERE e.id=? LIMIT 1");
        $stmt->bind_param('i',$examId); $stmt->execute(); return $stmt->get_result()->fetch_assoc();
    }

    public function gradesForExam($examId){
        $examId=(int)$examId;
        $stmt=db()->prepare("SELECT g.*,r.issued_id,r.first_name,r.middle_name,r.last_name,u.email
            FROM academic_exam_grades g JOIN institution_registry r ON r.id=g.student_registry_id
            LEFT JOIN users u ON u.registry_id=r.id WHERE g.exam_id=? ORDER BY r.first_name,r.middle_name,r.last_name,r.id");
        $stmt->bind_param('i',$examId); $stmt->execute(); return $stmt->get_result();
    }

    public function createExam($groupId,$title,$subject,$examDate,$maxScore,$teacherNote){
        $academic=$this->academic(); $ctx=$academic->accessContext($groupId);
        if(!$ctx || $ctx['role']!=='teacher') throw new Exception(t('not_allowed'));
        $title=trim((string)$title); $subject=trim((string)$subject); $examDate=trim((string)$examDate); $teacherNote=mb_substr(trim((string)$teacherNote),0,1000);
        if($title==='' || $subject==='' || mb_strlen($title)>190 || mb_strlen($subject)>190) throw new Exception(t('required_fields'));
        $d=DateTime::createFromFormat('Y-m-d',$examDate); if(!$d || $d->format('Y-m-d')!==$examDate) throw new Exception(t('invalid_action'));
        if(!is_numeric($maxScore) || (float)$maxScore<=0 || (float)$maxScore>10000) throw new Exception(t('invalid_grade_max'));
        $max=round((float)$maxScore,2); $rid=(int)$ctx['registry_id'];
        $stmt=db()->prepare("INSERT INTO academic_exams(group_id,teacher_registry_id,title,subject,exam_date,max_score,status,teacher_note,created_at,updated_at) VALUES(?,?,?,?,?,?,'draft',?,NOW(),NOW())");
        $stmt->bind_param('iisssds',$groupId,$rid,$title,$subject,$examDate,$max,$teacherNote); $stmt->execute();
        $examId=(int)$stmt->insert_id;
        $audit=db()->prepare("INSERT INTO academic_grade_audit(exam_id,actor_type,actor_registry_id,action,note,created_at) VALUES(?,'teacher',?,'create',?,NOW())");
        $audit->bind_param('iis',$examId,$rid,$teacherNote); $audit->execute();
        return $examId;
    }

    private function requireTeacherExam($examId,$allowSubmitted=false){
        require_auth(); $user=current_user();
        if(!$user || ($user['role']??'')!=='teacher' || empty($user['registry_id'])) throw new Exception(t('not_allowed'));
        $exam=$this->getExam($examId);
        if(!$exam || (int)$exam['teacher_registry_id']!==(int)$user['registry_id']) throw new Exception(t('not_allowed'));
        $allowed=$allowSubmitted?['draft','returned','submitted']:['draft','returned'];
        if(!in_array($exam['status'],$allowed,true)) throw new Exception(t('grade_exam_locked'));
        $ctx=$this->academic()->accessContext((int)$exam['group_id']);
        if(!$ctx || $ctx['role']!=='teacher' || (int)$ctx['registry_id']!==(int)$exam['teacher_registry_id']) throw new Exception(t('not_allowed'));
        return [$exam,$ctx];
    }

    public function saveGrades($examId,$scores,$notes=[]){
        [$exam,$ctx]=$this->requireTeacherExam($examId,false);
        if(!is_array($scores)) throw new Exception(t('invalid_grade'));
        $students=$this->studentsForGroup((int)$exam['group_id'])->fetch_all(MYSQLI_ASSOC);
        $allowed=[]; foreach($students as $s) $allowed[(int)$s['id']]=$s;
        $conn=db(); $conn->begin_transaction();
        try{
            foreach($scores as $studentId=>$raw){
                $studentId=(int)$studentId; if(!isset($allowed[$studentId])) throw new Exception(t('not_allowed'));
                if(trim((string)$raw)==='') continue;
                $score=$this->normalizeScore($raw,$exam['max_score']);
                $note=mb_substr(trim((string)($notes[$studentId]??'')),0,500);
                $old=$conn->prepare("SELECT id,teacher_score FROM academic_exam_grades WHERE exam_id=? AND student_registry_id=? LIMIT 1 FOR UPDATE");
                $old->bind_param('ii',$examId,$studentId); $old->execute(); $existing=$old->get_result()->fetch_assoc();
                if($existing){
                    $gradeId=(int)$existing['id']; $oldScore=(float)$existing['teacher_score'];
                    $u=$conn->prepare("UPDATE academic_exam_grades SET teacher_score=?,final_score=?,teacher_note=?,admin_note=NULL,updated_at=NOW() WHERE id=?");
                    $u->bind_param('ddsi',$score,$score,$note,$gradeId); $u->execute();
                    $action='update';
                }else{
                    $oldScore=null;
                    $u=$conn->prepare("INSERT INTO academic_exam_grades(exam_id,student_registry_id,teacher_score,final_score,teacher_note,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())");
                    $u->bind_param('iidds',$examId,$studentId,$score,$score,$note); $u->execute(); $gradeId=(int)$u->insert_id; $action='create';
                }
                $rid=(int)$ctx['registry_id'];
                $a=$conn->prepare("INSERT INTO academic_grade_audit(exam_id,grade_id,actor_type,actor_registry_id,action,old_score,new_score,note,created_at) VALUES(?,?,'teacher',?,?,?,?,?,NOW())");
                $a->bind_param('iiisdds',$examId,$gradeId,$rid,$action,$oldScore,$score,$note); $a->execute();
            }
            $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }

    public function submitExam($examId){
        [$exam,$ctx]=$this->requireTeacherExam($examId,false);
        $studentCount=$this->studentsForGroup((int)$exam['group_id'])->num_rows;
        $groupId=(int)$exam['group_id'];
        $stmt=db()->prepare("SELECT COUNT(*) c FROM academic_exam_grades g JOIN institution_registry r ON r.id=g.student_registry_id WHERE g.exam_id=? AND r.person_type='student' AND r.academic_group_id=? AND r.student_academic_status='active' AND r.status<>'suspended'");
        $stmt->bind_param('ii',$examId,$groupId); $stmt->execute(); $gradeCount=(int)$stmt->get_result()->fetch_assoc()['c'];
        if($studentCount<=0 || $gradeCount!==$studentCount) throw new Exception(t('all_student_grades_required'));
        $u=db()->prepare("UPDATE academic_exams SET status='submitted',submitted_at=NOW(),review_note=NULL,reviewed_by_admin_id=NULL,reviewed_at=NULL,published_at=NULL,updated_at=NOW() WHERE id=? AND status IN ('draft','returned')");
        $u->bind_param('i',$examId); $u->execute(); if($u->affected_rows!==1) throw new Exception(t('grade_exam_locked'));
        $rid=(int)$ctx['registry_id'];
        $a=db()->prepare("INSERT INTO academic_grade_audit(exam_id,actor_type,actor_registry_id,action,created_at) VALUES(?,'teacher',?,'submit',NOW())"); $a->bind_param('ii',$examId,$rid); $a->execute();
    }

    public function submittedExams(){
        (new AdminController())->requireDepartmentAccess(['academic_affairs']);
        return db()->query("SELECT e.*,ag.stage,ag.group_code,ag.academic_year,o.label AS department_label,r.first_name,r.middle_name,r.last_name,
            (SELECT COUNT(*) FROM academic_exam_grades g WHERE g.exam_id=e.id) AS grade_count
            FROM academic_exams e JOIN academic_groups ag ON ag.id=e.group_id JOIN institution_options o ON o.id=ag.department_option_id
            JOIN institution_registry r ON r.id=e.teacher_registry_id
            WHERE e.status IN ('submitted','published','returned') ORDER BY (e.status='submitted') DESC,e.submitted_at DESC,e.created_at DESC");
    }

    public function reviewExam($examId,$scores,$adminNotes,$reviewNote,$publish,$admin){
        if(!(new AdminController())->canAccessDepartment($admin,['academic_affairs'])) throw new Exception(t('not_allowed'));
        $examId=(int)$examId;
        if(!is_array($scores)) throw new Exception(t('invalid_grade'));
        $conn=db(); $conn->begin_transaction();
        try{
            $lock=$conn->prepare("SELECT status FROM academic_exams WHERE id=? LIMIT 1 FOR UPDATE");
            $lock->bind_param('i',$examId); $lock->execute(); $locked=$lock->get_result()->fetch_assoc();
            if(!$locked || $locked['status']!=='submitted') throw new Exception(t('grade_exam_locked'));
            $exam=$this->getExam($examId); if(!$exam) throw new Exception(t('grade_exam_locked'));
            $grades=$this->gradesForExam($examId)->fetch_all(MYSQLI_ASSOC); $byId=[]; foreach($grades as $g) $byId[(int)$g['id']]=$g;
            foreach($scores as $gradeId=>$raw){
                $gradeId=(int)$gradeId; if(!isset($byId[$gradeId])) throw new Exception(t('not_allowed'));
                $score=$this->normalizeScore($raw,$exam['max_score']); $old=(float)$byId[$gradeId]['final_score']; $note=mb_substr(trim((string)($adminNotes[$gradeId]??'')),0,500);
                $u=$conn->prepare("UPDATE academic_exam_grades SET final_score=?,admin_note=?,updated_at=NOW() WHERE id=? AND exam_id=?"); $u->bind_param('dsii',$score,$note,$gradeId,$examId); $u->execute();
                if(abs($old-$score)>0.0001 || $note!==''){
                    $adminId=(int)$admin['id'];
                    $a=$conn->prepare("INSERT INTO academic_grade_audit(exam_id,grade_id,actor_type,actor_admin_id,action,old_score,new_score,note,created_at) VALUES(?,?,'admin',?,'review_edit',?,?,?,NOW())");
                    $a->bind_param('iiidds',$examId,$gradeId,$adminId,$old,$score,$note); $a->execute();
                }
            }
            $reviewNote=mb_substr(trim((string)$reviewNote),0,1000); $adminId=(int)$admin['id'];
            if($publish){
                $u=$conn->prepare("UPDATE academic_exams SET status='published',review_note=?,reviewed_by_admin_id=?,reviewed_at=NOW(),published_at=NOW(),updated_at=NOW() WHERE id=? AND status='submitted'");
                $u->bind_param('sii',$reviewNote,$adminId,$examId); $u->execute();
                if($u->affected_rows!==1) throw new Exception(t('grade_exam_locked'));
                $a=$conn->prepare("INSERT INTO academic_grade_audit(exam_id,actor_type,actor_admin_id,action,note,created_at) VALUES(?,'admin',?,'publish',?,NOW())"); $a->bind_param('iis',$examId,$adminId,$reviewNote); $a->execute();
            }else{
                $u=$conn->prepare("UPDATE academic_exams SET status='returned',review_note=?,reviewed_by_admin_id=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=? AND status='submitted'");
                $u->bind_param('sii',$reviewNote,$adminId,$examId); $u->execute();
                if($u->affected_rows!==1) throw new Exception(t('grade_exam_locked'));
                $a=$conn->prepare("INSERT INTO academic_grade_audit(exam_id,actor_type,actor_admin_id,action,note,created_at) VALUES(?,'admin',?,'return',?,NOW())"); $a->bind_param('iis',$examId,$adminId,$reviewNote); $a->execute();
            }
            $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }

    public function publishedForStudent($studentRegistryId){
        $studentRegistryId=(int)$studentRegistryId;
        $stmt=db()->prepare("SELECT e.id AS exam_id,e.title,e.subject,e.exam_date,e.max_score,e.published_at,g.final_score,g.admin_note,
            r.first_name,r.middle_name,r.last_name,ag.academic_year,ag.stage,ag.group_code,o.label AS department_label
            FROM academic_exam_grades g JOIN academic_exams e ON e.id=g.exam_id
            JOIN institution_registry r ON r.id=e.teacher_registry_id
            JOIN academic_groups ag ON ag.id=e.group_id
            JOIN institution_options o ON o.id=ag.department_option_id
            WHERE g.student_registry_id=? AND e.status='published'
            ORDER BY e.exam_date DESC,e.id DESC");
        $stmt->bind_param('i',$studentRegistryId); $stmt->execute(); return $stmt->get_result();
    }
}
