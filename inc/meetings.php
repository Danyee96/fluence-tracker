<?php
defined('FL') or exit;

function meeting_schema(bool $sqlite): array
{
    $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $id = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return ["CREATE TABLE IF NOT EXISTS creator_meetings (
        id $auto, creator_id $id NOT NULL, meeting_date DATE NOT NULL,
        attendees TEXT NOT NULL, discussion TEXT NULL, actionables TEXT NULL,
        author_id $id NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        revision INTEGER NOT NULL DEFAULT 1,
        FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
    )$engine"];
}

function can_edit_meeting(array $u, array $meeting): bool
{
    return is_staff($u) || ($u['role'] === 'creator' && (int)$u['id'] === (int)$meeting['creator_id'] && (int)$u['id'] === (int)$meeting['author_id']);
}

function meeting_fields(array $input): array
{
    $date = (string)($input['meeting_date'] ?? '');
    if (!preg_match('/^([1-9]\d{3})-(\d{2})-(\d{2})$/', $date, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) throw new InvalidArgumentException('Choose a valid meeting date.');
    $fields = ['meeting_date'=>$date];
    foreach (['attendees'=>1000,'discussion'=>10000,'actionables'=>10000] as $name=>$limit) {
        if (!is_scalar($input[$name] ?? '')) throw new InvalidArgumentException('Enter valid meeting details.');
        $value = trim((string)($input[$name] ?? ''));
        if (mb_strlen($value) > $limit) throw new InvalidArgumentException(ucfirst($name) . " must be $limit characters or fewer.");
        $fields[$name] = $value;
    }
    if ($fields['attendees'] === '') throw new InvalidArgumentException('Add the names of the people who attended.');
    return $fields;
}

function save_meeting(array $u, int $cid, array $input): int
{
    if (!can_view_creator($u, $cid)) throw new InvalidArgumentException('You cannot log meetings for this creator.');
    $id = (int)($input['meeting_id'] ?? 0);
    $fields = meeting_fields($input);
    if ($id) {
        $meeting = q('SELECT * FROM creator_meetings WHERE id=? AND creator_id=?', [$id,$cid])->fetch();
        if (!$meeting || !can_edit_meeting($u,$meeting)) throw new InvalidArgumentException('You can only edit your own meeting logs. Admins can edit any log.');
        $revision = (int)($input['meeting_revision'] ?? 0);
        $changed = q('UPDATE creator_meetings SET meeting_date=?, attendees=?, discussion=?, actionables=?, updated_at=?, revision=revision+1 WHERE id=? AND creator_id=? AND revision=?',
            [$fields['meeting_date'],$fields['attendees'],$fields['discussion'],$fields['actionables'],now(),$id,$cid,$revision]);
        if (!$changed->rowCount()) throw new InvalidArgumentException('This meeting was changed in another tab. Reload it before editing again.');
    } else {
        q('INSERT INTO creator_meetings (creator_id,meeting_date,attendees,discussion,actionables,author_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)',
            [$cid,$fields['meeting_date'],$fields['attendees'],$fields['discussion'],$fields['actionables'],(int)$u['id'],now(),now()]);
        $id = (int)db()->lastInsertId();
    }
    return $id;
}

function remove_meeting(array $u, int $cid, int $id, int $revision): void
{
    $meeting = q('SELECT * FROM creator_meetings WHERE id=? AND creator_id=?', [$id,$cid])->fetch();
    if (!$meeting || !can_edit_meeting($u,$meeting)) throw new InvalidArgumentException('You cannot remove this meeting log.');
    $deleted = q('DELETE FROM creator_meetings WHERE id=? AND creator_id=? AND revision=?', [$id,$cid,$revision]);
    if (!$deleted->rowCount()) throw new InvalidArgumentException('This meeting changed in another tab. Reload before removing it.');
}

function meeting_post(array $u, int $cid, string $month): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['meeting_action'])) return null;
    csrf_check();
    try {
        $action = (string)$_POST['meeting_action'];
        if ($action === 'save') {
            save_meeting($u,$cid,$_POST);
            flash('Meeting saved.');
        } elseif ($action === 'delete') {
            remove_meeting($u,$cid,(int)($_POST['meeting_id'] ?? 0),(int)($_POST['meeting_revision'] ?? 0));
            flash('Meeting removed.');
        } else throw new InvalidArgumentException('Unknown meeting action.');
        log_activity($cid,(int)$u['id'],'meeting',$month,$action === 'delete' ? 'removed a meeting log' : 'logged a meeting on ' . $_POST['meeting_date']);
        redirect('creator.php?' . http_build_query(['id'=>$cid,'m'=>$month]) . '#meetings');
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        return ['error'=>$e->getMessage(),'fields'=>$action === 'save' ? $_POST : []];
    }
}

