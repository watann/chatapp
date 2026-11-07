<?php
$viewerId = (int)($_SESSION['unique_id'] ?? 0);
$postOwner = (int)$post['user_unique_id'] === $viewerId;
$postId = (int)$post['id'];
$returnWithAnchor = $returnTo . '#post-' . $postId;
$comments = $feedController->commentsForPost($postId, 12);
$isShared = !empty($post['shared_post_id']) && !empty($post['original_post_id']);
$shareVisibility = $isShared ? ($post['original_visibility'] ?? '') : ($post['visibility'] ?? '');
$shareable = $shareVisibility === 'public';
?>
<article class="panel post-card" id="post-<?php echo $postId; ?>">
  <header class="post-header">
    <a class="profile-avatar-link" href="profile.php?user_id=<?php echo (int)$post['user_unique_id']; ?>"><img class="avatar" src="<?php echo e(avatar_src($post['img'])); ?>" alt=""></a>
    <div class="post-author">
      <a class="profile-name-link" href="profile.php?user_id=<?php echo (int)$post['user_unique_id']; ?>"><strong><?php echo e(display_name($post)); ?></strong></a>
      <div class="post-meta-line">
        <time><?php echo e(date('Y-m-d H:i', strtotime($post['created_at']))); ?></time>
        <span aria-hidden="true">·</span>
        <span class="visibility-inline" title="<?php echo e($post['visibility'] === 'public' ? t('visibility_public') : t('visibility_friends')); ?>"><i class="fas <?php echo $post['visibility'] === 'public' ? 'fa-globe-americas' : 'fa-user-friends'; ?>"></i></span>
        <?php if($isShared): ?><span aria-hidden="true">·</span><span class="shared-label"><i class="fas fa-share"></i><?php echo e(t('shared')); ?></span><?php endif; ?>
      </div>
    </div>

    <?php if(!$postOwner): ?>
      <div class="owner-menu"><button type="button" class="icon-action" data-open-dialog="report-post-<?php echo $postId; ?>" aria-label="ڕیپۆرتی پۆست" title="ڕیپۆرتی پۆست"><i class="fas fa-flag"></i></button></div>
    <?php endif; ?>
    <?php if($postOwner): ?>
      <div class="owner-menu">
        <button type="button" class="icon-action" data-open-dialog="edit-post-<?php echo $postId; ?>" aria-label="<?php echo e(t('edit')); ?>"><i class="fas fa-ellipsis-h"></i></button>
      </div>
    <?php endif; ?>
  </header>

  <?php if($post['content'] !== ''): ?><div class="post-content"><?php echo nl2br(e($post['content'])); ?></div><?php endif; ?>
  <?php if(!$isShared && !empty($post['media_path'])): ?><img class="post-media" src="api/content-media.php?type=post&amp;id=<?php echo $postId; ?>" alt=""><?php endif; ?>

  <?php if($isShared): ?>
    <div class="shared-original-card">
      <header class="shared-original-header">
        <a href="profile.php?user_id=<?php echo (int)$post['original_user_unique_id']; ?>"><img class="avatar small-avatar" src="<?php echo e(avatar_src($post['original_img'])); ?>" alt=""></a>
        <div>
          <a class="profile-name-link" href="profile.php?user_id=<?php echo (int)$post['original_user_unique_id']; ?>"><strong><?php echo e(trim(($post['original_fname'] ?? '').' '.($post['original_middle_name'] ?? '').' '.($post['original_lname'] ?? ''))); ?></strong></a>
          <div class="post-meta-line"><time><?php echo e(date('Y-m-d H:i', strtotime($post['original_created_at']))); ?></time><span>·</span><i class="fas fa-globe-americas"></i></div>
        </div>
      </header>
      <?php if(($post['original_content'] ?? '') !== ''): ?><div class="shared-original-content"><?php echo nl2br(e($post['original_content'])); ?></div><?php endif; ?>
      <?php if(!empty($post['original_media_path'])): ?><img class="post-media shared-media" src="api/content-media.php?type=post&amp;id=<?php echo (int)$post['original_post_id']; ?>" alt=""><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="post-stats">
    <span><i class="fas fa-thumbs-up"></i><?php echo (int)$post['like_count']; ?> <?php echo e(t('likes')); ?></span>
    <span><?php echo (int)$post['comment_count']; ?> <?php echo e(t('comments')); ?> · <?php echo (int)$post['share_count']; ?> <?php echo e(t('shares')); ?></span>
  </div>

  <div class="post-actions-bar">
    <form action="api/post-action.php" method="post" class="post-action-form"><?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="toggle_like"><input type="hidden" name="post_id" value="<?php echo $postId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnWithAnchor); ?>">
      <button type="submit" class="social-action <?php echo !empty($post['liked_by_me']) ? 'liked' : ''; ?>"><i class="<?php echo !empty($post['liked_by_me']) ? 'fas' : 'far'; ?> fa-thumbs-up"></i><span><?php echo e(!empty($post['liked_by_me']) ? t('liked') : t('like')); ?></span></button>
    </form>
    <button type="button" class="social-action" data-focus-comment="comment-<?php echo $postId; ?>"><i class="far fa-comment-alt"></i><span><?php echo e(t('comment')); ?></span></button>
    <?php if($shareable): ?>
      <form action="api/post-action.php" method="post" class="post-action-form"><?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="share"><input type="hidden" name="post_id" value="<?php echo $postId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnWithAnchor); ?>">
        <button type="submit" class="social-action"><i class="fas fa-share"></i><span><?php echo e(t('share')); ?></span></button>
      </form>
    <?php else: ?>
      <button type="button" class="social-action disabled-action" disabled title="<?php echo e(t('share_public_only')); ?>"><i class="fas fa-share"></i><span><?php echo e(t('share')); ?></span></button>
    <?php endif; ?>
  </div>

  <section class="comments-section">
    <?php foreach($comments as $comment): ?>
      <div class="comment-row">
        <a href="profile.php?user_id=<?php echo (int)$comment['user_unique_id']; ?>"><img class="avatar comment-avatar" src="<?php echo e(avatar_src($comment['img'])); ?>" alt=""></a>
        <div class="comment-bubble"><a class="profile-name-link" href="profile.php?user_id=<?php echo (int)$comment['user_unique_id']; ?>"><strong><?php echo e(display_name($comment)); ?></strong></a><p><?php echo nl2br(e($comment['comment'])); ?></p><time><?php echo e(date('Y-m-d H:i', strtotime($comment['created_at']))); ?></time></div>
      </div>
    <?php endforeach; ?>
    <form action="api/post-action.php" method="post" class="comment-composer"><?php echo csrf_input(); ?>
      <img class="avatar comment-avatar" src="<?php echo e(avatar_src($viewerForSocial['img'] ?? '')); ?>" alt="">
      <input type="hidden" name="action" value="comment"><input type="hidden" name="post_id" value="<?php echo $postId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnWithAnchor); ?>">
      <div class="comment-input-wrap"><input id="comment-<?php echo $postId; ?>" type="text" name="comment" maxlength="1000" required placeholder="<?php echo e(t('write_comment')); ?>"><button type="submit" aria-label="<?php echo e(t('send')); ?>"><i class="fas fa-paper-plane"></i></button></div>
    </form>
  </section>

  <?php if(!$postOwner): ?>
    <dialog class="social-dialog" id="report-post-<?php echo $postId; ?>">
      <form action="api/post-action.php" method="post" class="dialog-card modern-form"><?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="report"><input type="hidden" name="post_id" value="<?php echo $postId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnWithAnchor); ?>">
        <div class="dialog-head"><h3>ڕیپۆرتی ئەم پۆستە</h3><button class="dialog-close" type="button" data-close-dialog><i class="fas fa-times"></i></button></div>
        <label class="field"><span>هۆکاری ڕیپۆرت</span><select name="reason" required><option value="">هۆکارێک هەڵبژێرە</option><?php foreach(['هەراسانکردن','سووکایەتی','ناوەڕۆکی نەگونجاو','سپام','هی تر'] as $reason): ?><option value="<?php echo e($reason); ?>"><?php echo e($reason); ?></option><?php endforeach; ?></select></label>
        <div class="dialog-actions"><button type="button" class="btn ghost" data-close-dialog>پاشگەزبوونەوە</button><button class="btn primary" type="submit"><i class="fas fa-flag"></i> ناردنی ڕیپۆرت</button></div>
      </form>
    </dialog>
  <?php endif; ?>
  <?php if($postOwner): ?>
    <dialog class="social-dialog" id="edit-post-<?php echo $postId; ?>">
      <form action="api/post-action.php" method="post" enctype="multipart/form-data" class="dialog-card modern-form"><?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit"><input type="hidden" name="post_id" value="<?php echo $postId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnWithAnchor); ?>">
        <div class="dialog-head"><h3><?php echo e(t('edit_post')); ?></h3><button class="dialog-close" type="button" data-close-dialog><i class="fas fa-times"></i></button></div>
        <label class="field"><span><?php echo e(t('post_text')); ?></span><textarea name="content" rows="5" maxlength="5000"><?php echo e($post['content']); ?></textarea></label>
        <label class="field"><span><?php echo e(t('visibility')); ?></span><select name="visibility"><option value="public" <?php echo $post['visibility']==='public'?'selected':''; ?>><?php echo e(t('visibility_public')); ?></option><option value="friends" <?php echo $post['visibility']==='friends'?'selected':''; ?>><?php echo e(t('visibility_friends')); ?></option></select></label>
        <?php if(!$isShared): ?>
          <label class="field file"><span><?php echo e(t('replace_image')); ?></span><input type="file" name="media" accept="image/jpeg,image/png,image/webp"></label>
          <?php if(!empty($post['media_path'])): ?><label class="check-row"><input type="checkbox" name="remove_media" value="1"><span><?php echo e(t('remove_image')); ?></span></label><?php endif; ?>
        <?php endif; ?>
        <div class="dialog-actions"><button class="btn ghost" type="button" data-close-dialog><?php echo e(t('cancel')); ?></button><button class="btn primary" type="submit"><i class="fas fa-save"></i><?php echo e(t('save_changes')); ?></button></div>
      </form>
      <form action="api/post-action.php" method="post" class="dialog-delete js-confirm-delete" data-confirm="<?php echo e(t('delete_post_confirm')); ?>"><?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="post_id" value="<?php echo $postId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
        <button class="btn danger full" type="submit"><i class="fas fa-trash-alt"></i><?php echo e(t('delete_post')); ?></button>
      </form>
    </dialog>
  <?php endif; ?>
</article>
