<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/InstitutionController.php';
require_once __DIR__ . '/../api/app/controller/AcademicController.php';
$ac = new AdminController(); $admin=$ac->requireDepartmentAccess(['administration','human_resources','student_affairs','registry']);
$rc = new InstitutionController(); $allowedRecordTypes=$rc->allowedRecordTypes($admin); $records=$rc->listRecords();
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('registry_records')); ?></span><h1><?php echo e(t('institution_registry')); ?></h1><p class="subtle"><?php echo e(t('registry_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_new_issued_id'])): ?><section class="panel issued-id-card"><span><?php echo e(t('generated_id')); ?></span><strong class="ltr-code"><?php echo e($_SESSION['admin_new_issued_id']); unset($_SESSION['admin_new_issued_id']); ?></strong><small><?php echo e(t('give_id_to_person')); ?></small></section><?php endif; ?>

<?php if(($admin['admin_role']??'')==='super_admin' || ($admin['department_code']??'')==='registry' || in_array(($admin['department_type']??''),['administration','student_affairs'],true)): ?>
<section class="panel official-id-shortcut"><div><h2>پێویستت بە ID ـی خوێندکارە؟</h2><p class="subtle">دوای پەسەندی کۆتایی، ID ـەکان لە پەڕەیەکی جیاواز بە ناو و بەشی خوێندکار دەردەکەون.</p></div><a class="btn primary" href="student-ids.php"><i class="fas fa-copy"></i> بینین و کۆپیکردنی ID</a></section>
<?php endif; ?>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(t('rw_official_records')); ?></h2><p class="subtle"><?php echo e(t('rw_official_help')); ?></p></div><a class="btn primary" href="registry-batches.php"><i class="fas fa-list-alt"></i> <?php echo e(t('rw_open_lists')); ?></a></div></section>

<section class="panel admin-records-panel"><div class="section-heading"><h2><?php echo e(t('registry_records')); ?></h2></div><div class="table-scroll"><table class="admin-table"><thead><tr><th><?php echo e(t('generated_id')); ?></th><th><?php echo e(t('full_name')); ?></th><th><?php echo e(t('person_type')); ?></th><th><?php echo e(t('identity_number')); ?></th><th><?php echo e(t('details')); ?></th><th><?php echo e(t('status')); ?></th><th><?php echo e(t('actions')); ?></th></tr></thead><tbody>
<?php while($row=$records->fetch_assoc()): ?>
<tr>
<td><strong class="ltr-code"><?php echo e($row['issued_id']); ?></strong></td>
<td><?php echo e(display_name($row)); ?><small><?php echo e($row['birth_date']); ?> · <?php echo e(gender_label($row['gender'])); ?></small></td>
<td><?php echo e(role_label($row['person_type'])); ?></td>
<td><?php echo e($row['identity_number']); ?></td>
<td>
<?php if($row['person_type']==='student'): ?>
  <?php echo e($row['department_label']); ?>
  <small><?php echo e(t('student_stage')); ?> <?php echo (int)($row['student_stage']?:1); ?><?php if($row['group_code']): ?> · <?php echo e(t('student_group')); ?> <?php echo e($row['group_code']); ?> · <?php echo e($row['academic_year']); ?><?php endif; ?></small>
<?php elseif($row['person_type']==='staff'): echo e($row['workplace_label']); else: echo e(trim(($row['certificate_label'] ? $row['certificate_label'].' · ' : '').$row['specialization_label'])); endif; ?>
</td>
<td><span class="registry-status <?php echo e($row['status']); ?>"><?php echo e(t('registry_status_'.$row['status'])); ?></span><?php if(!empty($row['linked_email'])): ?><small><?php echo e($row['linked_email']); ?></small><?php endif; ?></td>
<td><?php if(($admin['admin_role']??'')==='super_admin' || ($admin['admin_role']??'')==='department_head'): ?><form method="post" action="api/record-status.php"><?php echo csrf_input(); ?><input type="hidden" name="registry_id" value="<?php echo (int)$row['id']; ?>"><button class="btn small ghost" type="submit"><?php echo e(t($row['status']==='suspended'?'restore_account':'suspend_account')); ?></button></form><?php else: ?>—<?php endif; ?></td>
</tr>
<?php endwhile; ?>
</tbody></table></div></section>
</main></div></body></html>
