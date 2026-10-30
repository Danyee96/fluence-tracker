<?php
require __DIR__ . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['ok'=>false],405);
$u=current_user();if(!$u) json_out(['ok'=>false,'error'=>'Log in again.'],401);
if(!csrf_ok($_SERVER['HTTP_X_CSRF']??null)) json_out(['ok'=>false,'error'=>'Refresh the page and try again.'],419);
$cid=(int)($_POST['cid']??0);$id=(int)($_POST['account_id']??0);
if(!can_manage_social($u,$cid)) json_out(['ok'=>false,'error'=>'No access.'],403);
$account=q("SELECT s.* FROM social_accounts s JOIN users u ON u.id=s.creator_id WHERE s.id=? AND s.creator_id=? AND s.sync_status<>'archived' AND u.role='creator' AND u.is_active=1",[$id,$cid])->fetch();
if(!$account) json_out(['ok'=>false,'error'=>'Channel not found.'],404);
if(!$account['is_active']) json_out(['ok'=>false,'error'=>'This channel is paused. Resume it in Manage channels.'],422);
if(!social_enabled()) json_out(['ok'=>false,'error'=>'Apify is not configured on this server.'],422);
if($account['run_id']) social_collect($id);else social_start($id);
json_out(['ok'=>true,'socials'=>social_dashboard_data($cid)]);
