<?php
require_once __DIR__ . '/app/controller/GradeController.php';
require_auth();
$groupId=(int)($_POST['group_id']??0);
try{
    verify_csrf();
    $grades=new GradeController();
    $action=(string)($_POST['action']??'');
    if($action==='create_exam'){
        $grades->createExam($groupId,(string)($_POST['title']??''),(string)($_POST['subject']??''),(string)($_POST['exam_date']??''),$_POST['max_score']??100,(string)($_POST['teacher_note']??''));
        $_SESSION['flash_success']=t('exam_created');
    }elseif($action==='save_grades'){
        $examId=(int)($_POST['exam_id']??0); $exam=$grades->getExam($examId); if($exam) $groupId=(int)$exam['group_id'];
        $grades->saveGrades($examId,$_POST['scores']??[],$_POST['notes']??[]);
        $_SESSION['flash_success']=t('grades_saved');
    }elseif($action==='submit_exam'){
        $examId=(int)($_POST['exam_id']??0); $exam=$grades->getExam($examId); if($exam) $groupId=(int)$exam['group_id'];
        $grades->submitExam($examId);
        $_SESSION['flash_success']=t('exam_submitted_for_review');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['flash_error']=$e instanceof mysqli_sql_exception?t('invalid_action'):$e->getMessage();
}
header('Location: ../academic-group.php?group_id='.$groupId); exit;
