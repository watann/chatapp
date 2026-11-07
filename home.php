<?php
require_once "api/app/controller/AuthController.php";
require_once "api/app/controller/FeedController.php";
$auth=new AuthController(); $auth->checkAuth();
$feedController=new FeedController(); $scope='home';
include_once "part/header.php";
?>
<body><div class="app-layout"><?php include "part/sidebar.php"; ?><main class="app-main feed-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('home')); ?></span><h1><?php echo e(t('home_title')); ?></h1><p class="subtle"><?php echo e(t('home_help')); ?></p></div><a class="btn ghost" href="users.php"><i class="fas fa-user-plus"></i><?php echo e(t('friends')); ?></a></section>
<?php include "part/feed.php"; ?>
</main></div><?php include "part/app-footer.php"; ?></body></html>
