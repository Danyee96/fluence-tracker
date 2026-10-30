<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login(['superadmin']);

$roles = ['creator', 'admin', 'superadmin'];

$formError = ''; $failedCreate = null; $failedEditId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string)($_POST['act'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if (in_array($act,['create','update'],true)) {
        try {
            $target=$act==='update' ? q('SELECT id,name,role,handle,niche,channel_url,start_month FROM users WHERE id=?',[$id])->fetch() : null;
            if ($act==='update' && !$target) throw new InvalidArgumentException('User not found.');
            $self=$target && (int)$target['id']===(int)$u['id'];
            $name=trim((string)($_POST['name']??''));$email=trim(strtolower((string)($_POST['email']??'')));
            $role=in_array($_POST['role']??'',$roles,true) ? $_POST['role'] : ($target['role']??'creator');
            $active=$act==='create' || isset($_POST['is_active']) ? 1 : 0;
            if ($self) {$role='superadmin';$active=1;}
            $pass=(string)($_POST['password']??'');
            if ($name==='' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a name (up to 120 characters) and a valid email.');
            if ($act==='create' && strlen($pass)<8) throw new InvalidArgumentException('Password must be at least 8 characters.');
            if (q('SELECT 1 FROM users WHERE email=? AND id<>?',[$email,$act==='update'?$id:0])->fetchColumn()) throw new InvalidArgumentException('That email already has a login.');
            $p=[];
            foreach (['handle','niche','channel_url','start_month'] as $field) $p[$field]=$target[$field]??null;
            if ($act==='create' && $role==='creator' && !$p['start_month']) $p['start_month']=date('Y-m');
            $oldPlan=$target && $target['role']==='creator' ? creator_plan($id) : null;
            $accounts=$oldPlan ? social_accounts($id) : [];
            $details=$role==='creator' && isset($_POST['creator_details_present']) ? creator_details_input($_POST,$oldPlan,$accounts) : null;
            db()->beginTransaction();
            if ($act==='create') {
                q('INSERT INTO users (name,email,password_hash,role,is_active,handle,niche,channel_url,start_month,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',[$name,$email,password_hash($pass,PASSWORD_DEFAULT),$role,$active,$p['handle'],$p['niche'],$p['channel_url'],$p['start_month'],now()]);
                $id=(int)db()->lastInsertId();
            } else q('UPDATE users SET name=?,email=?,role=?,is_active=?,handle=?,niche=?,channel_url=?,start_month=? WHERE id=?',[$name,$email,$role,$active,$p['handle'],$p['niche'],$p['channel_url'],$p['start_month'],$id]);
            $queue=[];
            if ($role==='creator') {
                get_goals($id);
                if ($details!==null) $queue=save_creator_details($id,$details,$oldPlan);
            }
            db()->commit();
            if ($queue && $active) $_SESSION['social_start_queue']=['cid'=>$id,'ids'=>$queue];
            if ($act==='create') $_SESSION['new_login']=['id'=>$id,'name'=>$name,'email'=>$email,'password'=>$pass,'role'=>$role,'url'=>app_url('login.php')];
            else flash('Saved '.$name.'. New social profiles are queued for syncing.');
            redirect('users.php'.($act==='update'?'#u'.$id:''));
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $formError=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not save this user. Check the details and try again.';
            if ($act==='create') $failedCreate=$_POST; else $failedEditId=$id;
        }
    } else {
        $target=$id ? q('SELECT id,name,role FROM users WHERE id=?',[$id])->fetch() : null;
        if (!$target) {flash('User not found.','err');redirect('users.php');}
        $self=(int)$target['id']===(int)$u['id'];
        if ($act==='password') {
            $pass=(string)($_POST['password']??'');
            if (strlen($pass)<8) flash('Password must be at least 8 characters.','err');
            else {
                q('UPDATE users SET password_hash=? WHERE id=?',[password_hash($pass,PASSWORD_DEFAULT),$id]);
                $_SESSION['new_login']=['id'=>$id,'name'=>$target['name'],'email'=>q('SELECT email FROM users WHERE id=?',[$id])->fetchColumn(),'password'=>$pass,'role'=>$target['role'],'url'=>app_url('login.php'),'reset'=>true];
            }
        } elseif ($act==='delete') {
            if ($self) flash("You can't delete your own login.",'err');
            else {q('DELETE FROM users WHERE id=?',[$id]);flash('Deleted '.$target['name'].' and their tracker data.');}
        }
        redirect('users.php'.($act==='password'?'#u'.$id:''));
    }
}

$newLogin = $_SESSION['new_login'] ?? null;
unset($_SESSION['new_login']);
$socialQueue=$_SESSION['social_start_queue']??null;unset($_SESSION['social_start_queue']);

$users = q("SELECT * FROM users ORDER BY CASE role WHEN 'superadmin' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END, name")->fetchAll();
$counts = ['creator' => 0, 'admin' => 0, 'superadmin' => 0];
foreach ($users as $x) $counts[$x['role']]++;
$newRole = in_array($_GET['new'] ?? '', $roles, true) ? $_GET['new'] : 'creator';

if ($failedCreate && in_array($failedCreate['role']??'',$roles,true)) $newRole=$failedCreate['role'];
page_header('Users', 'users');
?>
<div class="dash-head">
  <div>
    <p class="eyebrow">Fluence · Access</p>
    <h1>Users &amp; Logins</h1>
    <p class="sub"><b><?= $counts['creator'] ?></b> creators · <b><?= $counts['admin'] ?></b> admins · <b><?= $counts['superadmin'] ?></b> super admins</p>
  </div>
  <button class="btn primary" type="button" id="openNew"><?= icon('plus') ?><span>Add login</span></button>
</div>

<?php if ($formError): ?><div class="flash err" role="alert"><?= e($formError) ?></div><?php endif; ?>
<?php if ($newLogin): ?>
<div class="cred" id="cred">
  <div class="cred-head"><?= icon('key') ?><div><b><?= !empty($newLogin['reset']) ? 'Password reset' : 'Login created' ?> for <?= e($newLogin['name']) ?></b><span>Copy these details and send them — the password won't be shown again.</span></div></div>
  <pre id="credtxt">Fluence login (<?= e(role_label($newLogin['role'])) ?>)
Link: <?= e($newLogin['url']) ?>

Email: <?= e($newLogin['email']) ?>

Password: <?= e($newLogin['password']) ?></pre>
  <div class="cred-actions">
    <button class="btn primary sm" type="button" data-copy="#credtxt"><?= icon('copy') ?><span>Copy details</span></button>
    <?php if ($newLogin['role'] === 'creator'): ?><a class="btn sm" href="creator.php?id=<?= (int)$newLogin['id'] ?>"><?= icon('arrow') ?><span>Open tracker</span></a><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="panel newform" id="newform" <?= isset($_GET['new']) || $failedCreate ? '' : 'hidden' ?>>
  <div class="panel-h"><h3>New login</h3><button class="iconbtn" type="button" id="closeNew" aria-label="Close"><?= icon('x') ?></button></div>
  <form method="post" class="form row" id="createForm">
    <?= csrf_field() ?><input type="hidden" name="act" value="create">
    <label>Full name<input name="name" required placeholder="e.g. Jordan Michaels" value="<?= e($failedCreate['name']??'') ?>"></label>
    <label>Email<input type="email" name="email" required placeholder="name@email.com" value="<?= e($failedCreate['email']??'') ?>"></label>
    <label>Role
      <select name="role" id="newRole">
        <?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= $r === $newRole ? 'selected' : '' ?>><?= role_label($r) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Password
      <span class="inwrap"><input name="password" id="newPass" minlength="8" required value="<?= e($failedCreate['password']??random_password()) ?>"><button type="button" class="inbtn" id="regen" title="New random password"><?= icon('key') ?></button></span>
    </label>
    <div class="creator-only fullrow creator-extra-fields"><?php creator_details_editor(null,[],$failedCreate); ?></div>
    <div class="fullrow"><button class="btn primary" type="submit"><?= icon('check') ?><span>Create login</span></button></div>
  </form>
</div>

<div class="toolbar">
  <label class="search"><?= icon('search') ?><input type="search" id="usearch" placeholder="Search name or email…" aria-label="Search users"></label>
  <div class="chips inline" id="uchips">
    <button type="button" class="chip on" data-r="">All <i><?= count($users) ?></i></button>
    <?php foreach ($roles as $r): ?><button type="button" class="chip" data-r="<?= $r ?>"><?= role_label($r) ?>s <i><?= $counts[$r] ?></i></button><?php endforeach; ?>
  </div>
</div>

<div class="ulist" id="ulist">
<?php foreach ($users as $x): $self = (int)$x['id'] === (int)$u['id']; $isC = $x['role'] === 'creator'; $editSource=$failedEditId===(int)$x['id']?$_POST:$x; ?>
  <details <?= $failedEditId===(int)$x['id']?'open':'' ?> class="urow <?= (int)$x['is_active'] ? '' : 'off' ?>" id="u<?= (int)$x['id'] ?>" data-role="<?= e($x['role']) ?>" data-q="<?= e(mb_strtolower($x['name'] . ' ' . $x['email'])) ?>">
    <summary>
      <?= avatar($x) ?>
      <span class="uname"><b><?= e($x['name']) ?><?= $self ? ' <em>(you)</em>' : '' ?></b><small><?= e($x['email']) ?></small></span>
      <span class="role-chip r-<?= e($x['role']) ?>"><?= role_label($x['role']) ?></span>
      <span class="ustate <?= (int)$x['is_active'] ? 'on' : '' ?>"><i></i><?= (int)$x['is_active'] ? 'Active' : 'Disabled' ?></span>
      <span class="ulast"><?= $x['last_login'] ? 'Seen ' . e(time_ago($x['last_login'])) : 'Never logged in' ?></span>
      <span class="uchev"><?= icon('chevron') ?></span>
    </summary>
    <div class="uedit">
      <form method="post" class="form row">
        <?= csrf_field() ?><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
        <label>Name<input name="name" value="<?= e($editSource['name']) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= e($editSource['email']) ?>" required></label>
        <label>Role
          <select name="role" <?= $self ? 'disabled' : '' ?>>
            <?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= $r === ($editSource['role']??$x['role']) ? 'selected' : '' ?>><?= role_label($r) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="check"><input type="checkbox" name="is_active" <?= ($failedEditId===(int)$x['id']?isset($_POST['is_active']):(int)$x['is_active']) ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>> Active (can log in)</label>
        <?php if ($isC): ?><div class="fullrow creator-extra-fields"><?php creator_details_editor(creator_plan((int)$x['id']),social_accounts((int)$x['id']),$failedEditId===(int)$x['id']?$_POST:null,(int)$x['id']); ?></div><?php endif; ?>
        <div class="fullrow"><button class="btn" type="submit"><?= icon('check') ?><span>Save changes</span></button></div>
      </form>
      <div class="uedit-foot">
        <form method="post" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="act" value="password"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <input name="password" minlength="8" required value="<?= e(random_password()) ?>" aria-label="New password" class="field">
          <button class="btn sm" type="submit"><?= icon('key') ?><span>Reset password</span></button>
        </form>
        <?php if ($isC): ?><a class="btn sm ghost" href="creator.php?id=<?= (int)$x['id'] ?>"><?= icon('arrow') ?><span>Open tracker</span></a><?php endif; ?>
        <?php if (!$self): ?>
        <form method="post" class="inline-form push" data-confirm="Delete <?= e($x['name']) ?>? This also deletes all their tracker data.">
          <?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <button class="btn sm danger" type="submit"><?= icon('trash') ?><span>Delete</span></button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </details>
<?php endforeach; ?>
<div class="empty" id="uempty" hidden>No users match.</div>
</div>
<template id="creator-goal-template"><?php creator_goal_row([], '__INDEX__'); ?></template>
<template id="creator-social-template"><?php creator_social_row([], '__INDEX__'); ?></template>
<script>window.FL_SOCIAL_QUEUE=<?= js_json(social_enabled()?$socialQueue:null) ?>;</script>
<?php page_footer(['users.js']); ?>
