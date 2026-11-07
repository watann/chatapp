<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/AcademicController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['academic_affairs','student_affairs']);
try{
    verify_csrf();
    $academic=new AcademicController();
    $academic->progressStudent(
        (int)($_POST['registry_id']??0),
        (string)($_POST['decision']??''),
        (string)($_POST['academic_year']??''),
        (string)($_POST['group_code']??''),
        (int)($_POST['target_stage']??0),
        (int)$admin['id'],
        (string)($_POST['note']??'')
    );
    $_SESSION['admin_flash_success']=t('student_progress_saved');
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
header('Location: ../students.php'); exit;
