<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';
require_once __DIR__ . '/OrganizationController.php';

class WorkflowController{
    private const LEGACY_TYPES=['staff_requisition','staff_add','staff_remove','resource','procurement','budget','other'];
    private const AUTO_CHANGE_TYPES=[
        'config_option_add','config_option_toggle','academic_department_head','academic_department_rename',
        'organization_department_create','organization_department_toggle','organization_head_assign',
        'registry_create','registry_status'
    ];

    public function allowedTypes(){ return self::LEGACY_TYPES; }

    private function requireHead($admin){
        if(!$admin || ($admin['admin_role']??'')!=='department_head' || (int)($admin['department_id']??0)<=0) throw new Exception(t('not_allowed'));
        if(!(new AdminController())->departmentOperational((int)$admin['department_id'])) throw new Exception(t('not_allowed'));
    }

    private function requireDepartmentActor($admin){
        if(!$admin || ($admin['admin_role']??'')==='super_admin' || (int)($admin['department_id']??0)<=0) throw new Exception(t('not_allowed'));
        if(!(new AdminController())->departmentOperational((int)$admin['department_id'])) throw new Exception(t('not_allowed'));
    }

    private function audit($actorId,$event,$entityId,$details=''){
        $actorId=(int)$actorId; $entityId=(int)$entityId; $details=mb_substr((string)$details,0,2000);
        $stmt=db()->prepare("INSERT INTO administrative_audit_log(actor_admin_id,event_type,entity_type,entity_id,details,created_at) VALUES(? ,?,'department_request',?,?,NOW())");
        $stmt->bind_param('isis',$actorId,$event,$entityId,$details); $stmt->execute();
    }

    private function createRequest($admin,$type,$title,$details,$targetRegistryId=0,$targetAdminUserId=0,$amountValue=null,$payload=null){
        $departmentId=(int)$admin['department_id']; $requester=(int)$admin['id'];
        $title=mb_substr(trim((string)$title),0,190); $details=mb_substr(trim((string)$details),0,3000);
        if($title===''||$details==='') throw new Exception(t('required_fields'));
        $payloadJson=$payload===null?null:json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($payload!==null && $payloadJson===false) throw new Exception(t('invalid_action'));
        $dup=db()->prepare("SELECT id FROM department_requests WHERE department_id=? AND requester_admin_id=? AND request_type=? AND title=? AND status='pending' LIMIT 1");
        $dup->bind_param('iiss',$departmentId,$requester,$type,$title); $dup->execute();
        if($dup->get_result()->num_rows) throw new Exception(t('duplicate_pending_request'));
        $stmt=db()->prepare("INSERT INTO department_requests(department_id,requester_admin_id,request_type,title,details,target_registry_id,target_admin_user_id,amount,payload_json,status,created_at,updated_at) VALUES(?,?,?,?,?,NULLIF(?,0),NULLIF(?,0),?,?,'pending',NOW(),NOW())");
        $stmt->bind_param('iisssiiss',$departmentId,$requester,$type,$title,$details,$targetRegistryId,$targetAdminUserId,$amountValue,$payloadJson);
        $stmt->execute(); $id=(int)$stmt->insert_id;
        $this->audit($requester,'request_submitted',$id,$type.' | '.$title); return $id;
    }

