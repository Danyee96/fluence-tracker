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
    $board=function_exists('creator_board')?creator_board($cid,'overall'):['vision'=>'','goals'=>[]];
    foreach(overall_goal_rollups($cid) as $goal){$p=item_progress($goal,$goal['current']??null);$goals[]=$goal+['current'=>null,'as_of'=>null,'percent'=>progress_percent($p['percent'],$p['status']==='cp')];}
    $meeting=q('SELECT COUNT(*) AS total,MAX(meeting_date) AS latest FROM creator_meetings WHERE creator_id=?',[$cid])->fetch();
    return ['vision'=>$board['vision'],'revenue'=>$sponsor+$info,'sponsor'=>$sponsor,'info'=>$info,'months'=>count($rows),'latest'=>$latest,'goals'=>$goals,'progress'=>compute_progress($statuses,$cid,$values),'meetings'=>$meeting];
}
function creator_overview_section(int $cid): void {
    $o=creator_overview($cid);
    $audienceLabel='Latest subscribers';foreach($o['goals'] as $goal)if(($goal['source']??'')==='subs')$audienceLabel=$goal['title'];
    ?>
<section id="alltime" class="sec creator-overview"><div class="section-h"><h2>Overall vision</h2><span>Long-term goals and the full path</span></div>
<?php if($o['vision']):?><p class="macro-vision"><?= nl2br(e($o['vision'])) ?></p><?php endif;?>
<div class="overview-summary-grid">
<div class="kpi"><span class="kpi-l">All-time revenue</span><strong class="kpi-v">$<?= number_format($o['revenue'],2) ?></strong><small><?= $o['months'] ?> recorded months · Sponsor $<?= number_format($o['sponsor'],2) ?> · Info $<?= number_format($o['info'],2) ?></small></div>
<div class="kpi"><span class="kpi-l"><?= e($audienceLabel) ?></span><strong class="kpi-v"><?= isset($o['latest']['subscribers'])?number_format((float)$o['latest']['subscribers']['value']):'—' ?></strong><small><?= isset($o['latest']['subscribers'])?'Recorded '.e(month_label($o['latest']['subscribers']['month'])):'No audience result recorded' ?></small></div>
<div class="kpi"><span class="kpi-l">Path progress</span><strong class="kpi-v"><?= $o['progress']['overall'] ?>%</strong><small><?= e($o['progress']['current']['label']) ?></small></div>
<div class="kpi"><span class="kpi-l">Meetings logged</span><strong class="kpi-v"><?= (int)$o['meetings']['total'] ?></strong><small>Latest: <?= e($o['meetings']['latest']??'Not logged') ?></small></div>
</div><div class="overview-goals">
<?php foreach($o['goals'] as $g): $format=static fn($v)=>$v===null||$v===''?'—':($g['type']==='money'?'$':'').(in_array($g['type'],['money','number'],true)?number_format((float)$v,$g['type']==='money'?2:0):($g['type']==='check'?($v==='1'?'Complete':'To do'):(string)$v)); ?>
<div class="overview-goal"><b><?= e($g['title']) ?></b><span><?= e($format($g['current'])) ?> <small>/ <?= e($format($g['target']??null)) ?></small></span><div class="gbar"><i style="width:<?= $g['percent'] ?>%"></i></div><small><?= $g['percent'] ?>% of overall goal · <?= e(['manual'=>'Manual result','sum'=>'Starting balance + monthly results','latest'=>'Latest monthly result'][$g['rollup']??'manual']) ?></small>
<?php if(!empty($g['contributions'])):?><details class="goal-contributions"><summary><?= count($g['contributions']) ?> linked monthly goals</summary><?php foreach(array_reverse($g['contributions']) as $contribution):?><a href="creator.php?id=<?= $cid ?>&view=monthly&m=<?= e($contribution['month']) ?>"><span><?= e(month_label($contribution['month'])) ?> · <?= e($contribution['title']) ?></span><strong><?= e($format($contribution['current'])) ?> / <?= e($format($contribution['target'])) ?></strong></a><?php endforeach;?></details><?php endif;?></div>
<?php endforeach; ?>
<?php if(!$o['goals']):?><p class="hint">No overall goals set yet. An admin can add long-term goals using Edit overall vision.</p><?php endif;?>
</div><div class="section-h"><h2>The full path</h2><span>Every phase · overall progress</span></div><div class="macro-phases">
<?php foreach(creator_plan($cid)['phases'] as $i=>$phase):$progress=$o['progress']['phases'][$i];?>
<div class="overview-goal"><small>Phase <?= $i+1 ?></small><b><?= e($phase['name']) ?></b><span><?= $progress['pct'] ?>%</span><div class="gbar"><i style="width:<?= $progress['pct'] ?>%"></i></div><?php foreach($phase['miles'] as $task):?><small><?= e($task['t']) ?></small><?php endforeach;?></div>
<?php endforeach;?></div><p class="hint">Revenue totals cover all recorded months. Overall goals are maintained separately from monthly targets. Path results carry forward as work is completed.</p></section>
<?php }
