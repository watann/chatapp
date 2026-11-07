<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/OrganizationController.php';
require_once __DIR__ . '/../api/app/controller/WorkflowController.php';
$ac=new AdminController(); $admin=$ac->requireAdmin();
if(($admin['admin_role']??'')==='super_admin'){ header('Location: organization.php'); exit; }
$org=new OrganizationController(); $workflow=new WorkflowController();
$dept=$org->department((int)$admin['department_id']); if(!$dept){ header('Location: index.php'); exit; }
$members=$org->members((int)$dept['id'])->fetch_all(MYSQLI_ASSOC);
$candidates=(($admin['admin_role']??'')==='department_head')?$org->staffCandidates(true)->fetch_all(MYSQLI_ASSOC):[];
$pending=$workflow->pendingCount($admin);
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('my_department')); ?></span><h1><?php echo e($dept['name']); ?></h1><p class="subtle"><?php echo e($dept['responsibilities']?:t('department_team_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<section class="admin-stat-grid">
<article class="admin-stat"><i class="fas fa-users"></i><div><strong><?php echo count($members); ?></strong><span><?php echo e(t('staff_members')); ?></span></div></article>
<article class="admin-stat"><i class="fas fa-hourglass-half"></i><div><strong><?php echo (int)$pending; ?></strong><span><?php echo e(t('pending_requests')); ?></span></div></article>
</section>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(t('department_manager_page')); ?></h2><p class="subtle"><?php echo e(t('department_manager_page_help')); ?></p></div></div>
<div class="admin-quick-actions">
<a class="btn ghost" href="requests.php"><i class="fas fa-check-double"></i><?php echo e(t('manager_approvals')); ?></a>
<?php if(($admin['department_type']??'')==='administration' && ($admin['admin_role']??'')==='department_head'): ?><a class="btn primary" href="study-departments.php"><i class="fas fa-graduation-cap"></i><?php echo e(t('rw_study_departments')); ?></a><a class="btn ghost" href="options.php"><i class="fas fa-sliders-h"></i><?php echo e(t('central_setup')); ?></a><a class="btn ghost" href="records.php"><i class="fas fa-id-card"></i><?php echo e(t('registry_records')); ?></a><?php endif; ?>
<?php if(($admin['department_type']??'')==='finance'): ?><a class="btn primary" href="finance.php"><i class="fas fa-coins"></i><?php echo e(t('finance_management')); ?></a><?php endif; ?>
<?php if(($admin['department_type']??'')==='academic_affairs'): ?><a class="btn primary" href="academic-groups.php"><i class="fas fa-school"></i><?php echo e(t('academic_groups')); ?></a><a class="btn ghost" href="grades.php"><i class="fas fa-clipboard-check"></i><?php echo e(t('grade_approval')); ?></a><?php endif; ?>
<?php if(($admin['admin_role']??'')==='department_head' && ($admin['department_type']??'')==='student_affairs'): ?><a class="btn primary" href="students.php"><i class="fas fa-user-graduate"></i><?php echo e(t('student_progression')); ?></a><a class="btn ghost" href="records.php"><i class="fas fa-id-card"></i><?php echo e(t('registry_records')); ?></a><?php endif; ?>
<?php if(($admin['admin_role']??'')==='department_head' && ($admin['department_type']??'')==='human_resources'): ?><a class="btn primary" href="records.php"><i class="fas fa-id-card"></i><?php echo e(t('registry_records')); ?></a><?php endif; ?>
<?php if((in_array(($admin['department_type']??''),['administration','student_affairs','human_resources'],true) || ($admin['department_code']??'')==='registry')): ?><a class="btn primary" href="registry-batches.php"><i class="fas fa-list-alt"></i><?php echo e(t('rw_batches')); ?></a><?php endif; ?>
</div></section>
<section class="panel department-team-panel"><div class="section-heading"><div><h2><?php echo e(t('department_staff')); ?></h2><p class="subtle"><?php echo e(t('department_team_help')); ?></p></div></div><div class="admin-list">
<?php foreach($members as $m): ?><article class="admin-list-row"><div class="admin-list-icon"><i class="fas <?php echo $m['admin_role']==='department_head'?'fa-user-tie':'fa-user'; ?>"></i></div><div><strong><?php echo e($m['full_name']); ?></strong><span><?php echo e($m['admin_role']==='department_head'?t('department_head'):t('department_staff')); ?> · <?php echo e($m['login_email']); ?></span></div></article><?php endforeach; ?>
</div></section>
<?php if(($admin['admin_role']??'')==='department_head'): ?><section class="panel"><div class="section-heading"><div><h2><?php echo e(t('request_department_employee')); ?></h2><p class="subtle"><?php echo e(t('manager_approval_required_help')); ?></p></div></div>
<form method="post" action="api/requests.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="action" value="submit"><input type="hidden" name="request_type" value="staff_add"><input type="hidden" name="title" value="<?php echo e(t('add_employee_request_title')); ?>"><input type="hidden" name="details" value="<?php echo e(t('add_employee_request_details')); ?>"><label class="field"><span><?php echo e(t('staff_member')); ?></span><select name="target_registry_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($candidates as $person): ?><option value="<?php echo (int)$person['id']; ?>"><?php echo e(display_name($person).' · '.$person['issued_id']); ?></option><?php endforeach; ?></select></label><button class="btn primary" type="submit"><i class="fas fa-paper-plane"></i><?php echo e(t('send_for_manager_approval')); ?></button></form></section><?php endif; ?>
</main></div></body></html>
