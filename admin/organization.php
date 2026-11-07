<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/OrganizationController.php';
$ac=new AdminController(); $admin=$ac->requireAdmin();
if(($admin['admin_role']??'')!=='super_admin'){ header('Location: department.php'); exit; }
$org=new OrganizationController();
require_once __DIR__.'/../api/app/controller/InitialStaffController.php';
$needsInitialStaff=(new InitialStaffController())->needed($admin);
$allDepartments=$org->departments(false)->fetch_all(MYSQLI_ASSOC);
$deptById=[]; foreach($allDepartments as $d) $deptById[(int)$d['id']]=$d;
$departmentDepth=function($departmentId) use (&$deptById){ $depth=0;$seen=[];$id=(int)$departmentId;while(isset($deptById[$id])&&!empty($deptById[$id]['parent_id'])&&$depth<12){if(isset($seen[$id]))break;$seen[$id]=1;$id=(int)$deptById[$id]['parent_id'];$depth++;}return max(0,$depth-1);};
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('institution_structure')); ?></span><h1><?php echo e(t('organization_management')); ?></h1><p class="subtle"><?php echo e(t('organization_simplified_help')); ?></p></div><div><a class="btn primary" href="options.php"><i class="fas fa-plus"></i><?php echo e(t('central_setup')); ?></a></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if($needsInitialStaff): ?><section class="panel starter-banner"><div><h2>پێش دەستپێکردنی وەرگرتنی خوێندکار</h2><p>فەرمانبەری تۆمار و سەرۆکی کارگێڕی لە یەک فۆڕمی سادەدا دابمەزرێنە؛ هەژمار و مۆڵەتیان خۆکارانە دروست دەبێت.</p></div><a class="btn primary" href="initial-staff.php">دامەزراندنی سەرەتایی</a></section><?php endif; ?>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(t('institution_structure')); ?></h2><p class="subtle"><?php echo e(t('structure_read_only_additions_help')); ?></p></div></div><div class="organization-tree">
<?php foreach($allDepartments as $d): if($d['department_type']==='root') continue; ?>
<article class="organization-node <?php echo !(int)$d['is_active']?'muted-node':''; ?>" style="--org-depth:<?php echo (int)$departmentDepth((int)$d['id']); ?>">
<div class="organization-node-icon"><i class="fas <?php echo $d['department_type']==='finance'?'fa-coins':($d['department_type']==='academic_affairs'?'fa-graduation-cap':($d['department_type']==='it'?'fa-laptop-code':'fa-sitemap')); ?>"></i></div>
<div class="organization-node-main"><strong><?php echo e($d['name']); ?></strong><span><?php echo e($d['code']); ?> · <?php echo (int)$d['member_count']; ?> <?php echo e(t('staff_members')); ?></span><small><?php echo e(t('department_head')); ?>: <?php echo e($d['head_name']?:t('not_assigned')); ?></small><?php if(!empty($d['responsibilities'])): ?><small class="department-responsibility"><?php echo e($d['responsibilities']); ?></small><?php endif; ?></div>
<form method="post" action="api/organization.php" class="inline-form"><?php echo csrf_input(); ?><input type="hidden" name="action" value="toggle_department"><input type="hidden" name="department_id" value="<?php echo (int)$d['id']; ?>"><button class="btn ghost small-btn" type="submit"><?php echo e((int)$d['is_active']?t('deactivate'):t('activate')); ?></button></form>
</article>
<?php endforeach; ?></div></section>
<?php foreach($allDepartments as $dept): if($dept['department_type']==='root') continue; $members=$org->members((int)$dept['id'])->fetch_all(MYSQLI_ASSOC); ?>
<section class="panel department-team-panel"><div class="section-heading"><div><h2><?php echo e($dept['name']); ?></h2><p class="subtle"><?php echo e(t('department_team_help')); ?></p></div></div><div class="admin-list">
<?php if(!$members): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_department_staff')); ?></span></div><?php endif; ?>
<?php foreach($members as $m): ?><article class="admin-list-row"><div class="admin-list-icon"><i class="fas <?php echo $m['admin_role']==='department_head'?'fa-user-tie':'fa-user'; ?>"></i></div><div><strong><?php echo e($m['full_name']); ?></strong><span><?php echo e($m['admin_role']==='department_head'?t('department_head'):t('department_staff')); ?> · <?php echo e($m['login_email']); ?></span></div><?php if($m['admin_role']==='department_staff'): ?><form method="post" action="api/organization.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="remove_employee"><input type="hidden" name="admin_user_id" value="<?php echo (int)$m['id']; ?>"><button class="icon-btn danger-icon" type="submit" title="<?php echo e(t('remove')); ?>"><i class="fas fa-user-minus"></i></button></form><?php endif; ?></article><?php endforeach; ?>
</div></section>
<?php endforeach; ?>
</main></div></body></html>
