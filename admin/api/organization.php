<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/OrganizationController.php';
$ac=new AdminController(); $admin=$ac->requireAdmin();
try{
    verify_csrf();
    $org=new OrganizationController();
    $action=(string)($_POST['action']??'');
    if($action==='assign_head'){
        if($admin['admin_role']!=='super_admin') throw new Exception(t('not_allowed'));
        $org->assignHead((int)($_POST['department_id']??0),(int)($_POST['registry_id']??0),(int)$admin['id']);
        $_SESSION['admin_flash_success']=t('department_head_assigned');
    }elseif($action==='add_employee'){
        $deptId=$admin['admin_role']==='super_admin'?(int)($_POST['department_id']??0):(int)$admin['department_id'];
        $org->addEmployee($deptId,(int)($_POST['registry_id']??0),$admin);
        $_SESSION['admin_flash_success']=t('department_employee_added');
    }elseif($action==='remove_employee'){
        $org->removeEmployee((int)($_POST['admin_user_id']??0),$admin);
        $_SESSION['admin_flash_success']=t('department_employee_removed');
    }elseif($action==='create_department'){
        if($admin['admin_role']!=='super_admin') throw new Exception(t('not_allowed'));
        $org->createDepartment((string)($_POST['name']??''),(string)($_POST['code']??''),(string)($_POST['department_type']??'other'),(int)($_POST['parent_id']??1),(int)$admin['id'],(string)($_POST['responsibilities']??''));
        $_SESSION['admin_flash_success']=t('department_created');
    }elseif($action==='toggle_department'){
        if($admin['admin_role']!=='super_admin') throw new Exception(t('not_allowed'));
        $org->toggleDepartment((int)($_POST['department_id']??0));
        $_SESSION['admin_flash_success']=t('department_updated');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
header('Location: ../organization.php'); exit;
