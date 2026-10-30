<?php
require __DIR__ . '/inc/bootstrap.php';
require_login(['superadmin']);
$command = 'php ' . dirname(__FILE__) . '/scripts/sync-social.php';
$last = q("SELECT v FROM meta WHERE k='social_cron_last_run'")->fetchColumn();
page_header('Social sync setup', 'account');
?>
<div class="dash-head"><div><p class="eyebrow">Social integration</p><h1>Automatic stats setup</h1><p class="sub">Connect once, then refresh every linked public account in the background.</p></div></div>
<section class="panel form">
<h3>1. Apify connection</h3><p><?= social_enabled() ? 'API token is configured on this server.' : 'Add your Apify token to config.local.php on this server.' ?></p>
<pre class="social-code">&lt;?php
return [
    'apify' =&gt; [
        'token' =&gt; 'YOUR_APIFY_TOKEN',
        'refresh_hours' =&gt; 24,
        'max_run_cost_usd' =&gt; 0.05,
    ],
];</pre>
<p class="hint">Merge these settings with your existing database configuration. The token stays on the server and is never sent to creators.</p>
<h3>2. Background schedule</h3>
<p>In Hostinger hPanel → Cron Jobs, add a custom job every five minutes (<code>*/5 * * * *</code>) with this command:</p>
<pre class="social-code" id="social-command"><?= e($command) ?></pre><div><button type="button" class="btn sm" data-copy="#social-command">Copy command</button></div>
<p class="hint">The job checks for due accounts and collects completed jobs. Each account starts a new refresh once every <?= social_interval()/3600 ?> hours. Up to 20 accounts are processed per pass. Use Hostinger's PHP executable path if the default php command is unavailable.</p>
<p>Scheduler last ran: <b><?= $last ? e($last) : 'Not yet' ?></b></p>
<h3>3. Link channels</h3><p>Open a creator tracker → Social channels → Manage channels. Select a platform and enter its handle or public profile URL.</p>
<p class="hint">Provider usage is billable. Default runs request one result and a $0.05 charge ceiling where supported by the actor's pricing model. Keep account-level spending limits enabled in Apify. Scraped public counts may be rounded or unavailable; failed refreshes retain the last successful snapshot. Monthly numbers are not overwritten.</p>
</section>
<?php page_footer(); ?>
