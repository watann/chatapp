<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';

class AuditController{
    public function listRecent($admin,$limit=500){
        if(!$admin || ($admin['admin_role']??'')!=='super_admin') throw new Exception(t('not_allowed'));
        $limit=max(1,min(1000,(int)$limit));
        $sql="SELECT l.*,a.full_name AS actor_name
              FROM administrative_audit_log l
              LEFT JOIN admin_users a ON a.id=l.actor_admin_id
              ORDER BY l.created_at DESC,l.id DESC
              LIMIT ".$limit;
        return db()->query($sql);
    }
}
