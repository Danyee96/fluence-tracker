<?php
require __DIR__.'/custom-plans.php';
$plan=creator_plan(2);
$post=['creator_details_complete'=>'1','plan_revision'=>$plan['revision'],'social_revision'=>social_edit_fingerprint([]),'creator_goals'=>onboarding_goals(),'creator_socials'=>[['platform'=>'instagram','handle'=>'@apify']]];
$post['creator_goals'][]=['key'=>'','title'=>'Sponsorships','type'=>'money','target'=>'2000','description'=>'Secure sponsors'];
$input=creator_details_input($post,$plan,[]);
check(count($input['goals'])===4 && $input['socials'][0]['handle']==='apify','Creator setup validation failed');
check($input['goals'][0]['source']==='revenue','Default goal lost monthly link');
db()->beginTransaction();$queue=save_creator_details(2,$input,$plan);db()->commit();
check(count($queue)===1 && count(social_accounts(2))===1,'Social accounts not queued');
$stored=creator_plan(2);check(count($stored['goals'])===4 && count($stored['phases'])===3,'Plan creation lost goals or phases');
$accounts=social_accounts(2);$id=$accounts[0]['id'];
$external=['creator_details_complete'=>'1','plan_revision'=>$stored['revision'],'social_revision'=>social_edit_fingerprint($accounts),'goals_edit_mode'=>'external','creator_goals'=>[],'creator_socials'=>[['id'=>$id,'platform'=>'instagram','handle'=>'apify']]];
check(creator_details_input($external,$stored,$accounts)['goals']===$stored['goals'],'Profile edit without inline goal fields removed targets');
upsert('social_snapshots',['account_id','snapshot_date'],['account_id'=>$id,'snapshot_date'=>date('Y-m-d'),'followers'=>100,'captured_at'=>now()]);
$post['plan_revision']=$stored['revision'];$post['creator_goals']=$stored['goals'];$post['social_revision']=social_edit_fingerprint($accounts);$post['creator_socials']=[['id'=>$id,'platform'=>'instagram','handle'=>'https://www.instagram.com/other/']];
$next=creator_details_input($post,$stored,$accounts);db()->beginTransaction();$queue=save_creator_details(2,$next,$stored);db()->commit();
$changed=social_accounts(2);check(count($changed)===1 && $changed[0]['handle']==='other' && $changed[0]['latest']===null,'Changed identity reused old counts');
check(q('SELECT sync_status FROM social_accounts WHERE id=?',[$id])->fetchColumn()==='archived','Old profile not archived');
check(q('SELECT followers FROM social_snapshots WHERE account_id=?',[$id])->fetchColumn()==100,'Archived profile history lost');
$stored=creator_plan(2);$post['plan_revision']=$stored['revision'];$post['creator_goals']=$stored['goals'];$post['social_revision']=social_edit_fingerprint($changed);$post['creator_socials']=[['platform'=>'instagram','handle'=>'apify']];
$next=creator_details_input($post,$stored,$changed);db()->beginTransaction();save_creator_details(2,$next,$stored);db()->commit();
$restored=social_accounts(2);check(count($restored)===1 && $restored[0]['id']==$id && $restored[0]['latest']['followers']==100,'Restoring same identity lost history');
foreach ([array_replace($post,['creator_details_complete'=>'']),array_replace($post,['plan_revision'=>999]),array_replace($post,['social_revision'=>'bad']),array_replace($post,['creator_socials'=>[['platform'=>'instagram','handle'=>'https://evil.test/user']]])] as $bad) {
 try {creator_details_input($bad,creator_plan(2),social_accounts(2));throw new RuntimeException('Invalid setup accepted');}catch(InvalidArgumentException $expected){}
}
$public=social_dashboard_data(2)[0];check(!array_key_exists('run_id',$public) && $public['followers']===100 && $public['stale'],'Dashboard freshness projection incorrect');
check(count(creator_plan(1)['goals'])!==count(creator_plan(2)['goals']) || creator_plan(1)['goals']!==creator_plan(2)['goals'],'Creator edits affected another plan');
// A sync completing while the admin archives a profile must not resurrect it or overwrite its history.
$CONFIG['apify']=['token'=>'fake-test-token'];
q("UPDATE social_accounts SET run_id='race_run',sync_status='syncing',locked_until=NULL WHERE id=?",[$id]);
$GLOBALS['social_test_transport']=function($method,$path)use($id){
 if(str_starts_with($path,'/actor-runs/'))return [200,['data'=>['status'=>'SUCCEEDED','defaultDatasetId'=>'race_dataset']]];
 q("UPDATE social_accounts SET is_active=0,sync_status='archived' WHERE id=?",[$id]);
 return [200,[['username'=>'apify','followersCount'=>999]]];
};
social_collect((int)$id);
check(q('SELECT sync_status FROM social_accounts WHERE id=?',[$id])->fetchColumn()==='archived','An in-flight sync resurrected an archived profile');
check(q('SELECT followers FROM social_snapshots WHERE account_id=?',[$id])->fetchColumn()==100,'Archived stats changed after identity removal');
echo "Creator details: goals, linked metrics, identity validation, queued sync, archival, restoration, stale edits and dashboard freshness passed.\n";
