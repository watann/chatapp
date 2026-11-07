<?php
require_once __DIR__ . '/../api/app/helpers.php';
if(empty($_SESSION['unique_id']) && isset($_GET['lang']) && in_array($_GET['lang'], supported_languages(), true)){
    $_SESSION['guest_language'] = $_GET['lang'];
}
$userForHeader = current_user();
$lang = user_language();
$dir = text_direction($lang);
$prefsForHeader = user_preferences($userForHeader);
?>
<!DOCTYPE html>
<html lang="<?php echo e($lang); ?>" dir="<?php echo e($dir); ?>" data-theme="<?php echo e($prefsForHeader['theme_mode']); ?>" data-accent="<?php echo e($prefsForHeader['accent_color']); ?>" data-density="<?php echo e($prefsForHeader['ui_density']); ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(t('app_name')); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="icon" type="image/jpeg" href="assets/img/project-logo.jpg">
    <script>
      (function(){
        var r=document.documentElement, mq=window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        function apply(){var m=r.dataset.theme||'light';r.dataset.resolvedTheme=m==='system'?(mq&&mq.matches?'dark':'light'):m;}
        apply();
        if(mq&&mq.addEventListener) mq.addEventListener('change',function(){if(r.dataset.theme==='system') apply();});
      })();
    </script>
    <link rel="stylesheet" href="assets/css/app.css?v=17">
</head>
