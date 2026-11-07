<?php
require_once __DIR__ . '/../helpers.php';

class MessageController{
    public function insertChat(){
        require_auth();
        $cleanupPath = null;
        try{
            verify_csrf();
            $outgoing_id = (int)$_SESSION['unique_id'];
            $incoming_id = (int)($_POST['incoming_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            if($incoming_id <= 0 || $incoming_id === $outgoing_id || !user_exists($incoming_id) || is_blocked_between($outgoing_id,$incoming_id) || mb_strlen($message) > 5000){
                throw new Exception(t('not_allowed'));
            }
            $file = upload_chat_file($_FILES['attachment'] ?? null);
            if($message === '' && !$file) return;

            $path = $file['path'] ?? null; $cleanupPath = $path; $name = $file['name'] ?? null; $mime = $file['mime'] ?? null; $size = $file['size'] ?? null;
            $stmt = db()->prepare("INSERT INTO messages (incoming_msg_id,outgoing_msg_id,msg,attachment_path,attachment_name,attachment_mime,attachment_size,read_at,created_at) VALUES (?,?,?,?,?,?,?,NULL,NOW())");
            $stmt->bind_param('iissssi', $incoming_id, $outgoing_id, $message, $path, $name, $mime, $size);
            $stmt->execute();
            $cleanupPath = null;
        }catch(Throwable $e){
            if($cleanupPath) delete_chat_file($cleanupPath);
            http_response_code(400);
            echo e($e->getMessage());
        }
    }

    public function getChat(){
        require_auth();
        verify_csrf();
        $me = (int)$_SESSION['unique_id'];
        $other = (int)($_POST['incoming_id'] ?? 0);
        if($other <= 0 || $other === $me || !user_exists($other) || is_blocked_between($me,$other)){
            echo '<div class="empty-chat"><strong>'.e(t('not_allowed')).'</strong></div>'; return;
        }
        if(($_POST['mark_read'] ?? '1') !== '0'){
            $read = db()->prepare("UPDATE messages SET read_at=NOW() WHERE incoming_msg_id=? AND outgoing_msg_id=? AND read_at IS NULL");
            $read->bind_param('ii', $me, $other); $read->execute();
        }
        // Keep polling bounded. The most recent 200 messages are enough for the live view; older history can be loaded separately later.
        $stmt = db()->prepare("SELECT * FROM (SELECT m.*,u.fname,u.middle_name,u.lname,u.img FROM messages m LEFT JOIN users u ON u.unique_id=m.outgoing_msg_id WHERE (m.outgoing_msg_id=? AND m.incoming_msg_id=?) OR (m.outgoing_msg_id=? AND m.incoming_msg_id=?) ORDER BY m.msg_id DESC LIMIT 200) recent ORDER BY msg_id ASC");
        $stmt->bind_param('iiii', $me,$other,$other,$me); $stmt->execute(); $query=$stmt->get_result();
        if($query->num_rows===0){ echo '<div class="empty-chat"><div class="empty-icon">💬</div><strong>'.e(t('no_messages')).'</strong></div>'; return; }
        while($row=$query->fetch_assoc()) echo $this->renderMessage($row,$me,false);
    }

    public function notifications(){
        require_auth();
        header('Content-Type: application/json; charset=utf-8');
        $me=(int)$_SESSION['unique_id'];
        $stmt=db()->prepare("SELECT m.outgoing_msg_id AS user_id, MAX(m.msg_id) latest_id, COUNT(*) unread_count FROM messages m WHERE m.incoming_msg_id=? AND m.read_at IS NULL AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=m.incoming_msg_id AND b.blocked_id=m.outgoing_msg_id) OR (b.blocker_id=m.outgoing_msg_id AND b.blocked_id=m.incoming_msg_id)) GROUP BY m.outgoing_msg_id ORDER BY latest_id DESC LIMIT 6");
        $stmt->bind_param('i',$me); $stmt->execute(); $rows=$stmt->get_result(); $items=[];
        while($r=$rows->fetch_assoc()){
            $q=db()->prepare("SELECT m.msg_id,m.msg,m.attachment_name,m.created_at,u.fname,u.middle_name,u.lname,u.img,u.status FROM messages m JOIN users u ON u.unique_id=m.outgoing_msg_id WHERE m.msg_id=? LIMIT 1");
            $q->bind_param('i',$r['latest_id']); $q->execute(); $m=$q->get_result()->fetch_assoc(); if(!$m) continue;
            $items[]=['user_id'=>(int)$r['user_id'],'name'=>display_name($m),'avatar'=>avatar_src($m['img']),'status'=>$m['status'],'preview'=>message_preview($m),'unread_count'=>(int)$r['unread_count'],'latest_id'=>(int)$r['latest_id'],'created_at'=>$m['created_at']];
        }
        echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    public function download(){
        require_auth();
        $me=(int)$_SESSION['unique_id']; $id=(int)($_GET['id']??0);
        $stmt=db()->prepare("SELECT * FROM messages WHERE msg_id=? AND (incoming_msg_id=? OR outgoing_msg_id=?) LIMIT 1");
        $stmt->bind_param('iii',$id,$me,$me); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
        if(!$row || empty($row['attachment_path'])){ http_response_code(404); exit; }
        stream_chat_file($row['attachment_path'],$row['attachment_name'],$row['attachment_mime'],!empty($_GET['inline']));
    }

    private function renderMessage($row,$me,$group=false){
        $mine=(int)($group?$row['sender_unique_id']:$row['outgoing_msg_id'])===$me;
        $id=(int)($group?$row['id']:$row['msg_id']);
        $download=$group?'api/download-group-file.php?id='.$id:'api/download-chat-file.php?id='.$id;
        $html='<div class="chat-bubble '.($mine?'outgoing':'incoming').'">';
        if(!$mine) $html.='<img class="avatar tiny" src="'.e(avatar_src($row['img']??'')).'" alt="">';
        $html.='<div class="bubble">';
        if($group&&!$mine) $html.='<b>'.e(display_name($row)).'</b>';
        $text=(string)($group?($row['message']??''):($row['msg']??''));
        if($text!=='') $html.='<p>'.nl2br(e($text)).'</p>';
        if(!empty($row['attachment_path'])){
            if(is_previewable_image_mime($row['attachment_mime']??'')) $html.='<a class="chat-image-link" href="'.e($download).'" target="_blank"><img class="chat-image-preview" src="'.e($download.'&inline=1').'" alt="'.e($row['attachment_name']).'"></a>';
            $html.='<a class="chat-attachment" href="'.e($download).'"><i class="fas fa-paperclip"></i><span><strong>'.e($row['attachment_name']).'</strong><small>'.e(human_file_size($row['attachment_size']??0)).'</small></span><i class="fas fa-download"></i></a>';
        }
        $html.='<time>'.e(date('H:i',strtotime($row['created_at']??'now'))).'</time></div></div>';
        return $html;
    }

    public function renderGroupMessage($row,$me){ return $this->renderMessage($row,$me,true); }
}
