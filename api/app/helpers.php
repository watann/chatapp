<?php
if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

require_once __DIR__ . '/Config.php';

function db(){
    static $connection = null;
    if($connection === null){
        $connection = (new Config())->connect();
    }
    return $connection;
}

function e($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function current_user(){
    if(empty($_SESSION['unique_id'])) return null;
    $stmt = db()->prepare("SELECT u.*,r.status AS registry_status FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['unique_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function touch_presence($uniqueId){
    $uniqueId=(int)$uniqueId; if($uniqueId<=0) return;
    $now=time(); $last=(int)($_SESSION['presence_touch_at']??0);
    if($now-$last<45) return;
    $_SESSION['presence_touch_at']=$now;
    $stmt=db()->prepare("UPDATE users SET status='Active now',last_seen_at=NOW(),updated_at=NOW() WHERE unique_id=?");
    $stmt->bind_param('i',$uniqueId); $stmt->execute();
    db()->query("UPDATE users SET status='Offline now' WHERE status='Active now' AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE))");
}

function require_auth(){
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $prefix = strpos($script, '/api/') !== false ? '../' : '';
    if(empty($_SESSION['unique_id'])){ header('Location: ' . $prefix . 'login.php'); exit; }
    $user=current_user();
    if(!$user || (!empty($user['registry_id']) && ($user['registry_status']??'')==='suspended')){
        unset($_SESSION['unique_id'],$_SESSION['presence_touch_at']);
        $_SESSION['flash_error']=t('account_suspended');
        header('Location: ' . $prefix . 'login.php'); exit;
    }
    touch_presence((int)$user['unique_id']);
}

function supported_languages(){
    return ['ku', 'en', 'ar', 'de', 'fa'];
}

function supported_roles(){
    return ['student', 'teacher', 'staff'];
}

function supported_theme_modes(){
    return ['light', 'dark', 'system'];
}

function supported_accent_colors(){
    return ['blue', 'green', 'purple', 'orange', 'rose'];
}

function supported_ui_densities(){
    return ['comfortable', 'compact'];
}

function user_preferences($user = null){
    $user = is_array($user) ? $user : current_user();
    $theme = $user['theme_mode'] ?? 'light';
    $accent = $user['accent_color'] ?? 'blue';
    $density = $user['ui_density'] ?? 'comfortable';
    return [
        'theme_mode' => in_array($theme, supported_theme_modes(), true) ? $theme : 'light',
        'accent_color' => in_array($accent, supported_accent_colors(), true) ? $accent : 'blue',
        'ui_density' => in_array($density, supported_ui_densities(), true) ? $density : 'comfortable',
    ];
}

function user_language(){
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $isAdminArea = strpos($script, '/admin/') !== false;
    if($isAdminArea && !empty($_SESSION['admin_language'])){
        $lang = $_SESSION['admin_language'];
    }else{
        $user = current_user();
        $lang = $user['language'] ?? ($_SESSION['guest_language'] ?? 'ku');
    }
    return in_array($lang, supported_languages(), true) ? $lang : 'ku';
}

function display_name($row){
    if(!is_array($row)) return '';
    $first = trim((string)($row['fname'] ?? $row['first_name'] ?? ''));
    $middle = trim((string)($row['middle_name'] ?? ''));
    $last = trim((string)($row['lname'] ?? $row['last_name'] ?? ''));
    return trim(implode(' ', array_values(array_filter([$first, $middle, $last], static fn($v) => $v !== ''))));
}

function csrf_token(){
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(){
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(){
    $sent = (string)($_POST['csrf_token'] ?? '');
    if($sent === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)){
        throw new Exception(t('invalid_request'));
    }
}

function supported_genders(){
    return ['male','female','other'];
}


function gender_label($gender){
    return t('gender_' . $gender);
}


function academic_day_label($day){
    $day=(int)$day;
    return $day>=1 && $day<=7 ? t('academic_day_'.$day) : '';
}

function text_direction($lang = null){
    $lang = $lang ?: user_language();
    return in_array($lang, ['ku', 'ar', 'fa'], true) ? 'rtl' : 'ltr';
}

function t($key){
    static $words = null;
    if($words === null){
        $words = require __DIR__ . '/i18n.php';
        foreach([__DIR__ . '/i18n_academic.php', __DIR__ . '/i18n_upgrade.php', __DIR__ . '/i18n_professional.php', __DIR__ . '/i18n_simplified.php', __DIR__ . '/i18n_registry_workflow.php'] as $extraPath){
            if(!is_file($extraPath)) continue;
            $extra = require $extraPath;
            foreach($extra as $langCode => $entries){
                if(!isset($words[$langCode])) $words[$langCode] = [];
                $words[$langCode] = array_merge($words[$langCode], $entries);
            }
        }
    }
    $lang = user_language();
    return $words[$lang][$key] ?? $words['en'][$key] ?? $key;
}

function language_name($code){
    $names = ['ku' => 'کوردی', 'en' => 'English', 'ar' => 'العربية', 'de' => 'Deutsch', 'fa' => 'فارسی'];
    return $names[$code] ?? $code;
}

function role_label($role){
    return t('role_' . $role);
}

function avatar_src($img){
    if(empty($img)){
        return 'assets/img/default-avatar.svg';
    }
    if(strpos($img, 'assets/') === 0){
        return $img;
    }
    return 'api/images/' . $img;
}


function clean_filename($name){
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
    return trim($name, '._');
}

function upload_image_to($file, $folderPath, $maxBytes, $defaultPrefix = 'image'){
    if(empty($file) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
        return null;
    }
    if(($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK){
        throw new Exception(t('image_upload_failed'));
    }
    if(($file['size'] ?? 0) > $maxBytes){
        throw new Exception(t('image_too_large'));
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']);
    if(!in_array($ext, $allowed_ext, true) || !in_array($mime, $allowed_mime, true)){
        throw new Exception(t('image_types'));
    }

    if(!is_dir($folderPath)){
        mkdir($folderPath, 0755, true);
    }
    $baseName = clean_filename($file['name'] ?? ($defaultPrefix . '.' . $ext));
    if($baseName === ''){
        $baseName = $defaultPrefix . '.' . $ext;
    }
    $filename = time() . '_' . bin2hex(random_bytes(5)) . '_' . $baseName;
    if(!move_uploaded_file($file['tmp_name'], $folderPath . $filename)){
        throw new Exception(t('image_save_failed'));
    }
    return $filename;
}

function upload_profile_image($file){
    return upload_image_to($file, __DIR__ . '/../images/', 5 * 1024 * 1024, 'profile');
}

function delete_profile_image($filename){
    $filename = basename((string)$filename);
    if($filename === '' || $filename === 'default-avatar.svg') return;
    $path = __DIR__ . '/../images/' . $filename;
    if(is_file($path)) @unlink($path);
}

function upload_content_media($file){
    return upload_image_to($file, __DIR__ . '/../uploads/', 8 * 1024 * 1024, 'media');
}


function academic_media_dir(){
    return __DIR__ . '/../academic_uploads/';
}

function upload_academic_media($file){
    return upload_image_to($file, academic_media_dir(), 8 * 1024 * 1024, 'academic');
}

function delete_academic_media($filename){
    $filename = basename((string)$filename);
    if($filename === '') return;
    $path = academic_media_dir() . $filename;
    if(is_file($path)) @unlink($path);
}

function last_direct_message($other_unique_id){
    $me = (int)($_SESSION['unique_id'] ?? 0);
    $stmt = db()->prepare("SELECT msg, attachment_name, outgoing_msg_id, created_at FROM messages
        WHERE (outgoing_msg_id = ? AND incoming_msg_id = ?) OR (outgoing_msg_id = ? AND incoming_msg_id = ?)
        ORDER BY msg_id DESC LIMIT 1");
    $stmt->bind_param('iiii', $me, $other_unique_id, $other_unique_id, $me);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function is_group_member($group_id, $unique_id){
    $stmt = db()->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_unique_id = ? LIMIT 1");
    $stmt->bind_param('ii', $group_id, $unique_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function friend_pair($a, $b){
    $a = (int)$a;
    $b = (int)$b;
    return $a < $b ? [$a, $b] : [$b, $a];
}

function friendship_between($a, $b){
    if((int)$a <= 0 || (int)$b <= 0 || (int)$a === (int)$b){
        return null;
    }
    [$one, $two] = friend_pair($a, $b);
    $stmt = db()->prepare("SELECT * FROM friendships WHERE user_one = ? AND user_two = ? LIMIT 1");
    $stmt->bind_param('ii', $one, $two);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function are_friends($a, $b){
    $relation = friendship_between($a, $b);
    return $relation && $relation['status'] === 'accepted';
}

function user_unique_id_taken($uniqueId){
    $stmt=db()->prepare('SELECT 1 FROM users WHERE unique_id=? LIMIT 1');
    $stmt->bind_param('i',$uniqueId);$stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function user_exists($uniqueId){
    $stmt = db()->prepare("SELECT 1 FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.unique_id=? AND (u.registry_id IS NULL OR r.status<>'suspended') LIMIT 1");
    $stmt->bind_param('i', $uniqueId); $stmt->execute();
    return $stmt->get_result()->num_rows === 1;
}

function has_blocked($blocker,$blocked){
    $blocker=(int)$blocker; $blocked=(int)$blocked; if($blocker<=0||$blocked<=0) return false;
    $stmt=db()->prepare("SELECT id FROM user_blocks WHERE blocker_id=? AND blocked_id=? LIMIT 1");
    $stmt->bind_param('ii',$blocker,$blocked); $stmt->execute(); return $stmt->get_result()->num_rows>0;
}

function is_blocked_between($a,$b){
    $a=(int)$a; $b=(int)$b; if($a<=0||$b<=0) return false;
    $stmt=db()->prepare("SELECT id FROM user_blocks WHERE (blocker_id=? AND blocked_id=?) OR (blocker_id=? AND blocked_id=?) LIMIT 1");
    $stmt->bind_param('iiii',$a,$b,$b,$a); $stmt->execute(); return $stmt->get_result()->num_rows>0;
}

function cleanup_expired_stories(){
    $now=time(); $last=(int)($_SESSION['story_cleanup_at']??0); if($now-$last<600) return;
    $_SESSION['story_cleanup_at']=$now;
    $res=db()->query("SELECT id,media_path FROM stories WHERE expires_at<=NOW() LIMIT 200");
    $ids=[]; $files=[]; if($res){ while($r=$res->fetch_assoc()){ $ids[]=(int)$r['id']; if(!empty($r['media_path'])) $files[]=$r['media_path']; } }
    if($ids){ db()->query("DELETE FROM stories WHERE id IN (".implode(',',array_map('intval',$ids)).")"); foreach($files as $f) delete_content_media($f); }
}

function safe_return_to($value, $fallback = 'home.php'){
    $allowed = ['home.php', 'profile.php', 'users.php', 'groups.php', 'settings.php', 'group-chat.php', 'academic-group.php'];
    $legacyHome = ['friends-feed.php', 'public-feed.php'];
    $raw = trim((string)$value);
    if($raw === '') return $fallback;

    $parts = parse_url($raw);
    $path = basename((string)($parts['path'] ?? ''));
    if(in_array($path, $legacyHome, true)) $path = 'home.php';
    if(!in_array($path, $allowed, true)) return $fallback;

    $query = '';
    if($path === 'profile.php' && !empty($parts['query'])){
        parse_str($parts['query'], $params);
        if(isset($params['user_id']) && ctype_digit((string)$params['user_id'])){
            $query = '?user_id=' . (int)$params['user_id'];
        }
    }

    $fragment = '';
    if(!empty($parts['fragment']) && preg_match('/^(post|story)-\d+$/', (string)$parts['fragment'])){
        $fragment = '#' . $parts['fragment'];
    }
    return $path . $query . $fragment;
}

function delete_content_media($filename){
    $filename = basename((string)$filename);
    if($filename === '') return;
    $path = __DIR__ . '/../uploads/' . $filename;
    if(is_file($path)) @unlink($path);
}

function can_view_user_content($ownerId, $visibility, $viewerId){
    $ownerId = (int)$ownerId;
    $viewerId = (int)$viewerId;
    if($ownerId === $viewerId) return true;
    if(is_blocked_between($ownerId,$viewerId)) return false;
    if(!user_exists($ownerId)) return false;
    if($visibility === 'public') return true;
    return $visibility === 'friends' && are_friends($ownerId, $viewerId);
}

function chat_upload_dir(){
    return __DIR__ . '/../chat_uploads/';
}

function upload_chat_file($file, $maxBytes = 25 * 1024 * 1024){
    if(empty($file) || empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE){
        return null;
    }
    if(($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK){
        throw new Exception(t('file_upload_failed'));
    }
    $size = (int)($file['size'] ?? 0);
    if($size <= 0 || $size > $maxBytes){
        throw new Exception(t('file_too_large'));
    }

    $allowed = [
        'jpg','jpeg','png','webp','gif','pdf','txt','csv',
        'doc','docx','xls','xlsx','ppt','pptx',
        'zip','rar','7z','mp3','wav','m4a','ogg','mp4','webm','mov'
    ];
    $original = basename((string)($file['name'] ?? 'file'));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if(!in_array($ext, $allowed, true)){
        throw new Exception(t('file_type_not_allowed'));
    }

    $dir = chat_upload_dir();
    if(!is_dir($dir) && !mkdir($dir, 0755, true)){
        throw new Exception(t('file_save_failed'));
    }
    $stored = time() . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if(!move_uploaded_file($file['tmp_name'], $dir . $stored)){
        throw new Exception(t('file_save_failed'));
    }
    $mime = 'application/octet-stream';
    if(function_exists('mime_content_type')){
        $detected = @mime_content_type($dir . $stored);
        if(is_string($detected) && $detected !== '') $mime = $detected;
    }
    return [
        'path' => $stored,
        'name' => mb_substr($original, 0, 240),
        'mime' => mb_substr($mime, 0, 120),
        'size' => $size,
    ];
}

function delete_chat_file($stored){
    $stored = basename((string)$stored);
    if($stored === '') return;
    $path = chat_upload_dir() . $stored;
    if(is_file($path)) @unlink($path);
}

function human_file_size($bytes){
    $bytes = max(0, (int)$bytes);
    if($bytes < 1024) return $bytes . ' B';
    if($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / (1024 * 1024), 1) . ' MB';
}

function message_preview($row){
    $text = trim((string)($row['msg'] ?? $row['message'] ?? ''));
    if($text !== '') return $text;
    if(!empty($row['attachment_name'])) return '📎 ' . $row['attachment_name'];
    return t('no_messages');
}

function is_previewable_image_mime($mime){
    return in_array(strtolower((string)$mime), ['image/jpeg','image/png','image/webp','image/gif'], true);
}

function stream_chat_file($stored, $original, $mime, $inline = false){
    $stored = basename((string)$stored);
    $path = chat_upload_dir() . $stored;
    if($stored === '' || !is_file($path)){
        http_response_code(404);
        exit;
    }
    $safeName = str_replace(["\r", "\n", '"'], '', basename((string)$original));
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    $disposition = $inline && is_previewable_image_mime($mime) ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
    readfile($path);
    exit;
}

