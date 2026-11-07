<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/AcademicController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['academic_affairs','student_affairs']);
$academic=new AcademicController(); $students=$academic->listStudents(); $defaultYear=$academic->defaultAcademicYear();
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('academic_management')); ?></span><h1><?php echo e(t('student_progression')); ?></h1><p class="subtle"><?php echo e(t('student_progression_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<section class="panel admin-records-panel"><div class="table-scroll"><table class="admin-table progression-table"><thead><tr>
<th><?php echo e(t('full_name')); ?></th><th><?php echo e(t('student_department')); ?></th><th><?php echo e(t('current_stage')); ?></th><th><?php echo e(t('current_group')); ?></th><th><?php echo e(t('academic_status')); ?></th><th><?php echo e(t('progress_decision')); ?></th>
</tr></thead><tbody>
<?php while($row=$students->fetch_assoc()): $active=($row['student_academic_status']??'active')==='active'; $targetYear=$defaultYear; if(!empty($row['academic_year']) && preg_match('/^(\d{4})-(\d{4})$/',$row['academic_year'],$ym)){ $targetYear=((int)$ym[1]+1).'-'.((int)$ym[2]+1); } ?>
<tr>
<td><strong><?php echo e(display_name($row)); ?></strong><small><?php echo e($row['issued_id']); ?><?php if(!empty($row['email'])): ?> · <?php echo e($row['email']); ?><?php endif; ?></small></td>
<td><?php echo e($row['department_label'] ?: $row['student_department']); ?></td>
<td><?php echo (int)($row['student_stage'] ?: 1); ?></td>
<td><?php echo $row['group_code']?e($row['group_code'].' · '.$row['academic_year']):'—'; ?></td>
<td><span class="registry-status <?php echo $active?'available':'registered'; ?>"><?php echo e(t('academic_status_'.($row['student_academic_status'] ?: 'active'))); ?></span></td>
<td>
<?php if($active): ?>
<form class="progress-form" method="post" action="api/student-progress.php"><?php echo csrf_input(); ?><input type="hidden" name="registry_id" value="<?php echo (int)$row['id']; ?>">
<div class="progress-form-grid">
<label><span><?php echo e(t('progress_decision')); ?></span><select name="decision" class="progress-decision"><option value="pass"><?php echo e(t('decision_pass')); ?></option><option value="stay"><?php echo e(t('decision_stay')); ?></option><option value="assign"><?php echo e(t('decision_assign')); ?></option></select></label>
<label class="target-stage-field"><span><?php echo e(t('target_stage')); ?></span><select name="target_stage"><?php for($s=1;$s<=4;$s++): ?><option value="<?php echo $s; ?>" <?php echo $s==(int)($row['student_stage']?:1)?'selected':''; ?>><?php echo $s; ?></option><?php endfor; ?></select></label>
<label><span><?php echo e(t('academic_year')); ?></span><input name="academic_year" value="<?php echo e($targetYear); ?>" pattern="\d{4}-\d{4}" required></label>
<label><span><?php echo e(t('group_code')); ?></span><input name="group_code" maxlength="20" value="<?php echo e($row['group_code'] ?: 'A'); ?>" required></label>
<label class="progress-note-field"><span><?php echo e(t('progress_note')); ?></span><input name="note" maxlength="500"></label>
<button class="btn primary small-btn" type="submit"><?php echo e(t('save_progress')); ?></button>
</div></form>
<?php else: ?><span class="subtle"><?php echo e(t('graduated')); ?></span><?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody></table></div></section>
</main></div><script>
document.querySelectorAll('.progress-form').forEach(function(form){var sel=form.querySelector('.progress-decision'), target=form.querySelector('.target-stage-field');function sync(){target.style.display=sel.value==='assign'?'grid':'none';}sel.addEventListener('change',sync);sync();});
</script></body></html>
