<?php
require __DIR__.'/custom-plans.php';
require_once __DIR__.'/../inc/boards.php';
require __DIR__.'/../inc/creator-overview.php';
save_creator_board(2,'overall',['vision'=>'Long-term growth','goals'=>[['key'=>'macro','title'=>'Community','type'=>'number','target'=>'100','current'=>'25']],'tasks'=>[],'configured'=>true],0);
foreach(['2026-09'=>[100,50,40],'2026-10'=>[200,75,47]] as $month=>$v)upsert('monthly_entries',['creator_id','month'],['creator_id'=>2,'month'=>$month,'sponsor_rev'=>$v[0],'info_rev'=>$v[1],'subscribers'=>$v[2]]);
$o=creator_overview(2);
check($o['revenue']===425.0 && $o['months']===2,'All-time revenue incorrect');
check((int)$o['latest']['subscribers']['value']===47,'Latest audience count wrong');
check($o['goals'][0]['current']==='25' && $o['goals'][0]['percent']===25,'Overall result incorrectly uses monthly data');
check(creator_overview(1)['revenue']===0.0,'Creator totals leaked');
echo "Creator overview: all-time totals, latest results, empty data and creator isolation passed.\n";
