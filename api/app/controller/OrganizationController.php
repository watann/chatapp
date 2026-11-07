<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';

class OrganizationController{
    private function adminController(){ return new AdminController(); }

    private function audit($actorId,$event,$entityType,$entityId,$details=''){
        $actorId=(int)$actorId; $entityId=(int)$entityId; $event=mb_substr((string)$event,0,80); $entityType=mb_substr((string)$entityType,0,80); $details=mb_substr((string)$details,0,2000);
        $stmt=db()->prepare("INSERT INTO administrative_audit_log(actor_admin_id,event_type,entity_type,entity_id,details,created_at) VALUES(?,?,?,?,?,NOW())");
        $stmt->bind_param('issis',$actorId,$event,$entityType,$entityId,$details); $stmt->execute();
    }

    public function departments($activeOnly=false){
        $sql="SELECT d.*,
            h.id AS head_admin_id,h.full_name AS head_name,h.registry_id AS head_registry_id,
            (SELECT COUNT(*) FROM admin_users m WHERE m.department_id=d.id AND m.is_active=1 AND m.admin_role IN ('department_head','department_staff')) AS member_count
            FROM institution_departments d
            LEFT JOIN admin_users h ON h.department_id=d.id AND h.admin_role='department_head' AND h.is_active=1
            " . ($activeOnly ? "WHERE d.is_active=1 " : "") . "ORDER BY COALESCE(d.parent_id,0),d.sort_order,d.name,d.id";
        return db()->query($sql);
    }

