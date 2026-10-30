<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();

$cid = $u['role'] === 'creator' ? (int)$u['id'] : (int)($_GET['id'] ?? 0);
$creator = q("SELECT id, name, email, is_active, handle, niche, channel_url, start_month FROM users WHERE id=? AND role='creator'", [$cid])->fetch();
if (!$creator || !can_view_creator($u, $cid)) {
    http_response_code(404);
    page_header('Not found');
    echo '<div class="empty-state">' . icon('user') . '<h2>Creator not found</h2><p>They may have been removed.</p><a class="btn" href="index.php">Back</a></div>';
    page_footer();
    exit;
}

$month   = valid_month($_GET['m'] ?? '') ?? date('Y-m');
$canEdit = can_edit_creator($u, $cid);
$isSelf  = (int)$u['id'] === $cid;
$plan = creator_plan($cid);
$meetingError = meeting_post($u, $cid, $month);

[$statuses, $inheritedFrom] = get_statuses($cid, $month);
$notes = [];
foreach (q('SELECT phase_key, notes FROM phase_notes WHERE creator_id=? AND month=?', [$cid, $month])->fetchAll() as $r) {
    $notes[$r['phase_key']] = $r['notes'];
}
$entryRow = get_entry($cid, $month);
$entryMeta = null;
if ($entryRow && $entryRow['updated_at']) {
    $by = $entryRow['updated_by'] ? q('SELECT name FROM users WHERE id=?', [$entryRow['updated_by']])->fetchColumn() : null;
    $entryMeta = ['by' => $by ?: '—', 'ago' => time_ago($entryRow['updated_at'])];
}
$prevRow = q('SELECT * FROM monthly_entries WHERE creator_id=? AND month<? ORDER BY month DESC LIMIT 1', [$cid, $month])->fetch() ?: null;

$from = month_shift($month, -11);
$hist = q('SELECT * FROM monthly_entries WHERE creator_id=? AND month BETWEEN ? AND ? ORDER BY month', [$cid, $from, $month])->fetchAll();
$history = array_map(fn($r) => entry_js($r) + ['m' => month_label($r['month'], true), 'label' => month_label($r['month'])], $hist);

$programMonth = $creator['start_month'] ? months_between($creator['start_month'], $month) + 1 : null;

$data = [
    'cid'           => $cid,
    'month'         => $month,
    'monthLabel'    => month_label($month),
    'monthName'     => month_name($month),
    'canEdit'       => $canEdit,
    'me'            => ['id' => (int)$u['id'], 'role' => $u['role'], 'name' => $u['name']],
    'isSelf'        => $isSelf,
    'phases'        => array_map(fn($p, $i) => array_replace($p, ['num' => 'PHASE ' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)]), $plan['phases'], array_keys($plan['phases'])),
    'statuses'      => (object)$statuses,
    'inheritedFrom' => $inheritedFrom ? month_label($inheritedFrom) : null,
    'notes'         => (object)$notes,
    'goalDefinitions' => $plan['goals'],
    'values' => (object)tracker_values($cid, $month),
    'canRespond' => $canEdit || is_staff($u),
    'entry'         => entry_js($entryRow),
    'entryMeta'     => $entryMeta,
    'prev'          => $prevRow ? entry_js($prevRow) + ['label' => month_label($prevRow['month']), 'name' => month_name($prevRow['month'])] : null,
    'history'       => $history,
    'feedback'      => feedback_list($cid, $month, $u),
    'activity'      => activity_feed($cid, 8),
];

page_header($creator['name'], $isSelf ? 'tracker' : 'dashboard');
?>
<?php if (is_staff($u)): ?>
  <a class="back" href="dashboard.php?m=<?= e($month) ?>"><?= icon('back') ?>All creators</a>
<?php endif; ?>

