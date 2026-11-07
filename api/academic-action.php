<?php
require_once __DIR__ . '/app/controller/AcademicController.php';
$groupId=(int)($_POST['group_id']??0);
try{
    require_auth();
    verify_csrf();
    $academic=new AcademicController();
    $action=(string)($_POST['action']??'');
    if($action==='create_post'){
        $academic->createAcademicPost($groupId,(string)($_POST['content']??''),$_FILES['media']??null);
        $_SESSION['flash_success']=t('academic_post_created');
    }elseif($action==='delete_post'){
        $academic->deleteAcademicPost($groupId,(int)($_POST['post_id']??0));
        $_SESSION['flash_success']=t('academic_post_deleted');
    }elseif($action==='comment'){
        $academic->addAcademicComment($groupId,(int)($_POST['post_id']??0),(string)($_POST['comment']??''));
        $_SESSION['flash_success']=t('academic_comment_added');
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){
    $_SESSION['flash_error']=$e instanceof mysqli_sql_exception?t('invalid_action'):$e->getMessage();
}
header('Location: ../academic-group.php?group_id='.$groupId); exit;
