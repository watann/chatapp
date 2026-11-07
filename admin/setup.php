<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
$controller = new AdminController();
if($controller->superAdminExists()){ header('Location: login.php'); exit; }
include __DIR__ . '/part/header.php';
?>
<body class="auth-body admin-auth-body">
<main class="auth-shell compact admin-auth-shell">
  <section class="auth-hero"><div class="brand big"><img class="brand-logo" src="../assets/img/project-logo.jpg" alt=""><span><?php echo e(t('app_name')); ?></span></div><h1><?php echo e(t('super_admin_setup')); ?></h1><p><?php echo e(t('super_admin_setup_help')); ?></p></section>
  <section class="auth-card form login">
    <header><?php echo e(t('create_super_admin')); ?></header>
    <?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
    <form method="post" action="api/setup.php">
      <?php echo csrf_input(); ?>
      <label class="field"><span><?php echo e(t('full_name')); ?></span><input type="text" name="full_name" required></label>
      <label class="field"><span><?php echo e(t('email')); ?></span><input type="email" name="email" required></label>
      <label class="field"><span><?php echo e(t('password')); ?></span><input type="password" name="password" minlength="8" required></label>
      <button class="btn primary" type="submit"><?php echo e(t('create_super_admin')); ?></button>
    </form>
  </section>
</main>
</body></html>
