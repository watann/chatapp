<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/WorkflowController.php';
require_once __DIR__ . '/../api/app/controller/OrganizationController.php';
require_once __DIR__ . '/../api/app/controller/RegistryBatchController.php';
$ac=new AdminController(); $admin=$ac->requireAdmin();
if(($admin['admin_role']??'')!=='super_admin' && empty($admin['department_id'])){
    $_SESSION['admin_flash_error']=t('not_allowed'); header('Location: index.php'); exit;
}
$workflow=new WorkflowController();
$requests=$workflow->listForAdmin($admin)->fetch_all(MYSQLI_ASSOC);
$registrationLists=[];
$batchController=new RegistryBatchController();
if(($admin['admin_role']??'')==='super_admin' || $batchController->canReviewAdministration($admin)){
    foreach($batchController->listBatches($admin) as $b){
        if((($admin['admin_role']??'')==='super_admin' && $b['status']==='manager_review') || ($batchController->canReviewAdministration($admin) && in_array($b['status'],['administration_review','returned_administration'],true))) $registrationLists[]=$b;
    }
}
$candidates=[]; $members=[];
if(($admin['admin_role']??'')==='department_head'){
    $org=new OrganizationController();
    $candidates=$org->staffCandidates(true)->fetch_all(MYSQLI_ASSOC);
    $members=$org->members((int)$admin['department_id'])->fetch_all(MYSQLI_ASSOC);
}
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('approval_workflow')); ?></span><h1><?php echo e(t('manager_approvals')); ?></h1><p class="subtle"><?php echo e(($admin['admin_role']??'')==='super_admin'?t('manager_requests_help'):t('department_requests_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>

<?php if(($admin['admin_role']??'')==='department_head'): ?>
<section class="panel request-composer"><div class="section-heading"><div><h2><?php echo e(t('new_department_request')); ?></h2><p class="subtle"><?php echo e(t('manager_approval_required_help')); ?></p></div></div>
<form method="post" action="api/requests.php" class="modern-form" data-request-form><?php echo csrf_input(); ?><input type="hidden" name="action" value="submit">
<div class="grid three">
<label class="field"><span><?php echo e(t('request_type')); ?></span><select name="request_type" required data-request-type>
<option value="staff_requisition"><?php echo e(t('request_type_staff_requisition')); ?></option><option value="resource"><?php echo e(t('request_type_resource')); ?></option><option value="procurement"><?php echo e(t('request_type_procurement')); ?></option><option value="budget"><?php echo e(t('request_type_budget')); ?></option><option value="staff_add"><?php echo e(t('request_type_staff_add')); ?></option><option value="staff_remove"><?php echo e(t('request_type_staff_remove')); ?></option><option value="other"><?php echo e(t('request_type_other')); ?></option>
</select></label>
<label class="field"><span><?php echo e(t('request_title')); ?></span><input name="title" maxlength="190" required></label>
<label class="field" data-request-amount><span><?php echo e(t('amount')); ?></span><input type="number" name="amount" min="0.01" max="999999999999.99" step="0.01"></label>
</div>
<label class="field" data-request-add hidden><span><?php echo e(t('staff_member')); ?></span><select name="target_registry_id"><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($candidates as $person): if(!empty($person['admin_id'])) continue; ?><option value="<?php echo (int)$person['id']; ?>"><?php echo e(display_name($person).' · '.$person['issued_id'].(!empty($person['department_name'])?' · '.$person['department_name']:'')); ?></option><?php endforeach; ?></select></label>
<label class="field" data-request-remove hidden><span><?php echo e(t('department_staff')); ?></span><select name="target_admin_user_id"><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($members as $m): if($m['admin_role']!=='department_staff') continue; ?><option value="<?php echo (int)$m['id']; ?>"><?php echo e($m['full_name'].' · '.$m['login_email']); ?></option><?php endforeach; ?></select></label>
<label class="field"><span><?php echo e(t('request_details')); ?></span><textarea name="details" rows="4" maxlength="3000" placeholder="<?php echo e(t('request_details_placeholder')); ?>" required></textarea></label>
<button class="btn primary" type="submit"><i class="fas fa-paper-plane"></i><?php echo e(t('send_for_manager_approval')); ?></button>
</form></section>
<?php endif; ?>

<?php if($registrationLists): ?>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(t('rw_pending_list_review')); ?> (<?php echo count($registrationLists); ?>)</h2><p class="subtle"><?php echo e(t('rw_flow')); ?></p></div><a class="btn ghost" href="registry-batches.php"><?php echo e(t('rw_all_lists')); ?></a></div>
<div class="table-scroll"><table class="admin-table"><thead><tr><th><?php echo e(t('rw_list_title')); ?></th><th><?php echo e(t('person_type')); ?></th><th><?php echo e(t('rw_count')); ?></th><th><?php echo e(t('status')); ?></th><th><?php echo e(t('actions')); ?></th></tr></thead><tbody>
<?php foreach($registrationLists as $b): ?><tr><td><?php echo e($b['title']); ?></td><td><?php echo e(role_label($b['person_type'])); ?></td><td><?php echo (int)$b['item_count']; ?></td><td><?php echo e(t('rw_status_'.$b['status'])); ?></td><td><a class="btn primary small" href="registry-batch.php?id=<?php echo (int)$b['id']; ?>"><?php echo e(t('rw_open_list')); ?></a></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2><?php echo e(t('request_history')); ?></h2><p class="subtle"><?php echo e(t('request_history_help')); ?></p></div></div>
<?php if(!$requests): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_department_requests')); ?></span></div><?php else: ?><div class="table-scroll"><table class="admin-table request-table"><thead><tr><th><?php echo e(t('department')); ?></th><th><?php echo e(t('request')); ?></th><th><?php echo e(t('requester')); ?></th><th><?php echo e(t('status')); ?></th><th><?php echo e(t('review')); ?></th><th><?php echo e(t('actions')); ?></th></tr></thead><tbody>
<?php foreach($requests as $r): ?>
<tr><td><strong><?php echo e($r['department_name']); ?></strong><small><?php echo e($r['department_code']); ?></small></td>
<td><strong><?php echo e(t('request_type_'.$r['request_type'])); ?> · <?php echo e($r['title']); ?></strong><?php if($r['details']!==''): ?><small><?php echo nl2br(e($r['details'])); ?></small><?php endif; ?><?php if($r['amount']!==null): ?><small><?php echo e(t('amount')); ?>: <b><?php echo e(number_format((float)$r['amount'],2)); ?></b></small><?php endif; ?><?php if(!empty($r['target_registry_name'])): ?><small><?php echo e(t('staff_member')); ?>: <?php echo e($r['target_registry_name'].' · '.$r['target_registry_issued']); ?></small><?php endif; ?><?php if(!empty($r['target_admin_name'])): ?><small><?php echo e(t('staff_member')); ?>: <?php echo e($r['target_admin_name']); ?></small><?php endif; ?></td>
<td><?php echo e($r['requester_name']); ?><small><?php echo e($r['created_at']); ?></small></td>
<td><span class="workflow-status <?php echo e($r['status']); ?>"><?php echo e(t('request_status_'.$r['status'])); ?></span><?php if(!empty($r['executed_at'])): ?><small><?php echo e(t('executed')); ?>: <?php echo e($r['executed_at']); ?></small><?php endif; ?></td>
<td><?php if(!empty($r['reviewer_name'])): ?><strong><?php echo e($r['reviewer_name']); ?></strong><small><?php echo e($r['review_note']?:t('no_review_note')); ?></small><?php else: ?><span class="subtle"><?php echo e(t('waiting_manager_review')); ?></span><?php endif; ?></td>
<td><?php if($r['status']==='pending' && ($admin['admin_role']??'')==='super_admin'): ?><form method="post" action="api/requests.php" class="review-inline-form"><?php echo csrf_input(); ?><input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>"><input name="review_note" maxlength="1000" placeholder="<?php echo e(t('review_note')); ?>"><button class="btn primary small" name="action" value="approve" type="submit"><?php echo e(t('approve')); ?></button><button class="btn ghost small danger-btn" name="action" value="reject" type="submit"><?php echo e(t('reject')); ?></button></form><?php elseif($r['status']==='pending' && ($admin['admin_role']??'')!=='super_admin' && (int)$r['requester_admin_id']===(int)$admin['id']): ?><form method="post" action="api/requests.php"><?php echo csrf_input(); ?><input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>"><button class="btn ghost small" name="action" value="cancel" type="submit"><?php echo e(t('cancel_request')); ?></button></form><?php elseif($r['status']==='approved' && ($admin['admin_role']??'')==='super_admin' && in_array($r['request_type'],['staff_requisition','resource','other'],true)): ?><form method="post" action="api/requests.php"><?php echo csrf_input(); ?><input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>"><button class="btn ghost small" name="action" value="complete" type="submit"><?php echo e(t('mark_completed')); ?></button></form><?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
</main></div>
<script>
(()=>{const form=document.querySelector('[data-request-form]');if(!form)return;const type=form.querySelector('[data-request-type]'),amount=form.querySelector('[data-request-amount]'),add=form.querySelector('[data-request-add]'),remove=form.querySelector('[data-request-remove]');const sync=()=>{const v=type.value;add.hidden=v!=='staff_add';remove.hidden=v!=='staff_remove';amount.hidden=!['procurement','budget'].includes(v);add.querySelector('select').required=v==='staff_add';remove.querySelector('select').required=v==='staff_remove';amount.querySelector('input').required=['procurement','budget'].includes(v)};type.addEventListener('change',sync);sync()})();
</script>
</body></html>
