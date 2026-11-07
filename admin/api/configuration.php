<?php
require_once __DIR__ . '/../../api/app/controller/AdminController.php';
require_once __DIR__ . '/../../api/app/controller/AcademicController.php';
require_once __DIR__ . '/../../api/app/controller/OrganizationController.php';
require_once __DIR__ . '/../../api/app/controller/WorkflowController.php';
$ac=new AdminController(); $admin=$ac->requireDepartmentAccess(['administration'],true);
$academic=new AcademicController();
try{
    verify_csrf();
    $academic=new AcademicController(); $org=new OrganizationController(); $workflow=new WorkflowController();
    $action=(string)($_POST['action']??''); $isManager=($admin['admin_role']??'')==='super_admin';
    if($action==='add_option'){
        $type=(string)($_POST['option_type']??''); $label=trim((string)($_POST['label']??'')); $head=(int)($_POST['head_teacher_registry_id']??0);
        if($isManager){
            if($type==='student_department' && $head>0){
                $conn=db();$conn->begin_transaction();
                try{ $id=$academic->addOption($type,$label,(int)$admin['id']);$academic->setAcademicDepartmentHead($id,$head);$conn->commit(); }
                catch(Throwable $e){$conn->rollback();throw $e;}
            }else{ $academic->addOption($type,$label,(int)$admin['id']); }
            $_SESSION['admin_flash_success']=t('option_added');
        }
        else{ $detail=t('option_type_'.$type).': '.$label; if($type==='student_department'&&$head>0) $detail.=' | '.t('academic_department_head').' #'.$head; $workflow->submitChange($admin,'config_option_add',t('request_type_config_option_add').' · '.$label,$detail,['option_type'=>$type,'label'=>$label,'head_teacher_registry_id'=>$head]); $_SESSION['admin_flash_success']=t('change_sent_for_approval'); }
    }elseif($action==='rename_study_department'){
        $id=(int)($_POST['option_id']??0); $name=trim((string)($_POST['label']??''));
        $row=$academic->getOption($id,'student_department',false); if(!$row) throw new Exception(t('invalid_department'));
        if($isManager){ $academic->renameStudyDepartment($id,$name); $_SESSION['admin_flash_success']=t('option_updated'); }
        else{ $workflow->submitChange($admin,'academic_department_rename',t('rw_study_departments').' · '.$row['label'],t('rw_study_name').': '.$name,['option_id'=>$id,'label'=>$name]); $_SESSION['admin_flash_success']=t('change_sent_for_approval'); }
    }elseif($action==='toggle_option'){
        $id=(int)($_POST['option_id']??0); $row=$academic->getOption($id,null,false); if(!$row) throw new Exception(t('invalid_action'));
        if($isManager){ $academic->toggleOption($id); $_SESSION['admin_flash_success']=t('option_updated'); }
        else{ $workflow->submitChange($admin,'config_option_toggle',t('request_type_config_option_toggle').' · '.$row['label'],t('status').': '.((int)$row['is_active']?t('active'):t('inactive')),['option_id'=>$id]); $_SESSION['admin_flash_success']=t('change_sent_for_approval'); }
    }elseif($action==='set_study_head'){
        $id=(int)($_POST['option_id']??0); $head=(int)($_POST['head_teacher_registry_id']??0); $row=$academic->getOption($id,'student_department',false); if(!$row) throw new Exception(t('invalid_department'));
        if($isManager){ $academic->setAcademicDepartmentHead($id,$head); $_SESSION['admin_flash_success']=t('academic_department_head_updated'); }
        else{ $headName=t('none'); if($head>0){ $q=db()->prepare("SELECT CONCAT_WS(' ',first_name,middle_name,last_name) n,issued_id FROM institution_registry WHERE id=? AND person_type='teacher' LIMIT 1"); $q->bind_param('i',$head); $q->execute(); $hr=$q->get_result()->fetch_assoc(); if($hr) $headName=$hr['n'].' · '.$hr['issued_id']; } $workflow->submitChange($admin,'academic_department_head',t('request_type_academic_department_head').' · '.$row['label'],t('academic_department_head').': '.$headName,['option_id'=>$id,'head_teacher_registry_id'=>$head]); $_SESSION['admin_flash_success']=t('change_sent_for_approval'); }
    }elseif($action==='create_org_department'){
        $payload=['name'=>(string)($_POST['name']??''),'code'=>(string)($_POST['code']??''),'department_type'=>(string)($_POST['department_type']??'other'),'parent_id'=>(int)($_POST['parent_id']??1),'responsibilities'=>(string)($_POST['responsibilities']??'')];
        if($isManager){ $org->createDepartment($payload['name'],$payload['code'],$payload['department_type'],$payload['parent_id'],(int)$admin['id'],$payload['responsibilities']); $_SESSION['admin_flash_success']=t('department_created'); }
        else{ $detail=t('department_code').': '.$payload['code'].' | '.t('department_type').': '.$payload['department_type']; if(trim($payload['responsibilities'])!=='') $detail.=' | '.t('department_responsibilities').': '.trim($payload['responsibilities']); $workflow->submitChange($admin,'organization_department_create',t('request_type_organization_department_create').' · '.$payload['name'],$detail,$payload); $_SESSION['admin_flash_success']=t('change_sent_for_approval'); }
    }elseif($action==='assign_org_head'){
        $dept=(int)($_POST['department_id']??0); $rid=(int)($_POST['registry_id']??0); $d=$org->department($dept); if(!$d) throw new Exception(t('invalid_action'));
        $assignment=db()->prepare("SELECT department_id,is_active FROM admin_users WHERE registry_id=? LIMIT 1"); $assignment->bind_param('i',$rid); $assignment->execute(); $ar=$assignment->get_result()->fetch_assoc();
        if($ar && (int)$ar['is_active']===1 && !empty($ar['department_id']) && (int)$ar['department_id']!==$dept) throw new Exception(t('staff_already_other_department'));
        if($isManager){ $org->assignHead($dept,$rid,(int)$admin['id']); $_SESSION['admin_flash_success']=t('department_head_assigned'); }
        else{ $q=db()->prepare("SELECT CONCAT_WS(' ',first_name,middle_name,last_name) n,issued_id FROM institution_registry WHERE id=? AND person_type='staff' LIMIT 1"); $q->bind_param('i',$rid); $q->execute(); $person=$q->get_result()->fetch_assoc(); $detail=t('responsible_person').': '.($person?($person['n'].' · '.$person['issued_id']):('#'.$rid)); $workflow->submitChange($admin,'organization_head_assign',t('request_type_organization_head_assign').' · '.$d['name'],$detail,['department_id'=>$dept,'registry_id'=>$rid]); $_SESSION['admin_flash_success']=t('change_sent_for_approval'); }
    }else throw new Exception(t('invalid_action'));
}catch(Throwable $e){ $_SESSION['admin_flash_error']=$e instanceof mysqli_sql_exception?t('admin_database_error'):$e->getMessage(); }
// Whitelisted return target; never redirect to arbitrary user-supplied URLs.
if((string)($_POST['return_to']??'')==='options'){
    $action=(string)($_POST['action']??'');
    $type=(string)($_POST['option_type']??'');
    $fragment='organization';
    if(in_array($action,['rename_study_department','set_study_head'],true) || ($action==='add_option' && $type==='student_department')) $fragment='study';
    elseif($action==='add_option' && in_array($type,['teacher_certificate','teacher_specialization'],true)) $fragment='teachers';
    elseif($action==='toggle_option'){
        try { $opt=$academic->getOption((int)($_POST['option_id']??0),null,false); $optType=$opt['option_type']??''; }
        catch(Throwable $ignored){ $optType=''; }
        if($optType==='student_department') $fragment='study';
        elseif(in_array($optType,['teacher_certificate','teacher_specialization'],true)) $fragment='teachers';
    }
    header('Location: ../options.php#'.$fragment);exit;
}
$studyActions=['rename_study_department','set_study_head'];
if(in_array((string)($_POST['action']??''),$studyActions,true) || ((string)($_POST['action']??'')==='add_option' && (string)($_POST['option_type']??'')==='student_department')){ header('Location: ../study-departments.php'); exit; }
if((string)($_POST['action']??'')==='toggle_option'){ $opt=$academic->getOption((int)($_POST['option_id']??0),null,false); if(($opt['option_type']??'')==='student_department'){header('Location: ../study-departments.php');exit;} }
header('Location: ../options.php'); exit;
