<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/WorkflowController.php';
$ac=new AdminController(); $admin=$ac->requireAdmin();
try{
    verify_csrf();
    $workflow=new WorkflowController();
    $action=(string)($_POST['action']??'');
    if($action==='submit'){
        $workflow->submit(
            $admin,
            (string)($_POST['request_type']??''),
            (string)($_POST['title']??''),
            (string)($_POST['details']??''),
            (int)($_POST['target_registry_id']??0),
            (int)($_POST['target_admin_user_id']??0),
            $_POST['amount']??null
        );
        $_SESSION['admin_flash_success']=t('request_submitted_success');
    }elseif($action==='cancel'){
        $workflow->cancel((int)($_POST['request_id']??0),$admin);
        $_SESSION['admin_flash_success']=t('request_cancelled_success');
    }elseif($action==='complete'){
        if(($admin['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
        $workflow->complete((int)($_POST['request_id']??0),$admin);
        $_SESSION['admin_flash_success']=t('request_completed_success');
    }elseif($action==='approve' || $action==='reject'){
        if(($admin['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
        $workflow->review((int)($_POST['request_id']??0),$action==='approve',(string)($_POST['review_note']??''),$admin);
        $_SESSION['admin_flash_success']=$action==='approve'?t('request_approved_success'):t('request_rejected_success');
    }else{
        throw new Exception(t('invalid_action'));
    }
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
header('Location: ../requests.php'); exit;
