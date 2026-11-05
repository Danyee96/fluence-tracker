<?php
defined('FL') or exit;
/** Cross-month summary: never accepts a selected month. */
function creator_overview(int $cid): array {
    $rows=q('SELECT * FROM monthly_entries WHERE creator_id=? ORDER BY month DESC',[$cid])->fetchAll();
    $revenue=0.0;$sponsor=0.0;$info=0.0;$latest=[];
    foreach($rows as $row){$sponsor+=(float)$row['sponsor_rev'];$info+=(float)$row['info_rev'];foreach(['subscribers','income_streams'] as $field)if(!isset($latest[$field])&&$row[$field]!==null)$latest[$field]=['value'=>$row[$field],'month'=>$row['month']];}
    $month=date('Y-m');
    foreach(['monthly_entries','tracker_values','milestone_status'] as $table){$last=q("SELECT MAX(month) FROM $table WHERE creator_id=?",[$cid])->fetchColumn();if($last && $last>$month)$month=$last;}
    [$statuses]=get_statuses($cid,$month);
    $values=tracker_values($cid,$month);$goals=[];
    foreach(creator_plan($cid)['goals'] as $goal){
        $source=$goal['source']??'manual';$result=null;$asOf=null;
        if($source==='revenue' && $rows){$result=(float)$rows[0]['sponsor_rev']+(float)$rows[0]['info_rev'];$asOf=$rows[0]['month'];}
        elseif(in_array($source,['subs','streams'],true)){$entry=$latest[$source==='subs'?'subscribers':'income_streams']??null;$result=$entry['value']??null;$asOf=$entry['month']??null;}
        elseif($source==='manual'){$entry=q('SELECT value,month FROM tracker_values WHERE creator_id=? AND item_key=? ORDER BY month DESC LIMIT 1',[$cid,$goal['key']])->fetch();$result=$entry['value']??null;$asOf=$entry['month']??null;}
        $p=item_progress($goal,$result);$goals[]=$goal+['current'=>$result,'as_of'=>$asOf,'percent'=>progress_percent($p['percent'],$p['status']==='cp')];
    }
    $meeting=q('SELECT COUNT(*) AS total,MAX(meeting_date) AS latest FROM creator_meetings WHERE creator_id=?',[$cid])->fetch();
    return ['revenue'=>$sponsor+$info,'sponsor'=>$sponsor,'info'=>$info,'months'=>count($rows),'latest'=>$latest,'goals'=>$goals,'progress'=>compute_progress($statuses,$cid,$values),'meetings'=>$meeting];
}
function creator_overview_section(int $cid): void {
    $o=creator_overview($cid);
    $audienceLabel='Latest subscribers';foreach($o['goals'] as $goal)if(($goal['source']??'')==='subs')$audienceLabel=$goal['title'];
    ?>
<section id="alltime" class="sec creator-overview"><div class="section-h"><h2>Creator overview</h2><span>All recorded history · independent of the month selector</span></div>
<div class="overview-summary-grid">
<div class="kpi"><span class="kpi-l">All-time revenue</span><strong class="kpi-v">$<?= number_format($o['revenue'],2) ?></strong><small><?= $o['months'] ?> recorded months · Sponsor $<?= number_format($o['sponsor'],2) ?> · Info $<?= number_format($o['info'],2) ?></small></div>
<div class="kpi"><span class="kpi-l"><?= e($audienceLabel) ?></span><strong class="kpi-v"><?= isset($o['latest']['subscribers'])?number_format((float)$o['latest']['subscribers']['value']):'—' ?></strong><small><?= isset($o['latest']['subscribers'])?'Recorded '.e(month_label($o['latest']['subscribers']['month'])):'No audience result recorded' ?></small></div>
<div class="kpi"><span class="kpi-l">Path progress</span><strong class="kpi-v"><?= $o['progress']['overall'] ?>%</strong><small><?= e($o['progress']['current']['label']) ?></small></div>
<div class="kpi"><span class="kpi-l">Meetings logged</span><strong class="kpi-v"><?= (int)$o['meetings']['total'] ?></strong><small>Latest: <?= e($o['meetings']['latest']??'Not logged') ?></small></div>
</div><div class="overview-goals">
<?php foreach($o['goals'] as $g): $format=static fn($v)=>$v===null||$v===''?'—':($g['type']==='money'?'$':'').(in_array($g['type'],['money','number'],true)?number_format((float)$v,$g['type']==='money'?2:0):($g['type']==='check'?($v==='1'?'Complete':'To do'):(string)$v)); ?>
<div class="overview-goal"><b><?= e($g['title']) ?></b><span><?= e($format($g['current'])) ?> <small>/ <?= e($format($g['target']??null)) ?></small></span><div class="gbar"><i style="width:<?= $g['percent'] ?>%"></i></div><small><?= $g['percent'] ?>% · <?= $g['as_of']?'Latest result: '.e(month_label($g['as_of'])):'No result recorded' ?></small></div>
<?php endforeach; ?>
</div><p class="hint">Goal targets use each goal’s latest recorded result. Revenue above is the sum of all recorded monthly entries; missing months are not estimated.</p></section>
<?php }
