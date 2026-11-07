<?php
require __DIR__.'/custom-plans.php';require __DIR__.'/../inc/overview-visuals.php';
check(creator_resource_url('https://example.com/lead-magnet')==='https://example.com/lead-magnet','Resource URL changed');check(creator_resource_url('')==='','Optional URL rejected');
foreach(['javascript:alert(1)','data:text/html,test','https://user:pass@example.com','not a link'] as $url){try{creator_resource_url($url);throw new RuntimeException('Unsafe URL accepted');}catch(InvalidArgumentException $expected){}}
$macro=['vision'=>'','configured'=>true,'tasks'=>[],'goals'=>[['key'=>'lead','title'=>'Lead magnet subscribers','type'=>'number','target'=>'200','current'=>'0','rollup'=>'latest','resource_url'=>'https://example.com/lead']]];save_creator_board(2,'overall',$macro,0);
foreach(['2026-08'=>50,'2026-10'=>100] as $month=>$value){save_creator_board(2,$month,['vision'=>'','configured'=>true,'tasks'=>[],'goals'=>[['key'=>'board_lead','title'=>'Lead subscribers','source'=>'manual','type'=>'number','target'=>'200','overall_key'=>'lead']]],0);upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>2,'month'=>$month,'item_key'=>'board_lead','value'=>$value,'status'=>'ip']);}
$series=overview_growth_series(2);check(count($series)===1&&$series[0]['points'][1]['value']===100.0,'Linked monthly history wrong');check(overview_growth_series(1)===[],'Other creator history leaked');
ob_start();overview_growth_chart($series[0]);$html=ob_get_clean();check(!str_contains($html,'class="growth-line"'),'Missing month joined as continuous data');check(str_contains($html,'View monthly values')&&str_contains($html,'100'),'Accessible chart values missing');
q('DELETE FROM tracker_values WHERE creator_id=2 AND month=?',['2026-08']);$series=overview_growth_series(2);check(count($series[0]['points'])===1,'Missing value converted to zero');
echo "Overview visuals: resource validation, linked history, isolation, missing-month gaps and accessible chart values passed.\n";
