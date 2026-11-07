<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
$controller = new AdminController();
if(!$controller->superAdminExists()){ header('Location: setup.php'); exit; }
if($controller->currentAdmin()){ header('Location: index.php'); exit; }
include __DIR__ . '/part/header.php';
?>
<body class="auth-body admin-auth-body">
<main class="auth-shell compact admin-auth-shell">
  <section class="auth-hero"><div class="brand big"><img class="brand-logo" src="../assets/img/project-logo.jpg" alt=""><span><?php echo e(t('app_name')); ?></span></div><h1><?php echo e(t('admin_portal')); ?></h1><p><?php echo e(t('admin_login_help')); ?></p></section>
  <section class="auth-card form login">
    <header><?php echo e(t('admin_login')); ?></header>
    <?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
    <form method="post" action="api/login.php">
      <?php echo csrf_input(); ?>
      <label class="field"><span><?php echo e(t('email')); ?></span><input type="email" name="email" required></label>
      <label class="field"><span><?php echo e(t('password')); ?></span><input type="password" name="password" required></label>
      <button class="btn primary" type="submit"><?php echo e(t('login')); ?></button>
    </form>
    <div class="link"><a href="../login.php"><?php echo e(t('back_to_social_login')); ?></a></div>
  </section>
</main>
</body></html>
