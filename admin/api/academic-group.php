<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/AcademicController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['academic_affairs']);
$returnId=(int)($_POST['group_id']??0);
try{
    verify_csrf();
    $academic=new AcademicController();
    $action=(string)($_POST['action']??'');
    if($action==='create'){
        $returnId=$academic->createGroup((int)($_POST['department_option_id']??0),(int)($_POST['stage']??0),(string)($_POST['group_code']??''),(string)($_POST['academic_year']??''),(int)$admin['id']);
        $_SESSION['admin_flash_success']=t('academic_group_created');
    }elseif($action==='assign_teacher'){
        $academic->assignTeacher($returnId,(int)($_POST['teacher_registry_id']??0),(int)$admin['id']);
        $_SESSION['admin_flash_success']=t('teacher_assigned');
    }elseif($action==='unassign_teacher'){
        $academic->unassignTeacher($returnId,(int)($_POST['teacher_registry_id']??0));
        $_SESSION['admin_flash_success']=t('teacher_removed');
    }elseif($action==='add_timetable'){
        $academic->addTimetable($returnId,(int)($_POST['day_of_week']??0),(string)($_POST['start_time']??''),(string)($_POST['end_time']??''),(string)($_POST['subject']??''),(int)($_POST['teacher_registry_id']??0),(string)($_POST['room']??''),(int)$admin['id']);
        $_SESSION['admin_flash_success']=t('timetable_added');
    }elseif($action==='delete_timetable'){
        $academic->deleteTimetable($returnId,(int)($_POST['timetable_id']??0));
        $_SESSION['admin_flash_success']=t('timetable_deleted');
    }elseif($action==='toggle_active'){
        $academic->toggleGroupActive($returnId);
        $_SESSION['admin_flash_success']=t('academic_group_status_updated');
    }elseif($action==='admin_delete_post'){
        $academic->adminDeleteAcademicPost($returnId,(int)($_POST['post_id']??0));
        $_SESSION['admin_flash_success']=t('academic_post_deleted');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
$target='../academic-groups.php'.($returnId>0?'?group_id='.$returnId:'');
header('Location: '.$target); exit;
