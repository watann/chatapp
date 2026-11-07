<?php
require_once "api/app/controller/AuthController.php";
require_once "api/app/controller/AcademicController.php";
require_once "api/app/controller/GradeController.php";
$auth=new AuthController(); $auth->checkAuth();
$academic=new AcademicController();
$groupId=(int)($_GET['group_id']??0);
$ctx=$academic->accessContext($groupId);
if(!$ctx){ header('Location: groups.php'); exit; }
$group=$ctx['group'];
$posts=$academic->visiblePosts($groupId,$ctx);
$timetable=$academic->timetable($groupId)->fetch_all(MYSQLI_ASSOC);
$gradeController=new GradeController();
$teacherExams=$ctx['role']==='teacher'?$gradeController->teacherExams($groupId,$ctx):[];
$gradeStudents=$ctx['role']==='teacher'?$gradeController->studentsForGroup($groupId)->fetch_all(MYSQLI_ASSOC):[];
$studentGrades=$ctx['role']==='student'?$gradeController->publishedForStudent((int)$ctx['registry_id'])->fetch_all(MYSQLI_ASSOC):[];
include_once "part/header.php";
?>
<body>
<div class="app-layout">
<?php include "part/sidebar.php"; ?>
<main class="app-main academic-group-main">
<section class="topbar academic-group-hero">
  <div><span class="eyebrow"><?php echo e(t('official_academic_groups')); ?></span><h1><?php echo e($group['department_label'].' · '.t('student_stage').' '.$group['stage'].' · '.$group['group_code']); ?></h1><p class="subtle"><?php echo e($group['academic_year']); ?> · <?php echo e($ctx['role']==='teacher'?t('role_teacher'):t('role_student')); ?></p></div>
  <a class="btn ghost" href="groups.php"><i class="fas fa-arrow-left"></i><?php echo e(t('groups')); ?></a>
</section>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

<section class="panel academic-rule-card">
  <h2><?php echo e(t('academic_group_rules')); ?></h2>
  <p><i class="fas fa-shield-alt"></i><?php echo e($ctx['role']==='teacher'?t('teacher_group_rule'):t('student_group_rule')); ?></p>
</section>

<section class="panel academic-timetable-card">
  <div class="section-heading"><h2><?php echo e(t('weekly_timetable')); ?></h2></div>
  <?php if(!$timetable): ?><div class="empty-state"><span><?php echo e(t('no_timetable')); ?></span></div><?php else: ?>
  <div class="timetable-user-grid">
    <?php foreach($timetable as $item): ?>
      <article class="timetable-user-item">
        <div class="timetable-day"><?php echo e(academic_day_label($item['day_of_week'])); ?></div>
        <strong><?php echo e($item['subject']); ?></strong>
        <span class="ltr-code"><?php echo e(substr($item['start_time'],0,5).' - '.substr($item['end_time'],0,5)); ?></span>
        <small><?php echo e(display_name($item)); ?><?php if($item['room']): ?> · <?php echo e($item['room']); ?><?php endif; ?></small>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if($ctx['role']==='student'): ?>
<section class="panel academic-grades-card">
  <div class="section-heading"><div><h2><?php echo e(t('my_exam_grades')); ?></h2><p class="subtle"><?php echo e(t('student_grades_help')); ?></p></div></div>
  <?php if(!$studentGrades): ?><div class="empty-state"><span><?php echo e(t('no_published_grades')); ?></span></div><?php else: ?>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th><?php echo e(t('exam')); ?></th><th><?php echo e(t('subject')); ?></th><th><?php echo e(t('teacher')); ?></th><th><?php echo e(t('academic_year')); ?></th><th><?php echo e(t('exam_date')); ?></th><th><?php echo e(t('grade')); ?></th></tr></thead><tbody>
  <?php foreach($studentGrades as $grade): ?><tr><td><strong><?php echo e($grade['title']); ?></strong></td><td><?php echo e($grade['subject']); ?></td><td><?php echo e(display_name($grade)); ?></td><td><?php echo e($grade['academic_year']); ?><small><?php echo e($grade['department_label'].' · '.t('student_stage').' '.$grade['stage'].' · '.$grade['group_code']); ?></small></td><td><?php echo e($grade['exam_date']); ?></td><td><strong><?php echo e($grade['final_score']); ?> / <?php echo e($grade['max_score']); ?></strong><?php if(!empty($grade['admin_note'])): ?><small><?php echo e($grade['admin_note']); ?></small><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if($ctx['role']==='teacher'): ?>
