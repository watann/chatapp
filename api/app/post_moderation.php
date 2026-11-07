<?php
// Only post reports, IT keyword flags, and their role-scoped moderation actions live here.
require_once __DIR__.'/helpers.php';

function moderation_is_super(array $admin): bool {
    return ($admin['admin_role']??'') === 'super_admin';
}
function moderation_is_it(array $admin): bool {
    return !moderation_is_super($admin) && ($admin['department_type']??'') === 'it';
}
function moderation_can_access(array $admin): bool {
    return moderation_is_super($admin) || moderation_is_it($admin);
}
function moderation_can_manage_words(array $admin): bool {
    return moderation_is_super($admin) || (moderation_is_it($admin) && ($admin['admin_role']??'')==='department_head');
}
function moderation_alert_counts(array $admin): array {
    $out=['reports'=>0,'words'=>0,'latest_report'=>0,'latest_word'=>0];
    if(moderation_is_super($admin)) {
        $r=db()->query("SELECT COUNT(*) AS n,COALESCE(MAX(id),0) AS latest FROM post_reports WHERE status='pending'")->fetch_assoc();
        $out['reports']=(int)$r['n']; $out['latest_report']=(int)$r['latest'];
    }
    if(moderation_is_it($admin)) {
        $r=db()->query("SELECT COUNT(*) AS n,COALESCE(MAX(UNIX_TIMESTAMP(updated_at)),0) AS latest FROM post_word_alerts WHERE status='pending'")->fetch_assoc();
        $out['words']=(int)$r['n']; $out['latest_word']=(int)$r['latest'];
    }
    return $out;
}
function moderation_normalize_text(string $s): string {
    // Arabic/Persian orthographic variants commonly typed in Sorani.
    return str_replace(["ك","ي","ى","\u{200C}","\u{200D}"],["ک","ی","ی","",""], $s);
}
function moderation_matching_words(string $content): array {
    $content=moderation_normalize_text($content);
    if(trim($content)==='') return [];
    $result=db()->query("SELECT word FROM moderation_words WHERE is_active=1 ORDER BY id");
    $matches=[];
    while($row=$result->fetch_assoc()) {
        $word=(string)$row['word'];
        $normalized=moderation_normalize_text($word);
        if($normalized==='' || mb_strlen($normalized)>100) continue;
        // Unicode word boundaries prevent matching 'cat' in 'category'; phrases work too.
        $pattern='/(?<![\p{L}\p{N}])'.preg_quote($normalized,'/').'(?![\p{L}\p{N}])/iu';
        if(preg_match($pattern,$content)===1) $matches[]=$word;
    }
    return $matches;
}
function moderation_scan_post(int $postId, string $content): void {
    if($postId<=0) throw new RuntimeException('Invalid post ID');
    $matches=moderation_matching_words($content);
    if(!$matches) {
        $stmt=db()->prepare('DELETE FROM post_word_alerts WHERE post_id=?');
        $stmt->bind_param('i',$postId); $stmt->execute(); return;
    }
    $hash=hash('sha256',moderation_normalize_text($content));
    $matched=implode('، ',array_slice($matches,0,30));
    $stmt=db()->prepare("INSERT INTO post_word_alerts(post_id,content_hash,matched_words,status,created_at,updated_at)
        VALUES(?,?,?,'pending',NOW(),NOW()) ON DUPLICATE KEY UPDATE
        updated_at=IF(content_hash<>VALUES(content_hash) OR matched_words<>VALUES(matched_words),NOW(),updated_at),
        reviewed_at=IF(content_hash<>VALUES(content_hash) OR matched_words<>VALUES(matched_words),NULL,reviewed_at),
        reviewed_by_admin_id=IF(content_hash<>VALUES(content_hash) OR matched_words<>VALUES(matched_words),NULL,reviewed_by_admin_id),
        status=IF(content_hash<>VALUES(content_hash) OR matched_words<>VALUES(matched_words),'pending',status),
        matched_words=VALUES(matched_words),content_hash=VALUES(content_hash)");
    $stmt->bind_param('iss',$postId,$hash,$matched); $stmt->execute();
}
