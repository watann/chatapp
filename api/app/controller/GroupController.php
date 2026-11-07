<?php
require_once __DIR__ . '/../helpers.php';

class GroupController{

    private function attachmentPaths($groupId,$conn=null){
        $groupId=(int)$groupId; $conn=$conn ?: db(); $paths=[];
        $stmt=$conn->prepare("SELECT attachment_path FROM group_messages WHERE group_id=? AND attachment_path IS NOT NULL AND attachment_path<>''");
        $stmt->bind_param('i',$groupId); $stmt->execute(); $result=$stmt->get_result();
        while($row=$result->fetch_assoc()) $paths[]=(string)$row['attachment_path'];
        return array_values(array_unique($paths));
    }

    private function deleteAttachments($paths){
        foreach((array)$paths as $path) delete_chat_file($path);
    }
    public function create(){
        require_auth();
        try{ verify_csrf(); }catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); header('Location: ../groups.php'); exit; }
        $name = trim($_POST['name'] ?? '');
        $members = $_POST['members'] ?? [];
        if($name === '' || mb_strlen($name) > 160){
            $_SESSION['flash_error'] = t('invalid_action');
            header('Location: ../groups.php');
            exit;
        }
        if(!is_array($members)){
            $members = [];
        }
        $me = (int)$_SESSION['unique_id'];
        $members = array_values(array_unique(array_filter(array_map('intval', $members), fn($id) => $id > 0 && $id !== $me && user_exists($id) && !is_blocked_between($me,$id))));
        $conn = db();
        $conn->begin_transaction();
        try{
            $stmt = $conn->prepare("INSERT INTO chat_groups (name, created_by, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->bind_param('si', $name, $me);
            $stmt->execute();
            $group_id = $conn->insert_id;

            $role = 'admin';
            $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_unique_id, role, joined_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('iis', $group_id, $me, $role);
            $stmt->execute();

            $role = 'member';
            $stmt = $conn->prepare("INSERT IGNORE INTO group_members (group_id, user_unique_id, role, joined_at) VALUES (?, ?, ?, NOW())");
            foreach($members as $member){
                $stmt->bind_param('iis', $group_id, $member, $role);
                $stmt->execute();
            }
            $conn->commit();
            header('Location: ../group-chat.php?group_id=' . $group_id);
            exit;
        }catch(Throwable $e){
            $conn->rollback();
            $_SESSION['flash_error'] = t('invalid_action');
            header('Location: ../groups.php');
            exit;
        }
    }

    public function sendMessage(){
        require_auth();
        $cleanupPath = null;
        try{
            verify_csrf();
            $group_id = (int)($_POST['group_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $me = (int)$_SESSION['unique_id'];
            if($group_id <= 0 || mb_strlen($message) > 5000 || !is_group_member($group_id, $me)) throw new Exception(t('not_allowed'));
            $file = upload_chat_file($_FILES['attachment'] ?? null);
            if($message === '' && !$file) return;
            $path=$file['path']??null; $cleanupPath=$path; $name=$file['name']??null; $mime=$file['mime']??null; $size=$file['size']??null;
            $stmt = db()->prepare("INSERT INTO group_messages (group_id,sender_unique_id,message,attachment_path,attachment_name,attachment_mime,attachment_size,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
            $stmt->bind_param('iissssi', $group_id, $me, $message, $path, $name, $mime, $size);
            $stmt->execute(); $cleanupPath=null;
            $stmt = db()->prepare("UPDATE chat_groups SET updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $group_id); $stmt->execute();
        }catch(Throwable $e){ if($cleanupPath) delete_chat_file($cleanupPath); http_response_code(400); echo e($e->getMessage()); }
    }

    public function getMessages(){
        require_auth();
        verify_csrf();
        $group_id=(int)($_POST['group_id']??0); $me=(int)$_SESSION['unique_id'];
        if($group_id<=0 || !is_group_member($group_id,$me)){ echo '<div class="empty-chat"><strong>'.e(t('not_allowed')).'</strong></div>'; return; }
        $stmt=db()->prepare("SELECT * FROM (SELECT gm.*,u.fname,u.middle_name,u.lname,u.img FROM group_messages gm JOIN users u ON u.unique_id=gm.sender_unique_id WHERE gm.group_id=? ORDER BY gm.id DESC LIMIT 200) recent ORDER BY id ASC");
        $stmt->bind_param('i',$group_id); $stmt->execute(); $result=$stmt->get_result();
        if($result->num_rows===0){ echo '<div class="empty-chat"><div class="empty-icon">👥</div><strong>'.e(t('no_messages')).'</strong></div>'; return; }
        require_once __DIR__ . '/MessageController.php'; $renderer=new MessageController();
        while($row=$result->fetch_assoc()) echo $renderer->renderGroupMessage($row,$me);
    }

    public function downloadFile(){
        require_auth();
        $me=(int)$_SESSION['unique_id']; $id=(int)($_GET['id']??0);
        $stmt=db()->prepare("SELECT gm.* FROM group_messages gm JOIN group_members m ON m.group_id=gm.group_id AND m.user_unique_id=? WHERE gm.id=? LIMIT 1");
        $stmt->bind_param('ii',$me,$id); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
        if(!$row || empty($row['attachment_path'])){ http_response_code(404); exit; }
        stream_chat_file($row['attachment_path'],$row['attachment_name'],$row['attachment_mime'],!empty($_GET['inline']));
    }

    public function listForUser(){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $stmt = db()->prepare("SELECT g.*, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) AS member_count
            FROM chat_groups g
            JOIN group_members my_membership ON my_membership.group_id = g.id
            WHERE my_membership.user_unique_id = ?
            ORDER BY g.updated_at DESC");
        $stmt->bind_param('i', $me);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function getGroup($group_id){
        $stmt = db()->prepare("SELECT * FROM chat_groups WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $group_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function membershipRole($groupId,$userId){
        $groupId=(int)$groupId; $userId=(int)$userId;
        $stmt=db()->prepare("SELECT role FROM group_members WHERE group_id=? AND user_unique_id=? LIMIT 1");
        $stmt->bind_param('ii',$groupId,$userId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
        return $row['role']??null;
    }

    public function members($groupId){
        require_auth(); $me=(int)$_SESSION['unique_id']; $groupId=(int)$groupId;
        if(!is_group_member($groupId,$me)) throw new Exception(t('not_allowed'));
        $stmt=db()->prepare("SELECT gm.user_unique_id,gm.role,gm.joined_at,u.fname,u.middle_name,u.lname,u.img,u.role AS user_role FROM group_members gm JOIN users u ON u.unique_id=gm.user_unique_id WHERE gm.group_id=? ORDER BY (gm.role='admin') DESC,gm.joined_at,u.fname");
        $stmt->bind_param('i',$groupId); $stmt->execute(); return $stmt->get_result();
    }

    public function candidates($groupId){
        require_auth(); $me=(int)$_SESSION['unique_id']; $groupId=(int)$groupId;
        if($this->membershipRole($groupId,$me)!=='admin') return [];
        $stmt=db()->prepare("SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.img,u.role FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id<>? AND (u.registry_id IS NULL OR r.status<>'suspended') AND NOT EXISTS(SELECT 1 FROM group_members gm WHERE gm.group_id=? AND gm.user_unique_id=u.unique_id) AND NOT EXISTS(SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?)) ORDER BY u.fname,u.middle_name,u.lname");
        $stmt->bind_param('iiii',$me,$groupId,$me,$me); $stmt->execute(); return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function manage(){
        require_auth();
        $me=(int)$_SESSION['unique_id']; $groupId=(int)($_POST['group_id']??0); $action=(string)($_POST['action']??'');
        try{
            verify_csrf();
            if($groupId<=0 || !is_group_member($groupId,$me)) throw new Exception(t('not_allowed'));
            $myRole=$this->membershipRole($groupId,$me);
            if($action==='rename'){
                if($myRole!=='admin') throw new Exception(t('not_allowed'));
                $name=trim((string)($_POST['name']??'')); if($name===''||mb_strlen($name)>160) throw new Exception(t('invalid_action'));
                $q=db()->prepare("UPDATE chat_groups SET name=?,updated_at=NOW() WHERE id=?"); $q->bind_param('si',$name,$groupId); $q->execute();
            }elseif($action==='add_member'){
                if($myRole!=='admin') throw new Exception(t('not_allowed'));
                $uid=(int)($_POST['user_id']??0); if($uid<=0||!user_exists($uid)||is_blocked_between($me,$uid)) throw new Exception(t('not_allowed'));
                $role='member'; $q=db()->prepare("INSERT IGNORE INTO group_members(group_id,user_unique_id,role,joined_at) VALUES(?,?,?,NOW())"); $q->bind_param('iis',$groupId,$uid,$role); $q->execute();
            }elseif($action==='remove_member'){
                if($myRole!=='admin') throw new Exception(t('not_allowed'));
                $uid=(int)($_POST['user_id']??0); if($uid<=0||$uid===$me) throw new Exception(t('not_allowed'));
                if($this->membershipRole($groupId,$uid)==='admin'){
                    $c=db()->prepare("SELECT COUNT(*) c FROM group_members WHERE group_id=? AND role='admin'"); $c->bind_param('i',$groupId); $c->execute(); if((int)$c->get_result()->fetch_assoc()['c']<=1) throw new Exception(t('group_needs_admin'));
                }
                $q=db()->prepare("DELETE FROM group_members WHERE group_id=? AND user_unique_id=?"); $q->bind_param('ii',$groupId,$uid); $q->execute();
            }elseif($action==='toggle_admin'){
                if($myRole!=='admin') throw new Exception(t('not_allowed'));
                $uid=(int)($_POST['user_id']??0); if($uid<=0||$uid===$me) throw new Exception(t('not_allowed'));
                $role=$this->membershipRole($groupId,$uid); if(!$role) throw new Exception(t('not_allowed'));
                if($role==='admin'){
                    $c=db()->prepare("SELECT COUNT(*) c FROM group_members WHERE group_id=? AND role='admin'"); $c->bind_param('i',$groupId); $c->execute(); if((int)$c->get_result()->fetch_assoc()['c']<=1) throw new Exception(t('group_needs_admin'));
                    $new='member';
                }else $new='admin';
                $q=db()->prepare("UPDATE group_members SET role=? WHERE group_id=? AND user_unique_id=?"); $q->bind_param('sii',$new,$groupId,$uid); $q->execute();
            }elseif($action==='leave'){
                $conn=db(); $filesToDelete=[]; $conn->begin_transaction();
                try{
                    if($myRole==='admin'){
                        $c=$conn->prepare("SELECT COUNT(*) c FROM group_members WHERE group_id=? AND role='admin'"); $c->bind_param('i',$groupId); $c->execute(); $admins=(int)$c->get_result()->fetch_assoc()['c'];
                        if($admins<=1){
                            $n=$conn->prepare("SELECT user_unique_id FROM group_members WHERE group_id=? AND user_unique_id<>? ORDER BY joined_at,id LIMIT 1 FOR UPDATE"); $n->bind_param('ii',$groupId,$me); $n->execute(); $next=$n->get_result()->fetch_assoc();
                            if($next){ $uid=(int)$next['user_unique_id']; $u=$conn->prepare("UPDATE group_members SET role='admin' WHERE group_id=? AND user_unique_id=?"); $u->bind_param('ii',$groupId,$uid); $u->execute(); }
                        }
                    }
                    $q=$conn->prepare("DELETE FROM group_members WHERE group_id=? AND user_unique_id=?"); $q->bind_param('ii',$groupId,$me); $q->execute();
                    $c=$conn->prepare("SELECT COUNT(*) c FROM group_members WHERE group_id=?"); $c->bind_param('i',$groupId); $c->execute();
                    if((int)$c->get_result()->fetch_assoc()['c']===0){
                        $filesToDelete=$this->attachmentPaths($groupId,$conn);
                        $d=$conn->prepare("DELETE FROM chat_groups WHERE id=?"); $d->bind_param('i',$groupId); $d->execute();
                    }
                    $conn->commit(); $this->deleteAttachments($filesToDelete); header('Location: ../groups.php'); exit;
                }catch(Throwable $e){ $conn->rollback(); throw $e; }
            }elseif($action==='delete_group'){
                if($myRole!=='admin') throw new Exception(t('not_allowed'));
                $conn=db(); $filesToDelete=[]; $conn->begin_transaction();
                try{
                    $filesToDelete=$this->attachmentPaths($groupId,$conn);
                    $q=$conn->prepare("DELETE FROM chat_groups WHERE id=?"); $q->bind_param('i',$groupId); $q->execute();
                    if($q->affected_rows!==1) throw new Exception(t('invalid_action'));
                    $conn->commit();
                }catch(Throwable $e){ $conn->rollback(); throw $e; }
                $this->deleteAttachments($filesToDelete); header('Location: ../groups.php'); exit;
            }else throw new Exception(t('invalid_action'));
            $_SESSION['flash_success']=t('group_updated');
        }catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); }
        header('Location: ../group-chat.php?group_id='.$groupId); exit;
    }

    public function memberCount($group_id){
        $stmt = db()->prepare("SELECT COUNT(*) AS total FROM group_members WHERE group_id = ?");
        $stmt->bind_param('i', $group_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
}
