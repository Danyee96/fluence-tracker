<?php
require __DIR__ . '/automatic-progress.php';
upsert('monthly_entries',['creator_id','month'],['creator_id'=>1,'month'=>'2026-10','sponsor_rev'=>2500,'info_rev'=>2500,'subscribers'=>50000,'income_streams'=>1]);
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>1,'month'=>'2026-10','item_key'=>'budget','value'=>'250','status'=>'cp']);
$data=dashboard_data('2026-10');$cards=array_column($data['cards'],null,'id');$goals=array_column($cards[1]['goals'],null,'key');
check($goals['target_revenue']['current']===5000.0 && $goals['target_revenue']['percent']===50,'Revenue goal does not use total revenue');
check($goals['target_subs']['percent']===50 && $goals['target_streams']['percent']===33,'Linked metric progress incorrect');
check($goals['budget']['percent']===50 && $goals['budget']['status']==='ip','Custom goal trusted old manual status');
check(!in_array('budget',array_column($cards[2]['goals'],'key'),true),'Creator goals leaked');
$data=dashboard_data('2026-11');$card=array_values(array_filter($data['cards'],fn($c)=>$c['id']===1))[0];$goals=array_column($card['goals'],null,'key');
check($goals['target_revenue']['current']===null && $goals['budget']['current']===null,'Monthly goal results carried into wrong month');
check($goals['target_revenue']['status']==='todo','Missing entry shown as completed');
$plan=creator_plan(1);unset($plan['revision']);$plan['goals'][]=['key'=>'text','title'=>'Positioning','type'=>'text','target'=>'Ready','source'=>'manual'];$plan['goals'][]=['key'=>'check','title'=>'Publish','type'=>'check','target'=>'','source'=>'manual'];
q('UPDATE creator_plans SET definition=? WHERE creator_id=1',[json_encode($plan)]);
foreach (['text'=>'Ready','check'=>'1'] as $key=>$value)upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>1,'month'=>'2026-10','item_key'=>$key,'value'=>$value,'status'=>'todo']);
$entry=get_entry(1,'2026-10');$goals=array_column(creator_goal_progress(1,'2026-10',$entry),null,'key');check($goals['text']['percent']===100 && $goals['check']['percent']===100,'Text or checkbox dashboard goals incorrect');
$plan['goals']=[];q('UPDATE creator_plans SET definition=? WHERE creator_id=1',[json_encode($plan)]);check(creator_goal_progress(1,'2026-10',$entry)===[],'Empty goal state failed');
echo "Dashboard goals: linked metrics, custom values, status derivation, selected months, creator isolation and empty state passed.\n";
