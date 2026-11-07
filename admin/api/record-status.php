<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/InstitutionController.php';
require_once __DIR__ . '/../../api/app/controller/WorkflowController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['administration','human_resources','student_affairs','registry'],true);
try{
    verify_csrf();
    $registryId=(int)($_POST['registry_id']??0); $institution=new InstitutionController();
    $q=db()->prepare("SELECT id,first_name,middle_name,last_name,person_type,status FROM institution_registry WHERE id=? LIMIT 1"); $q->bind_param('i',$registryId); $q->execute(); $record=$q->get_result()->fetch_assoc();
    if(!$record) throw new Exception(t('invalid_action'));
    if(($admin['admin_role']??'')!=='super_admin' && !in_array($record['person_type'],$institution->allowedRecordTypes($admin),true)) throw new Exception(t('not_allowed'));
    $recordName=display_name($record);
    if(($admin['admin_role']??'')==='super_admin'){
        $institution->toggleSuspensionAs($registryId,$admin);
        $_SESSION['admin_flash_success']=t('registry_status_updated');
    }else{
        $details=t('full_name').': '.$recordName."
".t('person_type').': '.role_label($record['person_type'])."
".t('status').': '.t('registry_status_'.$record['status']);
        (new WorkflowController())->submitChange($admin,'registry_status',t('request_type_registry_status').' · '.$recordName,$details,['registry_id'=>$registryId]);
        $_SESSION['admin_flash_success']=t('change_sent_for_approval');
    }
}catch(Throwable $e){ $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage(); }
header('Location: ../records.php'); exit;
