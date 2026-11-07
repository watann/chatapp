<?php
require_once "api/app/controller/AuthController.php";
$auth = new AuthController();
$auth->checkAuth();
$me = current_user();
$prefs = user_preferences($me);
$registryRecord = null;
if(!empty($me['registry_id'])){
    $rs = db()->prepare("SELECT issued_id,identity_number,birth_date,gender,person_type,student_department,staff_workplace,teacher_specialization FROM institution_registry WHERE id=? LIMIT 1");
    $rid = (int)$me['registry_id'];
    $rs->bind_param('i', $rid);
    $rs->execute();
    $registryRecord = $rs->get_result()->fetch_assoc();
}
$blockedStmt=db()->prepare("SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.img,u.role FROM user_blocks b JOIN users u ON u.unique_id=b.blocked_id WHERE b.blocker_id=? ORDER BY b.created_at DESC");
$myId=(int)$me['unique_id']; $blockedStmt->bind_param('i',$myId); $blockedStmt->execute(); $blockedUsers=$blockedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
include_once "part/header.php";
?>
<body>
<div class="app-layout">
  <?php include "part/sidebar.php"; ?>
  <main class="app-main settings-main">
    <section class="topbar">
      <div><span class="eyebrow"><?php echo e(t('settings')); ?></span><h1><?php echo e(t('settings_title')); ?></h1><p class="subtle"><?php echo e(t('settings_help')); ?></p></div>
      <a class="btn ghost" href="profile.php"><i class="fas fa-user"></i><?php echo e(t('profile')); ?></a>
    </section>

    <?php if(!empty($_SESSION['flash_success'])): ?><div class="notice success settings-notice"><?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
    <?php if(!empty($_SESSION['flash_error'])): ?><div class="notice error settings-notice"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

    <div class="settings-grid">
      <section class="panel settings-card" id="profile-settings">
        <div class="section-heading"><div><span class="eyebrow"><?php echo e(t('profile')); ?></span><h2><?php echo e(t('profile_settings')); ?></h2></div><i class="fas fa-user-edit settings-card-icon"></i></div>
        <div class="settings-profile-preview">
          <img class="profile-hero-avatar settings-avatar" src="<?php echo e(avatar_src($me['img'] ?? '')); ?>" alt="">
          <div><strong><?php echo e(display_name($me)); ?></strong><span><?php echo e(role_label($me['role'] ?? 'student')); ?></span></div>
        </div>
        <form action="api/update-profile.php" method="post" enctype="multipart/form-data" class="modern-form"><?php echo csrf_input(); ?>
          <?php if($registryRecord): ?>
            <div class="identity-lock-card">
              <div><span><?php echo e(t('official_identity')); ?></span><strong><?php echo e(display_name($me)); ?></strong></div>
              <div><span><?php echo e(t('registration_id')); ?></span><strong class="ltr-code"><?php echo e($registryRecord['issued_id']); ?></strong></div>
              <div><span><?php echo e(t('role')); ?></span><strong><?php echo e(role_label($me['role'])); ?></strong></div>
              <p><i class="fas fa-lock"></i><?php echo e(t('official_identity_locked')); ?></p>
            </div>
          <?php else: ?>
            <div class="grid three">
              <label class="field"><span><?php echo e(t('first_name')); ?></span><input type="text" name="fname" value="<?php echo e($me['fname'] ?? ''); ?>" required></label>
              <label class="field"><span><?php echo e(t('middle_name')); ?></span><input type="text" name="middle_name" value="<?php echo e($me['middle_name'] ?? ''); ?>"></label>
              <label class="field"><span><?php echo e(t('last_name')); ?></span><input type="text" name="lname" value="<?php echo e($me['lname'] ?? ''); ?>" required></label>
            </div>
            <div class="identity-lock-card"><div><span><?php echo e(t('role')); ?></span><strong><?php echo e(role_label($me['role'] ?? 'student')); ?></strong></div><p><i class="fas fa-lock"></i><?php echo e(t('official_role_locked')); ?></p></div>
          <?php endif; ?>
          <label class="field"><span><?php echo e(t('email')); ?></span><input type="email" name="email" value="<?php echo e($me['email'] ?? ''); ?>" required></label>
          <label class="field"><span><?php echo e(t('bio')); ?></span><textarea name="bio" rows="4" maxlength="1000"><?php echo e($me['bio'] ?? ''); ?></textarea></label>
          <label class="field"><span><?php echo e(t('new_password')); ?></span><input type="password" name="password" minlength="6" placeholder="<?php echo e(t('keep_password')); ?>"></label>
          <label class="field file"><span><?php echo e(t('profile_image')); ?></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
          <button type="submit" class="btn primary"><i class="fas fa-save"></i><?php echo e(t('save_changes')); ?></button>
        </form>
      </section>

      <section class="panel settings-card" id="blocked-users">
        <div class="section-heading"><div><span class="eyebrow"><?php echo e(t('privacy')); ?></span><h2><?php echo e(t('blocked_users')); ?></h2><p class="subtle"><?php echo e(t('blocked_users_help')); ?></p></div><i class="fas fa-user-shield settings-card-icon"></i></div>
        <div class="admin-list"><?php if(!$blockedUsers): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_blocked_users')); ?></span></div><?php endif; ?><?php foreach($blockedUsers as $bu): ?><article class="admin-list-row"><img class="avatar tiny" src="<?php echo e(avatar_src($bu['img'])); ?>" alt=""><div><strong><?php echo e(display_name($bu)); ?></strong><span><?php echo e(role_label($bu['role'])); ?></span></div><form method="post" action="api/friend-action.php"><?php echo csrf_input(); ?><input type="hidden" name="user_id" value="<?php echo (int)$bu['unique_id']; ?>"><input type="hidden" name="action" value="unblock"><input type="hidden" name="return_to" value="settings.php"><button class="btn ghost small-btn" type="submit"><?php echo e(t('unblock_user')); ?></button></form></article><?php endforeach; ?></div>
      </section>

      <section class="panel settings-card" id="appearance">
        <div class="section-heading"><div><span class="eyebrow"><?php echo e(t('settings')); ?></span><h2><?php echo e(t('appearance_settings')); ?></h2><p class="subtle"><?php echo e(t('appearance_help')); ?></p></div><i class="fas fa-palette settings-card-icon"></i></div>
        <form action="api/update-settings.php" method="post" class="modern-form" id="appearance-settings-form"><?php echo csrf_input(); ?>
          <label class="field"><span><?php echo e(t('language')); ?></span><select name="language" data-setting="language"><?php foreach(supported_languages() as $code): ?><option value="<?php echo e($code); ?>" <?php echo user_language() === $code ? 'selected' : ''; ?>><?php echo e(language_name($code)); ?></option><?php endforeach; ?></select></label>

          <label class="field"><span><?php echo e(t('theme')); ?></span><select name="theme_mode" data-setting="theme"><?php foreach(supported_theme_modes() as $theme): ?><option value="<?php echo e($theme); ?>" <?php echo $prefs['theme_mode'] === $theme ? 'selected' : ''; ?>><?php echo e(t('theme_'.$theme)); ?></option><?php endforeach; ?></select></label>

          <fieldset class="settings-choice-field"><legend><?php echo e(t('accent_color')); ?></legend><div class="accent-options">
            <?php foreach(supported_accent_colors() as $accent): ?>
              <label class="accent-option accent-<?php echo e($accent); ?>"><input type="radio" name="accent_color" value="<?php echo e($accent); ?>" data-setting="accent" <?php echo $prefs['accent_color'] === $accent ? 'checked' : ''; ?>><span class="accent-swatch"></span><strong><?php echo e(t('accent_'.$accent)); ?></strong></label>
            <?php endforeach; ?>
          </div></fieldset>

          <fieldset class="settings-choice-field"><legend><?php echo e(t('interface_density')); ?></legend><div class="density-options">
            <?php foreach(supported_ui_densities() as $density): ?>
              <label class="density-option"><input type="radio" name="ui_density" value="<?php echo e($density); ?>" data-setting="density" <?php echo $prefs['ui_density'] === $density ? 'checked' : ''; ?>><span><strong><?php echo e(t('density_'.$density)); ?></strong></span></label>
            <?php endforeach; ?>
          </div></fieldset>

          <button type="submit" class="btn primary"><i class="fas fa-save"></i><?php echo e(t('save_preferences')); ?></button>
        </form>
      </section>
    </div>
  </main>
</div>
<?php include "part/app-footer.php"; ?>
<script src="assets/js/settings.js?v=1"></script>
</body>
</html>
