<?php
defined('FL') or exit;

function social_card(array $account, bool $manage = false): void
{
    $platform = SOCIAL_PLATFORMS[$account['platform']];
    $stats = $account['latest'];
    $status = !social_enabled() ? 'Setup required' : (!$account['is_active'] ? 'Paused' : match ($account['sync_status']) { 'syncing'=>'Updating…', 'ready'=>'Synced', 'error'=>'Needs attention', default=>'Awaiting first sync' });
    ?>
    <article class="social-card panel" data-social-id="<?= (int)$account['id'] ?>">
      <div class="social-card-head"><span class="social-platform platform-<?= e($account['platform']) ?>"><?= e($platform['label']) ?></span><span class="social-status"><?= e($status) ?></span></div>
      <a class="social-handle" href="<?= e($account['profile_url']) ?>" target="_blank" rel="noopener noreferrer">@<?= e($account['handle']) ?> <?= icon('external') ?></a>
      <?php if ($account['display_name']): ?><p class="hint"><?= e($account['display_name']) ?></p><?php endif; ?>
      <div class="social-main"><b><?= $stats ? number_format((int)$stats['followers']) : '—' ?></b><span><?= e($platform['metric']) ?></span></div>
      <?php if ($account['growth'] !== null): ?><p class="delta <?= $account['growth'] >= 0 ? 'up' : 'down' ?>"><?= $account['growth'] > 0 ? '+' : '' ?><?= number_format($account['growth']) ?> since <?= e(date('M j', strtotime($account['history'][0]['snapshot_date']))) ?></p><?php endif; ?>
      <?php if ($stats): ?><div class="social-secondary"><?php foreach (['posts'=>'Posts / videos','likes'=>'Total likes','views'=>'Channel views'] as $key=>$label): if ($stats[$key] === null) continue; ?><span><?= e($label) ?><b><?= number_format((int)$stats[$key]) ?></b></span><?php endforeach; ?></div><?php endif; ?>
      <?php if (count($account['history']) >= 2): ?><div class="social-spark" data-values="<?= e(json_encode(array_column($account['history'], 'followers'))) ?>"></div><?php endif; ?>
      <p class="hint">Last updated <?= $account['last_synced'] ? e(time_ago($account['last_synced'])) : 'never' ?><?= $account['last_synced'] && strtotime($account['last_synced']) < time()-social_interval()-3600 ? ' · Stats may be outdated' : '' ?></p>
      <?php if (!social_enabled()): ?><p class="social-error">Apify is not configured on this server. Ask your admin to add its token in config.local.php.</p><?php endif; ?>
      <?php if (!$manage && $account['is_active'] && social_enabled()): ?><button type="button" class="btn sm ghost" data-social-sync="<?= (int)$account['id'] ?>">Sync now</button><?php endif; ?>
      <?php if ($account['last_error']): ?><p class="social-error" role="status"><?= e($account['last_error']) ?></p><?php endif; ?>
      <?php if ($manage): ?><div class="social-controls">
      <?php foreach (['refresh'=>'Refresh stats', $account['is_active'] ? 'pause' : 'resume'=>$account['is_active'] ? 'Pause' : 'Resume', 'remove'=>'Remove'] as $action=>$label): ?>
      <form method="post" <?= $action === 'remove' ? 'onsubmit="return confirm(\'Remove this channel and its saved stats?\')"' : '' ?>><?= csrf_field() ?><input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>"><input type="hidden" name="act" value="<?= $action ?>"><button class="btn sm <?= $action === 'remove' ? 'danger' : '' ?>" type="submit" <?= $action === 'refresh' && (!$account['is_active'] || !social_enabled()) ? 'disabled' : '' ?>><?= e($label) ?></button></form>
      <?php endforeach; ?></div><?php endif; ?>
      <?php if ($manage && $account['history']): ?><details class="social-history"><summary>Stats history</summary><div class="mtable-wrap"><table class="mtable"><thead><tr><th>Date</th><th><?= e($platform['metric']) ?></th></tr></thead><tbody><?php foreach (array_reverse($account['history']) as $day): ?><tr><td><?= e($day['snapshot_date']) ?></td><td><?= number_format((int)$day['followers']) ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
    </article>
    <?php
}
