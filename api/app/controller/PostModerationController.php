<?php
require_once __DIR__.'/../post_moderation.php';
require_once __DIR__.'/AdminController.php';

class PostModerationController {
    private function admin(): array {
        $a=(new AdminController())->requireAdmin();
        if(!moderation_can_access($a)) throw new RuntimeException('مۆڵەتی بەکارهێنانی ئەم پەڕەیەت نییە.');
        return $a;
    }
    public function reports(): array {
        $a=$this->admin();
        if(!moderation_is_super($a)) return [];
        $sql="SELECT p.id,p.shared_post_id,p.content,p.media_path,p.created_at,p.visibility,u.fname,u.middle_name,u.lname,
            COALESCE(NULLIF(p.content,''),op.content,'') AS display_content, COALESCE(p.media_path,op.media_path) AS display_media,
            rr.report_count,rr.latest_report,
            (SELECT reason FROM post_reports r2 WHERE r2.post_id=p.id AND r2.status='pending' ORDER BY r2.id DESC LIMIT 1) AS reason
            FROM posts p JOIN users u ON u.unique_id=p.user_unique_id
            LEFT JOIN posts op ON op.id=p.shared_post_id
            JOIN (SELECT post_id,COUNT(*) report_count,MAX(created_at) latest_report FROM post_reports WHERE status='pending' GROUP BY post_id) rr ON rr.post_id=p.id
            ORDER BY rr.latest_report DESC LIMIT 100";
        return db()->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    public function flags(): array {
        $a=$this->admin();
        if(!moderation_is_it($a)) return [];
        return db()->query("SELECT p.id,p.content,p.created_at,p.visibility,u.fname,u.middle_name,u.lname,
            f.matched_words,f.updated_at AS flagged_at FROM post_word_alerts f
            JOIN posts p ON p.id=f.post_id JOIN users u ON u.unique_id=p.user_unique_id
            WHERE f.status='pending' ORDER BY f.updated_at DESC,f.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
    }
    public function words(): array {
        $a=$this->admin();
        if(!moderation_can_manage_words($a)) return [];
        return db()->query('SELECT id,word,is_active,created_at FROM moderation_words ORDER BY id DESC LIMIT 300')->fetch_all(MYSQLI_ASSOC);
    }
    public function handle(): void {
        $return='../moderation.php';
        try {
            $admin=$this->admin();
            verify_csrf();
            $action=(string)($_POST['action']??'');
            if($action==='add_word') {
                if(!moderation_can_manage_words($admin)) throw new Exception('مۆڵەتی زیادکردنی وشەت نییە.');
                $word=trim((string)($_POST['word']??''));
                $word=preg_replace('/\s+/u',' ',$word);
                if($word==='' || mb_strlen($word)>100 || !preg_match('/[\p{L}\p{N}]/u',$word)) throw new Exception('وشە یان دەستەواژەیەکی دروست بنووسە (تا ١٠٠ پیت).');
                $id=(int)$admin['id'];
                $stmt=db()->prepare('INSERT INTO moderation_words(word,is_active,created_by_admin_id) VALUES(?,1,?)');
                $stmt->bind_param('si',$word,$id);$stmt->execute();
                $_SESSION['admin_flash_success']='وشەکە زیاد کرا و لە پۆستە نوێ و دەستکاریکراوەکاندا دەپشکنرێت.';
            } elseif($action==='toggle_word') {
                if(!moderation_can_manage_words($admin)) throw new Exception('مۆڵەتت نییە.');
                $wordId=(int)($_POST['word_id']??0);$active=(int)($_POST['active']??-1);
                if($wordId<=0 || !in_array($active,[0,1],true)) throw new Exception('داواکاری نادروستە.');
                $stmt=db()->prepare('UPDATE moderation_words SET is_active=? WHERE id=?');
                $stmt->bind_param('ii',$active,$wordId);$stmt->execute();
                $_SESSION['admin_flash_success']='دۆخی وشەکە نوێ کرایەوە.';
            } elseif(in_array($action,['delete_reported','delete_flagged','dismiss_reports','dismiss_flag'],true)) {
                $postId=(int)($_POST['post_id']??0);
                if($postId<=0) throw new Exception('پۆستێکی دروست دیاری بکە.');
                $reportAction=in_array($action,['delete_reported','dismiss_reports'],true);
                if($reportAction && !moderation_is_super($admin)) throw new Exception('تەنها بەڕێوبەری سەرەکی مۆڵەتی ڕیپۆرتەکانی هەیە.');
                if(!$reportAction && !moderation_is_it($admin)) throw new Exception('تەنها بەشی IT مۆڵەتی ئاگادارکردنەوەی وشەکانی هەیە.');
                db()->begin_transaction();
                $media=null;
                try {
                    $stmt=db()->prepare('SELECT id,user_unique_id,content,media_path FROM posts WHERE id=? FOR UPDATE');
                    $stmt->bind_param('i',$postId);$stmt->execute();$post=$stmt->get_result()->fetch_assoc();
                    if(!$post) throw new Exception('ئەم پۆستە چیتر بوونی نییە.');
                    $table=$reportAction?'post_reports':'post_word_alerts';
                    $stmt=db()->prepare("SELECT id FROM {$table} WHERE post_id=? AND status='pending' LIMIT 1 FOR UPDATE");
                    $stmt->bind_param('i',$postId);$stmt->execute();
                    if(!$stmt->get_result()->fetch_assoc()) throw new Exception('ئەم ئاگادارکردنەوەیە چیتر چاوەڕوان نییە.');
                    $adminId=(int)$admin['id'];$authorId=(int)$post['user_unique_id'];
                    $excerpt=mb_substr((string)$post['content'],0,500);
                    $stmt=db()->prepare('INSERT INTO post_moderation_actions(post_id,post_author_user_id,actor_admin_id,action_type,post_excerpt,created_at) VALUES(?,?,?,?,?,NOW())');
                    $stmt->bind_param('iiiss',$postId,$authorId,$adminId,$action,$excerpt);$stmt->execute();
                    if(str_starts_with($action,'delete_')) {
                        $media=$post['media_path'];
                        $stmt=db()->prepare('DELETE FROM posts WHERE id=?');$stmt->bind_param('i',$postId);$stmt->execute();
                    } elseif($reportAction) {
                        $stmt=db()->prepare("UPDATE post_reports SET status='dismissed',reviewed_by_admin_id=?,reviewed_at=NOW() WHERE post_id=? AND status='pending'");
                        $stmt->bind_param('ii',$adminId,$postId);$stmt->execute();
                    } else {
                        $stmt=db()->prepare("UPDATE post_word_alerts SET status='dismissed',reviewed_by_admin_id=?,reviewed_at=NOW() WHERE post_id=? AND status='pending'");
                        $stmt->bind_param('ii',$adminId,$postId);$stmt->execute();
                    }
                    db()->commit();
                    if($media) delete_content_media($media);
                    $_SESSION['admin_flash_success']=str_starts_with($action,'delete_')?'پۆستەکە سڕایەوە.':'ئاگادارکردنەوەکە داخرا.';
                }catch(Throwable $e){db()->rollback();throw $e;}
            } else {
                throw new Exception('داواکاری نادروستە.');
            }
        }catch(Throwable $e) {
            if($e instanceof mysqli_sql_exception && $e->getCode()===1062) {
                $_SESSION['admin_flash_error']='ئەم وشەیە پێشتر تۆمار کراوە.';
            }else{
                $_SESSION['admin_flash_error']=$e->getMessage();
            }
        }
        header('Location: '.$return);exit;
    }
}
