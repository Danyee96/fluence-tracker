<?php
require __DIR__ . '/custom-plans.php';
$item = ['key'=>'sales','type'=>'money','target'=>'1000'];
foreach ([['', 'todo',0], ['0','todo',0], ['500','ip',50], ['1000','cp',100], ['2000','cp',100], ['999.99','ip',99]] as [$value,$status,$pct]) {
 $p=item_progress($item,$value);check($p['status']===$status && progress_percent($p['percent'],$status==='cp')===$pct,'Automatic numeric status mismatch');
}
check(item_progress(['type'=>'number'], '5')['status']==='ip','Targetless numeric result completed');
check(item_progress(['type'=>'text','target'=>'Ready'], 'ready')['status']==='cp','Text matching failed');
check(item_progress(['type'=>'text','target'=>'Ready'], 'draft')['status']==='ip','Partial text status failed');
check(item_progress(['type'=>'text'], 'Anything')['status']==='cp','Text without target did not complete');
check(item_progress(['type'=>'check'], '', 'cp')['status']==='cp','Legacy checkbox progress lost');
check(item_progress(['type'=>'check'], '0', 'cp')['status']==='todo','Checkbox reset failed');
$plan=creator_plan(1);unset($plan['revision']);
$plan['phases']=[['key'=>'launch','name'=>'Launch','short'=>'Launch','miles'=>[
 ['key'=>'sales','t'=>'Sales','type'=>'money','target'=>'1000'],
 ['key'=>'parent','t'=>'Audience','children'=>[['key'=>'leads','t'=>'Leads','type'=>'number','target'=>'10']]],
]]];
q('UPDATE creator_plans SET definition=? WHERE creator_id=1',[json_encode($plan)]);
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>1,'month'=>'2026-10','item_key'=>'sales','value'=>'500','status'=>'cp']);
[$map]=get_statuses(1,'2026-10');
check($map['sales']==='ip','Legacy incorrect completed numeric status not corrected');
$values=tracker_values(1,'2026-10');
check(compute_progress($map,1,$values)['overall']===25,'Partial numeric progress not included in phase/overall');
[$next]=get_statuses(1,'2026-11');check($next['sales']==='ip','Carried progress not derived from evidence');
$data=dashboard_data('2026-10');$card=array_values(array_filter($data['cards'],fn($c)=>$c['id']===1))[0];check($card['overall']===25,'Dashboard progress differs from tracker');
echo "Automatic progress: zero/partial/complete, rounding, text, legacy data, inheritance and dashboard aggregation passed.\n";
