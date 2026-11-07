<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';

class FinanceController{
    private function requireFinance($admin){
        if(!$admin || !(new AdminController())->canAccessDepartment($admin,['finance'])) throw new Exception(t('not_allowed'));
    }

    private function audit($actorId,$event,$entityId,$details=''){
        $actorId=(int)$actorId; $entityId=(int)$entityId; $details=mb_substr((string)$details,0,2000);
        $stmt=db()->prepare("INSERT INTO administrative_audit_log(actor_admin_id,event_type,entity_type,entity_id,details,created_at) VALUES(? ,?,'finance_transaction',?,?,NOW())");
        $stmt->bind_param('isis',$actorId,$event,$entityId,$details); $stmt->execute();
    }

    private function auditRequest($actorId,$event,$requestId,$details=''){
        $actorId=(int)$actorId; $requestId=(int)$requestId; $details=mb_substr((string)$details,0,2000);
        $stmt=db()->prepare("INSERT INTO administrative_audit_log(actor_admin_id,event_type,entity_type,entity_id,details,created_at) VALUES(? ,?,'department_request',?,?,NOW())");
        $stmt->bind_param('isis',$actorId,$event,$requestId,$details); $stmt->execute();
    }

    public function totals($admin){
        $this->requireFinance($admin);
        $r=db()->query("SELECT COALESCE(SUM(CASE WHEN transaction_type='income' AND status='approved' THEN amount ELSE 0 END),0) income,COALESCE(SUM(CASE WHEN transaction_type='expense' AND status='approved' THEN amount ELSE 0 END),0) expense,COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END),0) pending FROM finance_transactions");
        $x=$r?$r->fetch_assoc():['income'=>0,'expense'=>0,'pending'=>0];
        $x['balance']=(float)$x['income']-(float)$x['expense'];
        return $x;
    }

    public function approvedMoneyRequests($admin){
        $this->requireFinance($admin);
        return db()->query("SELECT dr.id,dr.title,dr.amount,dr.department_id,d.name AS department_name FROM department_requests dr JOIN institution_departments d ON d.id=dr.department_id LEFT JOIN finance_transactions ft ON ft.department_request_id=dr.id AND ft.status IN ('pending','approved') WHERE dr.status='approved' AND dr.request_type IN ('procurement','budget') AND dr.amount IS NOT NULL AND ft.id IS NULL ORDER BY dr.reviewed_at ASC,dr.id ASC");
    }

