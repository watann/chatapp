<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';
require_once __DIR__ . '/AcademicController.php';

class InstitutionController{

    public function allowedRecordTypes($admin){
        if(($admin['admin_role'] ?? '')==='super_admin' || ($admin['department_type'] ?? '')==='administration' || ($admin['department_code'] ?? '')==='registry') return ['student','teacher','staff'];
        if(($admin['department_type'] ?? '')==='student_affairs') return ['student'];
        if(($admin['department_type'] ?? '')==='human_resources') return ['teacher','staff'];
        return [];
    }

    private function validateDate($value){
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value && $value <= date('Y-m-d');
    }

    private function makeIssuedId($rowId, $type){
        $prefix = ['student'=>'STD','teacher'=>'TCH','staff'=>'STF'][$type] ?? 'USR';
        return 'RUN-' . date('Y') . '-' . $prefix . '-' . str_pad((string)$rowId, 6, '0', STR_PAD_LEFT);
    }

    public function createRecordFromData($admin,$data,$createdByAdminId=null,$externalTransaction=false){
        if(!$admin || !in_array(($admin['admin_role']??''),['super_admin','department_head','department_staff'],true)) throw new Exception(t('not_allowed'));
        $first=trim((string)($data['first_name']??''));
        $middle=trim((string)($data['middle_name']??''));
        $last=trim((string)($data['last_name']??''));
        $birth=trim((string)($data['birth_date']??''));
        $gender=(string)($data['gender']??'');
        $type=(string)($data['person_type']??'');
        $identity=trim((string)($data['identity_number']??''));
        if($first===''||$middle===''||$last===''||$birth===''||$gender===''||$type===''||$identity==='') throw new Exception(t('required_fields'));
        if(mb_strlen($first)>100||mb_strlen($middle)>100||mb_strlen($last)>100||mb_strlen($identity)>100) throw new Exception(t('invalid_action'));
        if(!$this->validateDate($birth)) throw new Exception(t('invalid_birth_date'));
        if(!in_array($gender,supported_genders(),true)||!in_array($type,supported_roles(),true)) throw new Exception(t('invalid_action'));
        if(($admin['admin_role']??'')!=='super_admin' && !in_array($type,$this->allowedRecordTypes($admin),true)) throw new Exception(t('not_allowed'));

        $academic=new AcademicController();
        $department=$workplace=$teacherCertificate=$teacherSpec=null;
        $departmentOptionId=$workplaceOptionId=$teacherCertificateOptionId=$teacherOptionId=null;
        $stage=$academicStatus=$academicGroupId=null;
        if($type==='student'){
            $departmentOptionId=(int)($data['student_department_option_id']??0);
            $option=$academic->getOption($departmentOptionId,'student_department',true);
            if(!$option) throw new Exception(t('student_department_required'));
            $department=$option['label'];
            $stage=(int)($data['student_stage']??1);
            $groupCode=trim((string)($data['student_group_code']??''));
            $academicYear=trim((string)($data['academic_year']??''));
            if($stage<1||$stage>4) throw new Exception(t('invalid_stage'));
            if($groupCode==='') throw new Exception(t('group_code_required'));
            $academicStatus='active';
        }elseif($type==='staff'){
            $workplaceOptionId=(int)($data['staff_workplace_option_id']??0);
            $option=$academic->getOption($workplaceOptionId,'staff_workplace',true);
            if(!$option) throw new Exception(t('staff_workplace_required'));
            $workplace=$option['label'];
        }elseif($type==='teacher'){
            $teacherCertificateOptionId=(int)($data['teacher_certificate_option_id']??0);
            $certificate=$academic->getOption($teacherCertificateOptionId,'teacher_certificate',true);
            if(!$certificate) throw new Exception(t('teacher_certificate_required'));
            $teacherCertificate=$certificate['label'];
            $teacherOptionId=(int)($data['teacher_specialization_option_id']??0);
            $option=$academic->getOption($teacherOptionId,'teacher_specialization',true);
            if(!$option) throw new Exception(t('teacher_specialization_required'));
            $teacherSpec=$option['label'];
        }
        $check=db()->prepare("SELECT id FROM institution_registry WHERE identity_number=? LIMIT 1");
        $check->bind_param('s',$identity); $check->execute();
        if($check->get_result()->num_rows) throw new Exception(t('identity_number_used'));

        $conn=db(); if(!$externalTransaction) $conn->begin_transaction();
        try{
            $adminId=$createdByAdminId!==null?(int)$createdByAdminId:(int)$admin['id'];
            if($type==='student') $academicGroupId=$academic->ensureGroup($departmentOptionId,$stage,$groupCode,$academicYear,$adminId);
            $stmt=$conn->prepare("INSERT INTO institution_registry
                (issued_id,first_name,middle_name,last_name,birth_date,gender,person_type,
                 student_department,staff_workplace,teacher_certificate,teacher_specialization,
                 student_department_option_id,staff_workplace_option_id,teacher_certificate_option_id,teacher_specialization_option_id,
                 student_stage,student_academic_status,academic_group_id,identity_number,status,created_by_admin_id,created_at,updated_at)
                VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'available',?,NOW(),NOW())");
            $stmt->bind_param('ssssssssssiiiiisisi',$first,$middle,$last,$birth,$gender,$type,$department,$workplace,$teacherCertificate,$teacherSpec,$departmentOptionId,$workplaceOptionId,$teacherCertificateOptionId,$teacherOptionId,$stage,$academicStatus,$academicGroupId,$identity,$adminId);
            $stmt->execute(); $rowId=(int)$stmt->insert_id;
            $issued=$this->makeIssuedId($rowId,$type);
            $u=$conn->prepare("UPDATE institution_registry SET issued_id=? WHERE id=?"); $u->bind_param('si',$issued,$rowId); $u->execute();
            if(!$externalTransaction) $conn->commit();
            return ['id'=>$rowId,'issued_id'=>$issued];
        }catch(Throwable $e){ if(!$externalTransaction) $conn->rollback(); throw $e; }
    }

