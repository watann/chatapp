<?php
$viewerId = (int)($_SESSION['unique_id'] ?? 0);
$storyOwner = (int)$story['user_unique_id'] === $viewerId;
$storyId = (int)$story['id'];
$returnWithAnchor = $returnTo . '#story-' . $storyId;
?>
<article class="story-card <?php echo !empty($story['media_path']) ? 'has-media' : ''; ?>" id="story-<?php echo $storyId; ?>">
  <?php if(!empty($story['media_path'])): ?><img class="story-bg" src="api/content-media.php?type=story&amp;id=<?php echo $storyId; ?>" alt=""><?php endif; ?>
  <div class="story-overlay"></div>
  <a class="story-user" href="profile.php?user_id=<?php echo (int)$story['user_unique_id']; ?>"><img class="avatar tiny" src="<?php echo e(avatar_src($story['img'])); ?>" alt=""><div><strong><?php echo e(display_name($story)); ?></strong><span><?php echo e(role_label($story['role'])); ?></span></div></a>
  <?php if($storyOwner): ?><button type="button" class="story-owner-action" data-open-dialog="edit-story-<?php echo $storyId; ?>" aria-label="<?php echo e(t('edit')); ?>"><i class="fas fa-ellipsis-h"></i></button><?php endif; ?>
  <?php if($story['content'] !== ''): ?><p><?php echo nl2br(e($story['content'])); ?></p><?php endif; ?>
  <time><?php echo e(date('Y-m-d H:i', strtotime($story['created_at']))); ?></time>

  <?php if($storyOwner): ?>
    <dialog class="social-dialog" id="edit-story-<?php echo $storyId; ?>">
      <form action="api/story-action.php" method="post" enctype="multipart/form-data" class="dialog-card modern-form"><?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="edit"><input type="hidden" name="story_id" value="<?php echo $storyId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnWithAnchor); ?>">
        <div class="dialog-head"><h3><?php echo e(t('edit_story')); ?></h3><button class="dialog-close" type="button" data-close-dialog><i class="fas fa-times"></i></button></div>
        <label class="field"><span><?php echo e(t('story_text')); ?></span><textarea name="content" rows="4" maxlength="700"><?php echo e($story['content']); ?></textarea></label>
        <label class="field"><span><?php echo e(t('visibility')); ?></span><select name="visibility"><option value="public" <?php echo $story['visibility']==='public'?'selected':''; ?>><?php echo e(t('visibility_public')); ?></option><option value="friends" <?php echo $story['visibility']==='friends'?'selected':''; ?>><?php echo e(t('visibility_friends')); ?></option></select></label>
        <label class="field file"><span><?php echo e(t('replace_image')); ?></span><input type="file" name="media" accept="image/jpeg,image/png,image/webp"></label>
        <?php if(!empty($story['media_path'])): ?><label class="check-row"><input type="checkbox" name="remove_media" value="1"><span><?php echo e(t('remove_image')); ?></span></label><?php endif; ?>
        <div class="dialog-actions"><button class="btn ghost" type="button" data-close-dialog><?php echo e(t('cancel')); ?></button><button class="btn primary" type="submit"><i class="fas fa-save"></i><?php echo e(t('save_changes')); ?></button></div>
      </form>
      <form action="api/story-action.php" method="post" class="dialog-delete js-confirm-delete" data-confirm="<?php echo e(t('delete_story_confirm')); ?>"><?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="delete"><input type="hidden" name="story_id" value="<?php echo $storyId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
        <button class="btn danger full" type="submit"><i class="fas fa-trash-alt"></i><?php echo e(t('delete_story')); ?></button>
      </form>
    </dialog>
  <?php endif; ?>
</article>
