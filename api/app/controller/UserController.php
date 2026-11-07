<?php
require_once __DIR__ . '/../helpers.php';

class UserController{
    public function getData(){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $stmt = db()->prepare("SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.email,u.img,u.status,u.role FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id <> ? AND (u.registry_id IS NULL OR r.status<>'suspended') AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?)) ORDER BY u.status = 'Active now' DESC,u.fname ASC,u.lname ASC");
        $stmt->bind_param('iii', $me, $me, $me);
        $stmt->execute();
        echo $this->renderUsers($stmt->get_result());
    }

    public function searchUser($searchTerm){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $term = '%' . trim($searchTerm) . '%';
        $stmt = db()->prepare("SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.email,u.img,u.status,u.role FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id <> ? AND (u.registry_id IS NULL OR r.status<>'suspended') AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?)) AND (u.fname LIKE ? OR u.middle_name LIKE ? OR u.lname LIKE ? OR u.email LIKE ?) ORDER BY u.status = 'Active now' DESC,u.fname ASC,u.lname ASC");
        $stmt->bind_param('iiissss', $me, $me, $me, $term, $term, $term, $term);
        $stmt->execute();
        echo $this->renderUsers($stmt->get_result());
    }

    public function getUserById($unique_id){
        $stmt = db()->prepare("SELECT u.* FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id=? AND (u.registry_id IS NULL OR r.status<>'suspended') LIMIT 1");
        $stmt->bind_param('i', $unique_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function renderUsers($result){
        if($result->num_rows === 0){
            return '<div class="empty-state"><strong>'.e(t('no_users')).'</strong></div>';
        }

        $me = (int)$_SESSION['unique_id'];
        $html = '';
        while($row = $result->fetch_assoc()){
            $other = (int)$row['unique_id'];
            $last = last_direct_message($other);
            $lastText = $last ? message_preview($last) : t('no_messages');
            if(mb_strlen($lastText) > 52){
                $lastText = mb_substr($lastText, 0, 52) . '...';
            }
            $online = $row['status'] === 'Active now';
            $relation = friendship_between($me, $other);

            $profileUrl = 'profile.php?user_id=' . $other;
            $html .= '<article class="person-card">'
                . '<a class="profile-avatar-link" href="'.e($profileUrl).'"><img class="avatar" src="'.e(avatar_src($row['img'])).'" alt=""></a>'
                . '<div class="person-main">'
                . '<div class="person-title"><div><a class="profile-name-link" href="'.e($profileUrl).'"><strong>'.e(display_name($row)).'</strong></a><span class="role-pill">'.e(role_label($row['role'])).'</span></div><span class="status-pill '.($online ? 'online' : '').'">'.e($online ? t('online_now') : t('offline_now')).'</span></div>'
                . '<p>'.e($lastText).'</p>'
                . '<div class="person-actions">'
                . '<a class="btn small ghost js-mini-chat" href="chat.php?user_id='.$other.'" data-user-id="'.$other.'" data-user-name="'.e(display_name($row)).'" data-user-avatar="'.e(avatar_src($row['img'])).'"><i class="fas fa-comment"></i>'.e(t('chat')).'</a>'
                . $this->friendControls($relation, $me, $other)
                . '</div></div></article>';
        }
        return $html;
    }

    public function friendControls($relation, $me, $other, $returnTo = 'users.php'){
        if(!$relation){
            return $this->actionForm($other, 'add', t('add_friend'), 'primary', $returnTo);
        }
        if($relation['status'] === 'accepted'){
            return '<span class="friend-badge"><i class="fas fa-user-check"></i>'.e(t('friends')).'</span>'
                . $this->actionForm($other, 'remove', t('remove_friend'), 'danger ghost', $returnTo);
        }
        if((int)$relation['requested_by'] === $me){
            return '<span class="request-badge">'.e(t('request_sent')).'</span>'
                . $this->actionForm($other, 'cancel', t('cancel_request'), 'ghost', $returnTo);
        }
        return $this->actionForm($other, 'accept', t('accept'), 'primary', $returnTo)
            . $this->actionForm($other, 'decline', t('decline'), 'danger ghost', $returnTo);
    }

    public function blockControls($me,$other,$returnTo='users.php'){
        $me=(int)$me; $other=(int)$other;
        $stmt=db()->prepare("SELECT id FROM user_blocks WHERE blocker_id=? AND blocked_id=? LIMIT 1"); $stmt->bind_param('ii',$me,$other); $stmt->execute();
        if($stmt->get_result()->num_rows) return $this->actionForm($other,'unblock',t('unblock_user'),'ghost',$returnTo);
        return $this->actionForm($other,'block',t('block_user'),'danger ghost',$returnTo);
    }

    private function actionForm($other, $action, $label, $class, $returnTo = 'users.php'){
        return '<form action="api/friend-action.php" method="post" class="inline-form">'.csrf_input()
            . '<input type="hidden" name="user_id" value="'.(int)$other.'">'
            . '<input type="hidden" name="action" value="'.e($action).'">'
            . '<input type="hidden" name="return_to" value="'.e($returnTo).'">'
            . '<button type="submit" class="btn small '.e($class).'">'.e($label).'</button>'
            . '</form>';
    }
}
