<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
(new AdminController())->requireSuperAdmin();
header('Location: organization.php');
exit;
