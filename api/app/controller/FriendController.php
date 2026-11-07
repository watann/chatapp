<?php
require_once __DIR__ . '/../helpers.php';

class FriendController{
    public function act(){
        require_auth();
        try{ verify_csrf(); }catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); $this->redirectBack(); }
        $me = (int)$_SESSION['unique_id'];
        $target = (int)($_POST['user_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');

        if($target <= 0 || $target === $me || ($action !== 'unblock' && !user_exists($target))){
            $_SESSION['flash_error'] = t('invalid_action');
            $this->redirectBack();
        }

        [$one, $two] = friend_pair($me, $target);
        $relation = friendship_between($me, $target);
        $conn = db();

        if($action === 'block'){
            $conn->begin_transaction();
            try{
                if($relation){ $d=$conn->prepare("DELETE FROM friendships WHERE id=?"); $d->bind_param('i',$relation['id']); $d->execute(); }
                $b=$conn->prepare("INSERT IGNORE INTO user_blocks(blocker_id,blocked_id,created_at) VALUES(?,?,NOW())"); $b->bind_param('ii',$me,$target); $b->execute();
                $conn->commit();
            }catch(Throwable $e){ $conn->rollback(); throw $e; }
            $this->redirectBack();
        }elseif($action === 'unblock'){
            $b=$conn->prepare("DELETE FROM user_blocks WHERE blocker_id=? AND blocked_id=?"); $b->bind_param('ii',$me,$target); $b->execute();
            $this->redirectBack();
        }
        if(is_blocked_between($me,$target)) $this->redirectBack();

        if($action === 'add'){
            if(!$relation){
                $status = 'pending';
                $stmt = $conn->prepare("INSERT INTO friendships (user_one, user_two, requested_by, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param('iiis', $one, $two, $me, $status);
                $stmt->execute();
            }elseif($relation['status'] === 'pending' && (int)$relation['requested_by'] === $target){
                $stmt = $conn->prepare("UPDATE friendships SET status='accepted', responded_at=NOW(), updated_at=NOW() WHERE id=?");
                $stmt->bind_param('i', $relation['id']);
                $stmt->execute();
            }
        }elseif($action === 'accept'){
            if($relation && $relation['status'] === 'pending' && (int)$relation['requested_by'] === $target){
                $stmt = $conn->prepare("UPDATE friendships SET status='accepted', responded_at=NOW(), updated_at=NOW() WHERE id=?");
                $stmt->bind_param('i', $relation['id']);
                $stmt->execute();
            }
        }elseif($action === 'decline' || $action === 'cancel'){
            if($relation && $relation['status'] === 'pending'){
                $canDelete = ($action === 'decline' && (int)$relation['requested_by'] === $target)
                    || ($action === 'cancel' && (int)$relation['requested_by'] === $me);
                if($canDelete){
                    $stmt = $conn->prepare("DELETE FROM friendships WHERE id=?");
                    $stmt->bind_param('i', $relation['id']);
                    $stmt->execute();
                }
            }
        }elseif($action === 'remove'){
            if($relation && $relation['status'] === 'accepted'){
                $stmt = $conn->prepare("DELETE FROM friendships WHERE id=?");
                $stmt->bind_param('i', $relation['id']);
                $stmt->execute();
            }
        }
        $this->redirectBack();
    }

    public function incomingRequests(){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $stmt = db()->prepare("SELECT f.id AS friendship_id, f.requested_by, u.unique_id, u.fname, u.middle_name, u.lname, u.img, u.role
            FROM friendships f
            JOIN users u ON u.unique_id = f.requested_by
            LEFT JOIN institution_registry r ON r.id=u.registry_id
            WHERE f.status='pending' AND (u.registry_id IS NULL OR r.status<>'suspended') AND f.requested_by <> ? AND (f.user_one=? OR f.user_two=?) AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=f.requested_by) OR (b.blocker_id=f.requested_by AND b.blocked_id=?))
            ORDER BY f.created_at DESC");
        $stmt->bind_param('iiiii', $me, $me, $me, $me, $me);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function acceptedFriends(){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $stmt = db()->prepare("SELECT u.unique_id, u.fname, u.middle_name, u.lname, u.img, u.role, u.status
            FROM friendships f
            JOIN users u ON u.unique_id = CASE WHEN f.user_one = ? THEN f.user_two ELSE f.user_one END
            LEFT JOIN institution_registry r ON r.id=u.registry_id
            WHERE f.status='accepted' AND (u.registry_id IS NULL OR r.status<>'suspended') AND (f.user_one=? OR f.user_two=?) AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?))
            ORDER BY (u.status='Active now') DESC, u.fname, u.middle_name, u.lname");
        $stmt->bind_param('iiiii', $me, $me, $me, $me, $me);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function communityRanking(){
        require_auth();
        cleanup_expired_stories();
        $me=(int)$_SESSION['unique_id'];
        // Ranking is deliberately limited to recent, meaningful activity so old/expired stories and spam do not dominate forever.
        $sql = "SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.img,u.role,u.status,
                COALESCE(pc.post_count,0) AS post_count, COALESCE(cc.comment_count,0) AS comment_count,
                COALESCE(lc.like_count,0) AS like_count, COALESCE(sc.story_count,0) AS story_count,
                (COALESCE(pc.post_count,0)*3 + COALESCE(cc.comment_count,0)*2 + COALESCE(lc.like_count,0) + COALESCE(sc.story_count,0)*2) AS activity_score
            FROM users u
            LEFT JOIN institution_registry r ON r.id=u.registry_id
            LEFT JOIN (SELECT user_unique_id,COUNT(*) AS post_count FROM posts WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY user_unique_id) pc ON pc.user_unique_id=u.unique_id
            LEFT JOIN (SELECT user_unique_id,COUNT(*) AS comment_count FROM post_comments WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY user_unique_id) cc ON cc.user_unique_id=u.unique_id
            LEFT JOIN (SELECT user_unique_id,COUNT(*) AS like_count FROM post_likes WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY user_unique_id) lc ON lc.user_unique_id=u.unique_id
            LEFT JOIN (SELECT user_unique_id,COUNT(*) AS story_count FROM stories WHERE expires_at>NOW() GROUP BY user_unique_id) sc ON sc.user_unique_id=u.unique_id
            WHERE (u.registry_id IS NULL OR r.status<>'suspended')
              AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?))
            ORDER BY activity_score DESC, post_count DESC, comment_count DESC, (u.status='Active now') DESC, u.fname ASC, u.lname ASC LIMIT 100";
        $stmt = db()->prepare($sql);
        $stmt->bind_param('ii',$me,$me);
        $stmt->execute();
        return $stmt->get_result();
    }

    private function redirectBack(){
        header('Location: ../' . safe_return_to($_POST['return_to'] ?? 'users.php', 'users.php'));
        exit;
    }
}
