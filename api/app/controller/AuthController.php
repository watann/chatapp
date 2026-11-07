<?php
require_once __DIR__ . '/../helpers.php';

class AuthController{
    public function checkAuth(){
        require_auth();
    }

    public function signUp(){
        try{ verify_csrf(); }catch(Throwable $e){ echo e($e->getMessage()); return; }
        $uploaded = null;
        $conn = db();
        try{
            require_once __DIR__ . '/InstitutionController.php';
            $registrationId = strtoupper(trim((string)($_POST['registration_id'] ?? '')));
            $identityNumber = trim((string)($_POST['identity_number'] ?? ''));
            $birthDate = trim((string)($_POST['birth_date'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $language = in_array(($_POST['language'] ?? 'ku'), supported_languages(), true) ? $_POST['language'] : 'ku';

            if($registrationId === '' || $identityNumber === '' || $birthDate === '' || $email === '' || $password === ''){
                echo t('required_fields');
                return;
            }
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                echo t('invalid_email');
                return;
            }
            if(strlen($password) < 6){
                echo t('password_min');
                return;
            }

            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0){
                echo t('email_taken');
                return;
            }

            $conn->begin_transaction();
            $institution = new InstitutionController();
            $record = $institution->findRegistrationRecord($registrationId, $identityNumber, $birthDate, true);
            if(!$record){
                throw new Exception(t('registration_record_not_found'));
            }
            if(($record['status'] ?? '') !== 'available'){
                throw new Exception(t('registration_id_unavailable'));
            }
            $registryId = (int)$record['id'];
            $used = $conn->prepare("SELECT user_id FROM users WHERE registry_id=? LIMIT 1");
            $used->bind_param('i', $registryId);
            $used->execute();
            if($used->get_result()->num_rows > 0){
                throw new Exception(t('registration_id_unavailable'));
            }

            $img = 'assets/img/default-avatar.svg';
            $uploaded = upload_profile_image($_FILES['image'] ?? null);
            if($uploaded) $img = $uploaded;

            do{
                $unique_id = random_int(100000000, 999999999);
            }while(user_unique_id_taken($unique_id));

            $fname = trim((string)$record['first_name']);
            $middleName = trim((string)$record['middle_name']);
            $lname = trim((string)$record['last_name']);
            $role = (string)$record['person_type'];
            $status = 'Active now';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $bio = '';
            $stmt = $conn->prepare("INSERT INTO users (unique_id, fname, middle_name, lname, email, password, img, status, last_seen_at, language, role, bio, registry_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param('issssssssssi', $unique_id, $fname, $middleName, $lname, $email, $hash, $img, $status, $language, $role, $bio, $registryId);
            if(!$stmt->execute()) throw new Exception(t('account_create_failed'));

            $update = $conn->prepare("UPDATE institution_registry SET status='registered', updated_at=NOW() WHERE id=? AND status='available'");
            $update->bind_param('i', $registryId);
            $update->execute();
            if($update->affected_rows !== 1) throw new Exception(t('registration_id_unavailable'));

            $conn->commit();
            session_regenerate_id(true);
            $_SESSION['unique_id'] = $unique_id;
            echo "success";
        }catch(Throwable $e){
            try{ $conn->rollback(); }catch(Throwable $ignore){}
            if($uploaded) delete_profile_image($uploaded);
            if($e instanceof mysqli_sql_exception){
                echo t('account_create_failed');
            }else{
                $known = $e->getMessage();
                echo $known !== '' ? $known : t('account_create_failed');
            }
        }
    }

    public function logIn(){
        try{ verify_csrf(); }catch(Throwable $e){ echo e($e->getMessage()); return; }
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if($email === '' || $password === ''){
            echo t('required_fields');
            return;
        }

        $stmt = db()->prepare("SELECT u.*,r.status AS registry_status FROM users u LEFT JOIN institution_registry r ON r.id=u.registry_id WHERE u.email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if(!$row){
            echo t('incorrect_credentials');
            return;
        }

        if(!empty($row['registry_id']) && ($row['registry_status']??'')==='suspended'){ echo t('account_suspended'); return; }

        $valid = password_verify($password, $row['password']);
        if(!$valid && strlen($row['password']) === 32 && hash_equals($row['password'], md5($password))){
            $valid = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upgrade = db()->prepare("UPDATE users SET password = ? WHERE unique_id = ?");
            $upgrade->bind_param('si', $newHash, $row['unique_id']);
            $upgrade->execute();
        }

        if(!$valid){
            echo t('incorrect_credentials');
            return;
        }

        $status = 'Active now';
        $stmt = db()->prepare("UPDATE users SET status = ?, last_seen_at=NOW(), updated_at = NOW() WHERE unique_id = ?");
        $stmt->bind_param('si', $status, $row['unique_id']);
        $stmt->execute();
        session_regenerate_id(true);
        $_SESSION['unique_id'] = (int)$row['unique_id'];
        echo "success";
    }

    public function logOut(){
        if(!empty($_SESSION['unique_id'])){
            $status = 'Offline now';
            $stmt = db()->prepare("UPDATE users SET status = ?, last_seen_at=NOW(), updated_at = NOW() WHERE unique_id = ?");
            $stmt->bind_param('si', $status, $_SESSION['unique_id']);
            $stmt->execute();
        }
        unset($_SESSION['unique_id'],$_SESSION['presence_touch_at']);
        session_regenerate_id(true);
        header('Location: ../login.php');
        exit;
    }
}
