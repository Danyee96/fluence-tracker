<?php
/** Run with php tests/social.php; optional FL_TEST_MYSQL=1 also verifies MySQL. No provider calls. */
require __DIR__ . '/custom-plans.php';
$CONFIG['apify'] = ['token'=>'fake-test-token'];
foreach ([
 ['instagram','https://www.instagram.com/Apify/?utm_source=test','apify'],
 ['youtube','@apify','apify'],
 ['youtube','https://www.youtube.com/channel/UCTgwcoeGGKmZ3zzCXN2qo_A','UCTgwcoeGGKmZ3zzCXN2qo_A'],
 ['tiktok','https://www.tiktok.com/@apifytech','apifytech'],
 ['facebook','https://facebook.com/profile.php?id=12345','12345'],
] as [$platform,$input,$expected]) check(social_identity($platform,$input)['handle']===$expected,'Handle normalization failed');
foreach (['https://evil.test/apify','https://instagram.com.evil.test/apify','https://www.instagram.com/p/post123','https://user:pass@instagram.com/apify','https://instagram.com/apify/extra','../../secret','@bad handle'] as $bad) {
 try {social_identity('instagram',$bad); throw new RuntimeException('Unsafe profile accepted');} catch(InvalidArgumentException $ok){}
}
check(social_count('12.3K')===12300 && social_count('1,250')===1250 && social_count(0)===0 && social_count('hidden')===null && social_count(-1)===null,'Counter parser failed');
$rows = [
 'instagram'=>[['username'=>'test','fullName'=>'Test','followersCount'=>100,'postsCount'=>5]],
 'youtube'=>[['channelUsername'=>'test','aboutChannelInfo'=>['channelUsername'=>'test','numberOfSubscribers'=>200,'channelTotalVideos'=>9,'channelTotalViews'=>1000]]],
 'tiktok'=>[['authorMeta'=>['name'=>'test','fans'=>300,'video'=>8,'heart'=>900]]],
 'facebook'=>[['pageName'=>'test','followers'=>400,'likes'=>200]],
];
foreach ($rows as $platform=>$response) check(social_parse(['platform'=>$platform,'handle'=>'test','profile_url'=>'https://example.invalid'],$response)['followers']>0,'Provider parser failed');
try {social_parse(['platform'=>'instagram','handle'=>'other','profile_url'=>''], $rows['instagram']);throw new RuntimeException('Mismatched identity accepted');} catch (RuntimeException $e) {check(!str_contains($e->getMessage(),'Mismatched'),'Identity validation failed');}
$identity = social_identity('instagram','test');
q('INSERT INTO social_accounts (creator_id,platform,handle,profile_url,created_at) VALUES (1,?,?,?,?)',['instagram','test',$identity['profile_url'],now()]);
$id=(int)db()->lastInsertId(); $calls=[]; $providerStatus='RUNNING'; $networkFailure=false;
$GLOBALS['social_test_transport']=function($method,$path,$body,$query) use (&$calls,&$providerStatus,&$networkFailure,$rows) {
 $calls[]=$method.' '.$path;
 if($networkFailure) throw new RuntimeException('Temporary provider outage.');
 if($method==='POST') {check($query['maxItems']===1 && $query['maxTotalChargeUsd']<=0.05,'Spend limits missing');check($body['usernames']===['test'],'Unexpected actor input');return [201,['data'=>['id'=>'run123']]];}
 if(str_starts_with($path,'/actor-runs/'))return [200,['data'=>['status'=>$providerStatus,'defaultDatasetId'=>'dataset123']]];
 return [200,$rows['instagram']];
};
social_start($id);social_start($id);
check(count($calls)===1,'Duplicate paid jobs started');
social_collect($id);
check(!q('SELECT 1 FROM social_snapshots WHERE account_id=?',[$id])->fetchColumn(),'Pending run created fake stats');
$networkFailure=true;social_collect($id);$networkFailure=false;
check(q('SELECT run_id FROM social_accounts WHERE id=?',[$id])->fetchColumn()==='run123','Network error discarded running job');
$providerStatus='SUCCEEDED';social_collect($id);
$a=social_accounts(1)[0];check($a['latest']['followers']==100 && $a['sync_status']==='ready','Stats were not persisted');
social_start($id);check(count(array_filter($calls,fn($x)=>str_starts_with($x,'POST')))===1,'Hourly rate limit bypassed');
upsert('social_snapshots',['account_id','snapshot_date'],['account_id'=>$id,'snapshot_date'=>date('Y-m-d',strtotime('-2 days')),'followers'=>80,'captured_at'=>now()]);
$a=social_accounts(1)[0];check($a['growth']===20,'Growth history failed');
q('UPDATE social_accounts SET last_attempt=NULL, next_sync=NULL, locked_until=? WHERE id=?',[social_time(60),$id]);
$count=count($calls);social_start($id);check(count($calls)===$count,'Concurrent run lock bypassed');
q('UPDATE social_accounts SET locked_until=NULL, is_active=0 WHERE id=?',[$id]);
$count=count($calls);social_tick();check(count($calls)===$count,'Paused account refreshed');
q('UPDATE social_accounts SET is_active=1, run_id=?,sync_status=? WHERE id=?',['run123','syncing',$id]);
$GLOBALS['social_test_transport']=fn()=>[402,['error'=>['message'=>'token=secret']]];
social_collect($id);
$a=social_accounts(1)[0];check($a['latest']['followers']==100 && !str_contains($a['last_error'],'secret'),'Failure erased valid stats or leaked upstream secrets');
check(!can_manage_social(['id'=>2,'role'=>'creator'],1) && can_manage_social(['id'=>2,'role'=>'admin'],1),'Social permissions incorrect');
check(q('SELECT COUNT(*) FROM monthly_entries')->fetchColumn()==0,'Social sync modified manual monthly numbers');
echo "Social checks passed: normalization, provider mapping, ownership, locks, spend limits, retries, snapshots and growth.\n";
