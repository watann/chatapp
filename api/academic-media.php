<?php
require_once __DIR__ . '/app/controller/AcademicController.php';
require_auth();
$academic=new AcademicController();
$post=$academic->academicMediaPost((int)($_GET['id']??0));
if(!$post){ http_response_code(404); exit; }
$stored=basename((string)$post['media_path']);
$path=academic_media_dir().$stored;
if($stored==='' || !is_file($path)){ http_response_code(404); exit; }
$mime=function_exists('mime_content_type') ? (@mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){ http_response_code(415); exit; }
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="academic-media"');
readfile($path);
exit;
