<?php
/**
 * One-time installer: creates the tables and the first Super Admin.
 * DELETE THIS FILE from the server after installing.
 */
require __DIR__ . '/inc/bootstrap.php';

function installed(): bool
{
    try {
        return (bool)q("SELECT COUNT(*) FROM users WHERE role='superadmin'")->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

$error = '';
$done  = false;

if (installed()) {
    $done = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a name and a valid email.';
    elseif (strlen($pass) < 8) $error = 'Password must be at least 8 characters.';
    else {
        migrate();
        q('INSERT INTO users (name, email, password_hash, role, is_active, created_at) VALUES (?,?,?,?,1,?)',
          [$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'superadmin', now()]);
        $done = true;
    }
}

page_header('Install', '', true);
?>
<div class="auth">
  <div class="auth-card solo">
    <p class="eyebrow">Fluence · Setup</p>
    <?php if ($done): ?>
      <h1>All set</h1>
      <p class="auth-sub">Tables are created and the Super Admin exists. <b>Delete <code>install.php</code> from your server now</b>, then log in.</p>
      <a class="btn primary wide" href="login.php">Go to login</a>
    <?php else: ?>
      <h1>Create Super Admin</h1>
      <p class="auth-sub">This creates the database tables and your first login. Run once.</p>
      <?php if ($error): ?><div class="flash err"><?= icon('x') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Name<input name="name" required value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label>Email<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Password<input type="password" name="password" minlength="8" required></label>
        <button class="btn primary wide" type="submit">Install</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>
