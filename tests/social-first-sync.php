<?php
require __DIR__.'/custom-plans.php';
$CONFIG['apify']=['token'=>'fake'];
$identity=social_identity('youtube','cryptobullseye');
q('INSERT INTO social_accounts (creator_id,platform,handle,profile_url,created_at,next_sync) VALUES (1,?,?,?,?,?)',['youtube',$identity['handle'],$identity['profile_url'],now(),now()]);
$id=(int)db()->lastInsertId();$starts=0;$ready=false;
$GLOBALS['social_test_transport']=function($method,$path)use(&$starts,&$ready){
 if($method==='POST'){++$starts;return [201,['data'=>['id'=>'first_run']]];}
 if(str_starts_with($path,'/actor-runs/'))return [200,['data'=>['status'=>$ready?'SUCCEEDED':'RUNNING','defaultDatasetId'=>'first_dataset']]];
 return [200,[['channelUsername'=>'CryptoBullseye','aboutChannelInfo'=>['channelUsername'=>'CryptoBullseye','numberOfSubscribers'=>12345,'channelTotalViews'=>50000,'channelTotalVideos'=>10]]]];
};
social_process_creator(1,false);check($starts===0,'Read-only status poll started a paid job');
social_process_creator(1,true);check($starts===1,'Page fallback did not start a missed first sync');
social_process_creator(1,true);check($starts===1,'Page poll started duplicate paid job');
$ready=true;social_process_creator(1,true);
$a=social_accounts(1)[0];check($a['sync_status']==='ready' && $a['latest']['followers']==12345,'First stats were not collected');
social_process_creator(1,true);check($starts===1,'Fresh account started another job');
q("UPDATE social_accounts SET sync_status='pending',last_synced=NULL,last_attempt=?,next_sync=? WHERE id=?",[social_time(-7200),social_time(86400),$id]);
check(social_needs_work(q('SELECT * FROM social_accounts WHERE id=?',[$id])->fetch()),'Interrupted first sync was not recoverable');
q('UPDATE social_accounts SET is_active=0 WHERE id=?',[$id]);social_process_creator(1,true);check($starts===1,'Paused first sync restarted');
$CONFIG['apify']=[];social_process_creator(1,true);check($starts===1,'Missing token attempted paid job');
require __DIR__.'/../inc/social-layout.php';
ob_start();social_card(social_accounts(1)[0],false);$html=ob_get_clean();
check(str_contains($html,'Setup required') && str_contains($html,'not configured on this server'),'Missing server token hidden behind awaiting first sync');
echo "First-sync recovery: missed queue, read-only polling, deduplication, collection, interruption, paused accounts and missing configuration passed.\n";
