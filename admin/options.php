<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/AcademicController.php';
require_once __DIR__ . '/../api/app/controller/OrganizationController.php';
require_once __DIR__ . '/../api/app/controller/WorkflowController.php';
$admin=(new AdminController())->requireDepartmentAccess(['administration'],true);
$academic=new AcademicController(); $org=new OrganizationController();
$isManager=($admin['admin_role']??'')==='super_admin';
$study=$academic->listOptions('student_department',false)->fetch_all(MYSQLI_ASSOC);
$teachers=$academic->listTeachers()->fetch_all(MYSQLI_ASSOC);
$certificates=$academic->listOptions('teacher_certificate',false)->fetch_all(MYSQLI_ASSOC);
$specializations=$academic->listOptions('teacher_specialization',false)->fetch_all(MYSQLI_ASSOC);
$workplaces=$academic->listOptions('staff_workplace',false)->fetch_all(MYSQLI_ASSOC);
$allDepartments=$org->departments(false)->fetch_all(MYSQLI_ASSOC);
$staffCandidates=$org->staffCandidates(false)->fetch_all(MYSQLI_ASSOC);
$pending=[];
foreach((new WorkflowController())->listForAdmin($admin)->fetch_all(MYSQLI_ASSOC) as $r){
    if($r['status']==='pending' && in_array($r['request_type'],[
        'config_option_add','config_option_toggle','academic_department_rename','academic_department_head',
        'organization_department_create','organization_head_assign'],true)) $pending[]=$r;
}
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main setup-page">
<section class="topbar"><div><span class="eyebrow">ڕێکخستنی دامەزراوە</span><h1>ڕێکخستنەکان — بە شێوەیەکی سادە</h1><p class="subtle">تەنها ئەو بەشە بکەرەوە کە پێویستت پێیە. هەر جۆرێک کاری تایبەتی خۆی هەیە.</p></div><a class="btn ghost" href="student-ids.php"><i class="fas fa-id-card"></i> ID ـی خوێندکاران</a></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success" role="status"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error" role="alert"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if(!$isManager): ?><div class="notice">گۆڕانکارییەکانی تۆ وەک داواکاری دەنێردرێن؛ تەنها دوای پەسەندی بەڕێوبەری دامەزراوە چالاک دەبن.</div><?php endif; ?>
<div class="setup-nav" aria-label="هەڵبژاردنی ڕێکخستن">
<a href="#study"><i class="fas fa-graduation-cap"></i><strong>١. بەشەکانی خوێندن</strong><small>ئەو بەشانەی خوێندکار هەڵیاندەبژێرێت</small></a>
<a href="#organization"><i class="fas fa-sitemap"></i><strong>٢. بەشەکانی دامەزراوە</strong><small>کارگێڕی، تۆمار، دارایی و شوێنی کاری فەرمانبەر</small></a>
<a href="#teachers"><i class="fas fa-chalkboard-teacher"></i><strong>٣. بڕوانامە و پسپۆڕی مامۆستا</strong><small>وەک Bachelor و Programming</small></a>
<a href="#approvals"><i class="fas fa-clipboard-check"></i><strong>٤. داواکاری و پەسەندکردن</strong><small><?php echo count($pending); ?> داواکاری چاوەڕوانە</small></a>
</div>

