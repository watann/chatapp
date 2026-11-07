<?php
require_once __DIR__ . '/../helpers.php';

class CallController{
    private function json($data,$status=200){ http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
    private function cleanup(){
        db()->query("UPDATE call_sessions SET status='missed',ended_at=NOW() WHERE status='ringing' AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        db()->query("UPDATE call_sessions SET status='ended',ended_at=NOW() WHERE status='accepted' AND COALESCE(last_activity_at,answered_at,created_at) < DATE_SUB(NOW(), INTERVAL 90 SECOND)");
    }
    private function getCall($id){ $s=db()->prepare("SELECT * FROM call_sessions WHERE id=? LIMIT 1"); $s->bind_param('i',$id); $s->execute(); return $s->get_result()->fetch_assoc(); }
    private function participant($call,$me){ return $call && ((int)$call['caller_id']===$me || (int)$call['callee_id']===$me); }
    private function heartbeat($id){ $s=db()->prepare("UPDATE call_sessions SET last_activity_at=NOW() WHERE id=? AND status IN ('ringing','accepted')"); $s->bind_param('i',$id); $s->execute(); }

    public function handle(){
        require_auth(); $this->cleanup(); $action=(string)($_REQUEST['action']??'poll');
        try{
            if($_SERVER['REQUEST_METHOD']==='POST') verify_csrf();
            if($action==='start') $this->start();
            if($action==='accept' || $action==='decline' || $action==='end') $this->statusAction($action);
            if($action==='signal') $this->signal();
            if($action==='poll') $this->poll();
            $this->json(['ok'=>false,'error'=>t('invalid_action')],400);
        }catch(Throwable $e){ $this->json(['ok'=>false,'error'=>$e->getMessage()],400); }
    }

    private function start(){
        $me=(int)$_SESSION['unique_id']; $target=(int)($_POST['target_id']??0); $type=(string)($_POST['call_type']??'voice');
        if($target<=0 || $target===$me || !user_exists($target) || is_blocked_between($me,$target) || !in_array($type,['voice','video'],true)) throw new Exception(t('not_allowed'));
        $busy=db()->prepare("SELECT id FROM call_sessions WHERE status IN ('ringing','accepted') AND (caller_id IN (?,?) OR callee_id IN (?,?)) LIMIT 1");
        $busy->bind_param('iiii',$me,$target,$me,$target); $busy->execute(); if($busy->get_result()->num_rows>0) throw new Exception(t('call_busy'));
        $stmt=db()->prepare("INSERT INTO call_sessions (caller_id,callee_id,call_type,status,created_at,last_activity_at) VALUES (?,?,?,'ringing',NOW(),NOW())");
        $stmt->bind_param('iis',$me,$target,$type); $stmt->execute();
        $this->json(['ok'=>true,'call_id'=>(int)db()->insert_id,'status'=>'ringing']);
    }

    private function statusAction($action){
        $me=(int)$_SESSION['unique_id']; $id=(int)($_POST['call_id']??0); $call=$this->getCall($id);
        if(!$this->participant($call,$me)) throw new Exception(t('not_allowed'));
        if($action==='accept'){
            if((int)$call['callee_id']!==$me || $call['status']!=='ringing') throw new Exception(t('not_allowed'));
            $s=db()->prepare("UPDATE call_sessions SET status='accepted',answered_at=NOW(),last_activity_at=NOW() WHERE id=? AND status='ringing'"); $s->bind_param('i',$id); $s->execute();
        }elseif($action==='decline'){
            if((int)$call['callee_id']!==$me || $call['status']!=='ringing') throw new Exception(t('not_allowed'));
            $s=db()->prepare("UPDATE call_sessions SET status='declined',ended_at=NOW(),last_activity_at=NOW() WHERE id=? AND status='ringing'"); $s->bind_param('i',$id); $s->execute();
        }else{
            if(!in_array($call['status'],['ringing','accepted'],true)){ $this->json(['ok'=>true,'status'=>$call['status']]); }
            $s=db()->prepare("UPDATE call_sessions SET status='ended',ended_at=NOW(),last_activity_at=NOW() WHERE id=? AND status IN ('ringing','accepted')"); $s->bind_param('i',$id); $s->execute();
        }
        $fresh=$this->getCall($id); $this->json(['ok'=>true,'status'=>$fresh['status']]);
    }

    private function signal(){
        $me=(int)$_SESSION['unique_id']; $id=(int)($_POST['call_id']??0); $type=(string)($_POST['signal_type']??''); $payload=(string)($_POST['payload']??'');
        $call=$this->getCall($id);
        if(!$this->participant($call,$me) || !in_array($call['status'],['ringing','accepted'],true) || !in_array($type,['offer','answer','candidate'],true) || strlen($payload)>120000) throw new Exception(t('not_allowed'));
        $recipient=((int)$call['caller_id']===$me)?(int)$call['callee_id']:(int)$call['caller_id'];
        if(is_blocked_between($me,$recipient)) throw new Exception(t('not_allowed'));
        $s=db()->prepare("INSERT INTO call_signals (call_id,sender_id,recipient_id,signal_type,payload,created_at) VALUES (?,?,?,?,?,NOW())");
        $s->bind_param('iiiss',$id,$me,$recipient,$type,$payload); $s->execute(); $this->heartbeat($id); $this->json(['ok'=>true]);
    }

    private function poll(){
        $me=(int)$_SESSION['unique_id']; $id=(int)($_REQUEST['call_id']??0); $last=(int)($_REQUEST['last_signal_id']??0);
        if($id<=0){
            $s=db()->prepare("SELECT c.*,u.fname,u.middle_name,u.lname,u.img FROM call_sessions c JOIN users u ON u.unique_id=c.caller_id WHERE c.callee_id=? AND c.status='ringing' AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_id=c.caller_id AND b.blocked_id=c.callee_id) OR (b.blocker_id=c.callee_id AND b.blocked_id=c.caller_id)) ORDER BY c.id DESC LIMIT 1");
            $s->bind_param('i',$me); $s->execute(); $r=$s->get_result()->fetch_assoc();
            $this->json(['ok'=>true,'incoming'=>$r?['call_id'=>(int)$r['id'],'caller_id'=>(int)$r['caller_id'],'call_type'=>$r['call_type'],'name'=>display_name($r),'avatar'=>avatar_src($r['img'])]:null]);
        }
        $call=$this->getCall($id); if(!$this->participant($call,$me)) throw new Exception(t('not_allowed')); $this->heartbeat($id);
        $s=db()->prepare("SELECT id,signal_type,payload FROM call_signals WHERE call_id=? AND recipient_id=? AND id>? ORDER BY id ASC LIMIT 100");
        $s->bind_param('iii',$id,$me,$last); $s->execute(); $signals=[]; $signalResult=$s->get_result(); while($r=$signalResult->fetch_assoc()) $signals[]=['id'=>(int)$r['id'],'type'=>$r['signal_type'],'payload'=>$r['payload']];
        $other=((int)$call['caller_id']===$me)?(int)$call['callee_id']:(int)$call['caller_id'];
        $u=db()->prepare("SELECT fname,middle_name,lname,img FROM users WHERE unique_id=? LIMIT 1"); $u->bind_param('i',$other); $u->execute(); $person=$u->get_result()->fetch_assoc();
        $this->json(['ok'=>true,'status'=>$call['status'],'call_type'=>$call['call_type'],'other_id'=>$other,'other_name'=>$person?display_name($person):'','other_avatar'=>$person?avatar_src($person['img']):'assets/img/default-avatar.svg','signals'=>$signals]);
    }
}
