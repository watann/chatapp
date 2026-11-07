<?php
require_once __DIR__ . '/app/helpers.php';
require_auth();
$me=(int)$_SESSION['unique_id'];
$type=(string)($_GET['type']??'');
$id=(int)($_GET['id']??0);
$row=null;
if($id>0 && $type==='post'){
    $stmt=db()->prepare("SELECT id,user_unique_id,visibility,media_path FROM posts WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
}elseif($id>0 && $type==='story'){
    $stmt=db()->prepare("SELECT id,user_unique_id,visibility,media_path,expires_at FROM stories WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
    if($row && strtotime((string)$row['expires_at'])<=time()) $row=null;
}
if(!$row || empty($row['media_path']) || !can_view_user_content((int)$row['user_unique_id'],(string)$row['visibility'],$me)){ http_response_code(404); exit; }
$stored=basename((string)$row['media_path']); $path=__DIR__.'/uploads/'.$stored;
if($stored==='' || !is_file($path)){ http_response_code(404); exit; }
$mime=function_exists('mime_content_type')?(@mime_content_type($path)?:'application/octet-stream'):'application/octet-stream';
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){ http_response_code(415); exit; }
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=180');
header('Content-Type: '.$mime); header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="content-media"'); readfile($path); exit;