    public function department($id){
        $id=(int)$id;
        $stmt=db()->prepare("SELECT * FROM institution_departments WHERE id=? LIMIT 1");
        $stmt->bind_param('i',$id); $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function staffCandidates($unassignedOnly=false){
        $unassignedOnly=(bool)$unassignedOnly;
        $sql="SELECT r.id,r.issued_id,r.first_name,r.middle_name,r.last_name,r.status,
            COALESCE(wp.label,r.staff_workplace) AS workplace_label,u.unique_id,u.email,
            a.id AS admin_id,a.admin_role,a.department_id,d.name AS department_name
            FROM institution_registry r
            LEFT JOIN institution_options wp ON wp.id=r.staff_workplace_option_id
            LEFT JOIN users u ON u.registry_id=r.id
            LEFT JOIN admin_users a ON a.registry_id=r.id AND a.is_active=1
            LEFT JOIN institution_departments d ON d.id=a.department_id
            WHERE r.person_type='staff' AND r.status<>'suspended' AND u.unique_id IS NOT NULL" . ($unassignedOnly ? " AND a.id IS NULL" : "") . "
            ORDER BY r.first_name,r.middle_name,r.last_name";
        return db()->query($sql);
    }

    public function members($departmentId){
        $departmentId=(int)$departmentId;
        $stmt=db()->prepare("SELECT a.*,COALESCE(u.email,a.email) AS login_email,r.issued_id,r.status AS registry_status
            FROM admin_users a
            LEFT JOIN users u ON u.registry_id=a.registry_id
            LEFT JOIN institution_registry r ON r.id=a.registry_id
            WHERE a.department_id=? AND a.is_active=1 AND a.admin_role IN ('department_head','department_staff')
            ORDER BY (a.admin_role='department_head') DESC,a.full_name");
        $stmt->bind_param('i',$departmentId); $stmt->execute();
        return $stmt->get_result();
    }

    private function validateStaffRegistry($registryId){
        $registryId=(int)$registryId;
        $stmt=db()->prepare("SELECT r.*,u.unique_id,u.email,u.password FROM institution_registry r LEFT JOIN users u ON u.registry_id=r.id WHERE r.id=? AND r.person_type='staff' AND r.status<>'suspended' LIMIT 1");
        $stmt->bind_param('i',$registryId); $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc();
        if(!$row) throw new Exception(t('staff_member_not_found'));
        if(empty($row['unique_id']) || empty($row['email']) || empty($row['password'])) throw new Exception(t('staff_platform_account_required'));
        return $row;
    }

    private function ensureAdminForStaff($staff,$departmentId,$role,$creatorId){
        $registryId=(int)$staff['id'];
        $name=display_name($staff);
        $email=!empty($staff['email']) ? strtolower((string)$staff['email']) : strtolower((string)$staff['issued_id']).'@pending.local';
        // Linked department users authenticate with their normal platform password; this value is only a non-empty fallback.
        $fallbackHash=password_hash(bin2hex(random_bytes(18)),PASSWORD_DEFAULT);
        $check=db()->prepare("SELECT id,admin_role,department_id FROM admin_users WHERE registry_id=? LIMIT 1");
        $check->bind_param('i',$registryId); $check->execute(); $existing=$check->get_result()->fetch_assoc();
        if($existing){
            $id=(int)$existing['id'];
            $stmt=db()->prepare("UPDATE admin_users SET full_name=?,email=?,admin_role=?,department_id=?,is_active=1,created_by=?,updated_at=NOW() WHERE id=?");
            $stmt->bind_param('sssiii',$name,$email,$role,$departmentId,$creatorId,$id); $stmt->execute();
            return $id;
        }
        $stmt=db()->prepare("INSERT INTO admin_users(full_name,email,password,admin_role,department_id,registry_id,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,NOW(),NOW())");
        $stmt->bind_param('ssssiii',$name,$email,$fallbackHash,$role,$departmentId,$registryId,$creatorId); $stmt->execute();
        return (int)$stmt->insert_id;
    }

    public function createDepartment($name,$code,$type,$parentId,$adminId,$responsibilities=''){
        $this->adminController()->requireSuperAdmin();
        $name=trim((string)$name); $code=strtolower(trim((string)$code)); $type=(string)$type; $parentId=(int)$parentId; $adminId=(int)$adminId; $responsibilities=mb_substr(trim((string)$responsibilities),0,2000);
        $allowed=['academic_affairs','finance','administration','student_affairs','human_resources','it','other'];
        if($name==='' || mb_strlen($name)>190 || !preg_match('/^[a-z0-9_-]{2,60}$/',$code) || !in_array($type,$allowed,true)) throw new Exception(t('invalid_action'));
        if($parentId<=0) $parentId=1;
        $parent=$this->department($parentId);
        if(!$parent || !(int)$parent['is_active'] || !$this->adminController()->departmentOperational($parentId)) throw new Exception(t('invalid_action'));
        $stmt=db()->prepare("INSERT INTO institution_departments(parent_id,code,name,department_type,responsibilities,is_active,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,1,100,NOW(),NOW())");
        $stmt->bind_param('issss',$parentId,$code,$name,$type,$responsibilities); $stmt->execute();
        $this->audit($adminId,'department_created','institution_department',(int)$stmt->insert_id,$code.' | '.$name);
    }

    public function assignHead($departmentId,$registryId,$creatorId){
        $this->adminController()->requireSuperAdmin();
        $departmentId=(int)$departmentId; $creatorId=(int)$creatorId;
        $dept=$this->department($departmentId);
        if(!$dept || $dept['department_type']==='root' || !$this->adminController()->departmentOperational($departmentId)) throw new Exception(t('invalid_action'));
        $staff=$this->validateStaffRegistry($registryId);
        $rid=(int)$registryId;
        $existing=db()->prepare("SELECT id,admin_role,department_id,is_active FROM admin_users WHERE registry_id=? LIMIT 1");
        $existing->bind_param('i',$rid); $existing->execute(); $assigned=$existing->get_result()->fetch_assoc();
        if($assigned && (int)$assigned['is_active']===1 && !empty($assigned['department_id']) && (int)$assigned['department_id']!==$departmentId){
            throw new Exception(t('staff_already_other_department'));
        }
        $conn=db(); $conn->begin_transaction();
        try{
            $demote=$conn->prepare("UPDATE admin_users SET admin_role='department_staff',updated_at=NOW() WHERE department_id=? AND admin_role='department_head' AND is_active=1 AND registry_id<>?");
            $demote->bind_param('ii',$departmentId,$rid); $demote->execute();
            $headAdminId=$this->ensureAdminForStaff($staff,$departmentId,'department_head',$creatorId);
            $this->audit($creatorId,'department_head_assigned','institution_department',$departmentId,'admin_user #'.$headAdminId.' | '.display_name($staff));
            $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }

    public function addEmployee($departmentId,$registryId,$creator){
        $departmentId=(int)$departmentId; $registryId=(int)$registryId;
        $adminController=$this->adminController();
        if(($creator['admin_role']??'')!=='super_admin') throw new Exception(t('manager_approval_required'));
        $dept=$this->department($departmentId);
        if(!$dept || $dept['department_type']==='root' || !$this->adminController()->departmentOperational($departmentId)) throw new Exception(t('invalid_action'));
        $staff=$this->validateStaffRegistry($registryId);
        $existing=db()->prepare("SELECT admin_role,department_id FROM admin_users WHERE registry_id=? AND is_active=1 LIMIT 1");
        $existing->bind_param('i',$registryId); $existing->execute(); $row=$existing->get_result()->fetch_assoc();
        if($row && $row['admin_role']==='department_head') throw new Exception(t('staff_already_department_head'));
        if($row && (int)$row['department_id']!==$departmentId) throw new Exception(t('staff_already_other_department'));
        if($row && (int)$row['department_id']===$departmentId) throw new Exception('ئەم فەرمانبەرە پێشتر لەم بەشە کار دەکات.');
        $staffAdminId=$this->ensureAdminForStaff($staff,$departmentId,'department_staff',(int)$creator['id']);
        $this->audit((int)$creator['id'],'department_staff_added','institution_department',$departmentId,'admin_user #'.$staffAdminId.' | '.display_name($staff));
    }

    public function removeEmployee($adminUserId,$actor){
        $adminUserId=(int)$adminUserId;
        $stmt=db()->prepare("SELECT * FROM admin_users WHERE id=? LIMIT 1"); $stmt->bind_param('i',$adminUserId); $stmt->execute(); $target=$stmt->get_result()->fetch_assoc();
        if(!$target || !(int)$target['is_active'] || $target['admin_role']==='super_admin') throw new Exception(t('not_allowed'));
        if(($actor['admin_role']??'')!=='super_admin') throw new Exception(t('manager_approval_required'));
        $u=db()->prepare("UPDATE admin_users SET is_active=0,updated_at=NOW() WHERE id=?"); $u->bind_param('i',$adminUserId); $u->execute();
        if($u->affected_rows!==1) throw new Exception(t('invalid_action'));
        $this->audit((int)$actor['id'],'department_staff_removed','admin_user',$adminUserId,(string)($target['full_name']??''));
    }

    public function toggleDepartment($departmentId){
        $this->adminController()->requireSuperAdmin();
        $departmentId=(int)$departmentId; $dept=$this->department($departmentId);
        if(!$dept || $dept['department_type']==='root') throw new Exception(t('invalid_action'));
        $active=(int)$dept['is_active']?0:1;
        $stmt=db()->prepare("UPDATE institution_departments SET is_active=?,updated_at=NOW() WHERE id=?"); $stmt->bind_param('ii',$active,$departmentId); $stmt->execute();
        $actor=$this->adminController()->currentAdmin();
        $this->audit((int)($actor['id']??0),$active?'department_activated':'department_deactivated','institution_department',$departmentId,(string)$dept['code']);
        // Keep staff assignments intact. Access is automatically denied while the department itself is inactive.
    }
}
