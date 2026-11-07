<?php
$meSidebar = current_user();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="app-sidebar">
    <a class="brand" href="home.php">
        <img class="brand-logo" src="assets/img/project-logo.jpg" alt="<?php echo e(t('app_name')); ?>">
        <span><?php echo e(t('app_name')); ?></span>
    </a>
    <a class="mini-profile" href="profile.php">
        <img class="avatar" src="<?php echo e(avatar_src($meSidebar['img'] ?? '')); ?>" alt="">
        <div><strong><?php echo e(display_name($meSidebar)); ?></strong><span><?php echo e(role_label($meSidebar['role'] ?? 'student')); ?></span></div>
    </a>
    <nav class="side-nav">
        <a class="<?php echo $currentPage === 'home.php' ? 'active' : ''; ?>" href="home.php"><i class="fas fa-home"></i><span><?php echo e(t('home')); ?></span></a>
        <a class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" href="users.php"><i class="fas fa-user-friends"></i><span><?php echo e(t('friends')); ?></span></a>
        <a class="<?php echo in_array($currentPage, ['groups.php','group-chat.php','academic-group.php'], true) ? 'active' : ''; ?>" href="groups.php"><i class="fas fa-comments"></i><span><?php echo e(t('groups')); ?></span></a>
        <a class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>" href="profile.php"><i class="fas fa-user"></i><span><?php echo e(t('profile')); ?></span></a>
        <a class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" href="settings.php"><i class="fas fa-cog"></i><span><?php echo e(t('settings')); ?></span></a>
        <form class="side-nav-form" method="post" action="api/logout.php"><?php echo csrf_input(); ?><button type="submit"><i class="fas fa-sign-out-alt"></i><span><?php echo e(t('logout')); ?></span></button></form>
    </nav>
</aside>
