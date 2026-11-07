<?php
defined('FL') or exit;
function creator_resource_url(mixed $raw): string {
    $url=trim((string)$raw);if($url==='')return '';
    if(strlen($url)>2000 || !filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['https','http'],true) || parse_url($url,PHP_URL_USER)!==null)throw new InvalidArgumentException('Use a full http:// or https:// resource link without embedded login details.');
    return $url;
}
function overview_growth_series(int $cid): array {
    $series=[];
    foreach(overall_goal_rollups($cid) as $goal){if(!in_array($goal['type'],['money','number'],true))continue;$points=[];foreach($goal['contributions'] as $c)if($c['current']!==null&&$c['current']!==''&&is_numeric($c['current']))$points[]=['month'=>$c['month'],'value'=>(float)$c['current']];if($points)$series[]=['title'=>$goal['title'],'type'=>$goal['type'],'points'=>$points];}
    if(!$series){$rows=q('SELECT month,subscribers FROM monthly_entries WHERE creator_id=? AND subscribers IS NOT NULL ORDER BY month',[$cid])->fetchAll();if($rows)$series[]=['title'=>'Subscribers','type'=>'number','points'=>array_map(static fn($r)=>['month'=>$r['month'],'value'=>(float)$r['subscribers']],$rows)];}
    return $series;
}
function overview_growth_chart(array $series): void {
    $points=$series['points'];$first=$points[0];$last=$points[count($points)-1];$previous=count($points)>1?$points[count($points)-2]:null;$change=$previous?$last['value']-$previous['value']:null;
    $format=static fn(float $value)=>($series['type']==='money'?'$':'').number_format($value,$series['type']==='money'?2:0);
    $max=max(1,max(array_column($points,'value')));$span=max(1,months_between($first['month'],$last['month']));$xy=[];
    foreach($points as $p)$xy[]=[count($points)===1?300:55+months_between($first['month'],$p['month'])/$span*505,155-$p['value']/$max*120];
    ?><article class="overview-growth panel"><div class="section-h"><h3><?= e($series['title']) ?></h3><span>Monthly recorded results</span></div><div class="growth-latest"><strong><?= e($format($last['value'])) ?></strong><?php if($change!==null):?><small class="<?= $change>=0?'up':'down' ?>"><?= $change>0?'+':'' ?><?= e($format($change)) ?> since <?= e(month_label($previous['month'])) ?></small><?php endif;?></div>
<svg viewBox="0 0 600 195" role="img" aria-label="<?= e($series['title']) ?> monthly results. Detailed values are listed below." class="overview-growth-svg">
<?php for($i=0;$i<3;$i++):$y=35+60*$i;?><line x1="55" y1="<?= $y ?>" x2="560" y2="<?= $y ?>" class="growth-grid"/><text x="48" y="<?= $y+4 ?>" text-anchor="end" class="growth-axis"><?= e(number_format($max*(1-$i/2),$max<10?1:0)) ?></text><?php endfor;?>
<?php foreach($xy as $i=>$p):if($i&&months_between($points[$i-1]['month'],$points[$i]['month'])===1):?><line x1="<?= $xy[$i-1][0] ?>" y1="<?= $xy[$i-1][1] ?>" x2="<?= $p[0] ?>" y2="<?= $p[1] ?>" class="growth-line"/><?php endif;?><circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4" class="growth-point"><title><?= e(month_label($points[$i]['month']).': '.$format($points[$i]['value'])) ?></title></circle><?php endforeach;?>
<text x="55" y="182" class="growth-axis"><?= e(month_label($first['month'])) ?></text><?php if(count($points)>1):?><text x="560" y="182" text-anchor="end" class="growth-axis"><?= e(month_label($last['month'])) ?></text><?php endif;?></svg>
<details class="growth-values"><summary>View monthly values</summary><table class="mtable"><thead><tr><th>Month</th><th>Result</th></tr></thead><tbody><?php foreach(array_reverse($points) as $p):?><tr><td><?= e(month_label($p['month'])) ?></td><td><?= e($format($p['value'])) ?></td></tr><?php endforeach;?></tbody></table></details><?php if(count($points)===1):?><p class="hint">Add another month’s result to see the trend.</p><?php endif;?></article><?php
}
