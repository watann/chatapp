<?php
require_once "api/app/controller/AuthController.php";
require_once "api/app/controller/UserController.php";
$auth=new AuthController(); $auth->checkAuth();
$userController=new UserController();
$otherId=(int)($_GET['user_id']??0); $other=$userController->getUserById($otherId);
if(!$other || $otherId===(int)$_SESSION['unique_id']){ header('Location: users.php'); exit; }
include_once "part/header.php";
?>
<body><div class="app-layout"><?php include "part/sidebar.php"; ?><main class="app-main chat-main">
<section class="chat-window panel">
<header class="chat-header"><a href="users.php" class="icon-btn"><i class="fas fa-arrow-left"></i></a><a class="profile-avatar-link" href="profile.php?user_id=<?php echo (int)$other['unique_id']; ?>"><img class="avatar" src="<?php echo e(avatar_src($other['img'])); ?>" alt=""></a><div class="chat-person"><a class="profile-name-link" href="profile.php?user_id=<?php echo (int)$other['unique_id']; ?>"><h2><?php echo e(display_name($other)); ?></h2></a><div class="chat-meta"><span class="role-pill"><?php echo e(role_label($other['role'])); ?></span><span class="status-pill <?php echo $other['status']==='Active now'?'online':''; ?>"><?php echo e($other['status']==='Active now'?t('online_now'):t('offline_now')); ?></span></div></div><div class="chat-call-actions"><button type="button" class="icon-btn js-call-button" data-call-type="voice" data-user-id="<?php echo $otherId; ?>" data-user-name="<?php echo e(display_name($other)); ?>" data-user-avatar="<?php echo e(avatar_src($other['img'])); ?>" title="<?php echo e(t('voice_call')); ?>"><i class="fas fa-phone"></i></button><button type="button" class="icon-btn js-call-button" data-call-type="video" data-user-id="<?php echo $otherId; ?>" data-user-name="<?php echo e(display_name($other)); ?>" data-user-avatar="<?php echo e(avatar_src($other['img'])); ?>" title="<?php echo e(t('video_call')); ?>"><i class="fas fa-video"></i></button></div></header>
<div class="chat-box"></div>
<form action="#" class="typing-area composer" enctype="multipart/form-data"><?php echo csrf_input(); ?><input type="hidden" name="incoming_id" class="incoming_id" value="<?php echo $otherId; ?>"><label class="composer-attach" title="<?php echo e(t('attach_file')); ?>"><i class="fas fa-paperclip"></i><input type="file" name="attachment" class="attachment-input"></label><span class="selected-file-name"></span><input type="text" name="message" class="input-field" maxlength="5000" placeholder="<?php echo e(t('message_placeholder')); ?>" autocomplete="off"><button type="submit" aria-label="<?php echo e(t('send')); ?>"><i class="fas fa-paper-plane"></i></button></form>
</section></main></div><?php include "part/app-footer.php"; ?><script src="assets/js/chat.js?v=3"></script></body></html>
