<?php
// Images may be reviewed only while a post is pending in this moderator's own queue.
require_once __DIR__.'/../../api/app/controller/AdminController.php';
require_once __DIR__.'/../../api/app/post_moderation.php';
$admin=(new AdminController())->requireAdmin();
$id=(int)($_GET['post_id']??0);
if($id<=0 || !moderation_can_access($admin)){http_response_code(404);exit;}
$table=moderation_is_super($admin)?'post_reports':'post_word_alerts';
$stmt=db()->prepare("SELECT p.media_path,op.media_path AS root_media FROM posts p
    LEFT JOIN posts op ON op.id=p.shared_post_id
    WHERE p.id=? AND EXISTS (SELECT 1 FROM {$table} q WHERE q.post_id=p.id AND q.status='pending') LIMIT 1");
$stmt->bind_param('i',$id);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
if(!$row){http_response_code(404);exit;}
$filename=basename((string)($row['media_path']?:$row['root_media']));
if($filename===''){http_response_code(404);exit;}
$path=__DIR__.'/../../api/uploads/'.$filename;
if(!is_file($path)){http_response_code(404);exit;}
$mime=@mime_content_type($path);
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="post-media"');readfile($path);exit;
