<?php
require_once "app/controller/UserController.php";
$user = new UserController();
$user->searchUser($_POST['searchTerm'] ?? '');
