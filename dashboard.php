<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login(['superadmin', 'admin']);

$month = valid_month($_GET['m'] ?? '') ?? date('Y-m');
$data  = dashboard_data($month);

page_header('Dashboard', 'dashboard');
?>
<div class="dash-head">
  <div>
    <p class="eyebrow">Fluence · Creator Management</p>
    <h1>Client Dashboard</h1>
    <p class="sub">Every creator's roadmap and numbers for <b><?= e(month_label($month)) ?></b>.</p>
  </div>
  <?php month_nav($month); ?>
</div>

<section class="kpis" id="kpis" aria-label="Month summary"></section>

<div class="toolbar">
  <label class="search"><?= icon('search') ?><input type="search" id="search" placeholder="Search creators…  ( / )" aria-label="Search creators"></label>
  <div class="tb-right">
    <label class="selectwrap"><span>Sort</span>
      <select id="sort" aria-label="Sort creators">
        <option value="name">Name A–Z</option>
        <option value="progress">Progress</option>
        <option value="rev">Revenue</option>
        <option value="growth">Sub growth</option>
        <option value="updated">Recently updated</option>
      </select>
    </label>
    <div class="seg" role="group" aria-label="View">
      <button type="button" data-view="grid" title="Grid view"><?= icon('grid') ?></button>
      <button type="button" data-view="list" title="List view"><?= icon('list') ?></button>
    </div>
    <a class="btn ghost sm" href="export.php?m=<?= e($month) ?>" title="Download this month as CSV"><?= icon('download') ?><span>CSV</span></a>
    <?php if ($u['role'] === 'superadmin'): ?>
      <a class="btn primary sm" href="users.php?new=creator"><?= icon('plus') ?><span>Add creator</span></a>
    <?php endif; ?>
  </div>
</div>
<div class="chips" id="chips" role="tablist" aria-label="Filter creators"></div>

<div id="grid"></div>

<section class="feed-wrap">
  <div class="section-h"><h2><?= icon('pulse') ?>Recent activity</h2><span>Latest updates across all creators.</span></div>
  <div class="feed" id="feed"></div>
</section>

<div class="modal" id="modal" hidden><div class="mcard" id="mcard" role="dialog" aria-modal="true"></div></div>

<script>window.FL_DASH = <?= js_json([
    'month'      => $month,
    'monthLabel' => month_label($month),
    'monthName'  => month_name($month),
    'prevName'   => month_name(month_shift($month, -1)),
    'role'       => $u['role'],
    'creators'   => $data['cards'],
    'kpi'        => $data['kpi'],
    'phases'     => array_map(fn($p) => ['key' => $p['key'], 'label' => $p['short']], phases()),
    'feed'       => activity_feed(null, 14),
]) ?>;</script>
<?php page_footer(['dashboard.js']); ?>
