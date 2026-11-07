<?php
$posts = $feedController->posts($scope);
$stories = $feedController->stories($scope);
$returnTo = 'home.php';
$defaultVisibility = 'friends';
$viewerForSocial = current_user();
?>
<?php if(!empty($_SESSION['flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

<div class="composer-grid social-composer-grid">
  <section class="panel social-composer facebook-composer">
    <div class="composer-identity">
      <a href="profile.php"><img class="avatar" src="<?php echo e(avatar_src($viewerForSocial['img'] ?? '')); ?>" alt=""></a>
      <div><strong><?php echo e(display_name($viewerForSocial)); ?></strong><span><?php echo e(t('create_post')); ?></span></div>
    </div>
    <form action="api/create-post.php" method="post" enctype="multipart/form-data" class="modern-form"><?php echo csrf_input(); ?>
      <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
      <label class="field composer-textarea"><textarea name="content" rows="3" maxlength="5000" placeholder="<?php echo e(t('post_placeholder')); ?>"></textarea></label>
      <div class="composer-tools">
        <label class="field compact-select"><span><i class="fas fa-user-shield"></i><?php echo e(t('visibility')); ?></span><select name="visibility"><option value="<?php echo $defaultVisibility; ?>"><?php echo e($defaultVisibility === 'public' ? t('visibility_public') : t('visibility_friends')); ?></option><option value="<?php echo $defaultVisibility === 'public' ? 'friends' : 'public'; ?>"><?php echo e($defaultVisibility === 'public' ? t('visibility_friends') : t('visibility_public')); ?></option></select></label>
        <label class="media-picker"><i class="fas fa-images"></i><span><?php echo e(t('media')); ?></span><input type="file" name="media" accept="image/jpeg,image/png,image/webp"></label>
        <button class="btn primary" type="submit"><i class="fas fa-paper-plane"></i><?php echo e(t('publish')); ?></button>
      </div>
    </form>
  </section>

  <section class="panel social-composer story-composer">
    <div class="story-composer-head"><span class="story-plus"><i class="fas fa-plus"></i></span><div><h2><?php echo e(t('create_story')); ?></h2><p class="subtle"><?php echo e(t('story_24h')); ?></p></div></div>
    <form action="api/create-story.php" method="post" enctype="multipart/form-data" class="modern-form"><?php echo csrf_input(); ?>
      <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
      <label class="field"><textarea name="content" rows="2" maxlength="700" placeholder="<?php echo e(t('story_placeholder')); ?>"></textarea></label>
      <div class="grid two">
        <label class="field"><span><?php echo e(t('visibility')); ?></span><select name="visibility"><option value="<?php echo $defaultVisibility; ?>"><?php echo e($defaultVisibility === 'public' ? t('visibility_public') : t('visibility_friends')); ?></option><option value="<?php echo $defaultVisibility === 'public' ? 'friends' : 'public'; ?>"><?php echo e($defaultVisibility === 'public' ? t('visibility_friends') : t('visibility_public')); ?></option></select></label>
        <label class="field file"><span><?php echo e(t('media')); ?></span><input type="file" name="media" accept="image/jpeg,image/png,image/webp"></label>
      </div>
      <button class="btn primary" type="submit"><i class="fas fa-plus"></i><?php echo e(t('publish')); ?></button>
    </form>
  </section>
</div>

<section class="panel stories-panel">
  <div class="section-heading"><h2><?php echo e(t('stories')); ?></h2></div>
  <?php if($stories->num_rows === 0): ?><div class="empty-state compact-empty"><span><?php echo e(t('no_stories')); ?></span></div><?php else: ?>
  <div class="stories-row">
    <?php while($story = $stories->fetch_assoc()): include __DIR__ . '/story-card.php'; endwhile; ?>
  </div>
  <?php endif; ?>
</section>

<section class="feed-list">
  <div class="section-heading feed-heading"><h2><?php echo e(t('posts')); ?></h2></div>
  <?php if($posts->num_rows === 0): ?><section class="panel empty-state"><span><?php echo e(t('no_posts')); ?></span></section><?php endif; ?>
  <?php while($post = $posts->fetch_assoc()): include __DIR__ . '/post-card.php'; endwhile; ?>
</section>
<script src="assets/js/social.js?v=3"></script>
