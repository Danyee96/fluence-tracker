<?php
require __DIR__.'/custom-plans.php';
$macro=['configured'=>true,'vision'=>'Grow','tasks'=>[],'goals'=>[
['key'=>'sales','title'=>'Sales','type'=>'number','target'=>'100','current'=>'10','rollup'=>'sum'],
['key'=>'audience','title'=>'Audience','type'=>'number','target'=>'1000','current'=>'50','rollup'=>'latest'],
]];save_creator_board(2,'overall',$macro,0);
foreach(['2026-09'=>[6,100],'2026-10'=>[4,120]] as $month=>$values){save_creator_board(2,$month,['configured'=>true,'vision'=>'','tasks'=>[],'goals'=>[
['key'=>'board_sales','title'=>'Sales this month','type'=>'number','source'=>'manual','target'=>'10','overall_key'=>'sales'],
['key'=>'target_subs','title'=>'Audience','type'=>'number','source'=>'subs','target'=>'200','overall_key'=>'audience']]],0);
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>2,'month'=>$month,'item_key'=>'board_sales','value'=>$values[0],'status'=>'ip']);upsert('monthly_entries',['creator_id','month'],['creator_id'=>2,'month'=>$month,'subscribers'=>$values[1]]);}
$r=array_column(overall_goal_rollups(2),null,'key');check($r['sales']['current']===20.0,'Additive rollup incorrect');check($r['audience']['current']===120,'Audience should use latest, not sum');check(count($r['sales']['contributions'])===2,'Contribution history missing');
check(overall_goal_rollups(1)===[],'Cross-creator rollup leaked');
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>2,'month'=>'2026-10','item_key'=>'board_sales','value'=>'2','status'=>'ip']);check(overall_goal_rollups(2)[0]['current']===18.0,'Edited result double counted');
$bad=creator_board(2,'2026-10');$bad['goals'][0]['overall_key']='unknown';try{save_creator_board(2,'2026-10',$bad,$bad['revision']);throw new RuntimeException('Unknown link accepted');}catch(InvalidArgumentException $expected){}
$bad=creator_board(2,'2026-10');$bad['goals'][0]['type']='money';try{save_creator_board(2,'2026-10',$bad,$bad['revision']);throw new RuntimeException('Incompatible link accepted');}catch(InvalidArgumentException $expected){}
echo "Goal rollups: sums, latest snapshots, contributions, edits, creator isolation and link validation passed.\n";
