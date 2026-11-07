<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../post_moderation.php';

class FeedController{
    public function createPost(){
        require_auth();
        $cleanupMedia=null;
        try{
            verify_csrf();
            $me = (int)$_SESSION['unique_id'];
            $content = trim($_POST['content'] ?? '');
            $visibility = $this->visibility($_POST['visibility'] ?? 'friends');
            if(mb_strlen($content) > 5000) throw new Exception(t('post_too_long'));

            $media = upload_content_media($_FILES['media'] ?? null);
            $cleanupMedia=$media;
            if($content === '' && !$media) throw new Exception(t('content_required'));

            db()->begin_transaction();
            try{
                $stmt = db()->prepare("INSERT INTO posts (user_unique_id, content, media_path, visibility, shared_post_id, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, NOW(), NOW())");
                $stmt->bind_param('isss', $me, $content, $media, $visibility);
                $stmt->execute();
                moderation_scan_post((int)$stmt->insert_id,$content);
                db()->commit();
            }catch(Throwable $e){ db()->rollback(); throw $e; }
            $cleanupMedia=null;
            $_SESSION['flash_success'] = t('post_created');
            $this->redirect($_POST['return_to'] ?? 'home.php');
        }catch(Throwable $e){
            if($cleanupMedia) delete_content_media($cleanupMedia);
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect($_POST['return_to'] ?? 'home.php');
        }
    }

    public function createStory(){
        require_auth();
        $cleanupMedia=null;
        try{
            verify_csrf();
            $me = (int)$_SESSION['unique_id'];
            $content = trim($_POST['content'] ?? '');
            $visibility = $this->visibility($_POST['visibility'] ?? 'friends');
            if(mb_strlen($content) > 700) throw new Exception(t('story_too_long'));

            $media = upload_content_media($_FILES['media'] ?? null);
            $cleanupMedia=$media;
            if($content === '' && !$media) throw new Exception(t('content_required'));

            $stmt = db()->prepare("INSERT INTO stories (user_unique_id, content, media_path, visibility, created_at, updated_at, expires_at) VALUES (?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->bind_param('isss', $me, $content, $media, $visibility);
            $stmt->execute();
            $cleanupMedia=null;
            $_SESSION['flash_success'] = t('story_created');
            $this->redirect($_POST['return_to'] ?? 'home.php');
        }catch(Throwable $e){
            if($cleanupMedia) delete_content_media($cleanupMedia);
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect($_POST['return_to'] ?? 'home.php');
        }
    }

    public function handlePostAction(){
        require_auth();
        try{ verify_csrf(); }catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); $this->redirect($_POST['return_to']??'home.php'); }
        $action = (string)($_POST['action'] ?? '');
        $returnTo = $_POST['return_to'] ?? 'home.php';
        try{
            switch($action){
                case 'toggle_like': $this->toggleLike(); break;
                case 'comment': $this->addComment(); break;
                case 'share': $this->sharePost(); break;
                case 'edit': $this->editPost(); break;
                case 'delete': $this->deletePost(); break;
                case 'report': $this->reportPost(); break;
                default: throw new Exception(t('invalid_action'));
            }
        }catch(Throwable $e){
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect($returnTo);
    }

    public function handleStoryAction(){
        require_auth();
        try{ verify_csrf(); }catch(Throwable $e){ $_SESSION['flash_error']=$e->getMessage(); $this->redirect($_POST['return_to']??'home.php'); }
        $action = (string)($_POST['action'] ?? '');
        $returnTo = $_POST['return_to'] ?? 'home.php';
        try{
            if($action === 'edit') $this->editStory();
            elseif($action === 'delete') $this->deleteStory();
            else throw new Exception(t('invalid_action'));
        }catch(Throwable $e){
            $_SESSION['flash_error'] = $e->getMessage();
        }
        $this->redirect($returnTo);
    }