<details class="setup-section" id="study" name="setup-category" open>
<summary><span class="setup-summary-icon"><i class="fas fa-graduation-cap"></i></span><span><strong>١. بەشەکانی خوێندن</strong><small>نموونە: AI، Cyber Security، Computer Science — بەشی خوێندن جیاوازە لە پسپۆڕی مامۆستا</small></span><i class="fas fa-chevron-down setup-chevron"></i></summary>
<div class="setup-body">
<div class="notice">تەنها بەشی <strong>چالاک و پەسەندکراو</strong> لە فۆڕمی تۆمارکردنی خوێندکار دەردەکەوێت. ناوی بەشەکە لێرە زیاد بکە و ئەگەر مامۆستای فەرمی هەیە سەرۆکی بەش دیاری بکە.</div>
<section class="setup-inner"><h3>زیادکردنی بەشی خوێندنی نوێ</h3>
<form method="post" action="api/configuration.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="add_option"><input type="hidden" name="option_type" value="student_department">
<div class="grid two"><label class="field"><span>ناوی بەشی خوێندن</span><input name="label" maxlength="190" placeholder="Artificial Intelligence" required></label>
<label class="field"><span>سەرۆکی بەش (ئارەزوومەندانە)</span><select name="head_teacher_registry_id"><option value="0">دواتر دیاری دەکەم</option><?php foreach($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo e(display_name($teacher).' · '.$teacher['issued_id']); ?></option><?php endforeach; ?></select></label></div>
<button class="btn primary" type="submit"><i class="fas fa-plus"></i> <?php echo $isManager?'زیادکردن و پشتڕاستکردنەوە':'ناردن بۆ پەسەندی بەڕێوبەر'; ?></button>
</form></section>
<section class="setup-inner"><h3>بەشە تۆمارکراوەکان (<?php echo count($study); ?>)</h3>
<?php if(!$study): ?><p class="subtle">هێشتا بەشی خوێندن زیاد نەکراوە.</p><?php else: ?><div class="setup-list">
<?php foreach($study as $d): ?><div class="setup-item"><div class="setup-item-info"><strong><?php echo e($d['label']); ?></strong><small>سەرۆکی بەش: <?php echo e($d['head_teacher_name']?:'دیاری نەکراوە'); ?></small><span class="registry-status <?php echo (int)$d['is_active']?'available':'suspended'; ?>"><?php echo (int)$d['is_active']?'چالاک':'ناچالاک'; ?></span></div>
<div class="setup-item-actions"><form class="setup-inline-form" method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="rename_study_department"><input type="hidden" name="option_id" value="<?php echo (int)$d['id']; ?>"><label>گۆڕینی ناو <input name="label" maxlength="190" value="<?php echo e($d['label']); ?>" required></label><button type="submit" class="btn ghost small">پاشەکەوتکردن</button></form>
<form class="setup-inline-form" method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="set_study_head"><input type="hidden" name="option_id" value="<?php echo (int)$d['id']; ?>"><label>سەرۆکی بەش <select name="head_teacher_registry_id"><option value="0">دیاری نەکراوە</option><?php foreach($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>" <?php echo (int)$d['head_teacher_registry_id']===(int)$teacher['id']?'selected':''; ?>><?php echo e(display_name($teacher)); ?></option><?php endforeach; ?></select></label><button type="submit" class="btn ghost small">پاشەکەوتکردن</button></form>
<form method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="toggle_option"><input type="hidden" name="option_id" value="<?php echo (int)$d['id']; ?>"><button class="btn ghost small" type="submit"><?php echo (int)$d['is_active']?'ناچالاککردن':'چالاککردن'; ?></button></form></div></div><?php endforeach; ?></div><?php endif; ?>
</section><p class="setup-next">هەنگاوی دواتر: <a href="registry-batches.php">لیستی تۆمارکردنی خوێندکار دروست بکە</a> (لە هەژماری فەرمانبەری تۆمار).</p>
</div></details>

<details class="setup-section" id="organization" name="setup-category">
<summary><span class="setup-summary-icon"><i class="fas fa-building"></i></span><span><strong>٢. بەشەکانی دامەزراوە</strong><small>بەشی تۆمار، کارگێڕی، دارایی ... و شوێنی کاری فەرمانبەر</small></span><i class="fas fa-chevron-down setup-chevron"></i></summary>
<div class="setup-body"><div class="notice">ئەم بەشانە بۆ کارکردنی فەرمانبەرانن، <strong>بەشەکانی خوێندن نین</strong>. بۆ زیادکردنی بەشی AI یان Network، بەشی یەکەم بەکاربهێنە.</div>
<div class="grid two"><section class="setup-inner"><h3>دروستکردنی بەشی دامەزراوە</h3><form method="post" action="api/configuration.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="create_org_department">
<label class="field"><span>ناوی بەش</span><input name="name" maxlength="190" required placeholder="Quality Assurance"></label>
<label class="field"><span>کۆدی بەش — بە پیتە بچووکە ئینگلیزییەکان</span><input name="code" maxlength="60" pattern="[a-z0-9_-]{2,60}" required placeholder="quality_assurance"></label>
<label class="field"><span>جۆری بەش</span><select name="department_type"><option value="other">بەشی تر</option><option value="administration">کارگێڕی</option><option value="finance">دارایی</option><option value="student_affairs">کاروباری خوێندکاران</option><option value="human_resources">سەرچاوە مرۆییەکان</option><option value="academic_affairs">کاروباری ئەکادیمی</option><option value="it">IT</option></select></label>
<label class="field"><span>بەشی سەرەوە</span><select name="parent_id" required><?php foreach($allDepartments as $d): if(!(int)$d['is_active']) continue; ?><option value="<?php echo (int)$d['id']; ?>"><?php echo e($d['department_type']==='root'?'دامەزراوە':$d['name']); ?></option><?php endforeach; ?></select></label>
<label class="field"><span>ئەرکەکانی بەش (ئارەزوومەندانە)</span><textarea name="responsibilities" maxlength="2000" rows="2"></textarea></label><button class="btn primary" type="submit"><?php echo $isManager?'دروستکردنی بەش':'ناردنی داواکاری'; ?></button></form></section>
<section class="setup-inner"><h3>دیاریکردنی بەڕێوبەری بەش</h3><form method="post" action="api/configuration.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="assign_org_head"><label class="field"><span>بەشی دامەزراوە</span><select name="department_id" required><option value="">بەش هەڵبژێرە</option><?php foreach($allDepartments as $d): if($d['department_type']==='root'||!(int)$d['is_active']) continue; ?><option value="<?php echo (int)$d['id']; ?>"><?php echo e($d['name']); ?></option><?php endforeach; ?></select></label>
<label class="field"><span>فەرمانبەری تۆمارکراو</span><select name="registry_id" required><option value="">فەرمانبەر هەڵبژێرە</option><?php foreach($staffCandidates as $person): ?><option value="<?php echo (int)$person['id']; ?>"><?php echo e(display_name($person).' · '.$person['issued_id']); ?></option><?php endforeach; ?></select></label><p class="subtle">بەڕێوبەری بەش دەبێت پێشتر فەرمانبەری فەرمی و خاوەن هەژمار بێت.</p><button type="submit" class="btn primary"><?php echo $isManager?'دیاریکردنی بەڕێوبەر':'ناردنی داواکاری'; ?></button></form></section></div>
<section class="setup-inner"><h3>بەشەکانی دامەزراوە</h3><div class="setup-list compact-list"><?php foreach($allDepartments as $d): if($d['department_type']==='root')continue; ?><div class="setup-item"><div class="setup-item-info"><strong><?php echo e($d['name']); ?></strong><small><?php echo e($d['code']); ?> · بەڕێوبەر: <?php echo e($d['head_name']?:'دیاری نەکراوە'); ?></small></div><span class="registry-status <?php echo (int)$d['is_active']?'available':'suspended'; ?>"><?php echo (int)$d['is_active']?'چالاک':'ناچالاک'; ?></span></div><?php endforeach; ?></div></section>
<section class="setup-inner"><h3>شوێنی کاری فەرمانبەر</h3><p class="subtle">ئەو هەڵبژاردنانەی لە فۆڕمی تۆمارکردنی فەرمانبەر دەردەکەون.</p>
<form method="post" action="api/configuration.php" class="modern-form setup-short-form"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="add_option"><input type="hidden" name="option_type" value="staff_workplace"><label class="field"><span>ناوی شوێنی کار</span><input name="label" maxlength="190" required placeholder="Library"></label><button class="btn primary" type="submit"><?php echo $isManager?'زیادکردن':'ناردنی داواکاری'; ?></button></form>
<div class="setup-option-list"><?php foreach($workplaces as $row): ?><div class="option-row"><strong><?php echo e($row['label']); ?></strong><span class="registry-status <?php echo (int)$row['is_active']?'available':'suspended'; ?>"><?php echo (int)$row['is_active']?'چالاک':'ناچالاک'; ?></span><form method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="toggle_option"><input type="hidden" name="option_id" value="<?php echo (int)$row['id']; ?>"><button class="btn ghost small" type="submit"><?php echo (int)$row['is_active']?'ناچالاککردن':'چالاککردن'; ?></button></form></div><?php endforeach; ?></div></section>
</div></details>

<details class="setup-section" id="teachers" name="setup-category">
<summary><span class="setup-summary-icon"><i class="fas fa-chalkboard-teacher"></i></span><span><strong>٣. بڕوانامە و پسپۆڕی مامۆستا</strong><small>بڕوانامە (Master) جیاوازە لە پسپۆڕی (Programming)</small></span><i class="fas fa-chevron-down setup-chevron"></i></summary>
<div class="setup-body"><div class="notice">کاتێک مامۆستا تۆمار دەکرێت، دوو هەڵبژاردن هەیە: <strong>جۆری بڕوانامە</strong> و <strong>پسپۆڕی</strong>. ئەمانە بەشی خوێندنی خوێندکار نین.</div>
<div class="grid two"><?php foreach(['teacher_certificate'=>$certificates,'teacher_specialization'=>$specializations] as $type=>$rows): ?><section class="setup-inner"><h3><?php echo $type==='teacher_certificate'?'جۆری بڕوانامە':'پسپۆڕی مامۆستا'; ?></h3><p class="subtle"><?php echo $type==='teacher_certificate'?'نموونە: Diploma / Bachelor / Master / PhD':'نموونە: Programming / Network / AI'; ?></p>
<form method="post" action="api/configuration.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="add_option"><input type="hidden" name="option_type" value="<?php echo e($type); ?>"><label class="field"><span>ناوی هەڵبژاردن</span><input name="label" maxlength="190" required></label><button class="btn primary" type="submit"><?php echo $isManager?'زیادکردن':'ناردن بۆ پەسەندکردن'; ?></button></form>
<div class="setup-option-list"><?php foreach($rows as $row): ?><div class="option-row"><strong><?php echo e($row['label']); ?></strong><span class="registry-status <?php echo (int)$row['is_active']?'available':'suspended'; ?>"><?php echo (int)$row['is_active']?'چالاک':'ناچالاک'; ?></span><form method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="return_to" value="options"><input type="hidden" name="action" value="toggle_option"><input type="hidden" name="option_id" value="<?php echo (int)$row['id']; ?>"><button class="btn ghost small" type="submit"><?php echo (int)$row['is_active']?'ناچالاککردن':'چالاککردن'; ?></button></form></div><?php endforeach; ?></div></section><?php endforeach; ?></div>
</div></details>

<details class="setup-section" id="approvals" name="setup-category">
<summary><span class="setup-summary-icon"><i class="fas fa-check-double"></i></span><span><strong>٤. داواکاری و پەسەندکردن</strong><small>گۆڕانکارییە نێردراوەکان لێرە دەبینیت؛ بڕیاری کۆتایی لە پەڕەی پەسەندکردن دەدرێت</small></span><span class="setup-count"><?php echo count($pending); ?></span><i class="fas fa-chevron-down setup-chevron"></i></summary>
<div class="setup-body"><div class="section-heading"><div><h3>داواکارییە چاوەڕوانەکان</h3><p class="subtle">بەشی کارگێڕی داواکاری دەنێرێت و بەڕێوبەری دامەزراوە پەسەند یان ڕەتی دەکاتەوە.</p></div><a class="btn primary" href="requests.php">کردنەوەی پەڕەی پەسەندکردن</a></div>
<?php if(!$pending): ?><div class="empty-state">هیچ داواکارییەکی ڕێکخستن چاوەڕوان نییە.</div><?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>داواکاری</th><th>نێرەر</th><th>بەش</th><th>دۆخ</th></tr></thead><tbody><?php foreach(array_slice($pending,0,20) as $r): ?><tr><td><?php echo e($r['title']); ?></td><td><?php echo e($r['requester_name']); ?></td><td><?php echo e($r['department_name']); ?></td><td><span class="registry-status registered">چاوەڕوانی پەسەندکردن</span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<div class="notice">تۆمارکردنی خوێندکاران ڕێڕەوی جیاواز هەیە: تۆمارکار → کارگێڕی → بەڕێوبەر. <a href="registry-batches.php">بینینی لیستەکانی تۆمارکردن</a></div>
</div></details>
</main></div><script>
(function(){function showHash(){var id=decodeURIComponent(location.hash.slice(1));var match=document.getElementById(id);if(match&&match.tagName==='DETAILS'&&match.classList.contains('setup-section')){document.querySelectorAll('details.setup-section').forEach(function(d){d.open=d===match;});}}window.addEventListener('hashchange',showHash);showHash();})();
</script></body></html>