    public function submit($admin,$type,$title,$details,$targetRegistryId=0,$targetAdminUserId=0,$amount=null){
        $this->requireHead($admin); $type=(string)$type;
        if(!in_array($type,self::LEGACY_TYPES,true)) throw new Exception(t('invalid_action'));
        $departmentId=(int)$admin['department_id']; $targetRegistryId=(int)$targetRegistryId; $targetAdminUserId=(int)$targetAdminUserId; $amountValue=null;
        if($amount!==null&&$amount!==''){
            if(!is_numeric($amount)||(float)$amount<=0||(float)$amount>999999999999.99) throw new Exception(t('invalid_amount'));
            $amountValue=number_format((float)$amount,2,'.','');
        }
        if($type==='staff_add'){
            if($targetRegistryId<=0) throw new Exception(t('staff_member_not_found'));
            $stmt=db()->prepare("SELECT r.id,u.unique_id,a.id AS admin_id,a.department_id,a.admin_role,a.is_active FROM institution_registry r LEFT JOIN users u ON u.registry_id=r.id LEFT JOIN admin_users a ON a.registry_id=r.id WHERE r.id=? AND r.person_type='staff' AND r.status<>'suspended' LIMIT 1");
            $stmt->bind_param('i',$targetRegistryId); $stmt->execute(); $staff=$stmt->get_result()->fetch_assoc();
            if(!$staff) throw new Exception(t('staff_member_not_found')); if(empty($staff['unique_id'])) throw new Exception(t('staff_platform_account_required'));
            if(!empty($staff['admin_id'])&&(int)$staff['is_active']===1){ if((int)$staff['department_id']===$departmentId) throw new Exception(t('staff_already_in_department')); throw new Exception(t('staff_already_other_department')); }
            $targetAdminUserId=0; $amountValue=null;
        }elseif($type==='staff_remove'){
            if($targetAdminUserId<=0) throw new Exception(t('invalid_action'));
            $stmt=db()->prepare("SELECT id,admin_role,department_id,is_active FROM admin_users WHERE id=? LIMIT 1"); $stmt->bind_param('i',$targetAdminUserId); $stmt->execute(); $member=$stmt->get_result()->fetch_assoc();
            if(!$member||!(int)$member['is_active']||(int)$member['department_id']!==$departmentId||$member['admin_role']!=='department_staff') throw new Exception(t('not_allowed'));
            $targetRegistryId=0; $amountValue=null;
        }else{
            $targetRegistryId=0; $targetAdminUserId=0;
            if(in_array($type,['procurement','budget'],true)){ if($amountValue===null) throw new Exception(t('amount_required')); } else $amountValue=null;
        }
        return $this->createRequest($admin,$type,$title,$details,$targetRegistryId,$targetAdminUserId,$amountValue,null);
    }

    public function submitChange($admin,$type,$title,$details,$payload){
        $this->requireDepartmentActor($admin); $type=(string)$type;
        if(!in_array($type,self::AUTO_CHANGE_TYPES,true)) throw new Exception(t('invalid_action'));
        if(!is_array($payload)) throw new Exception(t('invalid_action'));
        return $this->createRequest($admin,$type,$title,$details,0,0,null,$payload);
    }

    public function listForAdmin($admin){
        if(!$admin) throw new Exception(t('not_allowed'));
        $where='';
        if(($admin['admin_role']??'')!=='super_admin'){
            if((int)($admin['department_id']??0)<=0) throw new Exception(t('not_allowed'));
            $where='WHERE dr.department_id=?';
        }
        $sql="SELECT dr.*,d.name AS department_name,d.code AS department_code,req.full_name AS requester_name,rev.full_name AS reviewer_name,
            CONCAT_WS(' ',tr.first_name,tr.middle_name,tr.last_name) AS target_registry_name,tr.issued_id AS target_registry_issued,ta.full_name AS target_admin_name
            FROM department_requests dr JOIN institution_departments d ON d.id=dr.department_id JOIN admin_users req ON req.id=dr.requester_admin_id
            LEFT JOIN admin_users rev ON rev.id=dr.reviewed_by_admin_id LEFT JOIN institution_registry tr ON tr.id=dr.target_registry_id LEFT JOIN admin_users ta ON ta.id=dr.target_admin_user_id
            $where ORDER BY (dr.status='pending') DESC,dr.created_at DESC,dr.id DESC";
        if($where==='') return db()->query($sql);
        $stmt=db()->prepare($sql); $dept=(int)$admin['department_id']; $stmt->bind_param('i',$dept); $stmt->execute(); return $stmt->get_result();
    }

    public function pendingCount($admin){
        if(($admin['admin_role']??'')==='super_admin'){ $r=db()->query("SELECT COUNT(*) c FROM department_requests WHERE status='pending'"); return $r?(int)$r->fetch_assoc()['c']:0; }
        $dept=(int)($admin['department_id']??0); if($dept<=0) return 0;
        $stmt=db()->prepare("SELECT COUNT(*) c FROM department_requests WHERE department_id=? AND status='pending'"); $stmt->bind_param('i',$dept); $stmt->execute(); return (int)$stmt->get_result()->fetch_assoc()['c'];
    }

    public function cancel($requestId,$admin){
        $this->requireDepartmentActor($admin); $requestId=(int)$requestId; $dept=(int)$admin['department_id']; $actor=(int)$admin['id'];
        $stmt=db()->prepare("UPDATE department_requests SET status='cancelled',updated_at=NOW() WHERE id=? AND department_id=? AND requester_admin_id=? AND status='pending'");
        $stmt->bind_param('iii',$requestId,$dept,$actor); $stmt->execute(); if($stmt->affected_rows!==1) throw new Exception(t('request_not_pending')); $this->audit($actor,'request_cancelled',$requestId);
    }

