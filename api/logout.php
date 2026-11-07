<?php
require_once "app/controller/AuthController.php";
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    header('Allow: POST');
    exit;
}
try{
    verify_csrf();
}catch(Throwable $e){
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: ../home.php');
    exit;
}
(new AuthController())->logOut();
