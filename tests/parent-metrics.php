<?php
require __DIR__.'/custom-plans.php';
$plan=creator_plan(1);unset($plan['revision']);
$plan['phases']=[['key'=>'launch','name'=>'Launch','short'=>'Launch','miles'=>[['key'=>'sale','t'=>'Halloween sale','type'=>'number','target'=>'10','children'=>[['key'=>'promo','t'=>'Email promo','type'=>'check']]]]]];
q('UPDATE creator_plans SET definition=? WHERE creator_id=1',[json_encode($plan)]);
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>1,'month'=>'2026-10','item_key'=>'sale','value'=>'6','status'=>'cp']);
[$statuses]=get_statuses(1,'2026-10');$progress=compute_progress($statuses,1,tracker_values(1,'2026-10'));
check($progress['overall']===60,'Parent target should track 6/10 independently of subtasks');
check(task_has_metric(plan_item(1,'sale')),'Parent metric not recognized');
check(!task_has_metric(['type'=>'check','target'=>'1']),'Checkbox parent incorrectly overrides subtasks');
check(task_progress_items([['key'=>'parent','children'=>[['key'=>'child']]]])[0]['key']==='child','Checklist inheritance changed');
echo "Parent metrics: numeric parent results, child independence and legacy checklist aggregation passed.\n";