    private function executeAutomaticChange($request,$manager){
        $payload=json_decode((string)($request['payload_json']??''),true); if(!is_array($payload)) throw new Exception(t('invalid_action'));
        $type=(string)$request['request_type']; $managerId=(int)$manager['id'];
        if($type==='config_option_add'){
            require_once __DIR__.'/AcademicController.php'; $academic=new AcademicController();
            $optionType=(string)($payload['option_type']??'');
            $head=(int)($payload['head_teacher_registry_id']??0);
            // Adding a new study department and assigning its head is one unit of work.
            // If head assignment fails, do not leave an orphaned approved-looking department.
            if($optionType==='student_department' && $head>0){
                $conn=db();$conn->begin_transaction();
                try{
                    $id=$academic->addOption($optionType,(string)($payload['label']??''),$managerId);
                    $academic->setAcademicDepartmentHead($id,$head);
                    $conn->commit();
                }catch(Throwable $e){$conn->rollback();throw $e;}
            }else{ $academic->addOption($optionType,(string)($payload['label']??''),$managerId); }
        }elseif($type==='config_option_toggle'){
            require_once __DIR__.'/AcademicController.php'; (new AcademicController())->toggleOption((int)($payload['option_id']??0));
        }elseif($type==='academic_department_head'){
            require_once __DIR__.'/AcademicController.php'; (new AcademicController())->setAcademicDepartmentHead((int)($payload['option_id']??0),(int)($payload['head_teacher_registry_id']??0));
        }elseif($type==='academic_department_rename'){
            require_once __DIR__.'/AcademicController.php'; (new AcademicController())->renameStudyDepartment((int)($payload['option_id']??0),(string)($payload['label']??''));
        }elseif($type==='organization_department_create'){
            (new OrganizationController())->createDepartment((string)($payload['name']??''),(string)($payload['code']??''),(string)($payload['department_type']??'other'),(int)($payload['parent_id']??1),$managerId,(string)($payload['responsibilities']??''));
        }elseif($type==='organization_department_toggle'){
            (new OrganizationController())->toggleDepartment((int)($payload['department_id']??0));
        }elseif($type==='organization_head_assign'){
            (new OrganizationController())->assignHead((int)($payload['department_id']??0),(int)($payload['registry_id']??0),$managerId);
        }elseif($type==='registry_create'){
            require_once __DIR__.'/InstitutionController.php';
            $requesterStmt=db()->prepare("SELECT a.*,d.code AS department_code,d.name AS department_name,d.department_type FROM admin_users a LEFT JOIN institution_departments d ON d.id=a.department_id WHERE a.id=? LIMIT 1");
            $requesterId=(int)$request['requester_admin_id']; $requesterStmt->bind_param('i',$requesterId); $requesterStmt->execute(); $requester=$requesterStmt->get_result()->fetch_assoc();
            if(!$requester || !(int)($requester['is_active']??0) || !(new AdminController())->departmentOperational((int)($requester['department_id']??0))) throw new Exception(t('not_allowed'));
            (new InstitutionController())->createRecordFromData($requester,$payload,$requesterId);
        }elseif($type==='registry_status'){
            require_once __DIR__.'/InstitutionController.php'; (new InstitutionController())->toggleSuspensionAs((int)($payload['registry_id']??0),$manager);
        }else throw new Exception(t('invalid_action'));
    }

