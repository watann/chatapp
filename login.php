<?php include_once "part/header.php"; ?>
<body class="auth-body">
  <main class="auth-shell compact">
    <section class="auth-hero">
      <div class="brand big"><img class="brand-logo" src="assets/img/project-logo.jpg" alt="<?php echo e(t('app_name')); ?>"><span><?php echo e(t('app_name')); ?></span></div>
      <h1><?php echo e(t('welcome')); ?></h1>
      <p><?php echo e(t('hero_text')); ?></p>
    </section>
    <section class="auth-card form login">
      <header><?php echo e(t('login')); ?></header>
      <nav class="auth-language" aria-label="<?php echo e(t('language')); ?>">
        <?php foreach(supported_languages() as $code): ?><a class="<?php echo user_language() === $code ? 'active' : ''; ?>" href="<?php echo e(basename($_SERVER['PHP_SELF'])); ?>?lang=<?php echo e($code); ?>"><?php echo e(language_name($code)); ?></a><?php endforeach; ?>
      </nav>
      <form action="#"><?php echo csrf_input(); ?>
        <div class="error-text"></div>
        <label class="field"><span><?php echo e(t('email')); ?></span><input type="email" name="email" required></label>
        <label class="field password-field"><span><?php echo e(t('password')); ?></span><input type="password" name="password" required><i class="fas fa-eye"></i></label>
        <div class="field button"><input type="submit" value="<?php echo e(t('login')); ?>"></div>
      </form>
      <div class="link"><?php echo e(t('need_account')); ?> <a href="index.php"><?php echo e(t('signup')); ?></a></div><div class="link admin-entry-link"><a href="admin/login.php"><i class="fas fa-user-shield"></i><?php echo e(t('admin_portal')); ?></a></div>
    </section>
  </main>
  <script src="assets/js/password.js"></script>
  <script src="assets/js/login.js"></script>
</body>
</html>
