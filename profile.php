<?php
require_once "api/app/controller/AuthController.php";
require_once "api/app/controller/ProfileController.php";
require_once "api/app/controller/FeedController.php";
require_once "api/app/controller/UserController.php";

$auth = new AuthController();
$auth->checkAuth();
$me = current_user();
$profileId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$me['unique_id'];
if($profileId <= 0) $profileId = (int)$me['unique_id'];

$profileController = new ProfileController();
$feedController = new FeedController();
$userController = new UserController();
$profileUser = $profileController->find($profileId);
$isOwner = $profileUser && (int)$profileUser['unique_id'] === (int)$me['unique_id'];
$returnTo = 'profile.php' . ($isOwner ? '' : '?user_id=' . $profileId);
$viewerForSocial = $me;
$blockedByMe=$profileUser && !$isOwner ? has_blocked((int)$me['unique_id'],$profileId) : false;
$blockedMe=$profileUser && !$isOwner ? has_blocked($profileId,(int)$me['unique_id']) : false;
$isBlocked=$blockedByMe||$blockedMe;

if($profileUser){
    $profilePosts = $isBlocked ? null : $feedController->profilePosts($profileId);
    $profileStories = $isBlocked ? null : $feedController->profileStories($profileId);
    $visiblePostCount = $isBlocked ? 0 : $profileController->visiblePostCount($profileId, (int)$me['unique_id']);
    $friendCount = $isBlocked ? 0 : $profileController->friendCount($profileId);
    $recentFriends = $isBlocked ? null : $profileController->recentFriends($profileId, 6);
    $relation = ($isOwner||$isBlocked) ? null : friendship_between((int)$me['unique_id'], $profileId);
}
include_once "part/header.php";
?>
<body>
<div class="app-layout facebook-layout">
  <?php include "part/sidebar.php"; ?>
  <main class="app-main profile-main">
    <?php if(!$profileUser): ?>
      <section class="panel empty-state profile-not-found"><i class="far fa-user-circle empty-icon"></i><strong><?php echo e(t('profile_not_found')); ?></strong><a class="btn primary" href="home.php"><?php echo e(t('home')); ?></a></section>
    <?php else: ?>
      <?php if(!empty($_SESSION['flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>
      <?php if(!empty($_SESSION['flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

      <section class="panel profile-hero-card">
        <div class="profile-cover"><div class="profile-cover-shine"></div></div>
        <div class="profile-hero-content">
          <img class="profile-hero-avatar" src="<?php echo e(avatar_src($profileUser['img'])); ?>" alt="">
          <div class="profile-hero-info">
            <div class="profile-title-row">
              <div>
                <h1><?php echo e(display_name($profileUser)); ?></h1>
                <p><span class="role-pill"><?php echo e(role_label($profileUser['role'])); ?></span><span class="status-pill <?php echo $profileUser['status']==='Active now'?'online':''; ?>"><?php echo e($profileUser['status']==='Active now' ? t('online_now') : t('offline_now')); ?></span></p>
              </div>
              <div class="profile-hero-actions">
                <?php if($isOwner): ?>
                  <a class="btn primary" href="settings.php#profile-settings"><i class="fas fa-user-edit"></i><?php echo e(t('edit_profile')); ?></a>
                <?php else: ?>
                  <?php if(!$isBlocked): ?><a class="btn ghost js-mini-chat" href="chat.php?user_id=<?php echo $profileId; ?>" data-user-id="<?php echo $profileId; ?>" data-user-name="<?php echo e(display_name($profileUser)); ?>" data-user-avatar="<?php echo e(avatar_src($profileUser['img'])); ?>"><i class="fab fa-facebook-messenger"></i><?php echo e(t('chat')); ?></a><?php echo $userController->friendControls($relation, (int)$me['unique_id'], $profileId, $returnTo); ?><?php endif; ?>
                  <?php if(!$blockedMe): echo $userController->blockControls((int)$me['unique_id'],$profileId,$returnTo); endif; ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="profile-metrics">
              <span><strong><?php echo (int)$visiblePostCount; ?></strong><?php echo e(t('posts')); ?></span>
              <span><strong><?php echo (int)$friendCount; ?></strong><?php echo e(t('friends')); ?></span>
              <span><i class="far fa-calendar-alt"></i><?php echo e(t('joined')); ?> <?php echo e(date('Y-m-d', strtotime($profileUser['created_at']))); ?></span>
            </div>
          </div>
        </div>
      </section>

      <?php if(!$isBlocked && $profileStories && $profileStories->num_rows > 0): ?>
      <section class="panel stories-panel profile-stories-panel">
        <div class="section-heading"><h2><?php echo e(t('active_stories')); ?></h2></div>
        <div class="stories-row">
          <?php while($story = $profileStories->fetch_assoc()): include __DIR__ . '/part/story-card.php'; endwhile; ?>
        </div>
      </section>
      <?php endif; ?>

      <div class="profile-content-grid">
        <aside class="profile-left-column">
          <section class="panel profile-intro-card">
            <h2><?php echo e(t('about')); ?></h2>
            <?php if(trim((string)$profileUser['bio']) !== ''): ?><p class="profile-bio"><?php echo nl2br(e($profileUser['bio'])); ?></p><?php else: ?><p class="subtle"><?php echo e(t('no_bio')); ?></p><?php endif; ?>
            <div class="intro-list">
              <span><i class="fas fa-user-tag"></i><?php echo e(role_label($profileUser['role'])); ?></span>
              <span><i class="fas fa-user-friends"></i><?php echo (int)$friendCount; ?> <?php echo e(t('friends')); ?></span>
              <span><i class="fas fa-calendar-check"></i><?php echo e(t('joined')); ?> <?php echo e(date('Y-m-d', strtotime($profileUser['created_at']))); ?></span>
            </div>
          </section>

          <section class="panel profile-friends-card">
            <div class="section-heading"><h2><?php echo e(t('friends')); ?></h2><span class="count-badge"><?php echo (int)$friendCount; ?></span></div>
            <?php if($isBlocked || !$recentFriends || $recentFriends->num_rows === 0): ?><p class="subtle"><?php echo e(t('no_friends_yet')); ?></p><?php else: ?>
              <div class="profile-friends-grid">
                <?php while($friend = $recentFriends->fetch_assoc()): ?>
                  <a class="profile-friend-tile" href="profile.php?user_id=<?php echo (int)$friend['unique_id']; ?>"><img src="<?php echo e(avatar_src($friend['img'])); ?>" alt=""><strong><?php echo e(display_name($friend)); ?></strong></a>
                <?php endwhile; ?>
              </div>
            <?php endif; ?>
          </section>
        </aside>

        <section class="profile-feed-column">
          <div class="section-heading profile-post-heading"><h2><?php echo e(t('profile_posts')); ?></h2></div>
          <div class="feed-list profile-feed-list">
            <?php if($isBlocked || !$profilePosts || $profilePosts->num_rows === 0): ?><section class="panel empty-state"><i class="far fa-newspaper empty-icon"></i><span><?php echo e(t('no_profile_posts')); ?></span></section><?php endif; ?>
            <?php if($profilePosts): while($post = $profilePosts->fetch_assoc()): include __DIR__ . '/part/post-card.php'; endwhile; endif; ?>
          </div>
        </section>
      </div>


    <?php endif; ?>
  </main>
</div>
<?php include "part/app-footer.php"; ?>
<script src="assets/js/social.js?v=3"></script>
</body>
</html>
