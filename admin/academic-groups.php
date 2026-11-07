<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/AcademicController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['academic_affairs']);
$academic=new AcademicController();
$departments=$academic->listOptions('student_department',true)->fetch_all(MYSQLI_ASSOC);
$groups=$academic->listGroups()->fetch_all(MYSQLI_ASSOC);
$teachers=$academic->listTeachers()->fetch_all(MYSQLI_ASSOC);
$groupId=(int)($_GET['group_id']??0);
$selected=$groupId>0?$academic->getGroup($groupId):null;
$assigned=$selected?$academic->teachersForGroup($groupId)->fetch_all(MYSQLI_ASSOC):[];
$timetable=$selected?$academic->timetable($groupId)->fetch_all(MYSQLI_ASSOC):[];
$adminPosts=$selected?$academic->adminPostsForGroup($groupId)->fetch_all(MYSQLI_ASSOC):[];
$defaultYear=$academic->defaultAcademicYear();
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('academic_management')); ?></span><h1><?php echo e(t('academic_groups')); ?></h1><p class="subtle"><?php echo e(t('academic_groups_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>

<div class="admin-two-col academic-admin-grid">
<section class="panel">
  <h2><?php echo e(t('create_academic_group')); ?></h2>
  <?php if(!$departments): ?><div class="notice error"><?php echo e(t('configure_options_first')); ?></div><?php endif; ?>
  <form class="modern-form" method="post" action="api/academic-group.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="create">
    <label class="field"><span><?php echo e(t('student_department')); ?></span><select name="department_option_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d['id']; ?>"><?php echo e($d['label']); ?></option><?php endforeach; ?></select></label>
    <div class="grid three">
      <label class="field"><span><?php echo e(t('student_stage')); ?></span><select name="stage" required><?php for($s=1;$s<=4;$s++): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endfor; ?></select></label>
      <label class="field"><span><?php echo e(t('group_code')); ?></span><input name="group_code" maxlength="20" value="A" required></label>
      <label class="field"><span><?php echo e(t('academic_year')); ?></span><input name="academic_year" value="<?php echo e($defaultYear); ?>" pattern="\d{4}-\d{4}" required></label>
    </div>
    <button class="btn primary" type="submit"><i class="fas fa-plus"></i><?php echo e(t('create_academic_group')); ?></button>
  </form>
</section>
<section class="panel">
  <h2><?php echo e(t('academic_groups')); ?></h2>
  <div class="academic-group-list">
  <?php if(!$groups): ?><div class="empty-state"><span><?php echo e(t('no_academic_groups')); ?></span></div><?php endif; ?>
  <?php foreach($groups as $g): ?>
    <a class="academic-group-admin-card <?php echo $selected && (int)$selected['id']===(int)$g['id']?'active':''; ?>" href="academic-groups.php?group_id=<?php echo (int)$g['id']; ?>">
      <div><strong><?php echo e($g['department_label'].' · '.t('student_stage').' '.$g['stage'].' · '.$g['group_code']); ?></strong><span><?php echo e($g['academic_year']); ?></span></div>
      <small><i class="fas fa-user-graduate"></i> <?php echo (int)$g['student_count']; ?> &nbsp; <i class="fas fa-chalkboard-teacher"></i> <?php echo (int)$g['teacher_count']; ?></small>
    </a>
  <?php endforeach; ?>
  </div>
</section>
</div>

<?php if($selected): ?>
<section class="panel academic-manage-panel">
<div class="section-heading"><div><h2><?php echo e(t('manage_group')); ?> — <?php echo e($selected['department_label'].' · '.t('student_stage').' '.$selected['stage'].' · '.$selected['group_code']); ?></h2><p class="subtle"><?php echo e($selected['academic_year']); ?> · <?php echo e((int)$selected['is_active']?t('active'):t('archived')); ?></p></div><form method="post" action="api/academic-group.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="group_id" value="<?php echo (int)$selected['id']; ?>"><button class="btn ghost" type="submit"><?php echo e((int)$selected['is_active']?t('archive_group'):t('reactivate_group')); ?></button></form></div>

<div class="admin-two-col academic-detail-grid">
<div>
  <h3><?php echo e(t('assigned_teachers')); ?></h3>
  <form class="modern-form" method="post" action="api/academic-group.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="assign_teacher"><input type="hidden" name="group_id" value="<?php echo (int)$selected['id']; ?>">
    <label class="field"><span><?php echo e(t('assign_teacher')); ?></span><select name="teacher_registry_id" required><option value=""><?php echo e(t('choose_option')); ?></option><?php foreach($teachers as $teacher): ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo e(display_name($teacher)); ?><?php if(!empty($teacher['certificate_label']) || !empty($teacher['specialization_label'])): ?> — <?php echo e(trim(($teacher['certificate_label']??'').(!empty($teacher['certificate_label'])&&!empty($teacher['specialization_label'])?' · ':'').($teacher['specialization_label']??''))); ?><?php endif; ?></option><?php endforeach; ?></select></label>
    <button class="btn primary" type="submit"><?php echo e(t('assign_teacher')); ?></button>
  </form>
  <div class="assigned-teacher-list">
  <?php if(!$assigned): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_assigned_teachers')); ?></span></div><?php endif; ?>
  <?php foreach($assigned as $teacher): $teacherDetails=trim(($teacher['certificate_label']??'').(!empty($teacher['certificate_label'])&&!empty($teacher['specialization_label'])?' · ':'').($teacher['specialization_label']??'')); ?>
    <div class="option-row"><div><strong><?php echo e(display_name($teacher)); ?></strong><small><?php echo e($teacherDetails!==''?$teacherDetails:$teacher['issued_id']); ?><?php if(empty($teacher['unique_id'])): ?> · <?php echo e(t('registry_status_available')); ?><?php endif; ?></small></div>
      <form method="post" action="api/academic-group.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="unassign_teacher"><input type="hidden" name="group_id" value="<?php echo (int)$selected['id']; ?>"><input type="hidden" name="teacher_registry_id" value="<?php echo (int)$teacher['teacher_registry_id']; ?>"><button class="btn ghost small-btn" type="submit"><?php echo e(t('remove_teacher')); ?></button></form>
    </div>
  <?php endforeach; ?>
  </div>
</div>

<div>
  <h3><?php echo e(t('weekly_timetable')); ?></h3>
  <p class="subtle"><?php echo e(t('timetable_help')); ?></p>
  <form class="modern-form timetable-form" method="post" action="api/academic-group.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="add_timetable"><input type="hidden" name="group_id" value="<?php echo (int)$selected['id']; ?>">
    <div class="grid three">
      <label class="field"><span><?php echo e(t('day')); ?></span><select name="day_of_week" required><?php for($d=1;$d<=7;$d++): ?><option value="<?php echo $d; ?>"><?php echo e(academic_day_label($d)); ?></option><?php endfor; ?></select></label>
      <label class="field"><span><?php echo e(t('start_time')); ?></span><input type="time" name="start_time" required></label>
      <label class="field"><span><?php echo e(t('end_time')); ?></span><input type="time" name="end_time" required></label>
    </div>
    <div class="grid three">
      <label class="field"><span><?php echo e(t('subject')); ?></span><input name="subject" maxlength="190" required></label>
      <label class="field"><span><?php echo e(t('teachers')); ?></span><select name="teacher_registry_id"><option value="0"><?php echo e(t('choose_option')); ?></option><?php foreach($assigned as $teacher): ?><option value="<?php echo (int)$teacher['teacher_registry_id']; ?>"><?php echo e(display_name($teacher)); ?></option><?php endforeach; ?></select></label>
      <label class="field"><span><?php echo e(t('room')); ?></span><input name="room" maxlength="100" required></label>
    </div>
    <button class="btn primary" type="submit"><?php echo e(t('add_timetable')); ?></button>
  </form>
  <div class="table-scroll"><table class="admin-table timetable-table"><thead><tr><th><?php echo e(t('day')); ?></th><th><?php echo e(t('start_time')); ?></th><th><?php echo e(t('subject')); ?></th><th><?php echo e(t('teachers')); ?></th><th><?php echo e(t('room')); ?></th><th></th></tr></thead><tbody>
  <?php if(!$timetable): ?><tr><td colspan="6"><?php echo e(t('no_timetable')); ?></td></tr><?php endif; ?>
  <?php foreach($timetable as $item): ?><tr><td><?php echo e(academic_day_label($item['day_of_week'])); ?></td><td class="ltr-code"><?php echo e(substr($item['start_time'],0,5).' - '.substr($item['end_time'],0,5)); ?></td><td><?php echo e($item['subject']); ?></td><td><?php echo e(display_name($item)); ?></td><td><?php echo e($item['room']); ?></td><td><form method="post" action="api/academic-group.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="delete_timetable"><input type="hidden" name="group_id" value="<?php echo (int)$selected['id']; ?>"><input type="hidden" name="timetable_id" value="<?php echo (int)$item['id']; ?>"><button class="icon-btn danger-icon" type="submit" title="<?php echo e(t('delete_timetable')); ?>"><i class="fas fa-trash"></i></button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
</div>
<section class="panel" style="margin-top:1rem"><div class="section-heading"><div><h3><?php echo e(t('academic_content_moderation')); ?></h3><p class="subtle"><?php echo e(t('academic_content_moderation_help')); ?></p></div></div>
<div class="admin-list"><?php if(!$adminPosts): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_academic_posts')); ?></span></div><?php endif; ?><?php foreach($adminPosts as $post): ?><article class="admin-list-row"><div class="admin-list-icon"><i class="fas fa-chalkboard-teacher"></i></div><div><strong><?php echo e(display_name($post)); ?></strong><span><?php echo e(mb_strimwidth(trim((string)$post['content']),0,140,'…')); ?> · <?php echo e($post['created_at']); ?></span></div><form method="post" action="api/academic-group.php" onsubmit="return confirm('<?php echo e(t('delete_academic_post_confirm')); ?>')"><?php echo csrf_input(); ?><input type="hidden" name="action" value="admin_delete_post"><input type="hidden" name="group_id" value="<?php echo (int)$selected['id']; ?>"><input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>"><button class="icon-btn danger-icon" type="submit"><i class="fas fa-trash"></i></button></form></article><?php endforeach; ?></div>
</section>
</section>
<?php endif; ?>
</main></div></body></html>
