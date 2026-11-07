<?php
require_once "api/app/controller/AuthController.php";
$auth = new AuthController();
$auth->checkAuth();
header('Location: home.php');
exit;
