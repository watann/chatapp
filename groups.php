<?php
require_once "api/app/controller/AuthController.php";
require_once "api/app/controller/GroupController.php";
require_once "api/app/controller/AcademicController.php";
$auth = new AuthController();
$auth->checkAuth();
$groupController = new GroupController();
$groups = $groupController->listForUser();
$academicController = new AcademicController();
$academicGroups = $academicController->groupsForCurrentUser();
$me = (int)$_SESSION['unique_id'];
$stmt = db()->prepare("SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.img,u.role FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id<>? AND (u.registry_id IS NULL OR r.status<>'suspended') AND NOT EXISTS(SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?)) ORDER BY u.fname,u.middle_name,u.lname");
$stmt->bind_param('iii', $me,$me,$me);
$stmt->execute();
$people = $stmt->get_result();
include_once "part/header.php";
?>
<body>
<div class="app-layout">
  <?php include "part/sidebar.php"; ?>
  <main class="app-main">
    <section class="topbar"><div><span class="eyebrow"><?php echo e(t('groups')); ?></span><h1><?php echo e(t('group_page_title')); ?></h1></div></section>
    <?php if(!empty($_SESSION['flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

    <section class="panel official-groups-panel">
      <div class="section-heading"><div><h2><?php echo e(t('official_academic_groups')); ?></h2><p class="subtle"><?php echo e(t('official_group_help')); ?></p></div></div>
      <div class="official-group-grid">
        <?php if(!$academicGroups): ?><div class="empty-state"><span><?php echo e(t('no_academic_groups')); ?></span></div><?php endif; ?>
        <?php foreach($academicGroups as $ag): ?>
          <a class="official-group-card" href="academic-group.php?group_id=<?php echo (int)$ag['id']; ?>">
            <div class="official-group-icon"><i class="fas fa-graduation-cap"></i></div>
            <div><strong><?php echo e($ag['department_label'].' · '.t('student_stage').' '.$ag['stage'].' · '.$ag['group_code']); ?></strong><span><?php echo e($ag['academic_year']); ?> · <?php echo e($ag['academic_role']==='teacher'?t('role_teacher'):t('role_student')); ?></span></div>
            <i class="fas fa-chevron-right"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="content-grid">
      <section class="panel">
        <h2><?php echo e(t('groups')); ?></h2>
        <div class="conversation-list">
          <?php if($groups->num_rows === 0): ?><div class="empty-state"><strong><?php echo e(t('no_groups')); ?></strong><span><?php echo e(t('create_first_group')); ?></span></div><?php endif; ?>
          <?php while($group = $groups->fetch_assoc()): ?>
            <a class="conversation-card" href="group-chat.php?group_id=<?php echo (int)$group['id']; ?>">
              <img class="avatar" src="assets/img/group-avatar.svg" alt="">
              <div class="conversation-main"><div class="conversation-title"><strong><?php echo e($group['name']); ?></strong><span><?php echo (int)$group['member_count']; ?> <?php echo e(t('member_count')); ?></span></div><p><?php echo e(t('open_group')); ?></p></div>
            </a>
          <?php endwhile; ?>
        </div>
      </section>

      <section class="panel form-panel">
        <h2><?php echo e(t('create_group')); ?></h2>
        <form action="api/create-group.php" method="post" class="modern-form"><?php echo csrf_input(); ?>
          <label class="field"><span><?php echo e(t('group_name')); ?></span><input type="text" name="name" maxlength="160" required placeholder="<?php echo e(t('group_placeholder')); ?>"></label>
          <div class="member-picker"><span><?php echo e(t('members')); ?></span>
            <?php if($people->num_rows === 0): ?><div class="empty-state"><span><?php echo e(t('no_people_available')); ?></span></div><?php endif; ?>
            <?php while($person = $people->fetch_assoc()): ?>
              <label class="member-row"><input type="checkbox" name="members[]" value="<?php echo (int)$person['unique_id']; ?>"><img class="avatar tiny" src="<?php echo e(avatar_src($person['img'])); ?>" alt=""><span class="member-name"><strong><?php echo e(display_name($person)); ?></strong><small><?php echo e(role_label($person['role'])); ?></small></span></label>
            <?php endwhile; ?>
          </div>
          <button class="btn primary" type="submit"><i class="fas fa-plus"></i><?php echo e(t('create_group')); ?></button>
        </form>
      </section>
    </div>
  </main>
</div>
<?php include "part/app-footer.php"; ?>
</body>
</html>
