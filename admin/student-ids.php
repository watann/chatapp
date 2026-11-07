<?php
require_once __DIR__ . '/../api/app/controller/AdminController.php';
$admin=(new AdminController())->requireDepartmentAccess(['administration','student_affairs','registry']);
$search=trim((string)($_GET['q']??''));
if(mb_strlen($search)>100) $search=mb_substr($search,0,100);
$conditions=" WHERE r.person_type='student'";
$params=[];
if($search!==''){
    $conditions.=" AND (r.issued_id LIKE ? OR r.first_name LIKE ? OR r.middle_name LIKE ? OR r.last_name LIKE ? OR CONCAT_WS(' ',r.first_name,r.middle_name,r.last_name) LIKE ?)";
    $like='%'.$search.'%';$params=array_fill(0,5,$like);
}
$sql="SELECT r.id,r.issued_id,r.first_name,r.middle_name,r.last_name,r.status,r.student_department,
             COALESCE(dep.label,r.student_department) AS department_label,
             u.email AS linked_email,u.unique_id AS linked_user_id
      FROM institution_registry r
      LEFT JOIN institution_options dep ON dep.id=r.student_department_option_id
      LEFT JOIN users u ON u.registry_id=r.id".$conditions." ORDER BY r.id DESC LIMIT 201";
$stmt=db()->prepare($sql);
if($params) $stmt->bind_param('sssss',...$params);
$stmt->execute();$students=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hasMore=count($students)>200;if($hasMore) array_pop($students);
include __DIR__.'/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__.'/part/sidebar.php'; ?>
<main class="admin-main student-ids-page">
<section class="topbar"><div><span class="eyebrow">خوێندکاران / ژمارەی فەرمی</span><h1>ID ـی خوێندکاران</h1><p class="subtle">تەنها خوێندکارانی پەسەندکراو لێرە دەردەکەون. ژمارەی لیست (#) ID ـی خوێندکار نییە.</p></div><a href="registry-batches.php" class="btn primary"><i class="fas fa-list-alt"></i> لیستەکانی تۆمارکردن</a></section>
<section class="panel student-id-guide"><div class="section-heading"><div><h2>چۆن ID بە خوێندکار بدەم؟</h2><p class="subtle">بەڕێوبەر دەبێت لیستی خوێندکاران پەسەند بکات؛ ئینجا ID ـی فەرمی دروست دەبێت.</p></div><a class="btn ghost" href="../index.php" target="_blank" rel="noopener">پەڕەی دروستکردنی هەژمار <i class="fas fa-external-link-alt"></i></a></div>
<div class="id-steps"><span><b>١</b> تۆمارکار لیست دەنێرێت</span><span><b>٢</b> کارگێڕی پەسەند دەکات</span><span><b>٣</b> بەڕێوبەر پەسەند دەکات</span><span><b>٤</b> ID کۆپی بکە و بیدە بە خوێندکار</span></div>
<p class="subtle">خوێندکار بۆ دروستکردنی هەژمار لە <strong>localhost/chatapp/</strong> پێویستی بە ID ـی فەرمی، ژمارەی ناسنامە و ڕۆژی لەدایکبوونی خۆی هەیە. ژمارەی ناسنامە و ڕۆژی لەدایکبوون بە شێوەی پارێزراو بۆی پشتڕاست بکەرەوە، لەگەڵ ID بۆ هەرکەسێک بڵاویان مەکەرەوە.</p></section>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2>لیستی ID ـی فەرمی</h2><p class="subtle"><?php echo count($students); ?> ئەنجام<?php if($hasMore): ?> (بۆ ئەنجامی زیاتر ناو یان ID بگەڕێ)<?php endif; ?></p></div></div>
<form method="get" class="student-id-search"><label class="field"><span>گەڕان بە ناو یان ID ـی خوێندکار</span><input name="q" maxlength="100" value="<?php echo e($search); ?>" placeholder="ناوی خوێندکار یان RUN-2026-STD-..."></label><button class="btn primary" type="submit"><i class="fas fa-search"></i> گەڕان</button><?php if($search!==''): ?><a class="btn ghost" href="student-ids.php">پاککردنەوە</a><?php endif; ?></form>
<?php if(!$students): ?><div class="empty-state"><strong>هیچ خوێندکارێکی فەرمی نەدۆزرایەوە.</strong><p>ئەگەر هێشتا تۆمار نەکراوە، سەرەتا لیستی تۆمارکردن دروست بکە و هەردوو پەسەندکردنەکە تەواو بکە.</p><a class="btn ghost" href="registry-batches.php">چوونە لیستەکان</a></div><?php else: ?>
<div class="table-scroll"><table class="admin-table student-id-table"><thead><tr><th>ناوی خوێندکار</th><th>بەشی خوێندن</th><th>ID ـی فەرمی</th><th>دۆخی هەژمار</th><th>کردار</th></tr></thead><tbody>
<?php foreach($students as $s): $canGiveId=$s['status']==='available';$registered=$s['status']==='registered'; ?>
<tr><td><strong><?php echo e(display_name($s)); ?></strong></td><td><?php echo e($s['department_label']?:'—'); ?></td>
<td><code class="ltr-code id-pill"><?php echo e($s['issued_id']); ?></code></td>
<td><span class="registry-status <?php echo e($s['status']); ?>"><?php echo $registered?'هەژماری دروست کردووە':($canGiveId?'چاوەڕوانی دروستکردنی هەژمار':'ڕاگیراو'); ?></span><?php if($registered && $s['linked_email']): ?><small><?php echo e($s['linked_email']); ?></small><?php endif; ?></td>
<td><?php if($canGiveId): ?><button type="button" class="btn ghost small" data-student-id="<?php echo e($s['issued_id']); ?>"><i class="fas fa-copy"></i> کۆپیکردنی ID</button><?php elseif($registered): ?><span class="subtle">پێشتر بەکارهاتووە</span><?php else: ?><span class="subtle">نابێت بەکاربهێنرێت</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<div class="student-copy-feedback" id="copy-feedback" role="status" aria-live="polite"></div></section>
</main></div><script>
(function(){document.querySelectorAll('[data-student-id]').forEach(function(button){button.addEventListener('click',async function(){var id=button.getAttribute('data-student-id');var feedback=document.getElementById('copy-feedback');try{if(!navigator.clipboard||!navigator.clipboard.writeText)throw Error('clipboard');await navigator.clipboard.writeText(id);feedback.textContent='ID کۆپی کرا: '+id;button.textContent='کۆپی کرا ✓';}catch(e){feedback.textContent='کۆپیکردنی خۆکار بەردەست نییە. ID ـەکە بە دەستی لە خشتەکە کۆپی بکە.';}});});})();
</script></body></html>