<section class="hero">
  <div class="hero-main">
    <?= avatar($creator, 'xl') ?>
    <div class="hero-txt">
      <p class="eyebrow">Fluence · Client Roadmap</p>
      <h1><?= e($creator['name']) ?></h1>
      <div class="meta-line">
        <?php if ($creator['handle']): ?><span><?= e($creator['handle']) ?></span><?php endif; ?>
        <?php if ($creator['niche']): ?><span><?= e($creator['niche']) ?></span><?php endif; ?>
        <?php if ($creator['channel_url']): ?><a href="<?= e($creator['channel_url']) ?>" target="_blank" rel="noopener">Channel <?= icon('external') ?></a><?php endif; ?>
        <?php if ($programMonth && $programMonth > 0): ?><span class="pm">Month <?= (int)$programMonth ?> of program</span><?php endif; ?>
        <?php if (!(int)$creator['is_active']): ?><span class="tag dis">Disabled</span><?php endif; ?>
      </div>
      <?php month_nav($month, $isSelf ? [] : ['id' => $cid]); ?>
    </div>
  </div>
  <div class="hero-ring" id="ring"></div>
</section>

<nav class="subnav" id="subnav">
  <div class="subnav-in">
    <a href="#overview" class="on">Overview</a>
    <a href="#numbers">Numbers</a>
    <a href="#path">Path</a>
    <a href="#feedback">Feedback<span class="cnt" id="fbcount"></span></a>
    <a href="#trends">Trends</a>
    <a href="#meetings">Meetings</a>
    <span class="savestate" id="savestate" aria-live="polite"></span>
  </div>
</nav>

<?php if (!$canEdit): ?>
  <div class="note-bar"><?= icon('eye') ?><span>View only — <?= $u['role'] === 'admin' ? 'monthly numbers and notes are view-only. You can manage the plan, update task results, and leave feedback.' : 'you can\'t edit this tracker.' ?></span></div>
<?php endif; ?>

<?php if (is_staff($u)): ?>
<div class="plan-toolbar"><a class="btn primary" href="planner.php?id=<?= $cid ?>&m=<?= e($month) ?>"><?= icon('edit') ?>Edit destinations &amp; path</a></div>
<?php endif; ?>
<section class="sec" id="social"><div class="section-h"><h2>Social channels</h2><a class="btn sm" href="social.php?id=<?= $cid ?>">Manage channels</a></div>
<div class="social-grid" id="social-channels" data-cid="<?= $cid ?>" data-manage="0"><?php $channels = social_accounts($cid); foreach ($channels as $channel) social_card($channel); if (!$channels): ?><div class="empty small">Link your public channels to track followers and subscribers automatically.</div><?php endif; ?></div></section>
<section id="overview" class="sec">
  <div class="section-h"><h2>The Destination</h2><span>Your goals and results for <?= e(month_label($month)) ?>.</span></div>
  <div class="kpis tiles" id="goals"></div>
</section>

<section id="numbers" class="sec">
  <div class="section-h"><h2><?= e(month_name($month)) ?> Numbers</h2><span id="entrymeta"></span><span class="sh-actions" id="entryactions"></span></div>
  <div class="entry" id="entry"></div>
</section>

<section id="path" class="sec">
  <div class="section-h"><h2>The Path</h2><span><?= $canEdit ? 'Enter results to update progress automatically. Expand a phase to see details.' : 'Step status for this month.' ?></span></div>
  <div class="note-bar info" id="inherit" hidden></div>
  <div class="stepper" id="stepper"></div>
  <div id="phases"></div>
</section>

<section id="feedback" class="sec">
  <div class="section-h"><h2>Feedback</h2><span>Notes between <?= $isSelf ? 'you' : e(explode(' ', $creator['name'])[0]) ?> and the Fluence team for <?= e(month_label($month)) ?>.</span></div>
  <div class="thread" id="thread"></div>
</section>

<section id="trends" class="sec">
  <div class="section-h"><h2>Trends</h2><span>Last 12 months up to <?= e(month_label($month)) ?>.</span></div>
  <div class="charts" id="charts"></div>
  <div class="split">
    <div class="mtable-wrap"><table class="mtable" id="mtable"></table></div>
    <div class="panel feed-mini"><h3><?= icon('pulse') ?>Recent activity</h3><div class="feed" id="feed"></div></div>
  </div>
</section>

<?php meeting_section($u, $cid, $month, $meetingError); ?>

<script>window.FL = <?= js_json($data) ?>;</script>
<?php page_footer(['tracker.js', 'social.js']); ?>
