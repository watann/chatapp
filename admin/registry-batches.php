<?php
require_once __DIR__ . '/../api/app/controller/RegistryBatchController.php';
$admin=(new AdminController())->requireAdmin();
$workflow=new RegistryBatchController();
if(!$workflow->canAccess($admin)){ header('Location: index.php');exit; }
$batches=$workflow->listBatches($admin);
$canDraft=$workflow->canDraft($admin);
$allowedTypes=$canDraft?(new InstitutionController())->allowedRecordTypes($admin):[];
$needsInitialStaff=false;
if(($admin['admin_role']??'')==='super_admin'){
    require_once __DIR__.'/../api/app/controller/InitialStaffController.php';
    $needsInitialStaff=(new InitialStaffController())->needed($admin);
}
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('registry_records')); ?></span><h1><?php echo e(t('rw_batches')); ?></h1><p class="subtle"><?php echo e(t('rw_flow')); ?></p></div><a class="btn ghost" href="records.php"><?php echo e(t('rw_official_records')); ?></a></section>
<section class="panel registry-howto"><div class="section-heading"><div><h2>چۆن خوێندکار زیاد دەکرێت؟</h2><p class="subtle">بەشی خوێندنی چالاک هەڵبژێرە؛ فەرمانبەری تۆمار لیست دروست دەکات؛ کارگێڕی و بەڕێوبەر پەسەندی دەکەن؛ ئینجا ID دروست دەبێت.</p></div><a class="btn ghost" href="student-ids.php"><i class="fas fa-id-card"></i> ID ـی خوێندکاران</a></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if($needsInitialStaff): ?><section class="panel starter-banner"><div><h2>بۆچی ناتوانیت لێرە لیستێک دروست بکەیت؟</h2><p>ئەم پەڕەیە بۆ تۆمارکار و کارگێڕییە. بەڕێوبەر سەرەتا فەرمانبەری تۆمار و سەرۆکی کارگێڕی دروست دەکات؛ پاشان تۆمارکار لیست دروست دەکات و بۆ پەسەندی دەنێرێت.</p></div><a class="btn primary" href="initial-staff.php">زیادکردنی یەکەم فەرمانبەر</a></section><?php elseif(($admin['admin_role']??'')==='super_admin'): ?><div class="notice">بەڕێوبەر لیستەکان پشکنین و پەسەندی کۆتایی دەکات؛ دروستکردنی لیستی نوێ تایبەتە بە فەرمانبەری تۆمار.</div><?php endif; ?>
<?php if($canDraft): ?>
<section class="panel"><div class="section-heading"><div><h2><?php echo e(t('rw_new_list')); ?></h2><p class="subtle"><?php echo e(t('rw_new_help')); ?></p></div></div>
<form class="modern-form" method="post" action="api/registry-batches.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="create">
<div class="grid two"><label class="field"><span><?php echo e(t('rw_list_title')); ?></span><input name="title" maxlength="190" required placeholder="<?php echo e(t('rw_title_example')); ?>"></label>
<label class="field"><span><?php echo e(t('person_type')); ?></span><select name="person_type" required><?php foreach($allowedTypes as $type): ?><option value="<?php echo e($type); ?>"><?php echo e(role_label($type)); ?></option><?php endforeach; ?></select></label></div>
<button class="btn primary" type="submit"><i class="fas fa-plus"></i> <?php echo e(t('rw_create_draft')); ?></button></form></section>
<?php endif; ?>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2><?php echo e(t('rw_all_lists')); ?></h2><p class="subtle"><?php echo e(t('rw_lists_help')); ?></p></div></div>
<?php if(!$batches): ?><div class="empty-state"><?php echo e(t('rw_none')); ?></div><?php else: ?>
<div class="table-scroll"><table class="admin-table"><thead><tr><th>#</th><th><?php echo e(t('rw_list_title')); ?></th><th><?php echo e(t('person_type')); ?></th><th><?php echo e(t('requester')); ?></th><th><?php echo e(t('rw_count')); ?></th><th><?php echo e(t('status')); ?></th><th><?php echo e(t('details')); ?></th></tr></thead><tbody>
<?php foreach($batches as $b): ?><tr>
<td>#<?php echo (int)$b['id']; ?></td><td><strong><?php echo e($b['title']); ?></strong><small><?php echo e($b['created_at']); ?></small></td>
<td><?php echo e(role_label($b['person_type'])); ?></td><td><?php echo e($b['creator_name']); ?></td>
<td><?php echo (int)$b['item_count']; ?></td><td><span class="registry-status <?php echo $b['status']==='approved'?'available':'registered'; ?>"><?php echo e(t('rw_status_'.$b['status'])); ?></span></td>
<td><a class="btn ghost small" href="registry-batch.php?id=<?php echo (int)$b['id']; ?>"><?php echo e(t('rw_open_list')); ?></a></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></section>
</main></div></body></html>
