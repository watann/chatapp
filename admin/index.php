<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
$c=new AdminController(); $admin=$c->requireAdmin(); $counts=$c->dashboardCounts();
$isSuper=($admin['admin_role']??'')==='super_admin'; $isHead=($admin['admin_role']??'')==='department_head'; $deptType=(string)($admin['department_type']??'');$deptCode=(string)($admin['department_code']??'');
$canRegistry=$isSuper || $deptCode==='registry' || in_array($deptType,['administration','human_resources','student_affairs'],true);
$canBatch=$canRegistry; $canStudents=$isSuper||in_array($deptType,['academic_affairs','student_affairs'],true);
$canAcademic=$isSuper||$deptType==='academic_affairs'; $canFinance=$isSuper||$deptType==='finance'; $canConfig=$isSuper||($isHead&&$deptType==='administration');
$needsInitialStaff=false;
if($isSuper){require_once __DIR__.'/../api/app/controller/InitialStaffController.php';$needsInitialStaff=(new InitialStaffController())->needed($admin);}
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('admin_portal')); ?></span><h1><?php echo e($isSuper?t('institution_manager'):($admin['department_name']?:t('dashboard'))); ?></h1><p class="subtle"><?php echo e($isSuper?t('simplified_manager_dashboard_help'):t('simplified_department_dashboard_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if($needsInitialStaff): ?><section class="panel starter-banner"><div><h2><i class="fas fa-user-plus"></i> یەکەم فەرمانبەرەکان دروست بکە</h2><p>بۆ ئەوەی تۆمارکردنی خوێندکار بە دروستی کار بکات، پێویستە فەرمانبەری تۆمار و سەرۆکی کارگێڕی هەبن.</p></div><a class="btn primary" href="initial-staff.php">دەستپێکردن</a></section><?php endif; ?>
<section class="admin-stat-grid">
<article class="admin-stat"><i class="fas fa-hourglass-half"></i><div><strong><?php echo (int)$counts['pending_requests']; ?></strong><span><?php echo e(t('pending_requests')); ?></span></div></article>
<?php if($canRegistry): ?><article class="admin-stat"><i class="fas fa-id-card"></i><div><strong><?php echo (int)$counts['records']; ?></strong><span><?php echo e(t('registry_records')); ?></span></div></article><?php endif; ?>
<?php if($canStudents): ?><article class="admin-stat"><i class="fas fa-user-graduate"></i><div><strong><?php echo (int)$counts['active_students']; ?></strong><span><?php echo e(t('students')); ?></span></div></article><?php endif; ?>
<?php if($canFinance): ?><article class="admin-stat"><i class="fas fa-coins"></i><div><strong><?php echo (int)$counts['pending_finance']; ?></strong><span><?php echo e(t('pending_finance_approvals')); ?></span></div></article><?php endif; ?>
</section>
<section class="panel admin-welcome"><div class="section-heading"><div><h2><?php echo e(t('admin_quick_actions')); ?></h2><p class="subtle"><?php echo e(t('only_relevant_actions_help')); ?></p></div></div><div class="admin-quick-actions">
<?php if($isSuper): ?><a class="btn primary" href="requests.php"><i class="fas fa-check-double"></i><?php echo e(t('manager_approvals')); ?></a><a class="btn ghost" href="organization.php"><i class="fas fa-sitemap"></i><?php echo e(t('institution_structure')); ?></a><?php else: ?><a class="btn primary" href="department.php"><i class="fas fa-building"></i><?php echo e(t('my_department')); ?></a><a class="btn ghost" href="requests.php"><i class="fas fa-check-double"></i><?php echo e(t('manager_approvals')); ?></a><?php endif; ?>
<?php if($isSuper || $deptType==='it'): ?><a class="btn ghost" href="moderation.php"><i class="fas fa-flag"></i><?php echo $isSuper?'ڕیپۆرتی پۆستەکان':'ئاگادارکردنەوەی وشەکان'; ?></a><?php endif; ?>
<?php if($canConfig): ?><a class="btn ghost" href="options.php#study"><i class="fas fa-graduation-cap"></i><?php echo e(t('rw_study_departments')); ?></a><?php endif; ?>
<?php if($canBatch): ?><a class="btn ghost" href="registry-batches.php"><i class="fas fa-list-alt"></i><?php echo e(t('rw_batches')); ?></a><?php endif; ?>
<?php if($canConfig): ?><a class="btn ghost" href="options.php"><i class="fas fa-sliders-h"></i><?php echo e(t('central_setup')); ?></a><?php endif; ?>
<?php if($isSuper || $deptCode==='registry' || in_array($deptType,['administration','student_affairs'],true)): ?><a class="btn ghost" href="student-ids.php"><i class="fas fa-copy"></i>ID ـی خوێندکاران</a><?php endif; ?>
<?php if($canRegistry): ?><a class="btn ghost" href="records.php"><i class="fas fa-id-card"></i><?php echo e(t('registry_records')); ?></a><?php endif; ?>
<?php if($canFinance): ?><a class="btn ghost" href="finance.php"><i class="fas fa-coins"></i><?php echo e(t('finance_management')); ?></a><?php endif; ?>
<?php if($canAcademic): ?><a class="btn ghost" href="academic-groups.php"><i class="fas fa-school"></i><?php echo e(t('academic_groups')); ?></a><?php endif; ?>
<?php if($canStudents): ?><a class="btn ghost" href="students.php"><i class="fas fa-user-graduate"></i><?php echo e(t('student_progression')); ?></a><?php endif; ?>
</div></section>
<?php if(!$isSuper): ?><section class="panel"><h2><?php echo e(t('approval_rule')); ?></h2><p class="subtle"><?php echo e(t('approval_rule_help')); ?></p></section><?php endif; ?>
</main></div></body></html>
