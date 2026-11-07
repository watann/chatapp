<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';
require_once __DIR__ . '/InstitutionController.php';
require_once __DIR__ . '/AcademicController.php';

/**
 * Official registry lists: registry staff -> Administration -> Institution Manager.
 * No institution_registry rows or issued identifiers are created before final approval.
 * The tables are installed by DATABASE_MIGRATION_2026_11_04.sql.
 */
class RegistryBatchController {
    private function isManager($a){ return ($a['admin_role']??'')==='super_admin'; }
    private function isAdministrationHead($a){ return ($a['admin_role']??'')==='department_head' && ($a['department_type']??'')==='administration'; }
    public function canDraft($a){
        if(!$a || $this->isManager($a) || $this->isAdministrationHead($a)) return false;
        return in_array(($a['admin_role']??''),['department_staff','department_head'],true)
            && (in_array(($a['department_type']??''),['student_affairs','human_resources','administration'],true) || ($a['department_code']??'')==='registry');
    }
    public function canReviewAdministration($a){ return $this->isAdministrationHead($a); }
    public function canWorkAdministration($a){ return $a && ($a['department_type']??'')==='administration' && in_array(($a['admin_role']??''),['department_head','department_staff'],true); }
    public function canAccess($a){ return $this->isManager($a) || $this->canWorkAdministration($a) || $this->canDraft($a); }
    private function assertAccess($a){ if(!$this->canAccess($a)) throw new Exception(t('not_allowed')); }
    private function checkType($a,$type){
        if(!in_array($type,['student','teacher','staff'],true)) throw new Exception(t('invalid_action'));
        $allowed=(new InstitutionController())->allowedRecordTypes($a);
        if(!in_array($type,$allowed,true)) throw new Exception(t('not_allowed'));
    }
    public function listBatches($admin){
        $this->assertAccess($admin);
        $sql="SELECT b.*,c.full_name AS creator_name,ar.full_name AS admin_reviewer_name,m.full_name AS manager_reviewer_name,
                 (SELECT COUNT(*) FROM registry_batch_items i WHERE i.batch_id=b.id) AS item_count
              FROM registry_batches b JOIN admin_users c ON c.id=b.created_by_admin_id
              LEFT JOIN admin_users ar ON ar.id=b.administration_reviewed_by
              LEFT JOIN admin_users m ON m.id=b.manager_reviewed_by";
        $order=" ORDER BY FIELD(b.status,'manager_review','administration_review','returned_administration','returned_registry','draft','approved'),b.id DESC";
        if($this->isManager($admin) || $this->isAdministrationHead($admin)) return db()->query($sql.$order)->fetch_all(MYSQLI_ASSOC);
        if($this->canWorkAdministration($admin)){
            $id=(int)$admin['id']; $q=db()->prepare($sql." WHERE b.status<>'draft' OR b.created_by_admin_id=?".$order);
            $q->bind_param('i',$id); $q->execute(); return $q->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $sql.=' WHERE b.created_by_admin_id=? ORDER BY b.id DESC';
        $id=(int)$admin['id']; $q=db()->prepare($sql); $q->bind_param('i',$id); $q->execute(); return $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    private function getBatch($batchId,$lock=false){
        $sql='SELECT * FROM registry_batches WHERE id=?'.($lock?' FOR UPDATE':'');
        $q=db()->prepare($sql); $q->bind_param('i',$batchId); $q->execute(); return $q->get_result()->fetch_assoc();
    }
    public function batch($batchId,$admin){
        $this->assertAccess($admin); $batch=$this->getBatch((int)$batchId);
        if(!$batch) throw new Exception(t('not_allowed'));
        if(!$this->isManager($admin) && !$this->isAdministrationHead($admin) && (int)$batch['created_by_admin_id']!==(int)$admin['id']){
            if(!$this->canWorkAdministration($admin) || $batch['status']==='draft') throw new Exception(t('not_allowed'));
        }
        return $batch;
    }
    public function items($batchId,$admin){
        $this->batch($batchId,$admin);
        $q=db()->prepare('SELECT * FROM registry_batch_items WHERE batch_id=? ORDER BY id ASC'); $q->bind_param('i',$batchId); $q->execute(); return $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    public function events($batchId,$admin){
        $this->batch($batchId,$admin);
        $q=db()->prepare('SELECT ev.*,a.full_name AS actor_name FROM registry_batch_events ev JOIN admin_users a ON a.id=ev.actor_admin_id WHERE ev.batch_id=? ORDER BY ev.id DESC'); $q->bind_param('i',$batchId); $q->execute(); return $q->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    private function event($batchId,$adminId,$event,$note=''){
        $note=mb_substr(trim((string)$note),0,1000); $q=db()->prepare('INSERT INTO registry_batch_events(batch_id,actor_admin_id,event,note,created_at) VALUES(?,?,?,?,NOW())');
        $q->bind_param('iiss',$batchId,$adminId,$event,$note); $q->execute();
    }
    private function requireEditing($b,$admin){
        if($this->canDraft($admin) && (int)$b['created_by_admin_id']===(int)$admin['id'] && in_array($b['status'],['draft','returned_registry'],true)) return;
        if($this->canWorkAdministration($admin) && in_array($b['status'],['administration_review','returned_administration'],true)) return;
        throw new Exception(t('not_allowed'));
    }
    public function canEdit($batch,$admin){
        if(!$batch || !$admin) return false;
        return ($this->canDraft($admin) && (int)$batch['created_by_admin_id']===(int)$admin['id'] && in_array($batch['status'],['draft','returned_registry'],true))
            || ($this->canWorkAdministration($admin) && in_array($batch['status'],['administration_review','returned_administration'],true));
    }
    public function create($admin,$title,$type){
        if(!$this->canDraft($admin)) throw new Exception(t('not_allowed'));
        $this->checkType($admin,$type); $title=trim((string)$title);
        if($title===''||mb_strlen($title)>190) throw new Exception(t('required_fields'));
        $owner=(int)$admin['id'];
        $q=db()->prepare("INSERT INTO registry_batches(title,person_type,created_by_admin_id,status,created_at,updated_at) VALUES(?,?,?,'draft',NOW(),NOW())");
        $q->bind_param('ssi',$title,$type,$owner); $q->execute(); $id=(int)$q->insert_id;
        $this->event($id,$owner,'created'); return $id;
    }
    private function cleanItem($input,$type){
        $out=[];
        foreach(['first_name','middle_name','last_name','birth_date','gender','identity_number','student_department_option_id','student_stage','student_group_code','academic_year','staff_workplace_option_id','teacher_certificate_option_id','teacher_specialization_option_id'] as $k){
            $out[$k]=trim((string)($input[$k]??''));
        }
        $out['person_type']=$type;
        foreach(['first_name','middle_name','last_name','identity_number'] as $k){ if($out[$k]==='' || mb_strlen($out[$k])>100) throw new Exception(t('required_fields')); }
        $birth=DateTime::createFromFormat('!Y-m-d',$out['birth_date']);
        if(!$birth || $birth->format('Y-m-d')!==$out['birth_date'] || $out['birth_date']>date('Y-m-d')) throw new Exception(t('invalid_birth_date'));
        if(!in_array($out['gender'],supported_genders(),true)) throw new Exception(t('invalid_action'));
        $academic=new AcademicController();
        if($type==='student'){
            if(!$academic->getOption((int)$out['student_department_option_id'],'student_department',true)) throw new Exception(t('student_department_required'));
            if((int)$out['student_stage']<1 || (int)$out['student_stage']>4) throw new Exception(t('invalid_stage'));
            if($out['student_group_code']==='' || mb_strlen($out['student_group_code'])>20) throw new Exception(t('group_code_required'));
            if(!preg_match('/^(\d{4})-(\d{4})$/',$out['academic_year'],$m) || (int)$m[2] !== (int)$m[1]+1) throw new Exception(t('invalid_academic_year'));
            $out['student_group_code']=strtoupper($out['student_group_code']);
        }elseif($type==='staff'){
            if(!$academic->getOption((int)$out['staff_workplace_option_id'],'staff_workplace',true)) throw new Exception(t('staff_workplace_required'));
        }else{
            if(!$academic->getOption((int)$out['teacher_certificate_option_id'],'teacher_certificate',true)) throw new Exception(t('teacher_certificate_required'));
            if(!$academic->getOption((int)$out['teacher_specialization_option_id'],'teacher_specialization',true)) throw new Exception(t('teacher_specialization_required'));
        }
        $dup=db()->prepare('SELECT id FROM institution_registry WHERE identity_number=? LIMIT 1'); $dup->bind_param('s',$out['identity_number']); $dup->execute();
        if($dup->get_result()->fetch_assoc()) throw new Exception(t('identity_number_used'));
        return $out;
    }
    public function saveItem($batchId,$itemId,$admin,$input){
        $this->assertAccess($admin); $conn=db(); $conn->begin_transaction();
        try{
            $b=$this->getBatch((int)$batchId,true); if(!$b) throw new Exception(t('invalid_action'));
            $this->requireEditing($b,$admin); $data=$this->cleanItem($input,$b['person_type']);
            $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); if($json===false) throw new Exception(t('invalid_action'));
            $first=$data['first_name']; $middle=$data['middle_name']; $last=$data['last_name']; $identity=$data['identity_number']; $type=$b['person_type'];
            $itemId=(int)$itemId; $actor=(int)$admin['id'];
            if($itemId>0){
                $q=$conn->prepare('UPDATE registry_batch_items SET first_name=?,middle_name=?,last_name=?,identity_number=?,payload_json=?,updated_at=NOW() WHERE id=? AND batch_id=?');
                $q->bind_param('sssssii',$first,$middle,$last,$identity,$json,$itemId,$batchId); $q->execute();
                if($q->affected_rows===0){ $check=$conn->prepare('SELECT id FROM registry_batch_items WHERE id=? AND batch_id=?');$check->bind_param('ii',$itemId,$batchId);$check->execute();if(!$check->get_result()->fetch_assoc()) throw new Exception(t('invalid_action')); }
                $ev='item_updated';
            }else{
                $q=$conn->prepare('INSERT INTO registry_batch_items(batch_id,person_type,first_name,middle_name,last_name,identity_number,payload_json,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())');
                $q->bind_param('issssss',$batchId,$type,$first,$middle,$last,$identity,$json); $q->execute(); $itemId=(int)$q->insert_id; $ev='item_added';
            }
            $this->event((int)$batchId,$actor,$ev,'#'.$itemId.' · '.$first.' '.$last); $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }
    public function deleteItem($batchId,$itemId,$admin){
        $this->assertAccess($admin); $conn=db(); $conn->begin_transaction();
        try{
            $b=$this->getBatch((int)$batchId,true); if(!$b) throw new Exception(t('invalid_action'));
            $this->requireEditing($b,$admin); $q=$conn->prepare('DELETE FROM registry_batch_items WHERE batch_id=? AND id=?'); $q->bind_param('ii',$batchId,$itemId); $q->execute();
            if($q->affected_rows!==1) throw new Exception(t('invalid_action'));
            $this->event((int)$batchId,(int)$admin['id'],'item_deleted','#'.$itemId); $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }
    public function changeStatus($batchId,$admin,$action,$note=''){
        $this->assertAccess($admin); $conn=db(); $conn->begin_transaction();
        $note=mb_substr(trim((string)$note),0,1000);
        try{
            $b=$this->getBatch((int)$batchId,true); if(!$b) throw new Exception(t('invalid_action'));
            $status=$b['status']; $next=''; $column='';
            if($this->isAdministrationHead($admin) && (int)$b['created_by_admin_id']===(int)$admin['id']) throw new Exception(t('not_allowed')); // never review own registry list
            $isOwner=$this->canDraft($admin) && (int)$b['created_by_admin_id']===(int)$admin['id'];
            $isHead=$this->isAdministrationHead($admin); $isManager=$this->isManager($admin);
            if($action==='submit' && $isOwner && in_array($status,['draft','returned_registry'],true)) $next='administration_review';
            elseif($action==='administration_approve' && $isHead && in_array($status,['administration_review','returned_administration'],true)){$next='manager_review';$column='administration_reviewed_by';}
            elseif($action==='administration_return' && $isHead && in_array($status,['administration_review','returned_administration'],true)){$next='returned_registry';$column='administration_reviewed_by';}
            elseif($action==='manager_return' && $isManager && $status==='manager_review'){$next='returned_administration';$column='manager_reviewed_by';}
            elseif($action==='manager_approve' && $isManager && $status==='manager_review'){$next='approved';$column='manager_reviewed_by';}
            else throw new Exception(t('not_allowed'));
            if(in_array($action,['administration_return','manager_return'],true) && $note==='') throw new Exception(t('rejection_note_required'));
            $rows=$conn->prepare('SELECT * FROM registry_batch_items WHERE batch_id=? ORDER BY id ASC FOR UPDATE'); $rows->bind_param('i',$batchId); $rows->execute(); $items=$rows->get_result()->fetch_all(MYSQLI_ASSOC);
            if(!$items && in_array($action,['submit','administration_approve','manager_approve'],true)) throw new Exception(t('required_fields'));
            if($action==='manager_approve'){
                // All records and the final approval commit or roll back as one transaction.
                // The creator's active department and current data/options are re-validated.
                $creator=(int)$b['created_by_admin_id'];
                $q=$conn->prepare('SELECT a.*,d.department_type,d.code AS department_code,d.name AS department_name FROM admin_users a JOIN institution_departments d ON d.id=a.department_id WHERE a.id=? LIMIT 1');
                $q->bind_param('i',$creator); $q->execute(); $original=$q->get_result()->fetch_assoc();
                if(!$original || !(int)$original['is_active'] || !(new AdminController())->departmentOperational((int)$original['department_id'])) throw new Exception(t('not_allowed'));
                foreach($items as $item){
                    $data=json_decode($item['payload_json'],true); if(!is_array($data) || ($data['person_type']??'')!==$b['person_type']) throw new Exception(t('invalid_action'));
                    $this->cleanItem($data,$b['person_type']);
                    (new InstitutionController())->createRecordFromData($original,$data,$creator,true);
                }
            }
            $actor=(int)$admin['id'];
            if($column==='administration_reviewed_by'){
                $q=$conn->prepare('UPDATE registry_batches SET status=?,administration_reviewed_by=?,administration_note=?,updated_at=NOW() WHERE id=?');
                $q->bind_param('sisi',$next,$actor,$note,$batchId);
            }elseif($column==='manager_reviewed_by'){
                $q=$conn->prepare('UPDATE registry_batches SET status=?,manager_reviewed_by=?,manager_note=?,approved_at=IF(?=\'approved\',NOW(),approved_at),updated_at=NOW() WHERE id=?');
                $q->bind_param('sissi',$next,$actor,$note,$next,$batchId);
            }else{
                $q=$conn->prepare('UPDATE registry_batches SET status=?,updated_at=NOW() WHERE id=?'); $q->bind_param('si',$next,$batchId);
            }
            $q->execute(); $this->event((int)$batchId,$actor,$action,$note); $conn->commit();
        }catch(Throwable $e){$conn->rollback();throw $e;}
    }
}
