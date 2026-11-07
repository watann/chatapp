<?php
require_once "api/app/controller/AuthController.php";
require_once "api/app/controller/FriendController.php";
require_once "api/app/controller/UserController.php";
$auth = new AuthController();
$auth->checkAuth();
$me = current_user();
$friendController = new FriendController();
$userController = new UserController();
$requests = $friendController->incomingRequests();
$friends = $friendController->acceptedFriends();
$community = $friendController->communityRanking();
include_once "part/header.php";
?>
<body>
<div class="app-layout">
  <?php include "part/sidebar.php"; ?>
  <main class="app-main friends-main">
    <section class="topbar">
      <div><span class="eyebrow"><?php echo e(t('friends')); ?></span><h1><?php echo e(t('friends_page_title')); ?></h1><p class="subtle"><?php echo e(t('friends_page_help')); ?></p></div>
    </section>

    <?php if(!empty($_SESSION['flash_error'])): ?><div class="notice error friends-notice"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

    <section class="panel request-panel friends-section-card">
      <div class="section-heading"><div><span class="eyebrow"><?php echo e(t('friends')); ?></span><h2><?php echo e(t('incoming_requests')); ?></h2></div><span class="count-badge"><?php echo (int)$requests->num_rows; ?></span></div>
      <?php if($requests->num_rows === 0): ?>
        <div class="empty-state compact-empty"><span><?php echo e(t('no_requests')); ?></span></div>
      <?php else: ?>
        <div class="request-list">
        <?php while($request = $requests->fetch_assoc()): ?>
          <article class="request-card">
            <a class="profile-avatar-link" href="profile.php?user_id=<?php echo (int)$request['unique_id']; ?>"><img class="avatar" src="<?php echo e(avatar_src($request['img'])); ?>" alt=""></a>
            <div class="request-main"><a class="profile-name-link" href="profile.php?user_id=<?php echo (int)$request['unique_id']; ?>"><strong><?php echo e(display_name($request)); ?></strong></a><span class="role-pill"><?php echo e(role_label($request['role'])); ?></span></div>
            <div class="person-actions">
              <form action="api/friend-action.php" method="post" class="inline-form"><?php echo csrf_input(); ?><input type="hidden" name="user_id" value="<?php echo (int)$request['unique_id']; ?>"><input type="hidden" name="action" value="accept"><input type="hidden" name="return_to" value="users.php"><button class="btn small primary"><?php echo e(t('accept')); ?></button></form>
              <form action="api/friend-action.php" method="post" class="inline-form"><?php echo csrf_input(); ?><input type="hidden" name="user_id" value="<?php echo (int)$request['unique_id']; ?>"><input type="hidden" name="action" value="decline"><input type="hidden" name="return_to" value="users.php"><button class="btn small danger ghost"><?php echo e(t('decline')); ?></button></form>
            </div>
          </article>
        <?php endwhile; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel friends-section-card">
      <div class="section-heading"><div><span class="eyebrow"><?php echo e(t('friends')); ?></span><h2><?php echo e(t('your_friends')); ?></h2></div><span class="count-badge"><?php echo (int)$friends->num_rows; ?></span></div>
      <?php if($friends->num_rows === 0): ?>
        <div class="empty-state"><i class="fas fa-user-friends empty-icon"></i><strong><?php echo e(t('no_friends_yet')); ?></strong></div>
      <?php else: ?>
        <div class="friends-grid">
          <?php while($friend = $friends->fetch_assoc()): $fid=(int)$friend['unique_id']; ?>
            <article class="friend-tile">
              <a href="profile.php?user_id=<?php echo $fid; ?>" class="friend-tile-profile"><img src="<?php echo e(avatar_src($friend['img'])); ?>" alt=""><span><strong><?php echo e(display_name($friend)); ?></strong><small><?php echo e(role_label($friend['role'])); ?></small></span></a>
              <div class="friend-tile-actions">
                <a class="btn small ghost js-mini-chat" href="chat.php?user_id=<?php echo $fid; ?>" data-user-id="<?php echo $fid; ?>" data-user-name="<?php echo e(display_name($friend)); ?>" data-user-avatar="<?php echo e(avatar_src($friend['img'])); ?>"><i class="fas fa-comment"></i><?php echo e(t('chat')); ?></a>
                <?php echo $userController->friendControls(friendship_between((int)$me['unique_id'], $fid), (int)$me['unique_id'], $fid, 'users.php'); ?>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel friends-section-card community-card">
      <div class="section-heading community-heading"><div><span class="eyebrow"><?php echo e(t('community_members')); ?></span><h2><?php echo e(t('community_activity')); ?></h2><p class="subtle"><?php echo e(t('community_activity_help')); ?></p></div></div>
      <div class="search-bar community-search"><i class="fas fa-search"></i><input type="text" data-community-search placeholder="<?php echo e(t('search_people')); ?>"></div>
      <div class="activity-list">
        <?php $rank=0; while($member = $community->fetch_assoc()): $rank++; $uid=(int)$member['unique_id']; $isMe=$uid===(int)$me['unique_id']; ?>
          <article class="activity-card" data-community-card data-search-text="<?php echo e(display_name($member).' '.$member['role']); ?>">
            <div class="activity-rank"><span>#<?php echo $rank; ?></span></div>
            <a class="profile-avatar-link" href="profile.php?user_id=<?php echo $uid; ?>"><img class="avatar" src="<?php echo e(avatar_src($member['img'])); ?>" alt=""></a>
            <div class="activity-person">
              <div class="activity-person-title"><a class="profile-name-link" href="profile.php?user_id=<?php echo $uid; ?>"><strong><?php echo e(display_name($member)); ?></strong></a><?php if($isMe): ?><span class="you-badge"><?php echo e(t('you')); ?></span><?php endif; ?><span class="role-pill"><?php echo e(role_label($member['role'])); ?></span></div>
              <div class="activity-stats">
                <span title="<?php echo e(t('activity_posts')); ?>"><i class="far fa-newspaper"></i><b><?php echo (int)$member['post_count']; ?></b><small><?php echo e(t('activity_posts')); ?></small></span>
                <span title="<?php echo e(t('activity_comments')); ?>"><i class="far fa-comment-dots"></i><b><?php echo (int)$member['comment_count']; ?></b><small><?php echo e(t('activity_comments')); ?></small></span>
                <span title="<?php echo e(t('activity_likes')); ?>"><i class="far fa-thumbs-up"></i><b><?php echo (int)$member['like_count']; ?></b><small><?php echo e(t('activity_likes')); ?></small></span>
                <span title="<?php echo e(t('activity_stories')); ?>"><i class="far fa-clock"></i><b><?php echo (int)$member['story_count']; ?></b><small><?php echo e(t('activity_stories')); ?></small></span>
                <span class="activity-score" title="<?php echo e(t('activity_score')); ?>"><i class="fas fa-chart-line"></i><b><?php echo (int)$member['activity_score']; ?></b><small><?php echo e(t('activity_score')); ?></small></span>
              </div>
            </div>
            <?php if(!$isMe): ?>
              <div class="activity-actions">
                <a class="icon-btn js-mini-chat" href="chat.php?user_id=<?php echo $uid; ?>" data-user-id="<?php echo $uid; ?>" data-user-name="<?php echo e(display_name($member)); ?>" data-user-avatar="<?php echo e(avatar_src($member['img'])); ?>" title="<?php echo e(t('chat')); ?>"><i class="fas fa-comment"></i></a>
                <?php echo $userController->friendControls(friendship_between((int)$me['unique_id'], $uid), (int)$me['unique_id'], $uid, 'users.php'); ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endwhile; ?>
      </div>
    </section>
  </main>
</div>
<?php include "part/app-footer.php"; ?>
<script src="assets/js/friends.js?v=1"></script>
</body>
</html>
