<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/AuditController.php';
$ac=new AdminController(); $admin=$ac->requireSuperAdmin();
$audit=new AuditController(); $rows=$audit->listRecent($admin,500)->fetch_all(MYSQLI_ASSOC);
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('governance')); ?></span><h1><?php echo e(t('audit_log')); ?></h1><p class="subtle"><?php echo e(t('audit_log_help')); ?></p></div></section>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2><?php echo e(t('recent_audit_events')); ?></h2><p class="subtle"><?php echo e(t('audit_limit_help')); ?></p></div></div>
<?php if(!$rows): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_audit_events')); ?></span></div><?php else: ?><div class="table-scroll"><table class="admin-table audit-table"><thead><tr><th><?php echo e(t('time')); ?></th><th><?php echo e(t('actor')); ?></th><th><?php echo e(t('event')); ?></th><th><?php echo e(t('entity')); ?></th><th><?php echo e(t('details')); ?></th></tr></thead><tbody>
<?php foreach($rows as $row): $eventKey='audit_event_'.$row['event_type']; $eventLabel=t($eventKey); if($eventLabel===$eventKey) $eventLabel=ucwords(str_replace('_',' ',$row['event_type'])); $entityKey='audit_entity_'.$row['entity_type']; $entityLabel=t($entityKey); if($entityLabel===$entityKey) $entityLabel=ucwords(str_replace('_',' ',$row['entity_type'])); ?>
<tr><td><strong><?php echo e($row['created_at']); ?></strong><small>#<?php echo (int)$row['id']; ?></small></td><td><?php echo e($row['actor_name']?:t('system_actor')); ?></td><td><strong><?php echo e($eventLabel); ?></strong></td><td><?php echo e($entityLabel); ?><small>#<?php echo (int)$row['entity_id']; ?></small></td><td><?php echo e($row['details']!==''?$row['details']:'—'); ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</section></main></div></body></html>
