<?php
require_once __DIR__ . '/../helpers.php';

class ProfileController{
    public function find($uniqueId){
        require_auth();
        $uniqueId = (int)$uniqueId;
        if($uniqueId <= 0) return null;
        $stmt = db()->prepare("SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.email,u.img,u.status,u.language,u.role,u.bio,u.registry_id,u.created_at,u.updated_at FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id=? AND (u.registry_id IS NULL OR r.status<>'suspended') LIMIT 1");
        $stmt->bind_param('i', $uniqueId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function visiblePostCount($profileId, $viewerId){
        require_auth();
        $profileId = (int)$profileId;
        $viewerId = (int)$viewerId;
        if($profileId <= 0 || $viewerId <= 0 || ($profileId!==$viewerId && is_blocked_between($profileId,$viewerId))) return 0;
        if($profileId === $viewerId || are_friends($profileId, $viewerId)){
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM posts WHERE user_unique_id=?");
            $stmt->bind_param('i', $profileId);
        }else{
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM posts WHERE user_unique_id=? AND visibility='public'");
            $stmt->bind_param('i', $profileId);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function friendCount($uniqueId){
        require_auth();
        $uniqueId = (int)$uniqueId;
        $me=(int)($_SESSION['unique_id']??0); if($uniqueId!==$me && is_blocked_between($uniqueId,$me)) return 0;
        $stmt = db()->prepare("SELECT COUNT(*) AS total
            FROM friendships f
            JOIN users u ON u.unique_id=CASE WHEN f.user_one=? THEN f.user_two ELSE f.user_one END
            LEFT JOIN institution_registry r ON r.id=u.registry_id
            WHERE f.status='accepted' AND (f.user_one=? OR f.user_two=?) AND (u.registry_id IS NULL OR r.status<>'suspended')");
        $stmt->bind_param('iii', $uniqueId, $uniqueId, $uniqueId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    public function recentFriends($uniqueId, $limit = 6){
        require_auth();
        $uniqueId = (int)$uniqueId;
        $limit = max(1, min(12, (int)$limit));
        $viewerId=(int)($_SESSION['unique_id']??0);
        $sql = "SELECT u.unique_id,u.fname,u.middle_name,u.lname,u.img,u.role,u.status
            FROM friendships f
            JOIN users u ON u.unique_id = CASE WHEN f.user_one=? THEN f.user_two ELSE f.user_one END
            LEFT JOIN institution_registry r ON r.id=u.registry_id
            WHERE f.status='accepted' AND (f.user_one=? OR f.user_two=?)
              AND (u.registry_id IS NULL OR r.status<>'suspended')
              AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=? AND b.blocked_id=u.unique_id) OR (b.blocker_id=u.unique_id AND b.blocked_id=?))
            ORDER BY f.updated_at DESC LIMIT " . $limit;
        $stmt = db()->prepare($sql);
        $stmt->bind_param('iiiii', $uniqueId, $uniqueId, $uniqueId, $viewerId, $viewerId);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function update(){
        require_auth();
        try{
            verify_csrf();
            $account = current_user();
            if(!$account) throw new Exception(t('invalid_action'));
            $lockedIdentity = !empty($account['registry_id']);
            $fname = $lockedIdentity ? (string)$account['fname'] : trim((string)($_POST['fname'] ?? ''));
            $middleName = $lockedIdentity ? (string)($account['middle_name'] ?? '') : trim((string)($_POST['middle_name'] ?? ''));
            $lname = $lockedIdentity ? (string)$account['lname'] : trim((string)($_POST['lname'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $bio = trim((string)($_POST['bio'] ?? ''));
            // Institutional role is authoritative and can never be self-assigned from Settings.
            $role = (string)$account['role'];
            $password = (string)($_POST['password'] ?? '');

            if($fname === '' || $lname === '' || $email === '' || $role === '') throw new Exception(t('name_email_role_required'));
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception(t('invalid_email'));
            if(mb_strlen($bio) > 1000) throw new Exception(t('bio_too_long'));
            if(mb_strlen($middleName) > 100) throw new Exception(t('invalid_action'));

            $me = (int)$_SESSION['unique_id'];
            $stmt = db()->prepare("SELECT user_id FROM users WHERE email = ? AND unique_id <> ? LIMIT 1");
            $stmt->bind_param('si', $email, $me);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) throw new Exception(t('email_used'));

            $oldImage=(string)($account['img']??'');
            $uploaded = upload_profile_image($_FILES['image'] ?? null);
            $cleanupUploaded=$uploaded;
            if($password !== ''){
                if(strlen($password) < 6) throw new Exception(t('password_min'));
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if($uploaded){
                    $stmt = db()->prepare("UPDATE users SET fname=?, middle_name=?, lname=?, email=?, bio=?, role=?, password=?, img=?, updated_at=NOW() WHERE unique_id=?");
                    $stmt->bind_param('ssssssssi', $fname, $middleName, $lname, $email, $bio, $role, $hash, $uploaded, $me);
                }else{
                    $stmt = db()->prepare("UPDATE users SET fname=?, middle_name=?, lname=?, email=?, bio=?, role=?, password=?, updated_at=NOW() WHERE unique_id=?");
                    $stmt->bind_param('sssssssi', $fname, $middleName, $lname, $email, $bio, $role, $hash, $me);
                }
            }else{
                if($uploaded){
                    $stmt = db()->prepare("UPDATE users SET fname=?, middle_name=?, lname=?, email=?, bio=?, role=?, img=?, updated_at=NOW() WHERE unique_id=?");
                    $stmt->bind_param('sssssssi', $fname, $middleName, $lname, $email, $bio, $role, $uploaded, $me);
                }else{
                    $stmt = db()->prepare("UPDATE users SET fname=?, middle_name=?, lname=?, email=?, bio=?, role=?, updated_at=NOW() WHERE unique_id=?");
                    $stmt->bind_param('ssssssi', $fname, $middleName, $lname, $email, $bio, $role, $me);
                }
            }
            $stmt->execute();
            if($uploaded && $oldImage && $oldImage!==$uploaded) delete_profile_image($oldImage);
            $cleanupUploaded=null;
            $_SESSION['flash_success'] = t('profile_updated');
        }catch(Throwable $e){
            if(!empty($cleanupUploaded)) delete_profile_image($cleanupUploaded);
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: ../settings.php#profile-settings');
        exit;
    }

}