    public function create($admin,$type,$amount,$date,$party,$reference,$description,$departmentRequestId=0){
        $this->requireFinance($admin);
        $type=(string)$type; if(!in_array($type,['income','expense'],true)) throw new Exception(t('invalid_action'));
        if(!is_numeric($amount) || (float)$amount<=0 || (float)$amount>999999999999.99) throw new Exception(t('invalid_amount'));
        $amountValue=number_format((float)$amount,2,'.','');
        $d=DateTime::createFromFormat('Y-m-d',(string)$date); if(!$d || $d->format('Y-m-d')!==$date || $date>date('Y-m-d')) throw new Exception(t('invalid_transaction_date'));
        $party=trim((string)$party); $reference=trim((string)$reference); $description=trim((string)$description);
        if($party==='' || mb_strlen($party)>190 || mb_strlen($reference)>100 || mb_strlen($description)>2000) throw new Exception(t('required_fields'));
        $departmentRequestId=(int)$departmentRequestId;
        if($departmentRequestId>0 && $type!=='expense') throw new Exception(t('invalid_action'));

        $creator=(int)$admin['id'];
        $status=($admin['admin_role'] ?? '')==='super_admin'?'approved':'pending';
        $reviewer=$status==='approved'?$creator:null;
        $conn=db(); $conn->begin_transaction();
        try{
            if($departmentRequestId>0){
                $rq=$conn->prepare("SELECT id,amount,status,request_type FROM department_requests WHERE id=? LIMIT 1 FOR UPDATE");
                $rq->bind_param('i',$departmentRequestId); $rq->execute(); $request=$rq->get_result()->fetch_assoc();
                if(!$request || $request['status']!=='approved' || !in_array($request['request_type'],['procurement','budget'],true) || $request['amount']===null) throw new Exception(t('finance_request_not_approved'));
                if(abs((float)$request['amount']-(float)$amountValue)>0.009) throw new Exception(t('finance_amount_must_match_request'));
                $used=$conn->prepare("SELECT id FROM finance_transactions WHERE department_request_id=? AND status IN ('pending','approved') LIMIT 1 FOR UPDATE");
                $used->bind_param('i',$departmentRequestId); $used->execute(); if($used->get_result()->num_rows) throw new Exception(t('finance_request_already_in_process'));
            }

            $stmt=$conn->prepare("INSERT INTO finance_transactions(department_request_id,transaction_type,amount,transaction_date,party_name,reference_no,description,status,submitted_by_admin_id,reviewed_by_admin_id,reviewed_at,created_at,updated_at) VALUES(NULLIF(?,0),?,?,?,?,?,?,?, ?,?,IF(? IS NULL,NULL,NOW()),NOW(),NOW())");
            $stmt->bind_param('isdsssssiii',$departmentRequestId,$type,$amountValue,$date,$party,$reference,$description,$status,$creator,$reviewer,$reviewer);
            $stmt->execute(); $id=(int)$stmt->insert_id;
            if($status==='approved' && $departmentRequestId>0){
                $u=$conn->prepare("UPDATE department_requests SET status='completed',executed_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved'");
                $u->bind_param('i',$departmentRequestId); $u->execute();
                if($u->affected_rows!==1) throw new Exception(t('finance_request_not_approved'));
                $this->auditRequest($creator,'request_completed_by_finance',$departmentRequestId,'finance_transaction #'.$id);
            }
            $this->audit($creator,'finance_submitted',$id,$type.' '.$amountValue);
            $conn->commit();
            return $id;
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }

    public function listAll($admin){
        $this->requireFinance($admin);
        return db()->query("SELECT ft.*,s.full_name AS submitted_by,r.full_name AS reviewed_by,dr.title AS request_title,d.name AS request_department FROM finance_transactions ft JOIN admin_users s ON s.id=ft.submitted_by_admin_id LEFT JOIN admin_users r ON r.id=ft.reviewed_by_admin_id LEFT JOIN department_requests dr ON dr.id=ft.department_request_id LEFT JOIN institution_departments d ON d.id=dr.department_id ORDER BY (ft.status='pending') DESC,ft.transaction_date DESC,ft.id DESC");
    }

    public function review($id,$approve,$note,$manager){
        if(($manager['admin_role'] ?? '')!=='super_admin') throw new Exception(t('not_allowed'));
        $id=(int)$id; $note=mb_substr(trim((string)$note),0,1000); $managerId=(int)$manager['id'];
        if(!$approve && $note==='') throw new Exception(t('rejection_note_required'));
        $conn=db(); $conn->begin_transaction();
        try{
            $lock=$conn->prepare("SELECT * FROM finance_transactions WHERE id=? LIMIT 1 FOR UPDATE"); $lock->bind_param('i',$id); $lock->execute(); $tx=$lock->get_result()->fetch_assoc();
            if(!$tx || $tx['status']!=='pending') throw new Exception(t('transaction_not_pending'));
            $status=$approve?'approved':'rejected';
            $u=$conn->prepare("UPDATE finance_transactions SET status=?,reviewed_by_admin_id=?,reviewed_at=NOW(),review_note=?,updated_at=NOW() WHERE id=? AND status='pending'");
            $u->bind_param('sisi',$status,$managerId,$note,$id); $u->execute(); if($u->affected_rows!==1) throw new Exception(t('transaction_not_pending'));
            if($approve && !empty($tx['department_request_id'])){
                $rid=(int)$tx['department_request_id'];
                $rq=$conn->prepare("UPDATE department_requests SET status='completed',executed_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved'"); $rq->bind_param('i',$rid); $rq->execute();
                if($rq->affected_rows!==1) throw new Exception(t('finance_request_not_approved'));
                $this->auditRequest($managerId,'request_completed_by_finance',$rid,'finance_transaction #'.$id);
            }
            $this->audit($managerId,$approve?'finance_approved':'finance_rejected',$id,$note);
            $conn->commit();
        }catch(Throwable $e){ $conn->rollback(); throw $e; }
    }
}
