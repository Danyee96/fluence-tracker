<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login();
$isC = $u['role'] === 'creator';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['act'] ?? '') === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') flash('Name can\'t be empty.', 'err');
        else {
            if ($isC) {
                q('UPDATE users SET name=?, handle=?, niche=?, channel_url=? WHERE id=?', [
                    $name, clean_handle($_POST['handle'] ?? ''),
                    ($n = trim((string)($_POST['niche'] ?? ''))) === '' ? null : mb_substr($n, 0, 120),
                    clean_url($_POST['channel_url'] ?? ''), $u['id'],
                ]);
            } else {
                q('UPDATE users SET name=? WHERE id=?', [$name, $u['id']]);
            }
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
      <?php if ($isC): ?>
      <label>Handle<input name="handle" value="<?= e($u['handle']) ?>" placeholder="@channel"></label>
      <label>Niche<input name="niche" value="<?= e($u['niche']) ?>" placeholder="e.g. Finance"></label>
      <label>Channel link<input name="channel_url" value="<?= e($u['channel_url']) ?>" placeholder="youtube.com/@channel"></label>
      <?php endif; ?>
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
<?php page_footer(); ?>