    public function posts($scope){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $select = $this->postSelect();
        $guard = " AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id={$me} AND b.blocked_id=p.user_unique_id) OR (b.blocker_id=p.user_unique_id AND b.blocked_id={$me})) AND NOT EXISTS (SELECT 1 FROM institution_registry rr WHERE rr.id=u.registry_id AND rr.status='suspended') AND (p.shared_post_id IS NULL OR (NOT EXISTS (SELECT 1 FROM user_blocks ob WHERE (ob.blocker_id={$me} AND ob.blocked_id=op.user_unique_id) OR (ob.blocker_id=op.user_unique_id AND ob.blocked_id={$me})) AND NOT EXISTS (SELECT 1 FROM institution_registry orr JOIN users ou2 ON ou2.registry_id=orr.id WHERE ou2.unique_id=op.user_unique_id AND orr.status='suspended')))";
        if($scope === 'public'){
            $stmt = db()->prepare($select . " WHERE p.visibility='public'" . $guard . " ORDER BY p.created_at DESC LIMIT 100");
            $stmt->bind_param('i', $me);
        }elseif($scope === 'home'){
            $stmt = db()->prepare($select . " WHERE (p.user_unique_id=? OR p.visibility='public' OR (p.visibility='friends' AND EXISTS (SELECT 1 FROM friendships f WHERE f.status='accepted' AND ((f.user_one=? AND f.user_two=p.user_unique_id) OR (f.user_two=? AND f.user_one=p.user_unique_id)))))" . $guard . " ORDER BY p.created_at DESC LIMIT 100");
            $stmt->bind_param('iiii', $me, $me, $me, $me);
        }else{
            $stmt = db()->prepare($select . " WHERE (p.user_unique_id=? OR EXISTS (
                SELECT 1 FROM friendships f WHERE f.status='accepted'
                AND ((f.user_one=? AND f.user_two=p.user_unique_id) OR (f.user_two=? AND f.user_one=p.user_unique_id))
            ))" . $guard . " ORDER BY p.created_at DESC LIMIT 100");
            $stmt->bind_param('iiii', $me, $me, $me, $me);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    public function stories($scope){
        require_auth();
        cleanup_expired_stories();
        $me = (int)$_SESSION['unique_id'];
        $storyGuard=" AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id={$me} AND b.blocked_id=s.user_unique_id) OR (b.blocker_id=s.user_unique_id AND b.blocked_id={$me})) AND NOT EXISTS (SELECT 1 FROM institution_registry rr WHERE rr.id=u.registry_id AND rr.status='suspended')";
        if($scope === 'public'){
            $stmt = db()->prepare("SELECT s.*, u.fname, u.middle_name, u.lname, u.img, u.role FROM stories s JOIN users u ON u.unique_id=s.user_unique_id WHERE s.visibility='public' AND s.expires_at > NOW()".$storyGuard." ORDER BY s.created_at DESC LIMIT 100");
        }elseif($scope === 'home'){
            $stmt = db()->prepare("SELECT s.*,u.fname,u.middle_name,u.lname,u.img,u.role FROM stories s JOIN users u ON u.unique_id=s.user_unique_id WHERE s.expires_at>NOW() AND (s.user_unique_id=? OR s.visibility='public' OR (s.visibility='friends' AND EXISTS (SELECT 1 FROM friendships f WHERE f.status='accepted' AND ((f.user_one=? AND f.user_two=s.user_unique_id) OR (f.user_two=? AND f.user_one=s.user_unique_id)))))".$storyGuard." ORDER BY s.created_at DESC LIMIT 100");
            $stmt->bind_param('iii', $me, $me, $me);
        }else{
            $stmt = db()->prepare("SELECT s.*, u.fname, u.middle_name, u.lname, u.img, u.role FROM stories s JOIN users u ON u.unique_id=s.user_unique_id
                WHERE s.expires_at > NOW() AND (s.user_unique_id=? OR EXISTS (
                    SELECT 1 FROM friendships f WHERE f.status='accepted'
                    AND ((f.user_one=? AND f.user_two=s.user_unique_id) OR (f.user_two=? AND f.user_one=s.user_unique_id))
                ))".$storyGuard." ORDER BY s.created_at DESC LIMIT 100");
            $stmt->bind_param('iii', $me, $me, $me);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    public function profilePosts($profileId){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $profileId = (int)$profileId;
        $select = $this->postSelect();
        if($profileId === $me){
            $stmt = db()->prepare($select . " WHERE p.user_unique_id=? ORDER BY p.created_at DESC LIMIT 100");
            $stmt->bind_param('ii', $me, $profileId);
        }elseif(are_friends($me, $profileId)){
            $stmt = db()->prepare($select . " WHERE p.user_unique_id=? AND p.visibility IN ('public','friends') ORDER BY p.created_at DESC LIMIT 100");
            $stmt->bind_param('ii', $me, $profileId);
        }else{
            $stmt = db()->prepare($select . " WHERE p.user_unique_id=? AND p.visibility='public' ORDER BY p.created_at DESC LIMIT 100");
            $stmt->bind_param('ii', $me, $profileId);
        }
        $stmt->execute();
        return $stmt->get_result();
    }

    public function profileStories($profileId){
        require_auth();
        $me = (int)$_SESSION['unique_id'];
        $profileId = (int)$profileId;
        if($profileId === $me){
            $stmt = db()->prepare("SELECT s.*, u.fname, u.middle_name, u.lname, u.img, u.role FROM stories s JOIN users u ON u.unique_id=s.user_unique_id WHERE s.user_unique_id=? AND s.expires_at>NOW() ORDER BY s.created_at DESC");
        }elseif(are_friends($me, $profileId)){
            $stmt = db()->prepare("SELECT s.*, u.fname, u.middle_name, u.lname, u.img, u.role FROM stories s JOIN users u ON u.unique_id=s.user_unique_id WHERE s.user_unique_id=? AND s.visibility IN ('public','friends') AND s.expires_at>NOW() ORDER BY s.created_at DESC");
        }else{
            $stmt = db()->prepare("SELECT s.*, u.fname, u.middle_name, u.lname, u.img, u.role FROM stories s JOIN users u ON u.unique_id=s.user_unique_id WHERE s.user_unique_id=? AND s.visibility='public' AND s.expires_at>NOW() ORDER BY s.created_at DESC");
        }
        $stmt->bind_param('i', $profileId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function commentsForPost($postId, $limit = 12){
        require_auth();
        $postId = (int)$postId;
        $limit = max(1, min(50, (int)$limit));
        $me=(int)$_SESSION['unique_id'];
        $sql = "SELECT pc.*, u.fname, u.middle_name, u.lname, u.img, u.role FROM post_comments pc JOIN users u ON u.unique_id=pc.user_unique_id LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE pc.post_id=? AND (u.registry_id IS NULL OR r.status<>'suspended') AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?)) ORDER BY pc.created_at DESC LIMIT " . $limit;
        $stmt = db()->prepare($sql);
        $stmt->bind_param('iii', $postId,$me,$me);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_reverse($rows);
    }

    private function postSelect(){
        return "SELECT p.*, u.fname, u.middle_name, u.lname, u.img, u.role,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
            EXISTS(SELECT 1 FROM post_likes plm WHERE plm.post_id=p.id AND plm.user_unique_id=?) AS liked_by_me,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comment_count,
            (SELECT COUNT(*) FROM posts ps WHERE ps.shared_post_id=COALESCE(p.shared_post_id,p.id)) AS share_count,
            op.id AS original_post_id, op.user_unique_id AS original_user_unique_id, op.content AS original_content,
            op.media_path AS original_media_path, op.visibility AS original_visibility, op.created_at AS original_created_at,
            ou.fname AS original_fname, ou.middle_name AS original_middle_name, ou.lname AS original_lname, ou.img AS original_img, ou.role AS original_role
            FROM posts p
            JOIN users u ON u.unique_id=p.user_unique_id
            LEFT JOIN posts op ON op.id=p.shared_post_id
            LEFT JOIN users ou ON ou.unique_id=op.user_unique_id";
    }

    private function toggleLike(){
        $me = (int)$_SESSION['unique_id'];
        $postId = (int)($_POST['post_id'] ?? 0);
        $post = $this->viewablePost($postId, $me);
        if(!$post) throw new Exception(t('not_allowed'));

        $stmt = db()->prepare("SELECT id FROM post_likes WHERE post_id=? AND user_unique_id=? LIMIT 1");
        $stmt->bind_param('ii', $postId, $me);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if($existing){
            $delete = db()->prepare("DELETE FROM post_likes WHERE id=? AND user_unique_id=?");
            $delete->bind_param('ii', $existing['id'], $me);
            $delete->execute();
        }else{
            $insert = db()->prepare("INSERT INTO post_likes (post_id,user_unique_id,created_at) VALUES (?,?,NOW())");
            $insert->bind_param('ii', $postId, $me);
            $insert->execute();
        }
    }

    private function addComment(){
        $me = (int)$_SESSION['unique_id'];
        $postId = (int)($_POST['post_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if(!$this->viewablePost($postId, $me)) throw new Exception(t('not_allowed'));
        if($comment === '') throw new Exception(t('empty_comment'));
        if(mb_strlen($comment) > 1000) throw new Exception(t('comment_too_long'));

        $stmt = db()->prepare("INSERT INTO post_comments (post_id,user_unique_id,comment,created_at,updated_at) VALUES (?,?,?,NOW(),NOW())");
        $stmt->bind_param('iis', $postId, $me, $comment);
        $stmt->execute();
    }

    private function sharePost(){
        $me = (int)$_SESSION['unique_id'];
        $postId = (int)($_POST['post_id'] ?? 0);
        $post = $this->viewablePost($postId, $me);
        if(!$post) throw new Exception(t('not_allowed'));

        $rootId = !empty($post['shared_post_id']) ? (int)$post['shared_post_id'] : (int)$post['id'];
        $stmt = db()->prepare("SELECT * FROM posts WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $rootId);
        $stmt->execute();
        $root = $stmt->get_result()->fetch_assoc();
        if(!$root || $root['visibility'] !== 'public') throw new Exception(t('share_public_only'));

        $check = db()->prepare("SELECT id FROM posts WHERE user_unique_id=? AND shared_post_id=? LIMIT 1");
        $check->bind_param('ii', $me, $rootId);
        $check->execute();
        if($check->get_result()->num_rows > 0) throw new Exception(t('already_shared'));

        $content = '';
        $visibility = 'public';
        $insert = db()->prepare("INSERT INTO posts (user_unique_id,content,media_path,visibility,shared_post_id,created_at,updated_at) VALUES (?,?,NULL,?,?,NOW(),NOW())");
        $insert->bind_param('issi', $me, $content, $visibility, $rootId);
        $insert->execute();
        $_SESSION['flash_success'] = t('post_shared');
    }

    private function editPost(){
        $me = (int)$_SESSION['unique_id'];
        $postId = (int)($_POST['post_id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM posts WHERE id=? AND user_unique_id=? LIMIT 1");
        $stmt->bind_param('ii', $postId, $me);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();
        if(!$post) throw new Exception(t('not_allowed'));

        $content = trim($_POST['content'] ?? '');
        $visibility = $this->visibility($_POST['visibility'] ?? $post['visibility']);
        if(mb_strlen($content) > 5000) throw new Exception(t('post_too_long'));

        db()->begin_transaction();
        try{
        if(!empty($post['shared_post_id'])){
            $update = db()->prepare("UPDATE posts SET content=?, visibility=?, updated_at=NOW() WHERE id=? AND user_unique_id=?");
            $update->bind_param('ssii', $content, $visibility, $postId, $me);
            $update->execute();
        }else{
            $oldMedia = $post['media_path'];
            $newMedia = upload_content_media($_FILES['media'] ?? null);
            $removeMedia = !empty($_POST['remove_media']);
            $finalMedia = $newMedia ?: ($removeMedia ? null : $oldMedia);
            if($content === '' && !$finalMedia) throw new Exception(t('content_required'));

            try{
                $update = db()->prepare("UPDATE posts SET content=?, media_path=?, visibility=?, updated_at=NOW() WHERE id=? AND user_unique_id=?");
                $update->bind_param('sssii', $content, $finalMedia, $visibility, $postId, $me);
                $update->execute();
                if($post['visibility'] === 'public' && $visibility === 'friends'){
                    // A private post must not remain embedded in previously public shares.
                    $dropShares = db()->prepare("DELETE FROM posts WHERE shared_post_id=?");
                    $dropShares->bind_param('i', $postId);
                    $dropShares->execute();
                }
            }catch(Throwable $e){
                throw $e;
            }
        }
        moderation_scan_post($postId,$content);
        db()->commit();
        }catch(Throwable $e){db()->rollback();if(!empty($newMedia)) delete_content_media($newMedia);throw $e;}
        if(empty($post['shared_post_id']) && ($newMedia || $removeMedia) && $oldMedia && $oldMedia !== $finalMedia) delete_content_media($oldMedia);
        $_SESSION['flash_success'] = t('post_updated');
    }

    private function reportPost(){
        $me=(int)$_SESSION['unique_id'];
        $postId=(int)($_POST['post_id']??0);
        $post=$this->viewablePost($postId,$me);
        if(!$post || (int)$post['user_unique_id']===$me) throw new Exception('ناتوانیت ئەم پۆستە ڕیپۆرت بکەیت.');
        $reason=trim((string)($_POST['reason']??''));
        $allowed=['هەراسانکردن','سووکایەتی','ناوەڕۆکی نەگونجاو','سپام','هی تر'];
        if(!in_array($reason,$allowed,true)) throw new Exception('هۆکاری ڕیپۆرت هەڵبژێرە.');
        $stmt=db()->prepare("INSERT IGNORE INTO post_reports(post_id,reporter_user_id,reason,status,created_at) VALUES(?,?,?,'pending',NOW())");
        $stmt->bind_param('iis',$postId,$me,$reason);$stmt->execute();
        $_SESSION['flash_success']=$stmt->affected_rows>0?'ڕیپۆرتەکەت نێردرا بۆ بەڕێوبەر.':'ئەم پۆستەت پێشتر ڕیپۆرت کردووە.';
    }

    private function deletePost(){
        $me = (int)$_SESSION['unique_id'];
        $postId = (int)($_POST['post_id'] ?? 0);
        $stmt = db()->prepare("SELECT media_path FROM posts WHERE id=? AND user_unique_id=? LIMIT 1");
        $stmt->bind_param('ii', $postId, $me);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();
        if(!$post) throw new Exception(t('not_allowed'));

        $delete = db()->prepare("DELETE FROM posts WHERE id=? AND user_unique_id=?");
        $delete->bind_param('ii', $postId, $me);
        $delete->execute();
        if(!empty($post['media_path'])) delete_content_media($post['media_path']);
        $_SESSION['flash_success'] = t('post_deleted');
    }

    private function editStory(){
        $me = (int)$_SESSION['unique_id'];
        $storyId = (int)($_POST['story_id'] ?? 0);
        $stmt = db()->prepare("SELECT * FROM stories WHERE id=? AND user_unique_id=? LIMIT 1");
        $stmt->bind_param('ii', $storyId, $me);
        $stmt->execute();
        $story = $stmt->get_result()->fetch_assoc();
        if(!$story) throw new Exception(t('not_allowed'));

        $content = trim($_POST['content'] ?? '');
        $visibility = $this->visibility($_POST['visibility'] ?? $story['visibility']);
        if(mb_strlen($content) > 700) throw new Exception(t('story_too_long'));
        $oldMedia = $story['media_path'];
        $newMedia = upload_content_media($_FILES['media'] ?? null);
        $removeMedia = !empty($_POST['remove_media']);
        $finalMedia = $newMedia ?: ($removeMedia ? null : $oldMedia);
        if($content === '' && !$finalMedia) throw new Exception(t('content_required'));

        try{
            $update = db()->prepare("UPDATE stories SET content=?, media_path=?, visibility=?, updated_at=NOW() WHERE id=? AND user_unique_id=?");
            $update->bind_param('sssii', $content, $finalMedia, $visibility, $storyId, $me);
            $update->execute();
        }catch(Throwable $e){
            if($newMedia) delete_content_media($newMedia);
            throw $e;
        }
        if(($newMedia || $removeMedia) && $oldMedia && $oldMedia !== $finalMedia) delete_content_media($oldMedia);
        $_SESSION['flash_success'] = t('story_updated');
    }

    private function deleteStory(){
        $me = (int)$_SESSION['unique_id'];
        $storyId = (int)($_POST['story_id'] ?? 0);
        $stmt = db()->prepare("SELECT media_path FROM stories WHERE id=? AND user_unique_id=? LIMIT 1");
        $stmt->bind_param('ii', $storyId, $me);
        $stmt->execute();
        $story = $stmt->get_result()->fetch_assoc();
        if(!$story) throw new Exception(t('not_allowed'));

        $delete = db()->prepare("DELETE FROM stories WHERE id=? AND user_unique_id=?");
        $delete->bind_param('ii', $storyId, $me);
        $delete->execute();
        if(!empty($story['media_path'])) delete_content_media($story['media_path']);
        $_SESSION['flash_success'] = t('story_deleted');
    }

    private function viewablePost($postId, $viewerId){
        if($postId <= 0) return null;
        $stmt = db()->prepare("SELECT * FROM posts WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();
        if(!$post || !can_view_user_content($post['user_unique_id'], $post['visibility'], $viewerId)) return null;
        return $post;
    }

    private function visibility($value){
        return in_array($value, ['public', 'friends'], true) ? $value : 'friends';
    }

    private function redirect($target){
        header('Location: ../' . safe_return_to($target, 'home.php'));
        exit;
    }
}
