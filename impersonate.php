<?php
require __DIR__ . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit('POST required.');}
csrf_check();
$action=(string)($_POST['action']??'');
if ($action==='stop') {
    $actor=impersonation_actor();
    if (!$actor) {unset($_SESSION['uid'],$_SESSION['impersonation']);redirect('login.php');}
    $cid=(int)$_SESSION['impersonation']['creator_id'];
    $month=valid_month($_SESSION['impersonation']['month']??'')??date('Y-m');
    $creatorExists=(bool)q("SELECT 1 FROM users WHERE id=? AND role='creator'",[$cid])->fetchColumn();
    if ($creatorExists) log_activity($cid,(int)$actor['id'],'impersonation',$month,'returned to their admin account');
    unset($_SESSION['impersonation']);$_SESSION['uid']=(int)$actor['id'];
    session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
    flash('Returned to your admin account.');
    redirect(($creatorExists?'creator.php?id='.$cid.'&':'dashboard.php?').'m='.$month);
}
if ($action!=='start') {http_response_code(422);exit('Unknown action.');}
$u=require_login(['superadmin','admin']);
if (!empty($_SESSION['impersonation'])) {http_response_code(403);exit('Return to your admin account first.');}
$cid=(int)($_POST['cid']??0);$month=valid_month($_POST['month']??'')??date('Y-m');
$creator=q("SELECT id,name FROM users WHERE id=? AND role='creator' AND is_active=1",[$cid])->fetch();
if (!$creator) {http_response_code(422);exit('Only an active creator account can be opened.');}
log_activity($cid,(int)$u['id'],'impersonation',$month,'opened the creator account');
$_SESSION['impersonation']=['actor_id'=>(int)$u['id'],'creator_id'=>$cid,'month'=>$month];
$_SESSION['uid']=$cid;session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
redirect('creator.php?m='.$month);
