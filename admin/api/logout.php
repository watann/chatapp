<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    header('Allow: POST');
    exit;
}
try{
    verify_csrf();
}catch(Throwable $e){
    $_SESSION['admin_flash_error'] = $e->getMessage();
    header('Location: ../index.php');
    exit;
}
(new AdminController())->logout();
