<?php
defined('FL') or exit;
function creator_board(int $cid,string $scope): array {
    $r=q('SELECT definition,revision FROM creator_boards WHERE creator_id=? AND scope=?',[$cid,$scope])->fetch();
    return $r ? json_decode($r['definition'],true,512,JSON_THROW_ON_ERROR)+['revision'=>(int)$r['revision']] : ['revision'=>0,'vision'=>'','goals'=>[],'tasks'=>[],'configured'=>false];
}
function save_creator_board(int $cid,string $scope,array $board,int $revision): void {
    validate_board_links($cid,$scope,$board);
    unset($board['revision']);$json=json_encode($board,JSON_THROW_ON_ERROR);
    if(!$revision){q('INSERT INTO creator_boards(creator_id,scope,definition,revision) VALUES (?,?,?,1)',[$cid,$scope,$json]);return;}
    if(!q('UPDATE creator_boards SET definition=?,revision=revision+1 WHERE creator_id=? AND scope=? AND revision=?',[$json,$cid,$scope,$revision])->rowCount())throw new InvalidArgumentException('This board changed in another tab. Refresh before saving.');
}
function monthly_goal_definitions(int $cid,string $month): array {
    $board=creator_board($cid,$month);
    return !empty($board['configured'])?$board['goals']:creator_plan($cid)['goals'];
}
function monthly_path(array $phases,array $board): array {
    if(empty($board['configured']))return $phases;
    foreach($phases as &$phase)$phase['miles']=array_values(array_filter($phase['miles'],static fn($t)=>in_array($t['key'],$board['tasks'],true)));
    unset($phase);return array_values(array_filter($phases,static fn($p)=>count($p['miles'])>0));
}

function validate_board_links(int $cid,string $scope,array $board): void {
    $overall=$scope==='overall'?$board:creator_board($cid,'overall');$known=array_column($overall['goals']??[],null,'key');
    foreach($overall['goals']??[] as $goal){$mode=$goal['rollup']??'manual';if(!in_array($mode,['manual','sum','latest'],true))throw new InvalidArgumentException('Choose a valid progress calculation.');if($mode==='sum'&&!in_array($goal['type'],['money','number'],true))throw new InvalidArgumentException('Only numeric or money goals can add monthly results.');}
    $months=$scope==='overall'?q("SELECT definition FROM creator_boards WHERE creator_id=? AND scope<>'overall'",[$cid])->fetchAll(PDO::FETCH_COLUMN):[json_encode($board)];
    foreach($months as $json){$seen=[];foreach(json_decode($json,true)['goals']??[] as $goal){$link=$goal['overall_key']??'';if(!$link)continue;if(!isset($known[$link])){if($scope==='overall')continue;throw new InvalidArgumentException('Choose an overall goal belonging to this creator.');}if($known[$link]['type']!==$goal['type'])throw new InvalidArgumentException('Linked monthly and overall goals must use the same value type.');if(isset($seen[$link]))throw new InvalidArgumentException('Link only one monthly goal to each overall goal per month to avoid double counting.');$seen[$link]=true;}}
}
/** Contributions are calculated from saved monthly results; no totals are copied or incremented. */
function overall_goal_rollups(int $cid): array {
    $overall=creator_board($cid,'overall');$out=[];
    foreach($overall['goals'] as $goal)$out[$goal['key']]=$goal+['rollup'=>'manual','contributions'=>[]];
    $boards=q("SELECT scope,definition FROM creator_boards WHERE creator_id=? AND scope<>'overall' ORDER BY scope",[$cid])->fetchAll();
    foreach($boards as $row){$month=valid_month($row['scope']);if(!$month)continue;$board=json_decode($row['definition'],true);$linked=array_filter($board['goals']??[],static fn($g)=>isset($out[$g['overall_key']??'']));if(!$linked)continue;$results=array_column(creator_goal_progress($cid,$month,get_entry($cid,$month)),null,'key');
        foreach($board['goals']??[] as $monthly){$link=$monthly['overall_key']??'';if(!isset($out[$link])||$out[$link]['type']!==$monthly['type'])continue;$result=$results[$monthly['key']]??null;if(!$result)continue;$out[$link]['contributions'][]=['month'=>$month,'title'=>$monthly['title'],'current'=>$result['current'],'target'=>$monthly['target']??'','percent'=>$result['percent']];}
    }
    foreach($out as &$goal){$mode=$goal['rollup'];$current=$goal['current']??null;$has=false;$sum=0;foreach($goal['contributions'] as $c){if($c['current']===null||$c['current']==='')continue;$has=true;$sum+=(float)(is_numeric($c['current'])?$c['current']:0);if($mode==='latest')$current=$c['current'];}if($mode==='sum'&&$has)$current=(float)(($goal['current']??null)?:0)+$sum;$goal['baseline']=$goal['current']??null;$goal['current']=$current;}
    unset($goal);return array_values($out);
}
