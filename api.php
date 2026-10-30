<?php
/** JSON endpoint for the tracker page (autosave + feedback). */
require __DIR__ . '/inc/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
$u = current_user();
if (!$u) json_out(['ok' => false, 'error' => 'You were logged out — refresh and log in again.'], 401);
if (!csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) json_out(['ok' => false, 'error' => 'Session expired — refresh the page.'], 419);

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string)($in['action'] ?? '');
$cid    = (int)($in['cid'] ?? 0);
$month  = valid_month($in['month'] ?? '');
$me     = (int)$u['id'];

$isCreator = (bool)q("SELECT 1 FROM users WHERE id=? AND role='creator'", [$cid])->fetchColumn();
if (!$isCreator) json_out(['ok' => false, 'error' => 'Unknown creator'], 404);

$viewerActions = ['feedback_add', 'feedback_del'];
if (in_array($action, $viewerActions, true)) {
    if (!can_view_creator($u, $cid)) json_out(['ok' => false, 'error' => 'No access to this creator.'], 403);
} elseif (!can_edit_creator($u, $cid) && !($action === 'item_value' && is_staff($u))) {
    json_out(['ok' => false, 'error' => 'You can view this tracker but not edit it.'], 403);
}

$bad = fn(string $msg = 'Bad input') => json_out(['ok' => false, 'error' => $msg], 422);
$result = ['ok' => true];

try {
    switch ($action) {
        case 'item_value':
            $key = (string)($in['key'] ?? '');
            $item = plan_item($cid, $key);
            if (!$month || !$item) $bad('This item no longer exists. Refresh the tracker.');
            if ($item['kind'] === 'goal' && ($item['source'] ?? 'manual') !== 'manual') $bad('Update this goal through monthly numbers.');
            if ($item['kind'] === 'task' && !empty($item['children'])) $bad('Update the subtasks to change this task’s progress.');
            try { $value = typed_value($item['type'] ?? 'check', $in['value'] ?? ''); }
            catch (InvalidArgumentException $e) { $bad($e->getMessage()); }
            $progress = item_progress($item, $value);
            $st = $progress['status'];
            db()->beginTransaction();
            if ($item['kind'] === 'task') save_status($cid, $month, $key, $st);
            upsert('tracker_values', ['creator_id', 'month', 'item_key'], [
                'creator_id' => $cid, 'month' => $month, 'item_key' => $key, 'value' => $value, 'status' => $st,
            ]);
            log_activity($cid, $me, $item['kind'] === 'goal' ? 'goal_result' : 'status', $month, ($item['title'] ?? $item['t']) . ' → ' . STATUS_LABEL[$st]);
            db()->commit();
            $result['value'] = $value;
            $result['status'] = $st;
            $result['percent'] = progress_percent($progress['percent'], $st === 'cp');
            break;

        case 'status':
            $key = (string)($in['key'] ?? '');
            $st  = (string)($in['status'] ?? '');
            if (!$month || !in_array($key, milestone_keys($cid), true) || !in_array($st, STATUSES, true)) $bad();
            $item = plan_item($cid, $key);
            if (($item['type'] ?? 'check') !== 'check') $bad('Save a value with this task.');
            save_status($cid, $month, $key, $st);
            log_activity($cid, $me, 'status', $month, milestone_title($key, $cid) . ' → ' . STATUS_LABEL[$st]);
            break;

        case 'task_note':
            $key = (string)($in['key'] ?? '');
            $item = plan_item($cid, $key);
            if (!$month || !$item || $item['kind'] !== 'task') $bad('This task no longer exists. Refresh the tracker.');
            upsert('phase_notes', ['creator_id', 'month', 'phase_key'], [
                'creator_id'=>$cid, 'month'=>$month, 'phase_key'=>task_note_key($key),
                'notes'=>mb_substr((string)($in['notes'] ?? ''), 0, 5000),
            ]);
            log_activity($cid, $me, 'note', $month, $item['t']);
            break;

        case 'note':
            $phase = (string)($in['phase'] ?? '');
            if (!$month || !in_array($phase, array_column(phases($cid), 'key'), true)) $bad();
            upsert('phase_notes', ['creator_id', 'month', 'phase_key'], [
                'creator_id' => $cid, 'month' => $month, 'phase_key' => $phase,
                'notes' => mb_substr((string)($in['notes'] ?? ''), 0, 5000),
            ]);
            log_activity($cid, $me, 'note', $month, phase_name($phase, $cid));
            break;

        case 'entry':
            if (!$month) $bad('Bad month');
            $f  = $in['fields'] ?? [];
            $mt = (string)($f['meeting'] ?? '');
            upsert('monthly_entries', ['creator_id', 'month'], [
                'creator_id'     => $cid,
                'month'          => $month,
                'sponsor_rev'    => max(0, num_or_null($f['spon'] ?? 0) ?? 0),
                'info_rev'       => max(0, num_or_null($f['info'] ?? 0) ?? 0),
                'subscribers'    => ($s = num_or_null($f['subs'] ?? null, true)) === null ? null : max(0, $s),
                'income_streams' => ($n = num_or_null($f['streams'] ?? null, true)) === null ? null : max(0, $n),
                'last_meeting'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $mt) ? $mt : null,
                'updated_by'     => $me,
                'updated_at'     => now(),
            ]);
            log_activity($cid, $me, 'entry', $month);
            $result['meta'] = ['by' => $u['name'], 'ago' => 'just now'];
            break;

        case 'goals':
            json_out(['ok' => false, 'error' => 'Admins manage targets in Edit creator plan.'], 403);
        case 'feedback_add':
            $body = trim((string)($in['body'] ?? ''));
            if (!$month || $body === '') $bad('Write something first.');
            q('INSERT INTO feedback (creator_id, month, author_id, body, created_at) VALUES (?,?,?,?,?)',
              [$cid, $month, $me, mb_substr($body, 0, 3000), now()]);
            $id = (int)db()->lastInsertId();
            log_activity($cid, $me, 'feedback', $month);
            $row = q('SELECT f.*, u.name AS author_name, u.role AS author_role FROM feedback f LEFT JOIN users u ON u.id=f.author_id WHERE f.id=?', [$id])->fetch();
            $result['item'] = feedback_item($row, $u);
            break;

        case 'feedback_del':
            $fid = (int)($in['id'] ?? 0);
            $row = q('SELECT id, author_id FROM feedback WHERE id=? AND creator_id=?', [$fid, $cid])->fetch();
            if (!$row) $bad('Already deleted.');
            if ((int)$row['author_id'] !== $me && $u['role'] !== 'superadmin') json_out(['ok' => false, 'error' => 'You can only delete your own feedback.'], 403);
            q('DELETE FROM feedback WHERE id=?', [$fid]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('[fluence api] ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Could not save — try again.'], 500);
}

json_out($result);
