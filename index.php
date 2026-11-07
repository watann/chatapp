<?php include_once "part/header.php"; ?>
<body class="auth-body">
  <main class="auth-shell">
    <section class="auth-hero">
      <div class="brand big"><img class="brand-logo" src="assets/img/project-logo.jpg" alt="<?php echo e(t('app_name')); ?>"><span><?php echo e(t('app_name')); ?></span></div>
      <h1><?php echo e(t('welcome')); ?></h1>
      <p><?php echo e(t('hero_text')); ?></p>
      <div class="hero-cards">
        <span><i class="fas fa-shield-alt"></i><?php echo e(t('hero_secure')); ?></span>
        <span><i class="fas fa-users"></i><?php echo e(t('hero_groups')); ?></span>
        <span><i class="fas fa-language"></i><?php echo e(t('hero_languages')); ?></span>
      </div>
    </section>

    <section class="auth-card form signup">
      <header><?php echo e(t('signup')); ?></header>
      <nav class="auth-language" aria-label="<?php echo e(t('language')); ?>">
        <?php foreach(supported_languages() as $code): ?><a class="<?php echo user_language() === $code ? 'active' : ''; ?>" href="<?php echo e(basename($_SERVER['PHP_SELF'])); ?>?lang=<?php echo e($code); ?>"><?php echo e(language_name($code)); ?></a><?php endforeach; ?>
      </nav>
      <form action="#" enctype="multipart/form-data"><?php echo csrf_input(); ?>
        <div class="error-text"></div>
        <div class="registration-gate-note"><i class="fas fa-id-card"></i><div><strong><?php echo e(t('registration_required_title')); ?></strong><span><?php echo e(t('registration_required_help')); ?></span></div></div>
        <label class="field"><span><?php echo e(t('registration_id')); ?></span><input class="ltr-code" type="text" name="registration_id" placeholder="RUN-2026-STD-000001" autocomplete="off" required></label>
        <div class="grid two">
          <label class="field"><span><?php echo e(t('identity_number')); ?></span><input type="text" name="identity_number" autocomplete="off" required></label>
          <label class="field"><span><?php echo e(t('birth_date')); ?></span><input type="date" name="birth_date" max="<?php echo e(date('Y-m-d')); ?>" required></label>
        </div>
        <label class="field"><span><?php echo e(t('email')); ?></span><input type="email" name="email" required></label>
        <label class="field password-field"><span><?php echo e(t('password')); ?></span><input type="password" name="password" minlength="6" required><i class="fas fa-eye"></i></label>
        <label class="field"><span><?php echo e(t('language')); ?></span><select name="language"><?php foreach(supported_languages() as $code): ?><option value="<?php echo e($code); ?>" <?php echo user_language() === $code ? 'selected' : ''; ?>><?php echo e(language_name($code)); ?></option><?php endforeach; ?></select></label>
        <label class="field file"><span><?php echo e(t('profile_image')); ?></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <div class="field button"><input type="submit" value="<?php echo e(t('signup')); ?>"></div>
      </form>
      <div class="link"><?php echo e(t('already_account')); ?> <a href="login.php"><?php echo e(t('login')); ?></a></div><div class="link admin-entry-link"><a href="admin/login.php"><i class="fas fa-user-shield"></i><?php echo e(t('admin_portal')); ?></a></div>
    </section>
  </main>
  <script src="assets/js/password.js"></script>
  <script src="assets/js/signup.js"></script>
</body>
</html>
