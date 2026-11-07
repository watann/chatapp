<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
require_once __DIR__ . '/../api/app/controller/GradeController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['academic_affairs']);
$gc=new GradeController();
$exams=$gc->submittedExams()->fetch_all(MYSQLI_ASSOC);
$examId=(int)($_GET['exam_id']??0); $selected=null;
if($examId>0){ foreach($exams as $listedExam){ if((int)$listedExam['id']===$examId){ $selected=$gc->getExam($examId); break; } } }
$gradeRows=$selected?$gc->gradesForExam($examId)->fetch_all(MYSQLI_ASSOC):[];
include __DIR__ . '/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__ . '/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow"><?php echo e(t('academic_affairs')); ?></span><h1><?php echo e(t('grade_approval')); ?></h1><p class="subtle"><?php echo e(t('grade_approval_help')); ?></p></div></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']); unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']); unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<div class="admin-two-col academic-admin-grid">
<section class="panel"><div class="section-heading"><h2><?php echo e(t('exam_submissions')); ?></h2></div><div class="academic-group-list">
<?php if(!$exams): ?><div class="empty-state"><span><?php echo e(t('no_grade_submissions')); ?></span></div><?php endif; ?>
<?php foreach($exams as $exam): ?>
<a class="academic-group-admin-card <?php echo $selected&&(int)$selected['id']===(int)$exam['id']?'active':''; ?>" href="grades.php?exam_id=<?php echo (int)$exam['id']; ?>">
  <div><strong><?php echo e($exam['title'].' · '.$exam['subject']); ?></strong><span><?php echo e($exam['department_label'].' · '.t('student_stage').' '.$exam['stage'].' · '.$exam['group_code'].' · '.$exam['academic_year']); ?></span><small><?php echo e(display_name($exam)); ?></small></div>
  <span class="registry-status <?php echo $exam['status']==='submitted'?'available':($exam['status']==='published'?'registered':'suspended'); ?>"><?php echo e(t('exam_status_'.$exam['status'])); ?></span>
</a>
<?php endforeach; ?>
</div></section>
<section class="panel">
<?php if(!$selected): ?><div class="empty-state"><span><?php echo e(t('choose_exam_to_review')); ?></span></div><?php else: ?>
<div class="section-heading"><div><h2><?php echo e($selected['title']); ?></h2><p class="subtle"><?php echo e($selected['subject'].' · '.$selected['exam_date'].' · '.t('max_score').' '.$selected['max_score']); ?></p></div><span class="registry-status"><?php echo e(t('exam_status_'.$selected['status'])); ?></span></div>
<p><strong><?php echo e(t('teacher')); ?>:</strong> <?php echo e(display_name($selected)); ?></p>
<?php if(!empty($selected['teacher_note'])): ?><div class="notice"><?php echo nl2br(e($selected['teacher_note'])); ?></div><?php endif; ?>
<form class="modern-form" method="post" action="api/grades.php"><?php echo csrf_input(); ?><input type="hidden" name="exam_id" value="<?php echo (int)$selected['id']; ?>">
<div class="table-scroll"><table class="admin-table"><thead><tr><th><?php echo e(t('student')); ?></th><th><?php echo e(t('teacher_score')); ?></th><th><?php echo e(t('final_score')); ?></th><th><?php echo e(t('note')); ?></th></tr></thead><tbody>
<?php foreach($gradeRows as $g): ?><tr><td><strong><?php echo e(display_name($g)); ?></strong><small><?php echo e($g['issued_id']); ?></small></td><td><?php echo e($g['teacher_score']); ?> / <?php echo e($selected['max_score']); ?></td><td><input type="number" step="0.01" min="0" max="<?php echo e($selected['max_score']); ?>" name="scores[<?php echo (int)$g['id']; ?>]" value="<?php echo e($g['final_score']); ?>" <?php echo $selected['status']!=='submitted'?'disabled':''; ?> required></td><td><input name="admin_notes[<?php echo (int)$g['id']; ?>]" maxlength="500" value="<?php echo e($g['admin_note']??''); ?>" <?php echo $selected['status']!=='submitted'?'disabled':''; ?>></td></tr><?php endforeach; ?>
</tbody></table></div>
<label class="field"><span><?php echo e(t('review_note')); ?></span><textarea name="review_note" rows="3" maxlength="1000" <?php echo $selected['status']!=='submitted'?'disabled':''; ?>><?php echo e($selected['review_note']??''); ?></textarea></label>
<?php if($selected['status']==='submitted'): ?><div class="dialog-actions"><button class="btn ghost" name="action" value="return" type="submit"><i class="fas fa-undo"></i><?php echo e(t('return_to_teacher')); ?></button><button class="btn primary" name="action" value="publish" type="submit"><i class="fas fa-check-circle"></i><?php echo e(t('approve_and_publish')); ?></button></div><?php elseif($selected['status']==='published'): ?><div class="notice success"><?php echo e(t('grades_already_published')); ?><?php if(!empty($selected['published_at'])): ?> · <?php echo e($selected['published_at']); ?><?php endif; ?></div><?php elseif($selected['status']==='returned'): ?><div class="notice"><?php echo e(t('waiting_teacher_resubmission')); ?></div><?php endif; ?>
</form>
<?php endif; ?>
</section></div>
</main></div></body></html>
