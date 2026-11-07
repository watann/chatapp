<?php
require_once __DIR__ . '/../api/app/controller/InitialStaffController.php';
$admin=(new AdminController())->requireSuperAdmin();
$setup=new InitialStaffController();
$missing=$setup->missingRoles($admin);
include __DIR__.'/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__.'/part/sidebar.php'; ?>
<main class="admin-main initial-setup-page">
<section class="topbar"><div><span class="eyebrow">ڕێکخستنی دامەزراوە</span><h1>دامەزراندنی سەرەتایی فەرمانبەران</h1><p class="subtle">تەنها بۆ چارەسەرکردنی کێشەی دەستپێکردن؛ دوای پڕبوونەوەی ئەم دوو پۆستە، تۆمارکردنی نوێ دەبێت بە ڕێڕەوی پەسەندکردن بێت.</p></div><a class="btn ghost" href="registry-batches.php">لیستەکانی تۆمارکردن</a></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<section class="panel setup-status-panel"><div class="section-heading"><h2>دۆخی دامەزراندن</h2></div>
<div class="setup-progress-grid"><article class="setup-progress-card <?php echo !$missing['registry_staff']?'complete':''; ?>"><i class="fas fa-<?php echo !$missing['registry_staff']?'check-circle':'user-plus'; ?>"></i><div><strong>فەرمانبەری بەشی تۆمار</strong><span><?php echo !$missing['registry_staff']?'ئامادەیە':'هێشتا پێویستە'; ?></span></div></article>
<article class="setup-progress-card <?php echo !$missing['administration_head']?'complete':''; ?>"><i class="fas fa-<?php echo !$missing['administration_head']?'check-circle':'user-tie'; ?>"></i><div><strong>سەرۆکی بەشی کارگێڕی</strong><span><?php echo !$missing['administration_head']?'ئامادەیە':'هێشتا پێویستە'; ?></span></div></article></div>
<p class="subtle">قۆناغەکان: ١. دروستکردنی فەرمانبەری تۆمار  ٢. دروستکردنی سەرۆکی کارگێڕی  ٣. تۆمارکردنی خوێندکاران بە لیست و دوو پەسەندی.</p><p class="subtle">هەر فەرمانبەرێک دواتر بە ئیمەیڵ و وشەی نهێنی خۆی لە admin/login.php دەچێتە ژوورەوە؛ باشترە وشەی نهێنی سەرەتایی بگۆڕێت.</p>
</section>
<?php if($missing['registry_staff']||$missing['administration_head']): ?>
<section class="panel setup-form-panel"><div class="section-heading"><div><h2>زیادکردنی فەرمانبەری سەرەتایی</h2><p class="subtle">تۆماری فەرمی، هەژماری چوونەژوورەوە و مۆڵەتی بەشی دیاریکراو بە یەک جار دروست دەکرێن. ئەم کارە لە تۆماری چاودێریدا دەنووسرێت.</p></div></div>
<form class="modern-form registry-form" method="post" action="api/initial-staff.php" autocomplete="off"><?php echo csrf_input(); ?>
<label class="field"><span>جۆری پۆست</span><select name="setup_role" required>
<?php if($missing['registry_staff']): ?><option value="registry_staff">فەرمانبەری بەشی تۆمار — وەرگرتنی خوێندکار</option><?php endif; ?>
<?php if($missing['administration_head']): ?><option value="administration_head">سەرۆکی بەشی کارگێڕی — پشکنین و پەسەندکردنی لیستەکان</option><?php endif; ?>
</select></label>
<div class="grid three">
<label class="field"><span><?php echo e(t('first_name')); ?></span><input name="first_name" maxlength="100" required></label>
<label class="field"><span><?php echo e(t('middle_name')); ?></span><input name="middle_name" maxlength="100" required></label>
<label class="field"><span><?php echo e(t('last_name')); ?></span><input name="last_name" maxlength="100" required></label>
</div>
<div class="grid three">
<label class="field"><span><?php echo e(t('birth_date')); ?></span><input type="date" name="birth_date" max="<?php echo e(date('Y-m-d')); ?>" required></label>
<label class="field"><span><?php echo e(t('gender')); ?></span><select name="gender" required><?php foreach(supported_genders() as $g): ?><option value="<?php echo e($g); ?>"><?php echo e(gender_label($g)); ?></option><?php endforeach; ?></select></label>
<label class="field"><span><?php echo e(t('identity_number')); ?></span><input name="identity_number" maxlength="100" required></label>
</div>
<div class="grid two">
<label class="field"><span>ئیمەیڵی چوونەژوورەوە</span><input name="email" type="email" maxlength="190" required autocomplete="new-email"></label>
<label class="field"><span>وشەی نهێنی سەرەتایی (لانی کەم ١٠ پیت)</span><input name="password" type="password" minlength="10" maxlength="1024" autocomplete="new-password" required></label>
</div>
<div class="notice">بەڕێوبەری سەرەکی ئەم تۆمارکردنە بە شێوەی تایبەتی دەکات، چونکە هێشتا بەشی تۆمار/کارگێڕی ئامادە نییە. دوای ئەوە، تۆمارکردنی ئاسایی بە هەردوو قۆناغی پەسەندکردن دەبێت.</div>
<button class="btn primary" type="submit" onclick="return confirm('دڵنیایت لە دامەزراندنی ئەم فەرمانبەرە و پێدانی مۆڵەتی بەشەکە؟')"><i class="fas fa-user-check"></i> تۆمارکردن و پێدانی مۆڵەت</button>
</form></section>
<?php else: ?>
<section class="panel"><h2>ڕێکخستنی سەرەتایی تەواوە</h2><p class="subtle">ئێستا فەرمانبەری تۆمار دەتوانێت لیستی خوێندکار دروست بکات، سەرۆکی کارگێڕی پشکنینی بکات و بەڕێوبەر پەسەندی کۆتایی بدات. ئەم فۆڕمە چیتر بۆ دامەزراندنی فەرمانبەری ئاسایی بەکار نایەت.</p><a class="btn primary" href="registry-batches.php">چوون بۆ لیستەکانی تۆمارکردن</a></section>
<?php endif; ?>
</main></div></body></html>
