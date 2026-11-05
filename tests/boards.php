<?php
require __DIR__.'/custom-plans.php';require_once __DIR__.'/../inc/boards.php';
$monthly=['configured'=>true,'vision'=>'Focus on lead magnets','goals'=>[['key'=>'board_leads','title'=>'Leads','type'=>'number','target'=>'10','source'=>'manual']],'tasks'=>['audio']];
save_creator_board(2,'2026-11',$monthly,0);
save_creator_board(2,'overall',['configured'=>true,'vision'=>'Build a sustainable business','goals'=>[['key'=>'macro','title'=>'Audience','type'=>'number','target'=>'1000','current'=>'200']],'tasks'=>[]],0);
check(monthly_goal_definitions(2,'2026-11')[0]['target']==='10','Monthly goals not loaded');
check(monthly_goal_definitions(2,'2026-12')[0]['key']==='target_revenue','Month plan leaked');
check(count(monthly_path(creator_plan(2)['phases'],creator_board(2,'2026-11')))===1,'Focus phases not filtered');
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>2,'month'=>'2026-11','item_key'=>'board_leads','value'=>'6','status'=>'ip']);
check(creator_goal_progress(2,'2026-11')[0]['percent']===60,'Monthly goal progress wrong');
check(!isset(tracker_values(2,'2026-12')['board_leads']),'Monthly result carried forward');
check(creator_board(2,'overall')['goals'][0]['current']==='200','Macro goal changed');
try{save_creator_board(2,'overall',[],99);throw new RuntimeException('Stale board overwrote data');}catch(InvalidArgumentException $expected){}
check(creator_board(1,'overall')['goals']===[],'Creator board isolation broken');
echo "Boards: separate macro/monthly targets, focus filtering, carry-forward prevention, revision and ownership passed.\n";