<section class="panel academic-grade-management">
  <div class="section-heading"><div><h2><?php echo e(t('exam_grades')); ?></h2><p class="subtle"><?php echo e(t('teacher_grade_workflow_help')); ?></p></div></div>
  <form class="modern-form" method="post" action="api/grade-action.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="create_exam"><input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>">
    <div class="grid three"><label class="field"><span><?php echo e(t('exam_title')); ?></span><input name="title" maxlength="190" required></label><label class="field"><span><?php echo e(t('subject')); ?></span><input name="subject" maxlength="190" required></label><label class="field"><span><?php echo e(t('exam_date')); ?></span><input type="date" name="exam_date" required></label></div>
    <div class="grid two"><label class="field"><span><?php echo e(t('max_score')); ?></span><input type="number" step="0.01" min="1" max="10000" name="max_score" value="100" required></label><label class="field"><span><?php echo e(t('teacher_note')); ?></span><input name="teacher_note" maxlength="1000"></label></div>
    <button class="btn primary" type="submit"><i class="fas fa-plus"></i><?php echo e(t('create_exam')); ?></button>
  </form>
  <div class="teacher-exam-list">
  <?php if(!$teacherExams): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_exams')); ?></span></div><?php endif; ?>
  <?php foreach($teacherExams as $exam): $examGrades=$gradeController->gradesForExam((int)$exam['id'])->fetch_all(MYSQLI_ASSOC); $saved=[]; foreach($examGrades as $eg){$saved[(int)$eg['student_registry_id']]=$eg;} ?>
    <article class="panel nested-panel teacher-exam-card">
      <div class="section-heading"><div><h3><?php echo e($exam['title'].' · '.$exam['subject']); ?></h3><p class="subtle"><?php echo e($exam['exam_date'].' · '.t('max_score').' '.$exam['max_score']); ?></p></div><span class="registry-status <?php echo $exam['status']==='published'?'registered':($exam['status']==='submitted'?'available':''); ?>"><?php echo e(t('exam_status_'.$exam['status'])); ?></span></div>
      <?php if(!empty($exam['review_note'])): ?><div class="notice"><?php echo e($exam['review_note']); ?></div><?php endif; ?>
      <?php if(in_array($exam['status'],['draft','returned'],true)): ?>
      <form class="modern-form" method="post" action="api/grade-action.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="save_grades"><input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>"><input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>">
      <div class="table-scroll"><table class="admin-table"><thead><tr><th><?php echo e(t('student')); ?></th><th><?php echo e(t('grade')); ?></th><th><?php echo e(t('note')); ?></th></tr></thead><tbody>
      <?php foreach($gradeStudents as $student): $sg=$saved[(int)$student['id']]??null; ?><tr><td><strong><?php echo e(display_name($student)); ?></strong><small><?php echo e($student['issued_id']); ?></small></td><td><input type="number" step="0.01" min="0" max="<?php echo e($exam['max_score']); ?>" name="scores[<?php echo (int)$student['id']; ?>]" value="<?php echo e($sg['teacher_score']??''); ?>" required></td><td><input name="notes[<?php echo (int)$student['id']; ?>]" maxlength="500" value="<?php echo e($sg['teacher_note']??''); ?>"></td></tr><?php endforeach; ?>
      </tbody></table></div><button class="btn primary" type="submit"><i class="fas fa-save"></i><?php echo e(t('save_grades')); ?></button></form>
      <form method="post" action="api/grade-action.php" class="grade-submit-form"><?php echo csrf_input(); ?><input type="hidden" name="action" value="submit_exam"><input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>"><input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>"><button class="btn ghost" type="submit"><i class="fas fa-paper-plane"></i><?php echo e(t('send_to_academic_affairs')); ?></button></form>
      <?php else: ?><div class="notice <?php echo $exam['status']==='published'?'success':''; ?>"><?php echo e($exam['status']==='submitted'?t('waiting_academic_review'):t('grade_exam_locked')); ?></div><?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if($ctx['role']==='teacher'): ?>
