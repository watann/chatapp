<?php
$globalCurrentChat = ($currentPage ?? '') === 'chat.php' ? (int)($_GET['user_id'] ?? 0) : 0;
?>
<div id="global-chat-root"
     data-current-chat="<?php echo $globalCurrentChat; ?>"
     data-you="<?php echo (int)($_SESSION['unique_id'] ?? 0); ?>"
     data-label-new="<?php echo e(t('new_message')); ?>"
     data-label-attach="<?php echo e(t('attach_file')); ?>"
     data-label-voice="<?php echo e(t('voice_call')); ?>"
     data-label-video="<?php echo e(t('video_call')); ?>"
     data-label-close="<?php echo e(t('close_chat')); ?>"
     data-label-message="<?php echo e(t('message_placeholder')); ?>"></div>

<div id="incoming-call-card" class="incoming-call-card" hidden>
  <img class="avatar" id="incoming-call-avatar" src="assets/img/default-avatar.svg" alt="">
  <div class="incoming-call-info"><strong id="incoming-call-name"></strong><span id="incoming-call-kind"></span></div>
  <div class="incoming-call-actions"><button type="button" class="call-round accept" id="accept-call" aria-label="<?php echo e(t('answer_call')); ?>"><i class="fas fa-phone"></i></button><button type="button" class="call-round decline" id="decline-call" aria-label="<?php echo e(t('decline_call')); ?>"><i class="fas fa-phone-slash"></i></button></div>
</div>

<div id="active-call-overlay" class="active-call-overlay" hidden>
  <div class="active-call-card">
    <div class="call-person"><img id="active-call-avatar" class="call-avatar" src="assets/img/default-avatar.svg" alt=""><h2 id="active-call-name"></h2><p id="active-call-status"><?php echo e(t('connecting')); ?></p></div>
    <video id="remote-video" autoplay playsinline></video><video id="local-video" autoplay muted playsinline></video><audio id="remote-audio" autoplay></audio>
    <div class="call-controls"><button type="button" id="toggle-mic" class="call-round neutral" title="<?php echo e(t('mute')); ?>"><i class="fas fa-microphone"></i></button><button type="button" id="toggle-camera" class="call-round neutral" title="<?php echo e(t('camera_off')); ?>"><i class="fas fa-video"></i></button><button type="button" id="end-call" class="call-round decline" title="<?php echo e(t('end_call')); ?>"><i class="fas fa-phone-slash"></i></button></div>
  </div>
</div>
<script>window.RUNAKI_CALL_I18N=<?php echo json_encode([
'voice_call'=>t('voice_call'),'video_call'=>t('video_call'),'incoming_voice_call'=>t('incoming_voice_call'),'incoming_video_call'=>t('incoming_video_call'),'calling'=>t('calling'),'connecting'=>t('connecting'),'call_ended'=>t('call_ended'),'call_declined'=>t('call_declined'),'call_missed'=>t('call_missed'),'call_failed'=>t('call_failed'),'secure_context_required'=>t('secure_context_required')
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="assets/js/global-chat.js?v=3"></script>
<script src="assets/js/call.js?v=3"></script>
