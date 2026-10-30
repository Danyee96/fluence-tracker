<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$cid = $u['role'] === 'creator' ? (int)$u['id'] : (int)($_GET['id'] ?? 0);
$creator = q("SELECT id, name FROM users WHERE id=? AND role='creator'", [$cid])->fetch();
if (!$creator || !can_manage_social($u, $cid)) { http_response_code(404); exit('Creator not found.'); }
$back = 'social.php?id=' . $cid;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = (string)($_POST['act'] ?? '');
        if ($action === 'add') {
            $platform = (string)($_POST['platform'] ?? '');
            $identity = social_identity($platform, (string)($_POST['handle'] ?? ''));
            $existing=q('SELECT * FROM social_accounts WHERE creator_id=? AND platform=? AND handle=?', [$cid, $platform, $identity['handle']])->fetch();
            if ($existing && $existing['sync_status']!=='archived') throw new InvalidArgumentException('This channel is already linked.');
            if ($existing) {
                if ($existing['handle']!==$identity['handle']) throw new InvalidArgumentException('Check the exact capitalization of this channel ID.');
                $id=(int)$existing['id'];
                q("UPDATE social_accounts SET is_active=1,sync_status='pending',next_sync=?,run_id=NULL WHERE id=?",[now(),$id]);
            } else {
                q('INSERT INTO social_accounts (creator_id, platform, handle, profile_url, created_at) VALUES (?,?,?,?,?)', [$cid, $platform, $identity['handle'], $identity['profile_url'], now()]);
                $id = (int)db()->lastInsertId();
            }
            if (social_enabled()) social_start($id);
            log_activity($cid, (int)$u['id'], 'social', null, 'Linked ' . SOCIAL_PLATFORMS[$platform]['label'] . ' @' . $identity['handle']);
            flash('Channel linked. Its first stats sync is queued.');
        } else {
            $id = (int)($_POST['account_id'] ?? 0);
            $account = q("SELECT * FROM social_accounts WHERE id=? AND creator_id=? AND sync_status<>'archived'", [$id, $cid])->fetch();
            if (!$account) throw new InvalidArgumentException('Channel not found.');
            if ($action === 'refresh') {
                if (!social_enabled()) throw new InvalidArgumentException('Stats syncing is not configured yet. Ask your admin to connect Apify.');
                if (!$account['is_active']) throw new InvalidArgumentException('Resume this channel before refreshing.');
                if ($account['run_id']) social_collect($id);
                else social_start($id);
                flash('Sync requested. Refreshes are limited to once per hour per channel.');
            } elseif ($action === 'pause' || $action === 'resume') {
                q('UPDATE social_accounts SET is_active=? WHERE id=?', [$action === 'resume' ? 1 : 0, $id]);
                flash($action === 'resume' ? 'Automatic syncing resumed.' : 'Automatic syncing paused.');
            } elseif ($action === 'remove') {
                q('DELETE FROM social_accounts WHERE id=?', [$id]);
                flash('Channel and its stats history removed.');
            } else throw new InvalidArgumentException('Unknown action.');
        }
        redirect($back);
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { $error = 'Could not update this channel. Refresh and try again.'; }
}
$accounts = social_accounts($cid);
$heartbeat = q("SELECT v FROM meta WHERE k='social_cron_last_run'")->fetchColumn();
page_header('Social channels · ' . $creator['name'], $u['role'] === 'creator' ? 'tracker' : 'dashboard');
?>
<a class="back" href="creator.php?id=<?= $cid ?>"><?= icon('back') ?>Back to tracker</a>
<div class="dash-head"><div><p class="eyebrow">Social channels</p><h1><?= e($creator['name']) ?></h1><p class="sub">Link public accounts to track followers, subscribers, and growth automatically.</p></div></div>
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<?php if (!social_enabled()): ?><div class="note-bar">Automatic stats are awaiting setup. Channels can be linked now; your admin needs to configure Apify.</div><?php endif; ?>
<?php if (is_staff($u)): ?>
<div class="note-bar <?= $heartbeat && strtotime($heartbeat) > time()-1800 ? 'info' : '' ?>"><?= $heartbeat ? 'Scheduler last checked ' . e(time_ago($heartbeat)) . '.' : 'Background scheduler has not run yet. Configure the scheduled command to update stats even when nobody is logged in.' ?> <?php if ($u['role'] === 'superadmin'): ?><a href="social-setup.php">Scheduler setup</a><?php endif; ?></div>
<?php endif; ?>
<section class="panel social-add">
 <div class="panel-h"><h3><?= icon('plus') ?>Link a channel</h3></div>
 <form method="post" class="form social-link-form">
  <?= csrf_field() ?><input type="hidden" name="act" value="add">
  <label>Platform<select name="platform" required><?php foreach (SOCIAL_PLATFORMS as $key=>$platform): ?><option value="<?= $key ?>" <?= ($_POST['platform'] ?? '') === $key ? 'selected' : '' ?>><?= e($platform['label']) ?></option><?php endforeach; ?></select></label>
  <label>Handle or profile URL<input name="handle" value="<?= e($_POST['handle'] ?? '') ?>" placeholder="@yourchannel or profile URL" maxlength="255" required autocomplete="off"></label>
  <button class="btn primary" type="submit">Link channel</button>
 </form>
 <p class="hint">Public profile stats only. Daily updates by default; each sync uses your team's Apify credits. Private profiles may not return stats.</p>
</section>
<section class="sec"><div class="section-h"><h2>Linked channels</h2><span>Automatic refresh every <?= (int)(social_interval()/3600) ?> hours.</span></div>
<div class="social-grid" id="social-channels" data-cid="<?= $cid ?>" data-manage="1">
<?php foreach ($accounts as $account) social_card($account, true); ?>
<?php if (!$accounts): ?><div class="empty">No channels linked yet. Add your first account above.</div><?php endif; ?>
</div></section>
<script>window.FL_SOCIAL_PENDING = <?= js_json(array_values(array_map(fn($a)=>(int)$a['id'], array_filter($accounts, fn($a)=>$a['is_active'] && $a['run_id'])))) ?>;</script>
<?php page_footer(['social.js']); ?>
