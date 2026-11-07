<?php
require_once __DIR__ . '/../../api/app/helpers.php';
if(isset($_GET['lang']) && in_array($_GET['lang'], supported_languages(), true)){
    $_SESSION['admin_language'] = $_GET['lang'];
}
$lang = user_language();
$dir = text_direction($lang);
?>
<!DOCTYPE html>
<html lang="<?php echo e($lang); ?>" dir="<?php echo e($dir); ?>" data-theme="light" data-accent="blue" data-density="comfortable">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e(t('admin_portal')); ?> - <?php echo e(t('app_name')); ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="icon" type="image/jpeg" href="../assets/img/project-logo.jpg">
  <link rel="stylesheet" href="../assets/css/app.css?v=18">
</head>
