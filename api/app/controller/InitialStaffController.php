<?php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/AdminController.php';
require_once __DIR__ . '/InstitutionController.php';

/**
 * Restricted recovery/initial setup: give the institution its first Registry clerk
 * and its first Administration head. Automatically unavailable once both exist.
 * This is NOT a general shortcut around the normal two-stage approval workflow.
 */
class InitialStaffController {
    private function assertManager($admin){
        if (!$admin || ($admin['admin_role'] ?? '') !== 'super_admin' || !(int)($admin['is_active'] ?? 0)) {
            throw new Exception(t('not_allowed'));
        }
    }

    public function missingRoles($admin){
        $this->assertManager($admin);
        $q = db()->query("SELECT d.code,d.id AS department_id,d.name AS department_name,
                    a.id AS admin_user_id,a.admin_role,a.registry_id, r.status AS record_status,
                    u.unique_id
                FROM institution_departments d
                LEFT JOIN admin_users a ON a.department_id=d.id AND a.is_active=1
                LEFT JOIN institution_registry r ON r.id=a.registry_id
                LEFT JOIN users u ON u.registry_id=r.id
                WHERE d.code IN ('registry','administration') AND d.is_active=1");
        $present=['registry_staff'=>false,'administration_head'=>false];
        while($r=$q->fetch_assoc()){
            if(empty($r['admin_user_id']) || empty($r['registry_id']) || empty($r['unique_id']) || ($r['record_status']??'')!=='registered') continue;
            if(!(new AdminController())->departmentOperational((int)$r['department_id'])) continue;
            if($r['code']==='registry') $present['registry_staff']=true;
            if($r['code']==='administration' && $r['admin_role']==='department_head') $present['administration_head']=true;
        }
        return ['registry_staff'=>!$present['registry_staff'], 'administration_head'=>!$present['administration_head']];
    }

    public function needed($admin){
        $m=$this->missingRoles($admin);
        return $m['registry_staff']||$m['administration_head'];
    }

    private function defaultWorkplace($adminId,$role){
        $label=($role==='registry_staff')?'Institution Registry':'Institution Administration';
        $q=db()->prepare("SELECT id,is_active FROM institution_options WHERE option_type='staff_workplace' AND label=? LIMIT 1 FOR UPDATE");
        $q->bind_param('s',$label);$q->execute();$found=$q->get_result()->fetch_assoc();
        if($found){
            if(!(int)$found['is_active']){
                $id=(int)$found['id'];
                $u=db()->prepare('UPDATE institution_options SET is_active=1,updated_at=NOW() WHERE id=?');
                $u->bind_param('i',$id);$u->execute();
            }
            return (int)$found['id'];
        }
        $q=db()->prepare("INSERT INTO institution_options(option_type,label,is_active,sort_order,created_by_admin_id,created_at,updated_at) VALUES('staff_workplace',?,1,10,?,NOW(),NOW())");
        $q->bind_param('si',$label,$adminId);$q->execute();
        return (int)$q->insert_id;
    }

    public function create($admin,$form){
        $this->assertManager($admin);
        $role=(string)($form['setup_role']??'');
        if(!in_array($role,['registry_staff','administration_head'],true)) throw new Exception(t('invalid_action'));
        $first=trim((string)($form['first_name']??''));
        $middle=trim((string)($form['middle_name']??''));
        $last=trim((string)($form['last_name']??''));
        $birth=trim((string)($form['birth_date']??''));
        $gender=(string)($form['gender']??'');
        $identity=trim((string)($form['identity_number']??''));
        $email=strtolower(trim((string)($form['email']??'')));
        $password=(string)($form['password']??'');
        if($first===''||$middle===''||$last===''||$birth===''||$identity===''||$email===''||$password==='') throw new Exception(t('required_fields'));
        if(mb_strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Exception(t('invalid_email'));
        if(strlen($password)<10 || strlen($password)>1024) throw new Exception('وشەی نهێنی دەبێت لانی کەم ١٠ پیت بێت.');
        if(!in_array($gender,supported_genders(),true)) throw new Exception(t('invalid_action'));
        $date=DateTime::createFromFormat('!Y-m-d',$birth);
        if(!$date || $date->format('Y-m-d')!==$birth || $birth>date('Y-m-d')) throw new Exception(t('invalid_birth_date'));

        $conn=db();$conn->begin_transaction();
        try {
            // Lock a single manager row: two simultaneous bootstrap requests cannot
            // both observe the same unfilled role and create duplicate heads.
            $actorId=(int)$admin['id'];
            $lock=$conn->prepare("SELECT id FROM admin_users WHERE id=? AND admin_role='super_admin' AND is_active=1 FOR UPDATE");
            $lock->bind_param('i',$actorId);$lock->execute();
            if(!$lock->get_result()->fetch_assoc()) throw new Exception(t('not_allowed'));
            $code=$role==='registry_staff'?'registry':'administration';
            // Serialize all managers by locking the specific institutional department.
            $q=$conn->prepare('SELECT id,is_active FROM institution_departments WHERE code=? LIMIT 1 FOR UPDATE');
            $q->bind_param('s',$code);$q->execute();$dept=$q->get_result()->fetch_assoc();
            if(!$dept || !(int)$dept['is_active'] || !(new AdminController())->departmentOperational((int)$dept['id'])) {
                throw new Exception('بەشی تۆمار یان کارگێڕی چالاک نییە؛ سەرەتا لە ڕێکخستنی ناوەندی چالاکی بکە.');
            }
            $missing=$this->missingRoles($admin);
            if(!$missing[$role]) throw new Exception('ئەم پۆستە پێشتر دیاریکراوە؛ دامەزراندنی سەرەتایی بۆی داخراوە.');
            $q=$conn->prepare('SELECT user_id FROM users WHERE email=? LIMIT 1');$q->bind_param('s',$email);$q->execute();
            if($q->get_result()->fetch_assoc()) throw new Exception(t('email_taken'));
            $q=$conn->prepare('SELECT id FROM admin_users WHERE email=? LIMIT 1');$q->bind_param('s',$email);$q->execute();
            if($q->get_result()->fetch_assoc()) throw new Exception(t('email_taken'));
            $workplaceId=$this->defaultWorkplace($actorId,$role);
            $registration=(new InstitutionController())->createRecordFromData($admin,[
                'first_name'=>$first,'middle_name'=>$middle,'last_name'=>$last,
                'birth_date'=>$birth,'gender'=>$gender,'person_type'=>'staff',
                'identity_number'=>$identity,'staff_workplace_option_id'=>$workplaceId
            ],$actorId,true);
            $registryId=(int)$registration['id'];
            do { $uniqueId=random_int(100000000,999999999); } while(user_unique_id_taken($uniqueId));
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $imagePath='assets/img/default-avatar.svg';$status='Offline now';$language='ku';$userRole='staff';$bio='';
            $q=$conn->prepare("INSERT INTO users(unique_id,fname,middle_name,lname,email,password,img,status,last_seen_at,language,role,bio,registry_id,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),NOW())");
            $q->bind_param('issssssssssi',$uniqueId,$first,$middle,$last,$email,$hash,$imagePath,$status,$language,$userRole,$bio,$registryId);
            $q->execute();
            $q=$conn->prepare("UPDATE institution_registry SET status='registered',updated_at=NOW() WHERE id=? AND status='available'");
            $q->bind_param('i',$registryId);$q->execute();
            if($q->affected_rows!==1) throw new Exception(t('invalid_action'));
            $fullName=trim($first.' '.$middle.' '.$last);
            $adminRole=$role==='administration_head'?'department_head':'department_staff';
            $deptId=(int)$dept['id'];
            $q=$conn->prepare("INSERT INTO admin_users(full_name,email,password,admin_role,department_id,registry_id,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,1,?,NOW(),NOW())");
            $q->bind_param('ssssiii',$fullName,$email,$hash,$adminRole,$deptId,$registryId,$actorId);$q->execute();
            $newAdmin=(int)$q->insert_id;
            $event='initial_staff_provisioned';$entity='admin_user';
            $details='Bootstrap: '.$role.'; official '.$registration['issued_id'].'; department #'.$deptId;
            $q=$conn->prepare('INSERT INTO administrative_audit_log(actor_admin_id,event_type,entity_type,entity_id,details,created_at) VALUES(?,?,?,?,?,NOW())');
            $q->bind_param('issis',$actorId,$event,$entity,$newAdmin,$details);$q->execute();
            $conn->commit();
            return $registration['issued_id'];
        }catch(Throwable $e){$conn->rollback();throw $e;}
    }
}
