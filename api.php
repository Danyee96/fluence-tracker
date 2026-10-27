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
} elseif (!can_edit_creator($u, $cid)) {
    json_out(['ok' => false, 'error' => 'You can view this tracker but not edit it.'], 403);
}

$bad = fn(string $msg = 'Bad input') => json_out(['ok' => false, 'error' => $msg], 422);
$result = ['ok' => true];

try {
    switch ($action) {
        case 'status':
            $key = (string)($in['key'] ?? '');
            $st  = (string)($in['status'] ?? '');
            if (!$month || !in_array($key, milestone_keys(), true) || !in_array($st, STATUSES, true)) $bad();
            save_status($cid, $month, $key, $st);
            log_activity($cid, $me, 'status', $month, milestone_title($key) . ' → ' . STATUS_LABEL[$st]);
            break;

        case 'note':
            $phase = (string)($in['phase'] ?? '');
            if (!$month || !in_array($phase, array_column(phases(), 'key'), true)) $bad();
            upsert('phase_notes', ['creator_id', 'month', 'phase_key'], [
                'creator_id' => $cid, 'month' => $month, 'phase_key' => $phase,
                'notes' => mb_substr((string)($in['notes'] ?? ''), 0, 5000),
            ]);
            log_activity($cid, $me, 'note', $month, phase_name($phase));
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
                'hours_week'     => ($h = num_or_null($f['hours'] ?? null)) === null ? null : max(0, min(168, $h)),
                'income_streams' => ($n = num_or_null($f['streams'] ?? null, true)) === null ? null : max(0, $n),
                'last_meeting'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $mt) ? $mt : null,
                'updated_by'     => $me,
                'updated_at'     => now(),
            ]);
            log_activity($cid, $me, 'entry', $month);
            $result['meta'] = ['by' => $u['name'], 'ago' => 'just now'];
            break;

        case 'goals':
            $g = $in['goals'] ?? [];
            upsert('creator_goals', ['creator_id'], [
                'creator_id'     => $cid,
                'target_revenue' => max(0, num_or_null($g['target_revenue'] ?? 0) ?? 0),
                'target_subs'    => max(0, num_or_null($g['target_subs'] ?? 0, true) ?? 0),
                'target_hours'   => max(0, num_or_null($g['target_hours'] ?? 0) ?? 0),
                'target_streams' => max(0, num_or_null($g['target_streams'] ?? 0, true) ?? 0),
                'updated_at'     => now(),
            ]);
            log_activity($cid, $me, 'goals', null);
            break;

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
