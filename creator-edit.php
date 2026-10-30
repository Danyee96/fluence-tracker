<?php
require __DIR__.'/inc/bootstrap.php';
$u=require_login(['superadmin','admin']);
$cid=(int)($_GET['id']??0);$month=valid_month($_GET['m']??'')??date('Y-m');
$creator=q("SELECT id,name,email,is_active FROM users WHERE id=? AND role='creator'",[$cid])->fetch();
if (!$creator) {http_response_code(404);exit('Creator not found.');}
$plan=creator_plan($cid);$accounts=social_accounts($cid);$error='';$draft=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();$draft=$_POST;
    try {
        $name=trim((string)($_POST['name']??''));$email=trim(strtolower((string)($_POST['email']??'')));
        if ($name==='' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a name (up to 120 characters) and a valid email.');
        if (q('SELECT 1 FROM users WHERE email=? AND id<>?',[$email,$cid])->fetchColumn()) throw new InvalidArgumentException('That email is used by another account.');
        $details=creator_details_input($_POST,$plan,$accounts);
        $active=$u['role']==='superadmin' ? (isset($_POST['is_active'])?1:0) : (int)$creator['is_active'];
        db()->beginTransaction();
        // Never change a staff login if the creator was promoted while this form was open.
        q("UPDATE users SET name=?,email=?,is_active=? WHERE id=? AND role='creator'",[$name,$email,$active,$cid]);
        if (!q("SELECT 1 FROM users WHERE id=? AND role='creator'",[$cid])->fetchColumn()) throw new InvalidArgumentException('This account is no longer a creator.');
        $queue=save_creator_details($cid,$details,$plan);
        log_activity($cid,(int)$u['id'],'profile',null,'updated creator details');
        db()->commit();
        if ($queue && $active) $_SESSION['social_start_queue']=['cid'=>$cid,'ids'=>$queue];
        flash('Creator details saved.');
        redirect('creator-edit.php?id='.$cid.'&m='.$month);
    } catch(Throwable $e) {
        if(db()->inTransaction())db()->rollBack();
        $error=$e instanceof InvalidArgumentException?$e->getMessage():'Could not save this creator. Try again.';
    }
}
$queue=$_SESSION['social_start_queue']??null;unset($_SESSION['social_start_queue']);
page_header('Edit '.$creator['name'],'dashboard');
?>
<a class="back" href="creator.php?id=<?= $cid ?>&m=<?= e($month) ?>"><?= icon('back') ?>Back to creator</a>
<div class="dash-head"><div><p class="eyebrow">Creator details</p><h1>Edit <?= e($creator['name']) ?></h1><p class="sub">Update their basic information and social profiles, or open the goals and path editor.</p></div></div>
<?php if($error):?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel"><form method="post" class="form">
 <?= csrf_field() ?>
 <div class="meeting-fields"><label>Full name<input name="name" value="<?= e($draft['name']??$creator['name']) ?>" maxlength="120" required></label><label>Email<input type="email" name="email" value="<?= e($draft['email']??$creator['email']) ?>" required></label></div>
 <?php if($u['role']==='superadmin'):?><label class="check"><input type="checkbox" name="is_active" <?= ($draft!==null?isset($draft['is_active']):(bool)$creator['is_active'])?'checked':'' ?>>Active (can log in)</label><?php endif; ?>
 <?php creator_details_editor($plan,$accounts,$draft,$cid); ?>
 <div class="plan-actions"><button class="btn primary" type="submit"><?= icon('check') ?>Save changes</button><a class="btn ghost" href="creator.php?id=<?= $cid ?>&m=<?= e($month) ?>">Cancel</a></div>
</form></section>
<template id="creator-goal-template"><?php creator_goal_row([],'__INDEX__'); ?></template>
<template id="creator-social-template"><?php creator_social_row([],'__INDEX__'); ?></template>
<script>window.FL_SOCIAL_QUEUE=<?= js_json(social_enabled()?$queue:null) ?>;</script>
<?php page_footer(['creator-details.js']); ?>
