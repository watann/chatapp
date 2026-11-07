<?php
require_once __DIR__ . '/../api/app/controller/RegistryBatchController.php';
$admin=(new AdminController())->requireAdmin(); $workflow=new RegistryBatchController();
if(!$workflow->canAccess($admin)){header('Location: index.php');exit;}
$id=(int)($_GET['id']??0);
try{ $batch=$workflow->batch($id,$admin); $items=$workflow->items($id,$admin); $events=$workflow->events($id,$admin); }
catch(Throwable $e){$_SESSION['admin_flash_error']=$e->getMessage();header('Location: registry-batches.php');exit;}
$canEdit=$workflow->canEdit($batch,$admin);
$editId=(int)($_GET['edit']??0); $editing=null;
foreach($items as $row){if($canEdit && (int)$row['id']===$editId){$editing=json_decode($row['payload_json'],true); $editing['id']=(int)$row['id'];break;}}
$data=$editing?:[];
$academic=new AcademicController();
$departments=$academic->listOptions('student_department',true)->fetch_all(MYSQLI_ASSOC);
$certificates=$academic->listOptions('teacher_certificate',true)->fetch_all(MYSQLI_ASSOC);
$specializations=$academic->listOptions('teacher_specialization',true)->fetch_all(MYSQLI_ASSOC);
$workplaces=$academic->listOptions('staff_workplace',true)->fetch_all(MYSQLI_ASSOC);
$defaultYear=$academic->defaultAcademicYear();
$isOwner=$workflow->canDraft($admin)&&(int)$batch['created_by_admin_id']===(int)$admin['id'];
$isHead=$workflow->canReviewAdministration($admin); $isManager=($admin['admin_role']??'')==='super_admin';
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('rw_batches')); ?> / #<?php echo $id; ?></span><h1><?php echo e($batch['title']); ?></h1><p class="subtle"><?php echo e(t('rw_flow')); ?></p></div><a class="btn ghost" href="registry-batches.php"><?php echo e(t('rw_back')); ?></a></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if($batch['status']==='approved' && $batch['person_type']==='student'): ?><section class="panel issued-id-card"><div><strong>ئەم لیستە پەسەند کراوە.</strong><p class="subtle">ID ـی هەر خوێندکارێک لە «ID ـی خوێندکاران» ببینە. ژمارەی لیست یاخود ژمارەی ڕیزی خوارەوە ID ـی خوێندکار نییە.</p></div><a class="btn primary" href="student-ids.php">بینینی ID ـی خوێندکاران</a></section><?php endif; ?>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(role_label($batch['person_type'])); ?> · <?php echo count($items).' '.e(t('rw_count')); ?></h2><p class="subtle"><?php echo e(t('rw_status_'.$batch['status'])); ?></p></div></div>
<div class="rw-steps"><span class="rw-step"><?php echo e(t('rw_stage_registry')); ?></span><span>←</span><span class="rw-step"><?php echo e(t('rw_stage_administration')); ?></span><span>←</span><span class="rw-step"><?php echo e(t('rw_stage_manager')); ?></span><span>←</span><span class="rw-step"><?php echo e(t('rw_official_records')); ?></span></div>
<?php if(!empty($batch['administration_note'])): ?><div class="notice"><strong><?php echo e(t('rw_admin_note')); ?>:</strong> <?php echo e($batch['administration_note']); ?></div><?php endif; ?>
<?php if(!empty($batch['manager_note'])): ?><div class="notice"><strong><?php echo e(t('rw_manager_note')); ?>:</strong> <?php echo e($batch['manager_note']); ?></div><?php endif; ?>
<?php if($isOwner && in_array($batch['status'],['draft','returned_registry'],true) && count($items)>0): ?>
<form method="post" action="api/registry-batches.php" class="rw-action-form"><?php echo csrf_input(); ?><input type="hidden" name="batch_id" value="<?php echo $id; ?>"><button class="btn primary" type="submit" name="action" value="submit"><i class="fas fa-paper-plane"></i> <?php echo e(t('rw_send_administration')); ?></button></form>
<?php endif; ?>
<?php if($isHead && in_array($batch['status'],['administration_review','returned_administration'],true)): ?>
<div class="rw-review"><form method="post" action="api/registry-batches.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="batch_id" value="<?php echo $id; ?>"><label class="field"><span><?php echo e(t('review_note')); ?></span><input name="note" maxlength="1000" placeholder="<?php echo e(t('rw_return_reason')); ?>"></label><div class="rw-review-buttons"><button class="btn primary" type="submit" name="action" value="administration_approve" <?php echo count($items)===0?'disabled':''; ?>><?php echo e(t('rw_admin_approve')); ?></button><button class="btn ghost danger-btn" type="submit" name="action" value="administration_return"><?php echo e(t('rw_return_registry')); ?></button></div></form></div>
<?php endif; ?>
<?php if($isManager && $batch['status']==='manager_review'): ?>
<div class="notice"><?php echo e(t('rw_atomic_help')); ?></div>
<form method="post" action="api/registry-batches.php" class="modern-form rw-review"><?php echo csrf_input(); ?><input type="hidden" name="batch_id" value="<?php echo $id; ?>"><label class="field"><span><?php echo e(t('review_note')); ?></span><input name="note" maxlength="1000" placeholder="<?php echo e(t('rw_return_reason')); ?>"></label><div class="rw-review-buttons"><button class="btn primary" name="action" value="manager_approve" <?php echo count($items)===0?'disabled':''; ?> type="submit" onclick="return confirm(<?php echo e(json_encode(t('rw_final_confirmation'),JSON_UNESCAPED_UNICODE)); ?>)"><?php echo e(t('rw_manager_approve')); ?></button><button class="btn ghost danger-btn" type="submit" name="action" value="manager_return"><?php echo e(t('rw_return_administration')); ?></button></div></form>
<?php endif; ?>
</section>
<?php if($canEdit): ?>
<section class="panel" id="rw-editor"><div class="section-heading"><div><h2><?php echo e($editing?t('rw_edit_person'):t('rw_add_person')); ?></h2><p class="subtle"><?php echo e(t('rw_edit_help')); ?></p></div><?php if($editing): ?><a class="btn ghost" href="registry-batch.php?id=<?php echo $id; ?>#rw-editor"><?php echo e(t('rw_cancel_edit')); ?></a><?php endif; ?></div>
<form class="modern-form registry-form" method="post" action="api/registry-batches.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="batch_id" value="<?php echo $id; ?>"><input type="hidden" name="item_id" value="<?php echo (int)($editing['id']??0); ?>">
<div class="grid three">
<?php foreach(['first_name','middle_name','last_name'] as $field): ?><label class="field"><span><?php echo e(t($field)); ?></span><input name="<?php echo e($field); ?>" value="<?php echo e($data[$field]??''); ?>" maxlength="100" required></label><?php endforeach; ?>
</div>
<div class="grid three"><label class="field"><span><?php echo e(t('birth_date')); ?></span><input type="date" name="birth_date" max="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e($data['birth_date']??''); ?>" required></label>
<label class="field"><span><?php echo e(t('gender')); ?></span><select name="gender" required><?php foreach(supported_genders() as $g): ?><option value="<?php echo e($g); ?>" <?php echo ($data['gender']??'')===$g?'selected':''; ?>><?php echo e(gender_label($g)); ?></option><?php endforeach; ?></select></label>
<label class="field"><span><?php echo e(t('identity_number')); ?></span><input name="identity_number" maxlength="100" value="<?php echo e($data['identity_number']??''); ?>" required></label></div>
<?php if($batch['person_type']==='student'): ?>
<div class="grid three"><label class="field"><span><?php echo e(t('student_department')); ?></span><select name="student_department_option_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($departments as $op): ?><option value="<?php echo (int)$op['id']; ?>" <?php echo (int)($data['student_department_option_id']??0)===(int)$op['id']?'selected':''; ?>><?php echo e($op['label']); ?></option><?php endforeach; ?></select></label>
<label class="field"><span><?php echo e(t('student_stage')); ?></span><select name="student_stage" required><?php for($s=1;$s<=4;$s++): ?><option value="<?php echo $s; ?>" <?php echo (int)($data['student_stage']??1)===$s?'selected':''; ?>><?php echo $s; ?></option><?php endfor; ?></select></label>
<label class="field"><span><?php echo e(t('group_code')); ?></span><input name="student_group_code" maxlength="20" value="<?php echo e($data['student_group_code']??'A'); ?>" required></label></div>
<label class="field"><span><?php echo e(t('academic_year')); ?></span><input name="academic_year" maxlength="9" pattern="[0-9]{4}-[0-9]{4}" value="<?php echo e($data['academic_year']??$defaultYear); ?>" required></label>
<?php elseif($batch['person_type']==='staff'): ?>
<label class="field"><span><?php echo e(t('staff_workplace')); ?></span><select name="staff_workplace_option_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($workplaces as $op): ?><option value="<?php echo (int)$op['id']; ?>" <?php echo (int)($data['staff_workplace_option_id']??0)===(int)$op['id']?'selected':''; ?>><?php echo e($op['label']); ?></option><?php endforeach; ?></select></label>
<?php else: ?>
<div class="grid two"><label class="field"><span><?php echo e(t('teacher_certificate')); ?></span><select name="teacher_certificate_option_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($certificates as $op): ?><option value="<?php echo (int)$op['id']; ?>" <?php echo (int)($data['teacher_certificate_option_id']??0)===(int)$op['id']?'selected':''; ?>><?php echo e($op['label']); ?></option><?php endforeach; ?></select></label>
<label class="field"><span><?php echo e(t('teacher_specialization')); ?></span><select name="teacher_specialization_option_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($specializations as $op): ?><option value="<?php echo (int)$op['id']; ?>" <?php echo (int)($data['teacher_specialization_option_id']??0)===(int)$op['id']?'selected':''; ?>><?php echo e($op['label']); ?></option><?php endforeach; ?></select></label></div>
<?php endif; ?>
<button class="btn primary" type="submit"><i class="fas fa-save"></i> <?php echo e($editing?t('rw_save_changes'):t('rw_add_person')); ?></button>
</form></section>
<?php endif; ?>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2><?php echo e(t('rw_list_people')); ?></h2><p class="subtle"><?php echo e(t('rw_data_help')); ?></p></div></div>
<?php if(!$items): ?><div class="empty-state"><?php echo e(t('rw_empty')); ?></div><?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>#</th><th><?php echo e(t('full_name')); ?></th><th><?php echo e(t('identity_number')); ?></th><th><?php echo e(t('birth_date')); ?></th><th><?php echo e(t('details')); ?></th><th><?php echo e(t('actions')); ?></th></tr></thead><tbody>
<?php foreach($items as $row): $p=json_decode($row['payload_json'],true)?:[]; $label='';
if($batch['person_type']==='student'){ foreach($departments as $o){if((int)$o['id']===(int)($p['student_department_option_id']??0)){$label=$o['label'];break;}} $label.=' · '.t('student_stage').' '.($p['student_stage']??''); }
elseif($batch['person_type']==='staff'){foreach($workplaces as $o){if((int)$o['id']===(int)($p['staff_workplace_option_id']??0)){$label=$o['label'];break;}}}
else {foreach($certificates as $o){if((int)$o['id']===(int)($p['teacher_certificate_option_id']??0)){$label=$o['label'];break;}} $label.=' · ';foreach($specializations as $o){if((int)$o['id']===(int)($p['teacher_specialization_option_id']??0)){$label.=$o['label'];break;}}}
?><tr><td><?php echo (int)$row['id']; ?></td><td><strong><?php echo e($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']); ?></strong></td><td><?php echo e($row['identity_number']); ?></td><td><?php echo e($p['birth_date']??''); ?></td><td><?php echo e($label); ?></td><td>
<?php if($canEdit): ?><div class="rw-inline"><a class="btn ghost small" href="registry-batch.php?id=<?php echo $id; ?>&amp;edit=<?php echo (int)$row['id']; ?>#rw-editor"><?php echo e(t('rw_edit_person')); ?></a><form method="post" action="api/registry-batches.php" onsubmit="return confirm(<?php echo e(json_encode(t('rw_confirm_delete'),JSON_UNESCAPED_UNICODE)); ?>)"><?php echo csrf_input(); ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="batch_id" value="<?php echo $id; ?>"><input type="hidden" name="item_id" value="<?php echo (int)$row['id']; ?>"><button class="btn ghost small danger-btn" type="submit"><?php echo e(t('rw_delete_person')); ?></button></form></div><?php else: ?>—<?php endif; ?>
</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<section class="panel admin-records-panel"><div class="section-heading"><h2><?php echo e(t('rw_history')); ?></h2></div><div class="admin-list">
<?php foreach($events as $event): ?><div class="option-row"><div><strong><?php echo e(t('rw_event_'.$event['event'])); ?></strong><small><?php echo e($event['actor_name'].' · '.$event['created_at']); ?><?php if($event['note']!==''): ?> · <?php echo e($event['note']); ?><?php endif; ?></small></div></div><?php endforeach; ?></div></section>
</main></div></body></html>
