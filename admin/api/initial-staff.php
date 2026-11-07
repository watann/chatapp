<?php
require_once __DIR__.'/../../api/app/controller/InitialStaffController.php';
$admin=(new AdminController())->requireSuperAdmin();
try {
    verify_csrf();
    $issued=(new InitialStaffController())->create($admin,$_POST);
    $_SESSION['admin_flash_success']='فەرمانبەرەکە بە سەرکەوتوویی تۆمارکرا، هەژمارەکە و مۆڵەتەکەی دروست بوو. ژمارەی فەرمی: '.$issued;
}catch(Throwable $e){
    $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error').' ('.$e->getCode().')':$e->getMessage();
}
header('Location: ../initial-staff.php');exit;
