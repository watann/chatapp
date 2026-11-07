<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/FinanceController.php';
$ac=new AdminController(); $admin=$ac->requireAdmin();
try{
    verify_csrf();
    $finance=new FinanceController();
    $action=(string)($_POST['action']??'');
    if($action==='create'){
        $finance->create($admin,(string)($_POST['transaction_type']??''),$_POST['amount']??null,(string)($_POST['transaction_date']??''),(string)($_POST['party_name']??''),(string)($_POST['reference_no']??''),(string)($_POST['description']??''),(int)($_POST['department_request_id']??0));
        $_SESSION['admin_flash_success']=($admin['admin_role']??'')==='super_admin'?t('finance_transaction_approved_directly'):t('finance_transaction_submitted');
    }elseif($action==='approve' || $action==='reject'){
        if(($admin['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
        $finance->review((int)($_POST['transaction_id']??0),$action==='approve',(string)($_POST['review_note']??''),$admin);
        $_SESSION['admin_flash_success']=$action==='approve'?t('finance_transaction_approved'):t('finance_transaction_rejected');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
header('Location: ../finance.php'); exit;
