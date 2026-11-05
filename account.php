<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$isC = $u['role'] === 'creator';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if(in_array($_POST['act']??'', ['grok_save','grok_test','grok_remove'],true)) {
        if($u['role']!=='superadmin'){http_response_code(403);exit('Only Super Admins can manage Grok settings.');}
        try {
            if($_POST['act']==='grok_save'){grok_account_settings($u,$_POST);flash('Grok settings saved. The assistant is '.(isset($_POST['grok_enabled'])?'enabled on this website.':'disabled.'));}
            elseif($_POST['act']==='grok_remove'){grok_write_settings(['api_key'=>'','enabled'=>false,'allow_remote'=>false,'model'=>$CONFIG['grok']['model']??'grok-4.7']);flash('Saved Grok key removed and assistant disabled.');}
            else {
                if(time()-(int)($_SESSION['grok_test_last']??0)<15)throw new RuntimeException('Please wait 15 seconds before testing again.');
                $_SESSION['grok_test_last']=time();
                assistant_reply(['purpose'=>'Connection test only; no creator records included.'],'Reply with the word Connected.');
                flash('Connected to Grok successfully. The saved API key and model work.');
            }
        }catch(InvalidArgumentException|RuntimeException $e){flash($e->getMessage(),'err');}
        redirect('account.php#grok-settings');
    }
    if (($_POST['act'] ?? '') === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') flash('Name can\'t be empty.', 'err');
        else {
            q('UPDATE users SET name=? WHERE id=?', [$name, $u['id']]);
            flash('Profile updated.');
        }
    } else {
        $row = q('SELECT password_hash FROM users WHERE id=?', [$u['id']])->fetch();
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $row['password_hash'])) flash('Current password is wrong.', 'err');
        elseif (strlen($new) < 8) flash('New password must be at least 8 characters.', 'err');
        elseif ($new !== ($_POST['confirm'] ?? '')) flash('New passwords don\'t match.', 'err');
        else {
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('Password changed.');
        }
    }
    redirect('account.php');
}

page_header('Account', 'account');
?>
<section class="hero slim">
  <div class="hero-main">
    <?= avatar($u, 'xl') ?>
    <div class="hero-txt">
      <p class="eyebrow">Fluence · Account</p>
      <h1><?= e($u['name']) ?></h1>
      <div class="meta-line"><span><?= e($u['email']) ?></span><span class="role-chip r-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></div>
    </div>
  </div>
</section>

<div class="two">
  <div class="panel">
    <div class="panel-h"><h3><?= icon('user') ?>Profile</h3></div>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="act" value="profile">
      <label>Name<input name="name" value="<?= e($u['name']) ?>" required></label>
      <div><button class="btn" type="submit"><?= icon('check') ?><span>Save profile</span></button></div>
    </form>
  </div>

  <div class="panel">
    <div class="panel-h"><h3><?= icon('key') ?>Change password</h3></div>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="act" value="password">
      <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
      <label>New password<input type="password" name="new" minlength="8" required autocomplete="new-password"></label>
      <label>Confirm new password<input type="password" name="confirm" minlength="8" required autocomplete="new-password"></label>
      <div><button class="btn primary" type="submit"><?= icon('check') ?><span>Change password</span></button></div>
    </form>
  </div>
</div>
<section class="panel sec"><div class="panel-h"><h3><?= icon('layers') ?>Social channels</h3></div>
<?php if ($isC): ?><p>Connect your public profiles to track followers, subscribers, and growth automatically.</p><a class="btn primary" href="social.php">Manage my channels</a>
<?php else: ?><p>Manage social channels from each creator’s tracker.</p><?php if ($u['role'] === 'superadmin'): ?><a class="btn" href="social-setup.php">Social sync setup</a><?php endif; ?><?php endif; ?></section>
<?php if($u['role']==='superadmin'): ?>
<section class="panel sec" id="grok-settings"><div class="panel-h"><h3><?= icon('chat') ?>Grok assistant</h3></div>
<p class="hint">Configure the assistant for everyone on this website. Admins can ask across creators; creators can only access their own data.</p>
<p class="hint"><?= grok_effective_key()!==''?'API key configured. The saved key is never displayed.':'No API key configured yet.' ?></p>
<form method="post" class="form" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="act" value="grok_save">
<label>xAI API key<input type="password" name="grok_key" autocomplete="new-password" placeholder="<?= grok_effective_key()!==''?'Leave blank to keep the current key':'xai-…' ?>" maxlength="512" spellcheck="false"></label>
<label>Model<input name="grok_model" value="<?= e($CONFIG['grok']['model']??'grok-4.7') ?>" maxlength="100" required></label>
<label class="check"><input type="checkbox" name="grok_enabled" <?= !empty($CONFIG['grok']['enabled'])?'checked':'' ?>>Enable assistant on this website</label>
<p class="hint">Questions send authorized tracker data to xAI and use your API credits. Keep the key private. Blank leaves the current key unchanged.</p>
<div><button class="btn primary" type="submit">Save Grok settings</button></div></form>
<div class="plan-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="grok_test"><button class="btn" <?= grok_effective_key()===''?'disabled':'' ?>>Test saved connection</button></form><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="grok_remove"><button class="btn danger" <?= grok_effective_key()===''?'disabled':'' ?>>Remove saved key &amp; disable</button></form></div>
<p class="hint">Connection tests make a small paid API request without creator records.</p>
</section><?php endif; ?>
<?php page_footer(); ?>