<section class="panel academic-composer">
  <h2><?php echo e(t('publish_to_class')); ?></h2>
  <form class="modern-form" method="post" action="api/academic-action.php" enctype="multipart/form-data"><?php echo csrf_input(); ?>
    <input type="hidden" name="action" value="create_post"><input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>">
    <label class="field"><textarea name="content" maxlength="5000" rows="4" placeholder="<?php echo e(t('academic_post_placeholder')); ?>"></textarea></label>
    <label class="media-picker"><i class="fas fa-image"></i><?php echo e(t('media')); ?><input type="file" name="media" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
    <button class="btn primary" type="submit"><i class="fas fa-bullhorn"></i><?php echo e(t('publish_to_class')); ?></button>
  </form>
</section>
<?php endif; ?>

<section class="academic-post-list">
  <div class="feed-heading"><h2><?php echo e(t('academic_posts')); ?></h2></div>
  <?php if($posts->num_rows===0): ?><section class="panel empty-state"><span><?php echo e(t('no_academic_posts')); ?></span></section><?php endif; ?>
  <?php while($post=$posts->fetch_assoc()): $comments=$academic->commentsForPost((int)$post['id']); ?>
  <article class="panel academic-post-card">
    <header class="post-header">
      <img class="avatar" src="<?php echo e(avatar_src($post['img']??'')); ?>" alt="">
      <div class="post-author"><strong><?php echo e(display_name($post)); ?></strong><time><?php echo e($post['created_at']); ?></time></div>
      <?php if($ctx['role']==='teacher' && (int)$ctx['registry_id']===(int)$post['teacher_registry_id']): ?>
      <form class="academic-post-delete" method="post" action="api/academic-action.php" onsubmit="return confirm('<?php echo e(t('delete_academic_post_confirm')); ?>')"><?php echo csrf_input(); ?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>"><input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>"><button class="icon-btn danger-icon" type="submit" title="<?php echo e(t('delete_academic_post')); ?>"><i class="fas fa-trash"></i></button></form>
      <?php endif; ?>
    </header>
    <?php if(trim((string)$post['content'])!==''): ?><div class="post-content"><?php echo nl2br(e($post['content'])); ?></div><?php endif; ?>
    <?php if(!empty($post['media_path'])): ?><img class="post-media" src="api/academic-media.php?id=<?php echo (int)$post['id']; ?>" alt=""><?php endif; ?>
    <div class="academic-comments">
      <?php while($comment=$comments->fetch_assoc()): ?>
        <div class="academic-comment"><img class="avatar tiny" src="<?php echo e(avatar_src($comment['img']??'')); ?>" alt=""><div><strong><?php echo e(display_name($comment)); ?></strong><p><?php echo nl2br(e($comment['comment'])); ?></p><time><?php echo e($comment['created_at']); ?></time></div></div>
      <?php endwhile; ?>
      <?php if($ctx['role']==='student'): ?>
      <form class="academic-comment-form" method="post" action="api/academic-action.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="comment"><input type="hidden" name="group_id" value="<?php echo (int)$groupId; ?>"><input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>"><input type="text" name="comment" maxlength="1500" required placeholder="<?php echo e(t('write_comment')); ?>"><button type="submit" class="icon-btn"><i class="fas fa-paper-plane"></i></button></form>
      <?php endif; ?>
    </div>
  </article>
  <?php endwhile; ?>
</section>
</main>
</div>
<?php include "part/app-footer.php"; ?>
</body></html>
