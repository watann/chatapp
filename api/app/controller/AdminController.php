<?php
require_once __DIR__ . '/../helpers.php';

class AdminController{
    public function superAdminExists(){
        $result = db()->query("SELECT id FROM admin_users WHERE admin_role='super_admin' LIMIT 1");
        return $result && $result->num_rows > 0;
    }

    public function departmentOperational($departmentId){
        $departmentId=(int)$departmentId;
        if($departmentId<=0) return false;
        $seen=[]; $hops=0;
        while($departmentId>0 && $hops++<32){
            if(isset($seen[$departmentId])) return false;
            $seen[$departmentId]=true;
            $stmt=db()->prepare("SELECT parent_id,is_active FROM institution_departments WHERE id=? LIMIT 1");
            $stmt->bind_param('i',$departmentId); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc();
            if(!$row || !(int)$row['is_active']) return false;
            $departmentId=(int)($row['parent_id']??0);
        }
        return $departmentId===0;
    }

    public function currentAdmin(){
        $id = (int)($_SESSION['admin_id'] ?? 0);
        if($id <= 0) return null;
        $stmt = db()->prepare("SELECT a.id,a.full_name,a.email,a.admin_role,a.department_id,a.registry_id,a.is_active,a.created_at,
            d.code AS department_code,d.name AS department_name,d.department_type,d.is_active AS department_is_active,
            r.status AS registry_status,u.unique_id AS linked_unique_id,u.email AS linked_email
            FROM admin_users a
            LEFT JOIN institution_departments d ON d.id=a.department_id
            LEFT JOIN institution_registry r ON r.id=a.registry_id
            LEFT JOIN users u ON u.registry_id=a.registry_id
            WHERE a.id=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        if(!$admin || !(int)$admin['is_active']) return null;
        if(!empty($admin['registry_id']) && ($admin['registry_status'] ?? '') === 'suspended') return null;
        if(!empty($admin['registry_id']) && empty($admin['linked_unique_id'])) return null;
        if(($admin['admin_role']??'')!=='super_admin' && (empty($admin['department_id']) || !$this->departmentOperational((int)$admin['department_id']))) return null;
        return $admin;
    }

    public function requireAdmin(){
        $admin = $this->currentAdmin();
        if(!$admin){
            unset($_SESSION['admin_id'], $_SESSION['admin_role']);
            $prefix = strpos(str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/api/') !== false ? '../' : '';
            header('Location: ' . $prefix . 'login.php');
            exit;
        }
        $_SESSION['admin_role'] = $admin['admin_role'];
        return $admin;
    }

    public function requireSuperAdmin(){
        $admin = $this->requireAdmin();
        if($admin['admin_role'] !== 'super_admin'){
            $_SESSION['admin_flash_error'] = t('admin_super_only');
            $prefix = strpos(str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/api/') !== false ? '../' : '';
            header('Location: ' . $prefix . 'index.php');
            exit;
        }
        return $admin;
    }

    public function canAccessDepartment($admin, $codes){
        if(!$admin) return false;
        if(($admin['admin_role'] ?? '') === 'super_admin') return true;
        $codes = is_array($codes) ? $codes : [$codes];
        return in_array((string)($admin['department_type'] ?? ''), $codes, true) || in_array((string)($admin['department_code'] ?? ''), $codes, true);
    }

    public function requireDepartmentAccess($codes, $headsOnly=false){
        $admin = $this->requireAdmin();
        if(($admin['admin_role'] ?? '') === 'super_admin') return $admin;
        if(!$this->canAccessDepartment($admin, $codes) || ($headsOnly && ($admin['admin_role'] ?? '') !== 'department_head')){
            $_SESSION['admin_flash_error'] = t('not_allowed');
            $prefix = strpos(str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/api/') !== false ? '../' : '';
            header('Location: ' . $prefix . 'index.php');
            exit;
        }
        return $admin;
    }

    public function setup(){
        if($this->superAdminExists()){
            header('Location: ../login.php');
            exit;
        }
        try{
            verify_csrf();
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            if($fullName === '' || $email === '' || $password === '') throw new Exception(t('required_fields'));
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception(t('invalid_email'));
            if(strlen($password) < 8) throw new Exception(t('admin_password_min'));
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'super_admin';
            $stmt = db()->prepare("INSERT INTO admin_users (full_name,email,password,admin_role,department_id,registry_id,is_active,created_at,updated_at) VALUES (?,?,?,?,NULL,NULL,1,NOW(),NOW())");
            $stmt->bind_param('ssss', $fullName, $email, $hash, $role);
            $stmt->execute();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$stmt->insert_id;
            $_SESSION['admin_role'] = 'super_admin';
            $_SESSION['admin_flash_success'] = t('admin_setup_complete');
            header('Location: ../index.php');
            exit;
        }catch(Throwable $e){
            $_SESSION['admin_flash_error'] = $e instanceof mysqli_sql_exception ? t('admin_database_error') : $e->getMessage();
            header('Location: ../setup.php');
            exit;
        }
    }

    public function login(){
        try{
            verify_csrf();
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            if($email === '' || $password === '') throw new Exception(t('required_fields'));
            $stmt = db()->prepare("SELECT a.*,r.status AS registry_status,u.email AS linked_email,u.password AS linked_password
                FROM admin_users a
                LEFT JOIN institution_registry r ON r.id=a.registry_id
                LEFT JOIN users u ON u.registry_id=a.registry_id
                WHERE (a.registry_id IS NULL AND a.email=?) OR (a.registry_id IS NOT NULL AND u.email=?)
                LIMIT 1");
            $stmt->bind_param('ss', $email, $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if(!$row || !(int)$row['is_active'] || (!empty($row['registry_id']) && ($row['registry_status'] ?? '') === 'suspended')){
                throw new Exception(t('incorrect_credentials'));
            }
            $hash = !empty($row['registry_id']) ? (string)($row['linked_password'] ?? '') : (string)$row['password'];
            if($hash === '' || !password_verify($password, $hash)) throw new Exception(t('incorrect_credentials'));
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_role'] = $row['admin_role'];
            header('Location: ../index.php');
            exit;
        }catch(Throwable $e){
            $_SESSION['admin_flash_error'] = $e instanceof mysqli_sql_exception ? t('admin_database_error') : $e->getMessage();
            header('Location: ../login.php');
            exit;
        }
    }

    public function logout(){
        unset($_SESSION['admin_id'], $_SESSION['admin_role']);
        session_regenerate_id(true);
        header('Location: ../login.php');
        exit;
    }

    // Kept only for old links. Department access is now assigned from official staff records.
    public function createAdmin(){
        $this->requireSuperAdmin();
        $_SESSION['admin_flash_error'] = t('use_organization_assignments');
        header('Location: ../organization.php');
        exit;
    }

    public function listAdmins(){
        $this->requireSuperAdmin();
        return db()->query("SELECT a.id,a.full_name,COALESCE(u.email,a.email) AS email,a.admin_role,a.is_active,a.created_at,
            d.name AS department_name,d.code AS department_code,c.full_name AS creator_name
            FROM admin_users a
            LEFT JOIN users u ON u.registry_id=a.registry_id
            LEFT JOIN institution_departments d ON d.id=a.department_id
            LEFT JOIN admin_users c ON c.id=a.created_by
            ORDER BY (a.admin_role='super_admin') DESC,d.sort_order ASC,(a.admin_role='department_head') DESC,a.full_name ASC");
    }

    public function dashboardCounts(){
        $admin=$this->requireAdmin();
        $counts = ['admins'=>0,'records'=>0,'available'=>0,'registered'=>0,'users'=>0,'academic_groups'=>0,'active_students'=>0,'pending_grades'=>0,'pending_requests'=>0,'pending_finance'=>0];
        $r = db()->query("SELECT COUNT(*) c FROM admin_users WHERE is_active=1"); if($r) $counts['admins']=(int)$r->fetch_assoc()['c'];
        $r = db()->query("SELECT COUNT(*) c,SUM(status='available') a,SUM(status='registered') r FROM institution_registry");
        if($r){ $x=$r->fetch_assoc(); $counts['records']=(int)($x['c']??0); $counts['available']=(int)($x['a']??0); $counts['registered']=(int)($x['r']??0); }
        $r = db()->query("SELECT COUNT(*) c FROM users"); if($r) $counts['users']=(int)$r->fetch_assoc()['c'];
        $r = db()->query("SELECT COUNT(*) c FROM academic_groups WHERE is_active=1"); if($r) $counts['academic_groups']=(int)$r->fetch_assoc()['c'];
        $r = db()->query("SELECT COUNT(*) c FROM institution_registry WHERE person_type='student' AND student_academic_status='active'"); if($r) $counts['active_students']=(int)$r->fetch_assoc()['c'];
        $r = db()->query("SELECT COUNT(*) c FROM academic_exams WHERE status='submitted'"); if($r) $counts['pending_grades']=(int)$r->fetch_assoc()['c'];
        if(($admin['admin_role']??'')==='super_admin'){
            $r = db()->query("SELECT COUNT(*) c FROM department_requests WHERE status='pending'"); if($r) $counts['pending_requests']=(int)$r->fetch_assoc()['c'];
        }elseif(!empty($admin['department_id'])){
            $dept=(int)$admin['department_id'];
            $stmt=db()->prepare("SELECT COUNT(*) c FROM department_requests WHERE department_id=? AND status='pending'"); $stmt->bind_param('i',$dept); $stmt->execute(); $counts['pending_requests']=(int)$stmt->get_result()->fetch_assoc()['c'];
        }
        if(($admin['admin_role']??'')==='super_admin' || ($admin['department_type']??'')==='finance'){
            $r = db()->query("SELECT COUNT(*) c FROM finance_transactions WHERE status='pending'"); if($r) $counts['pending_finance']=(int)$r->fetch_assoc()['c'];
        }
        return $counts;
    }
}
