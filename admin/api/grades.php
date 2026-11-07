<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/GradeController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['academic_affairs']);
$examId=(int)($_POST['exam_id']??0);
try{
    verify_csrf();
    $grades=new GradeController();
    $action=(string)($_POST['action']??'');
    if($action==='publish'){
        $grades->reviewExam($examId,$_POST['scores']??[],$_POST['admin_notes']??[],(string)($_POST['review_note']??''),true,$admin);
        $_SESSION['admin_flash_success']=t('grades_published');
    }elseif($action==='return'){
        $grades->reviewExam($examId,$_POST['scores']??[],$_POST['admin_notes']??[],(string)($_POST['review_note']??''),false,$admin);
        $_SESSION['admin_flash_success']=t('exam_returned_to_teacher');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage();
}
header('Location: ../grades.php'.($examId>0?'?exam_id='.$examId:'')); exit;
