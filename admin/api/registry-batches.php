<?php
require_once __DIR__ . '/../../api/app/controller/RegistryBatchController.php';
$admin=(new AdminController())->requireAdmin();
$id=(int)($_POST['batch_id']??0); $location='../registry-batches.php';
try{
    verify_csrf();
    $controller=new RegistryBatchController(); $action=(string)($_POST['action']??'');
    if($action==='create'){
        $id=$controller->create($admin,(string)($_POST['title']??''),(string)($_POST['person_type']??''));
    }elseif($action==='save_item'){
        $controller->saveItem($id,(int)($_POST['item_id']??0),$admin,$_POST);
    }elseif($action==='delete_item'){
        $controller->deleteItem($id,(int)($_POST['item_id']??0),$admin);
    }elseif(in_array($action,['submit','administration_approve','administration_return','manager_approve','manager_return'],true)){
        $controller->changeStatus($id,$admin,$action,(string)($_POST['note']??''));
    }else throw new Exception(t('invalid_action'));
    $_SESSION['admin_flash_success']=t('rw_saved_success');
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error').' ('.$e->getCode().')':$e->getMessage();
}
if($id>0) $location='../registry-batch.php?id='.$id;
header('Location: '.$location);exit;
