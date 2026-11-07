<?php
$adminPage = basename($_SERVER['PHP_SELF'] ?? '');
$isSuper = ($admin['admin_role'] ?? '') === 'super_admin';
$isHead = ($admin['admin_role'] ?? '') === 'department_head';
$deptType = (string)($admin['department_type'] ?? '');
$deptCode=(string)($admin['department_code']??'');
$canRegistry = $isSuper || $deptCode==='registry' || in_array($deptType,['administration','human_resources','student_affairs'],true);
$canBatch = $isSuper || $deptCode==='registry' || in_array($deptType,['administration','human_resources','student_affairs'],true);
$canStudents = $isSuper || in_array($deptType,['academic_affairs','student_affairs'],true);
$canAcademic = $isSuper || $deptType==='academic_affairs';
$canFinance = $isSuper || $deptType==='finance';
$canConfig = $isSuper || ($isHead && $deptType==='administration');
$canRequests = $isSuper || !empty($admin['department_id']);
require_once __DIR__.'/../../api/app/post_moderation.php';
$canModerate=moderation_can_access($admin);
$moderationBadge=0;
if($canModerate){
    try{
        $moderationCounts=moderation_alert_counts($admin);
        $moderationBadge=$isSuper ? $moderationCounts['reports'] : $moderationCounts['words'];
    }catch(Throwable $e){$moderationBadge=0;}
}

$showInitialStaff = false;
if($isSuper){
    require_once __DIR__.'/../../api/app/controller/InitialStaffController.php';
    $showInitialStaff = (new InitialStaffController())->needed($admin);
}
?>
<aside class="admin-sidebar">
  <a class="brand admin-brand" href="index.php"><img class="brand-logo" src="../assets/img/project-logo.jpg" alt=""><span><?php echo e(t('admin_portal')); ?></span></a>
  <div class="admin-identity"><i class="fas fa-user-shield"></i><div><strong><?php echo e($admin['full_name']); ?></strong><span><?php echo e($isSuper?t('institution_manager'):($isHead?t('department_head'):t('department_staff'))); ?><?php if(!$isSuper && !empty($admin['department_name'])): ?> · <?php echo e($admin['department_name']); ?><?php endif; ?></span></div></div>
  <nav class="side-nav admin-nav">
    <a class="<?php echo $adminPage==='index.php'?'active':''; ?>" href="index.php"><i class="fas fa-tachometer-alt"></i><span><?php echo e(t('dashboard')); ?></span></a>
    <?php if($isSuper): ?><a class="<?php echo $adminPage==='organization.php'?'active':''; ?>" href="organization.php"><i class="fas fa-sitemap"></i><span><?php echo e(t('institution_structure')); ?></span></a><?php else: ?><a class="<?php echo $adminPage==='department.php'?'active':''; ?>" href="department.php"><i class="fas fa-building"></i><span><?php echo e(t('my_department')); ?></span></a><?php endif; ?>
    <?php if($showInitialStaff): ?><a class="<?php echo $adminPage==='initial-staff.php'?'active':''; ?>" href="initial-staff.php"><i class="fas fa-user-plus"></i><span>دامەزراندنی سەرەتایی</span></a><?php endif; ?>
    <?php if($canRequests): ?><a class="<?php echo $adminPage==='requests.php'?'active':''; ?>" href="requests.php"><i class="fas fa-check-double"></i><span><?php echo e(t('manager_approvals')); ?></span></a><?php endif; ?>
    <?php if($canModerate): ?><a class="<?php echo $adminPage==='moderation.php'?'active':''; ?>" href="moderation.php"><i class="fas fa-flag"></i><span><?php echo $isSuper?'ڕیپۆرتی پۆستەکان':'ئاگادارکردنەوەی IT'; ?></span><span class="moderation-nav-badge" data-moderation-badge <?php echo $moderationBadge<=0?'hidden':''; ?>><?php echo (int)$moderationBadge; ?></span></a><?php endif; ?>

    <?php if($canConfig): ?><a class="<?php echo $adminPage==='options.php'?'active':''; ?>" href="options.php"><i class="fas fa-sliders-h"></i><span><?php echo e(t('central_setup')); ?></span></a><?php endif; ?>
    <?php if($canBatch): ?><a class="<?php echo in_array($adminPage,['registry-batches.php','registry-batch.php'],true)?'active':''; ?>" href="registry-batches.php"><i class="fas fa-list-alt"></i><span><?php echo e(t('rw_batches')); ?></span></a><?php endif; ?>
    <?php if($canFinance): ?><a class="<?php echo $adminPage==='finance.php'?'active':''; ?>" href="finance.php"><i class="fas fa-coins"></i><span><?php echo e(t('finance')); ?></span></a><?php endif; ?>
    <?php if($isSuper || $deptCode==='registry' || in_array($deptType,['administration','student_affairs'],true)): ?><a class="<?php echo $adminPage==='student-ids.php'?'active':''; ?>" href="student-ids.php"><i class="fas fa-id-card"></i><span>ID ـی خوێندکاران</span></a><?php endif; ?>
    <?php if($canRegistry): ?><a class="<?php echo $adminPage==='records.php'?'active':''; ?>" href="records.php"><i class="fas fa-id-card"></i><span><?php echo e(t('registry_records')); ?></span></a><?php endif; ?>
    <?php if($canStudents): ?><a class="<?php echo $adminPage==='students.php'?'active':''; ?>" href="students.php"><i class="fas fa-user-graduate"></i><span><?php echo e(t('student_progression')); ?></span></a><?php endif; ?>
    <?php if($canAcademic): ?><a class="<?php echo $adminPage==='academic-groups.php'?'active':''; ?>" href="academic-groups.php"><i class="fas fa-school"></i><span><?php echo e(t('academic_groups')); ?></span></a><a class="<?php echo $adminPage==='grades.php'?'active':''; ?>" href="grades.php"><i class="fas fa-clipboard-check"></i><span><?php echo e(t('grade_approval')); ?></span></a><?php endif; ?>
    <?php if($isSuper): ?><a class="<?php echo $adminPage==='audit.php'?'active':''; ?>" href="audit.php"><i class="fas fa-history"></i><span><?php echo e(t('audit_log')); ?></span></a><?php endif; ?>
    <a href="../login.php"><i class="fas fa-users"></i><span><?php echo e(t('social_platform')); ?></span></a>
    <form class="side-nav-form" method="post" action="api/logout.php"><?php echo csrf_input(); ?><button type="submit"><i class="fas fa-sign-out-alt"></i><span><?php echo e(t('logout')); ?></span></button></form>
  </nav>
  <form class="admin-language-switch" method="get"><label><span><?php echo e(t('language')); ?></span><select name="lang" onchange="this.form.submit()"><?php foreach(supported_languages() as $code): ?><option value="<?php echo e($code); ?>" <?php echo user_language()===$code?'selected':''; ?>><?php echo e(language_name($code)); ?></option><?php endforeach; ?></select></label></form>
  <?php if($canModerate): ?><script src="../assets/js/moderation-notifications.js?v=1" defer></script><?php endif; ?>
</aside>
