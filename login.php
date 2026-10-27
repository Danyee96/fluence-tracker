<?php
require __DIR__ . '/inc/bootstrap.php';

if ($u = current_user()) redirect(home_for($u));

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    // simple brute-force brake (per session)
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if ($_SESSION['fails'] >= 5) sleep(min(5, $_SESSION['fails'] - 4));

    $row = $email ? q('SELECT id, password_hash, is_active FROM users WHERE email=?', [$email])->fetch() : null;
    if ($row && password_verify($pass, $row['password_hash'])) {
        if ((int)$row['is_active'] !== 1) {
            $error = 'This account is disabled. Contact your Fluence admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$row['id'];
            $_SESSION['fails'] = 0;
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
            }
            q('UPDATE users SET last_login=? WHERE id=?', [now(), $row['id']]);
            redirect('index.php');
        }
    } else {
        $_SESSION['fails']++;
        $error = 'Wrong email or password.';
    }
}

page_header('Log in', '', true);
?>
<div class="auth">
  <div class="auth-shell">
    <aside class="auth-brand">
      <a class="brand" href="index.php" aria-label="Fluence home"><img class="brand-logo" src="assets/fluence-logo.svg?v=<?= FL_VERSION ?>" alt="Fluence" width="2048" height="309"></a>
      <div>
        <h2>From content to a real creator business.</h2>
        <ul class="auth-points">
          <li><?= icon('layers') ?><span>Your four-phase roadmap, step by step</span></li>
          <li><?= icon('trend') ?><span>Monthly revenue and subscriber trends</span></li>
          <li><?= icon('chat') ?><span>Feedback from the Fluence team in one place</span></li>
        </ul>
      </div>
      <div class="auth-glow" aria-hidden="true"></div>
    </aside>
    <div class="auth-card">
      <p class="eyebrow">Creator Management</p>
      <h1>Welcome back</h1>
      <p class="auth-sub">Log in to see your roadmap and monthly progress.</p>
      <?php if ($error): ?><div class="flash err"><?= icon('x') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username" placeholder="you@email.com"></label>
        <label>Password
          <span class="inwrap"><input type="password" name="password" id="pw" required autocomplete="current-password" placeholder="••••••••"><button type="button" class="inbtn" onclick="var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'" title="Show password" aria-label="Show password"><?= icon('eye') ?></button></span>
        </label>
        <button class="btn primary wide lg" type="submit"><span>Log in</span><?= icon('arrow') ?></button>
      </form>
      <p class="auth-foot">Forgot your password? Ask your Fluence admin to reset it.</p>
    </div>
  </div>
</div>
<?php page_footer(); ?>
