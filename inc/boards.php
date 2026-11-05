<?php
defined('FL') or exit;
function creator_board(int $cid,string $scope): array {
    $r=q('SELECT definition,revision FROM creator_boards WHERE creator_id=? AND scope=?',[$cid,$scope])->fetch();
    return $r ? json_decode($r['definition'],true,512,JSON_THROW_ON_ERROR)+['revision'=>(int)$r['revision']] : ['revision'=>0,'vision'=>'','goals'=>[],'tasks'=>[],'configured'=>false];
}
function save_creator_board(int $cid,string $scope,array $board,int $revision): void {
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
