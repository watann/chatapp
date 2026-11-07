<?php
require_once __DIR__.'/../../api/app/controller/AdminController.php';
require_once __DIR__.'/../../api/app/post_moderation.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$admin=(new AdminController())->requireAdmin();
if(!moderation_can_access($admin)) { http_response_code(403);echo json_encode(['error'=>'Forbidden']);exit; }
try { echo json_encode(moderation_alert_counts($admin),JSON_UNESCAPED_UNICODE); }
catch(Throwable $e) {http_response_code(503);echo json_encode(['error'=>'Moderation tables are not available']);}
