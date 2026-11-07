<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/AcademicController.php';
$admin=(new AdminController())->requireDepartmentAccess(['administration'],true);
$academic=new AcademicController(); $departments=$academic->listOptions('student_department',false)->fetch_all(MYSQLI_ASSOC);
$teachers=$academic->listTeachers()->fetch_all(MYSQLI_ASSOC);
$isManager=($admin['admin_role']??'')==='super_admin';
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('academic_management')); ?></span><h1><?php echo e(t('rw_study_departments')); ?></h1><p class="subtle"><?php echo e(t('rw_study_help')); ?></p></div><a class="btn ghost" href="options.php"><?php echo e(t('central_setup')); ?></a></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if(!$isManager): ?><div class="notice"><?php echo e(t('rw_study_pending_help')); ?></div><?php endif; ?>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(t('rw_new_study_department')); ?></h2><p class="subtle"><?php echo e(t('rw_study_dropdown_help')); ?></p></div></div>
<form class="modern-form" method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="add_option"><input type="hidden" name="option_type" value="student_department">
<div class="grid two"><label class="field"><span><?php echo e(t('rw_study_name')); ?></span><input name="label" maxlength="190" required placeholder="Computer Science"></label>
<label class="field"><span><?php echo e(t('academic_department_head')); ?></span><select name="head_teacher_registry_id"><option value="0"><?php echo e(t('not_assigned')); ?></option><?php foreach($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo e(display_name($teacher).' · '.$teacher['issued_id']); ?></option><?php endforeach; ?></select></label></div>
<button class="btn primary" type="submit"><i class="fas fa-plus"></i> <?php echo e(t($isManager?'rw_confirm_study':'send_for_manager_approval')); ?></button></form>
</section>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2><?php echo e(t('rw_active_study_departments')); ?></h2><p class="subtle"><?php echo e(t('rw_study_dropdown_help')); ?></p></div></div>
<?php if(!$departments): ?><div class="empty-state"><?php echo e(t('rw_no_study_departments')); ?></div><?php endif; ?>
<div class="rw-department-grid">
<?php foreach($departments as $d): ?><article class="rw-department-item"><div class="section-heading"><div><h3><?php echo e($d['label']); ?></h3><p class="subtle"><?php echo e(t('academic_department_head')); ?>: <?php echo e($d['head_teacher_name']?:t('not_assigned')); ?></p></div><span class="registry-status <?php echo (int)$d['is_active']?'available':'suspended'; ?>"><?php echo e(t((int)$d['is_active']?'active':'inactive')); ?></span></div>
<form class="modern-form" method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="rename_study_department"><input type="hidden" name="option_id" value="<?php echo (int)$d['id']; ?>"><label class="field"><span><?php echo e(t('rw_study_name')); ?></span><input name="label" maxlength="190" value="<?php echo e($d['label']); ?>" required></label><button class="btn ghost small" type="submit"><?php echo e(t($isManager?'rw_confirm_change':'send_for_manager_approval')); ?></button></form>
<form class="modern-form" method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="set_study_head"><input type="hidden" name="option_id" value="<?php echo (int)$d['id']; ?>"><label class="field"><span><?php echo e(t('academic_department_head')); ?></span><select name="head_teacher_registry_id"><option value="0"><?php echo e(t('not_assigned')); ?></option><?php foreach($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>" <?php echo (int)$d['head_teacher_registry_id']===(int)$teacher['id']?'selected':''; ?>><?php echo e(display_name($teacher).' · '.$teacher['issued_id']); ?></option><?php endforeach; ?></select></label><button class="btn ghost small" type="submit"><?php echo e(t($isManager?'rw_confirm_change':'send_for_manager_approval')); ?></button></form>
<form method="post" action="api/configuration.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="toggle_option"><input type="hidden" name="option_id" value="<?php echo (int)$d['id']; ?>"><button class="btn ghost small danger-btn" type="submit"><?php echo e(t($isManager?((int)$d['is_active']?'deactivate':'activate'):'send_for_manager_approval')); ?></button></form>
</article><?php endforeach; ?>
</div></section>
</main></div></body></html>