    public function createRecord(){
        (new AdminController())->requireAdmin();
        verify_csrf();
        $_SESSION['admin_flash_error']='New official registrations must go through a registry list and both approval stages.';
        header('Location: ../registry-batches.php'); exit;
    }

    public function listRecords(){
        $admin=(new AdminController())->requireDepartmentAccess(['administration','human_resources','student_affairs','registry']);
        $allowed=$this->allowedRecordTypes($admin);
        if(!$allowed) throw new Exception(t('not_allowed'));
        $typeFilter=count($allowed)===3 ? '' : (count($allowed)===1 ? " WHERE r.person_type='".$allowed[0]."' " : " WHERE r.person_type IN ('teacher','staff') ");
        $sql = "SELECT r.*,a.full_name AS admin_name,u.unique_id AS linked_user_id,u.email AS linked_email,
                COALESCE(dep.label,r.student_department) AS department_label,
                COALESCE(wp.label,r.staff_workplace) AS workplace_label,
                COALESCE(cert.label,r.teacher_certificate) AS certificate_label,
                COALESCE(sp.label,r.teacher_specialization) AS specialization_label,
                g.group_code,g.academic_year,g.stage AS group_stage
                FROM institution_registry r
                JOIN admin_users a ON a.id=r.created_by_admin_id
                LEFT JOIN users u ON u.registry_id=r.id
                LEFT JOIN institution_options dep ON dep.id=r.student_department_option_id
                LEFT JOIN institution_options wp ON wp.id=r.staff_workplace_option_id
                LEFT JOIN institution_options cert ON cert.id=r.teacher_certificate_option_id
                LEFT JOIN institution_options sp ON sp.id=r.teacher_specialization_option_id
                LEFT JOIN academic_groups g ON g.id=r.academic_group_id
                " . $typeFilter . " ORDER BY r.created_at DESC,r.id DESC";
        return db()->query($sql);
    }

    public function toggleSuspensionAs($registryId,$admin){
        $registryId=(int)$registryId;
        if($registryId<=0) throw new Exception(t('invalid_action'));
        if(!empty($admin['registry_id']) && (int)$admin['registry_id']===$registryId) throw new Exception(t('not_allowed'));
        $conn=db(); $conn->begin_transaction();
        try{
            $stmt=$conn->prepare("SELECT r.id,r.person_type,r.status,u.unique_id FROM institution_registry r LEFT JOIN users u ON u.registry_id=r.id WHERE r.id=? LIMIT 1 FOR UPDATE");
            $stmt->bind_param('i',$registryId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
            if(!$row) throw new Exception(t('not_allowed'));
            if(($admin['admin_role']??'')!=='super_admin' && !in_array($row['person_type'],$this->allowedRecordTypes($admin),true)) throw new Exception(t('not_allowed'));
            $newStatus=$row['status']==='suspended'?(!empty($row['unique_id'])?'registered':'available'):'suspended';
            $u=$conn->prepare("UPDATE institution_registry SET status=?,updated_at=NOW() WHERE id=?"); $u->bind_param('si',$newStatus,$registryId); $u->execute();
            if($newStatus==='suspended'&&!empty($row['unique_id'])){
                $uid=(int)$row['unique_id']; $offline='Offline now';
                $p=$conn->prepare("UPDATE users SET status=?,last_seen_at=NOW() WHERE unique_id=?"); $p->bind_param('si',$offline,$uid); $p->execute();
            }
            $conn->commit(); return $newStatus;
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }

    public function toggleSuspension($registryId){
        $admin=(new AdminController())->requireDepartmentAccess(['administration','human_resources','student_affairs'],true);
        return $this->toggleSuspensionAs($registryId,$admin);
    }

    public function findRegistrationRecord($issuedId, $identityNumber, $birthDate, $forUpdate=false){
        $sql = "SELECT * FROM institution_registry WHERE issued_id=? AND identity_number=? AND birth_date=? LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
        $stmt = db()->prepare($sql);
        $stmt->bind_param('sss', $issuedId, $identityNumber, $birthDate);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