    public function review($requestId,$approve,$note,$manager){
        if(($manager['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
        $requestId=(int)$requestId; $note=mb_substr(trim((string)$note),0,1000); $managerId=(int)$manager['id']; if(!$approve&&$note==='') throw new Exception(t('rejection_note_required'));
        $conn=db(); $conn->begin_transaction(); $inTransaction=true;
        try{
            $lock=$conn->prepare("SELECT * FROM department_requests WHERE id=? LIMIT 1 FOR UPDATE"); $lock->bind_param('i',$requestId); $lock->execute(); $request=$lock->get_result()->fetch_assoc();
            if(!$request||$request['status']!=='pending') throw new Exception(t('request_not_pending'));
            if(!$approve){
                $u=$conn->prepare("UPDATE department_requests SET status='rejected',reviewed_by_admin_id=?,reviewed_at=NOW(),review_note=?,updated_at=NOW() WHERE id=? AND status='pending'"); $u->bind_param('isi',$managerId,$note,$requestId); $u->execute();
                if($u->affected_rows!==1) throw new Exception(t('request_not_pending')); $conn->commit(); $inTransaction=false; $this->audit($managerId,'request_rejected',$requestId,$note); return;
            }
            if(in_array($request['request_type'],self::AUTO_CHANGE_TYPES,true)){
                $u=$conn->prepare("UPDATE department_requests SET status='approved',reviewed_by_admin_id=?,reviewed_at=NOW(),review_note=?,updated_at=NOW() WHERE id=? AND status='pending'"); $u->bind_param('isi',$managerId,$note,$requestId); $u->execute();
                if($u->affected_rows!==1) throw new Exception(t('request_not_pending')); $conn->commit(); $inTransaction=false;
                try{
                    $this->executeAutomaticChange($request,$manager);
                }catch(Throwable $e){
                    $reset=db()->prepare("UPDATE department_requests SET status='pending',reviewed_by_admin_id=NULL,reviewed_at=NULL,review_note=?,updated_at=NOW() WHERE id=? AND status='approved'");
                    $msg=mb_substr(t('execution_failed').': '.$e->getMessage(),0,1000); $reset->bind_param('si',$msg,$requestId); $reset->execute(); throw $e;
                }
                // The side effect has succeeded. Never reset this request to pending after this point,
                // otherwise a retry could execute the same approved change twice.
                $done=db()->prepare("UPDATE department_requests SET status='completed',executed_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved'"); $done->bind_param('i',$requestId); $done->execute();
                if($done->affected_rows!==1) throw new Exception(t('execution_failed'));
                $this->audit($managerId,'request_approved',$requestId,$note); $this->audit($managerId,'request_completed',$requestId,$request['request_type']);
                return;
            }
            if($request['request_type']==='staff_add'){
                $rid=(int)$request['target_registry_id'];
                $check=$conn->prepare("SELECT a.id,a.department_id,a.admin_role,a.is_active,u.unique_id,r.status FROM institution_registry r LEFT JOIN users u ON u.registry_id=r.id LEFT JOIN admin_users a ON a.registry_id=r.id WHERE r.id=? AND r.person_type='staff' LIMIT 1 FOR UPDATE");
                $check->bind_param('i',$rid); $check->execute(); $staff=$check->get_result()->fetch_assoc();
                if(!$staff||($staff['status']??'')==='suspended'||empty($staff['unique_id'])) throw new Exception(t('staff_platform_account_required'));
                if(!empty($staff['id'])&&(int)$staff['is_active']===1&&(int)$staff['department_id']!==(int)$request['department_id']) throw new Exception(t('staff_already_other_department'));
                (new OrganizationController())->addEmployee((int)$request['department_id'],$rid,$manager);
            }elseif($request['request_type']==='staff_remove'){
                $targetId=(int)$request['target_admin_user_id'];
                $check=$conn->prepare("SELECT id,department_id,admin_role,is_active FROM admin_users WHERE id=? LIMIT 1 FOR UPDATE"); $check->bind_param('i',$targetId); $check->execute(); $target=$check->get_result()->fetch_assoc();
                if(!$target||!(int)$target['is_active']||$target['admin_role']!=='department_staff'||(int)$target['department_id']!==(int)$request['department_id']) throw new Exception(t('request_target_changed'));
                (new OrganizationController())->removeEmployee($targetId,$manager);
            }
            $u=$conn->prepare("UPDATE department_requests SET status=IF(request_type IN ('staff_add','staff_remove'),'completed','approved'),reviewed_by_admin_id=?,reviewed_at=NOW(),review_note=?,executed_at=IF(request_type IN ('staff_add','staff_remove'),NOW(),executed_at),updated_at=NOW() WHERE id=? AND status='pending'");
            $u->bind_param('isi',$managerId,$note,$requestId); $u->execute(); if($u->affected_rows!==1) throw new Exception(t('request_not_pending'));
            $conn->commit(); $inTransaction=false; $this->audit($managerId,'request_approved',$requestId,$note);
        }catch(Throwable $e){ if($inTransaction){ try{$conn->rollback();}catch(Throwable $_){} } throw $e; }
    }

    public function complete($requestId,$manager){
        if(($manager['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
        $requestId=(int)$requestId; $managerId=(int)$manager['id'];
        $stmt=db()->prepare("UPDATE department_requests SET status='completed',executed_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved' AND request_type IN ('staff_requisition','resource','other')");
        $stmt->bind_param('i',$requestId); $stmt->execute(); if($stmt->affected_rows!==1) throw new Exception(t('request_not_completable')); $this->audit($managerId,'request_completed',$requestId);
    }
}