function meeting_section(array $u, int $cid, string $month, ?array $failed = null): void
{
    $base = ['id'=>$cid,'m'=>$month];
    $editing = null; $editError = '';
    if (!empty($_GET['meeting_edit'])) {
        $editing = q('SELECT * FROM creator_meetings WHERE id=? AND creator_id=?', [(int)$_GET['meeting_edit'],$cid])->fetch() ?: null;
        if (!$editing || !can_edit_meeting($u,$editing)) { $editing = null; $editError = 'You cannot edit this meeting log.'; }
    }
    $fields = $failed['fields'] ?? ($editing ? $editing + ['meeting_id'=>$editing['id'],'meeting_revision'=>$editing['revision']] : []);
    $count = (int)q('SELECT COUNT(*) FROM creator_meetings WHERE creator_id=?',[$cid])->fetchColumn();
    $page = max(1,min(max(1,(int)ceil($count/10)),(int)($_GET['meetings_page'] ?? 1)));
    $rows = q('SELECT m.*, u.name AS author_name FROM creator_meetings m LEFT JOIN users u ON u.id=m.author_id WHERE m.creator_id=? ORDER BY m.meeting_date DESC,m.id DESC LIMIT 10 OFFSET ' . (($page-1)*10),[$cid])->fetchAll();
    ?>
<section class="sec" id="meetings">
 <div class="section-h"><h2><?= icon('calendar') ?>Meetings</h2><span>Meeting dates, attendees, discussion, and next steps. All months, newest first.</span></div>
 <?php if ($failed || $editError): ?><div class="flash err" role="alert"><?= e($failed['error'] ?? $editError) ?></div><?php endif; ?>
 <details class="panel meeting-editor" <?= $editing || $failed ? 'open' : '' ?>><summary><?= !empty($fields['meeting_id']) ? 'Edit meeting' : '+ Log a meeting' ?></summary>
 <form method="post" action="?<?= e(http_build_query($base)) ?>#meetings" class="form">
 <?= csrf_field() ?><input type="hidden" name="meeting_action" value="save"><input type="hidden" name="meeting_id" value="<?= (int)($fields['meeting_id'] ?? 0) ?>"><input type="hidden" name="meeting_revision" value="<?= (int)($fields['meeting_revision'] ?? 0) ?>">
 <div class="meeting-fields"><label>Meeting date<input type="date" name="meeting_date" value="<?= e($fields['meeting_date'] ?? date('Y-m-d')) ?>" required></label><label>Who attended?<input name="attendees" value="<?= e($fields['attendees'] ?? '') ?>" placeholder="e.g. Alex, Daniyal, Sarah" maxlength="1000" required></label></div>
 <label>What was discussed?<textarea name="discussion" rows="4" maxlength="10000" placeholder="Topics discussed, decisions, blockers…"><?= e($fields['discussion'] ?? '') ?></textarea></label>
 <label>Action items<textarea name="actionables" rows="4" maxlength="10000" placeholder="Next steps, who owns each action, and due dates…"><?= e($fields['actionables'] ?? '') ?></textarea></label>
 <div class="plan-actions"><button class="btn primary" type="submit"><?= !empty($fields['meeting_id']) ? 'Save changes' : 'Save meeting' ?></button><?php if (!empty($fields['meeting_id'])): ?><a class="btn ghost" href="?<?= e(http_build_query($base)) ?>#meetings">Cancel edit</a><?php endif; ?></div>
 </form></details>
 <div class="meeting-list">
 <?php foreach ($rows as $meeting): ?>
 <article class="panel meeting-log">
  <div class="meeting-log-head"><h3><?= e(date('F j, Y',strtotime($meeting['meeting_date']))) ?></h3><span class="hint">Logged by <?= e($meeting['author_name'] ?: 'Removed user') ?></span></div>
  <p class="meeting-attendees"><b>Attendees</b> <?= e($meeting['attendees']) ?></p>
  <div class="meeting-notes"><div><h4>Discussion</h4><p><?= e($meeting['discussion'] ?: 'No discussion notes added.') ?></p></div><div><h4>Action items</h4><p><?= e($meeting['actionables'] ?: 'No action items added.') ?></p></div></div>
  <?php if (can_edit_meeting($u,$meeting)): ?><div class="meeting-log-actions"><a class="btn sm" href="?<?= e(http_build_query($base+['meeting_edit'=>$meeting['id']])) ?>#meetings">Edit</a><form method="post" action="?<?= e(http_build_query($base)) ?>#meetings" onsubmit="return confirm('Remove this meeting log?')"><?= csrf_field() ?><input type="hidden" name="meeting_action" value="delete"><input type="hidden" name="meeting_id" value="<?= (int)$meeting['id'] ?>"><input type="hidden" name="meeting_revision" value="<?= (int)$meeting['revision'] ?>"><button class="btn sm danger" type="submit">Remove</button></form></div><?php endif; ?>
 </article>
 <?php endforeach; ?>
 <?php if (!$rows): ?><div class="empty">No meetings logged yet. Add a meeting to keep the discussion and next steps in one place.</div><?php endif; ?>
 </div>
 <?php if ($count>10): ?><nav class="meeting-pagination" aria-label="Meeting history pages"><?php if ($page>1): ?><a class="btn sm" href="?<?= e(http_build_query($base+['meetings_page'=>$page-1])) ?>#meetings">Newer meetings</a><?php endif; ?><span>Page <?= $page ?> of <?= (int)ceil($count/10) ?></span><?php if ($page<(int)ceil($count/10)): ?><a class="btn sm" href="?<?= e(http_build_query($base+['meetings_page'=>$page+1])) ?>#meetings">Older meetings</a><?php endif; ?></nav><?php endif; ?>
</section>
    <?php
}
