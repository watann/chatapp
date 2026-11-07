<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/AcademicController.php';
$ac=new AdminController(); $admin=$ac->requireSuperAdmin();
try{
    verify_csrf();
    $academic=new AcademicController();
    $action=(string)($_POST['action']??'');
    if($action==='add'){
        $academic->addOption((string)($_POST['option_type']??''),(string)($_POST['label']??''),(int)$admin['id']);
        $_SESSION['admin_flash_success']=t('option_added');
    }elseif($action==='toggle'){
        $academic->toggleOption((int)($_POST['option_id']??0));
        $_SESSION['admin_flash_success']=t('option_updated');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
header('Location: ../options.php'); exit;
