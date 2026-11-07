<?php
require_once __DIR__.'/../api/app/controller/PostModerationController.php';
$controller=new PostModerationController();
$admin=(new AdminController())->requireAdmin();
if(!moderation_can_access($admin)){http_response_code(403);exit('Access denied');}
$super=moderation_is_super($admin);
$it=moderation_is_it($admin);
$manageWords=moderation_can_manage_words($admin);
$reports=[];$flags=[];$words=[];$migrationError='';
try{
    if($super) $reports=$controller->reports();
    if($it) $flags=$controller->flags();
    if($manageWords) $words=$controller->words();
}catch(Throwable $e){$migrationError='خشتەکانی چاودێری هێشتا دروست نەکراون. پێش بەکارهێنان فایلەکەی DATABASE_MIGRATION_POST_MODERATION_2026_11_07.sql لە phpMyAdmin جێبەجێ بکە.';}
include __DIR__.'/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__.'/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow">Post moderation</span><h1>چاودێری پۆستەکان</h1><p class="subtle"><?php echo $super?'ڕیپۆرتەکانی پۆستەکان لێرە دەبینیت و دەتوانیت سڕینەوە یان داخستنی ڕیپۆرت هەڵبژێریت.':'بەشی IT لێرە ئاگادارکردنەوەی وشە فلتەرکراوەکان دەبینێت.'; ?></p></div></section>
<?php if($migrationError): ?><div class="notice error"><?php echo e($migrationError); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<?php if($super): ?>
<section class="panel" id="reports"><div class="section-heading"><div><h2><i class="fas fa-flag"></i> ڕیپۆرتی پۆستەکان <span class="moderation-count"><?php echo count($reports); ?> پۆست</span></h2><p class="subtle">هەر پۆست تەنها یەکجار لە لیستەکەدا پیشان دەدرێت، تەنانەت ئەگەر چەند کەسێک ڕیپۆرتیان کردبێت.</p></div></div>
<?php if(!$reports): ?><div class="empty-state compact-empty">هیچ ڕیپۆرتێکی چاوەڕوان نییە.</div><?php endif; ?>
<div class="moderation-list">
<?php foreach($reports as $r): ?><article class="moderation-item">
<div class="moderation-item-head"><strong>پۆست #<?php echo (int)$r['id']; ?> · <?php echo e(display_name($r)); ?></strong><span class="moderation-count"><?php echo (int)$r['report_count']; ?> ڕیپۆرت</span></div>
<p class="subtle">هۆکاری دوایین ڕیپۆرت: <?php echo e($r['reason']); ?> · <?php echo e($r['latest_report']); ?></p>
<?php if(!empty($r['shared_post_id'])): ?><p class="subtle">ئەمە هاوبەشکردنەوەی پۆستی #<?php echo (int)$r['shared_post_id']; ?> ـە؛ سڕینەوەی ئەم ڕیزە تەنها هاوبەشکردنەوەکە دەسڕێتەوە، نەک پۆستە ڕەسەنەکە.</p><?php endif; ?>
<div class="moderation-post-preview"><?php echo nl2br(e($r['display_content']!==''?$r['display_content']:'[پۆستی وێنە]')); ?>
<?php if(!empty($r['display_media'])): ?><a href="api/moderation-media.php?post_id=<?php echo (int)$r['id']; ?>" target="_blank" rel="noopener noreferrer"><img class="moderation-media-preview" src="api/moderation-media.php?post_id=<?php echo (int)$r['id']; ?>" alt="وێنەی پۆستی ڕیپۆرتکراو"></a><?php endif; ?></div>
<div class="moderation-actions">
<form method="post" action="api/post-moderation.php" onsubmit="return confirm('دڵنیایت لە سڕینەوەی ئەم پۆستە؟');"><?php echo csrf_input(); ?><input type="hidden" name="post_id" value="<?php echo (int)$r['id']; ?>"><button class="btn danger" type="submit" name="action" value="delete_reported"><i class="fas fa-trash-alt"></i> سڕینەوەی پۆست</button></form>
<form method="post" action="api/post-moderation.php"><?php echo csrf_input(); ?><input type="hidden" name="post_id" value="<?php echo (int)$r['id']; ?>"><button class="btn ghost" type="submit" name="action" value="dismiss_reports">داخستنی ڕیپۆرت (بێ سڕینەوە)</button></form>
</div></article><?php endforeach; ?>
</div></section>
<?php endif; ?>
<?php if($it): ?>
<section class="panel" id="flags"><div class="section-heading"><div><h2><i class="fas fa-bell"></i> ئاگادارکردنەوەکانی وشە <span class="moderation-count"><?php echo count($flags); ?> پۆست</span></h2><p class="subtle">پۆستە نوێیەکان و پۆستە دەستکاریکراوەکان بە وشە چالاکەکان دەپشکنرێن؛ پۆست خۆکارانە ناسڕدرێتەوە.</p></div></div>
<?php if(!$flags): ?><div class="empty-state compact-empty">هیچ ئاگادارکردنەوەیەکی چاوەڕوان نییە.</div><?php endif; ?>
<div class="moderation-list">
<?php foreach($flags as $r): ?><article class="moderation-item">
<div class="moderation-item-head"><strong>پۆست #<?php echo (int)$r['id']; ?> · <?php echo e(display_name($r)); ?></strong><span class="moderation-count"><?php echo e($r['matched_words']); ?></span></div>
<p class="subtle">دۆزراوەتەوە: <?php echo e($r['flagged_at']); ?></p>
<div class="moderation-post-preview"><?php echo nl2br(e($r['content'])); ?></div>
<div class="moderation-actions">
<form method="post" action="api/post-moderation.php" onsubmit="return confirm('دڵنیایت لە سڕینەوەی ئەم پۆستە؟');"><?php echo csrf_input(); ?><input type="hidden" name="post_id" value="<?php echo (int)$r['id']; ?>"><button class="btn danger" type="submit" name="action" value="delete_flagged"><i class="fas fa-trash-alt"></i> سڕینەوەی پۆست</button></form>
<form method="post" action="api/post-moderation.php"><?php echo csrf_input(); ?><input type="hidden" name="post_id" value="<?php echo (int)$r['id']; ?>"><button class="btn ghost" type="submit" name="action" value="dismiss_flag">داخستنی ئاگادارکردنەوە</button></form>
</div></article><?php endforeach; ?>
</div></section>
<?php endif; ?>
<?php if($manageWords): ?>
<section class="panel" id="words"><div class="section-heading"><div><h2><i class="fas fa-filter"></i> وشە فلتەرکراوەکان</h2><p class="subtle">وشە یان دەستەواژە زیاد بکە. فلتەرەکە تەنها ئاگادارکردنەوە بۆ IT دروست دەکات و پۆست خۆکارانە ناسڕێتەوە. پۆستە کۆنەکان تەنها کاتێک دووبارە دەپشکنرێن کە دەستکاری بکرێن.</p></div></div>
<form method="post" class="modern-form moderation-word-form" action="api/post-moderation.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="add_word"><label class="field"><span>وشە یان دەستەواژەی نوێ</span><input name="word" maxlength="100" required placeholder="وشە بنووسە"></label><button class="btn primary" type="submit"><i class="fas fa-plus"></i> زیادکردنی وشە</button></form>
<?php if(!$words): ?><p class="subtle">هێشتا هیچ وشەیەک زیاد نەکراوە.</p><?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>وشە</th><th>دۆخ</th><th>گۆڕینی دۆخ</th></tr></thead><tbody>
<?php foreach($words as $w): ?><tr><td><?php echo e($w['word']); ?></td><td><?php echo (int)$w['is_active']?'چالاک':'ناچالاک'; ?></td><td><form method="post" action="api/post-moderation.php"><?php echo csrf_input(); ?><input type="hidden" name="action" value="toggle_word"><input type="hidden" name="word_id" value="<?php echo (int)$w['id']; ?>"><input type="hidden" name="active" value="<?php echo (int)!$w['is_active']; ?>"><button type="submit" class="btn ghost small"><?php echo (int)$w['is_active']?'ناچالاککردن':'چالاککردن'; ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php endif; ?>
</main></div></body></html>
