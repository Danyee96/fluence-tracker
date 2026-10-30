<?php
defined('FL') or exit;

function page_header(string $title, string $active = '', bool $bare = false): void
{
    global $CONFIG;
    $u = $bare ? null : current_user();
    $app = $CONFIG['app_name'] ?? 'Fluence';
    $v = FL_VERSION;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="theme-color" content="#0a0c11">
<title><?= e($title) ?> · <?= e($app) ?></title>
<link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=<?= $v ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/app.css?v=<?= $v ?>">
</head>
<body class="<?= $bare ? 'bare' : '' ?>">
<?php if ($u): ?>
<header class="nav">
  <div class="nav-in">
    <a class="brand" href="index.php"><img class="brand-logo" src="assets/fluence-logo.svg?v=<?= $v ?>" alt="<?= e($app) ?>" width="2048" height="309"></a>
    <nav class="links">
      <?php if (is_staff($u)): ?>
        <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'on' : '' ?>"><?= icon('grid') ?><span>Dashboard</span></a>
      <?php else: ?>
        <a href="creator.php" class="<?= $active === 'tracker' ? 'on' : '' ?>"><?= icon('target') ?><span>My Tracker</span></a>
      <?php endif; ?>
      <?php if ($u['role'] === 'superadmin'): ?>
        <a href="users.php" class="<?= $active === 'users' ? 'on' : '' ?>"><?= icon('users') ?><span>Users</span></a>
      <?php endif; ?>
      <?php if (assistant_local()): ?><a href="assistant.php" class="<?= $active === 'assistant' ? 'on' : '' ?>"><?= icon('chat') ?><span>Assistant</span></a><?php endif; ?>
    </nav>
    <div class="me">
      <a class="me-link <?= $active === 'account' ? 'on' : '' ?>" href="account.php" title="Your account">
        <?= avatar($u, 'sm') ?>
        <span class="me-txt"><b><?= e($u['name']) ?></b><small><?= e(role_label($u['role'])) ?></small></span>
      </a>
      <a class="iconbtn" href="logout.php" title="Log out" aria-label="Log out"><?= icon('logout') ?></a>
    </div>
  </div>
</header>
<?php endif; ?>
<main class="page"><div class="in">
<?php if ($u && !empty($_SESSION['impersonation'])): ?>
<div class="impersonation-banner" role="status"><span><?= icon('eye') ?>You are logged in as <strong><?= e($u['name']) ?></strong>.</span><form method="post" action="impersonate.php"><?= csrf_field() ?><input type="hidden" name="action" value="stop"><button class="btn sm" type="submit"><?= icon('back') ?>Return to admin account</button></form></div>
<?php endif; ?>
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'err' ? 'x' : 'check') ?><span><?= e($f['msg']) ?></span></div>
<?php endif;
}

function page_footer(array $scripts = []): void
{
    $v = FL_VERSION;
    ?>
</div></main>
<?php if (assistant_local() && current_user() && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'assistant.php'): ?>
<a class="assistant-launcher" href="assistant.php" aria-label="Open Grok assistant" title="Ask Grok"><?= icon('chat') ?><span>Ask Grok</span></a>
<?php endif; ?>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script>window.FL_ICONS = <?= js_json(icon_paths()) ?>;</script>
<script src="assets/app.js?v=<?= $v ?>"></script>
<?php foreach ($scripts as $s): ?>
<script src="assets/<?= e($s) ?>?v=<?= $v ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

/** ‹ Month › switcher. $params = other query params to keep (e.g. ['id'=>5]). */
function month_nav(string $month, array $params = []): void
{
    $base = $params ? http_build_query($params) . '&' : '';
    $isNow = $month === date('Y-m');
    ?>
<form class="monthnav" method="get">
  <?php foreach ($params as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <a class="mbtn" href="?<?= e($base) ?>m=<?= e(month_shift($month, -1)) ?>" aria-label="Previous month" data-key="ArrowLeft">‹</a>
  <input type="hidden" name="m" value="<?= e($month) ?>">
  <div class="monthpick-wrap">
    <button class="mpick" type="button" aria-label="Pick month" aria-haspopup="dialog" aria-expanded="false">
      <?= icon('calendar') ?><span><?= e(month_label($month)) ?></span>
    </button>
  </div>
  <a class="mbtn" href="?<?= e($base) ?>m=<?= e(month_shift($month, 1)) ?>" aria-label="Next month" data-key="ArrowRight">›</a>
  <?php if (!$isNow): ?><a class="mtoday" href="?<?= e($base) ?>m=<?= e(date('Y-m')) ?>">Jump to this month</a><?php endif; ?>
</form>
<?php
}
