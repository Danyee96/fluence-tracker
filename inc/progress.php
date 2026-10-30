<?php
defined('FL') or exit;

const STATUSES = ['todo', 'ip', 'cp', 'sk'];
const STATUS_LABEL = ['todo' => 'To do', 'ip' => 'In progress', 'cp' => 'Complete', 'sk' => 'Skipped'];

function phases(?int $cid = null): array
{
    if ($cid !== null) return creator_plan($cid)['phases'];
    static $p = null;
    return $p ??= require __DIR__ . '/phases.php';
}

function milestone_keys(?int $cid = null): array
{
    $keys = [];
    foreach (phases($cid) as $p) foreach (task_leaves($p['miles']) as $m) $keys[] = $m['key'];
    return $keys;
}

function milestone_title(string $key, ?int $cid = null): string
{
    foreach (phases($cid) as $p) foreach (task_nodes($p['miles']) as $m) if ($m['key'] === $key) return $m['t'];
    return $key;
}

function phase_name(string $key, ?int $cid = null): string
{
    foreach (phases($cid) as $p) if ($p['key'] === $key) return $p['name'];
    return $key;
}

/* ---------- step status (carries forward month to month) ---------- */

/** Returns [statusMap, inheritedFromMonth|null]. */
function get_statuses(int $cid, string $month): array
{
    $rows = q('SELECT milestone_key, status FROM milestone_status WHERE creator_id=? AND month=?', [$cid, $month])->fetchAll();
    $from = null;
    if (!$rows) {
        $from = q('SELECT MAX(month) FROM milestone_status WHERE creator_id=? AND month<?', [$cid, $month])->fetchColumn() ?: null;
        if ($from) $rows = q('SELECT milestone_key, status FROM milestone_status WHERE creator_id=? AND month=?', [$cid, $from])->fetchAll();
    }
    $map = [];
    foreach ($rows as $r) $map[$r['milestone_key']] = $r['status'];
    $values = tracker_values($cid, $month);
    foreach (phases($cid) as $phase) foreach (task_leaves($phase['miles']) as $item) {
        $key = $item['key'];
        $map[$key] = item_progress($item, $values[$key]['value'] ?? null, $map[$key] ?? 'todo')['status'];
    }
    return [$map, $from];
}

/** Write a full status snapshot for the month (so it stops inheriting). */
function save_status(int $cid, string $month, string $key, string $status): void
{
    [$map] = get_statuses($cid, $month);
    $map[$key] = $status;
    $ownsTransaction = !db()->inTransaction();
    if ($ownsTransaction) db()->beginTransaction();
    foreach (milestone_keys($cid) as $k) {
        upsert('milestone_status', ['creator_id', 'month', 'milestone_key'], [
            'creator_id' => $cid, 'month' => $month, 'milestone_key' => $k, 'status' => $map[$k] ?? 'todo',
        ]);
    }
    if ($ownsTransaction) db()->commit();
}

/** Phase %, overall %, and the current phase. */
function compute_progress(array $map, ?int $cid = null, ?array $values = null): array
{
    $values ??= [];
    $out = []; $total = 0; $sum = 0.0; $doneTotal = 0; $current = null; $anyStarted = false;
    foreach (phases($cid) as $i => $phase) {
        $done = 0; $active = 0; $points = 0.0; $started = false;
        foreach (task_leaves($phase['miles']) as $item) {
            $key = $item['key'];
            $p = item_progress($item, $values[$key]['value'] ?? null, $map[$key] ?? 'todo');
            if ($p['status'] === 'sk') continue;
            $active++; $points += $p['percent'];
            if ($p['status'] === 'cp') $done++;
            if ($p['status'] !== 'todo') $started = true;
        }
        $complete = (bool)$phase['miles'] && ($active === 0 || $done === $active);
        $status = $complete ? 'cp' : ($started ? 'ip' : 'ns');
        $percent = $active ? progress_percent($points / $active, $complete) : ($complete ? 100 : 0);
        if ($status !== 'ns') $anyStarted = true;
        if ($current === null && !$complete) $current = $i;
        $out[] = ['key'=>$phase['key'], 'label'=>$phase['short'], 'pct'=>$percent, 'status'=>$status];
        $total += $active; $sum += $points; $doneTotal += $done;
    }
    $overall = $total ? progress_percent($sum / $total, $doneTotal === $total) : ($out && $current === null ? 100 : 0);
    if ($current === null && $out) $cur = ['k'=>'done','label'=>'Complete','pct'=>100];
    elseif (!$out || !$anyStarted) $cur = ['k'=>'new','label'=>'Not started','pct'=>0];
    else $cur = ['k'=>$out[$current]['key'],'label'=>$out[$current]['label'],'pct'=>$out[$current]['pct']];
    return ['phases'=>$out,'overall'=>$overall,'current'=>$cur];
}

/* ---------- entries & goals ---------- */

function get_entry(int $cid, string $month): ?array
{
    $r = q('SELECT * FROM monthly_entries WHERE creator_id=? AND month=?', [$cid, $month])->fetch();
    return $r ?: null;
}

function entry_js(?array $r): ?array
{
    if (!$r) return null;
    return [
        'month'   => $r['month'],
        'spon'    => (float)$r['sponsor_rev'],
        'info'    => (float)$r['info_rev'],
        'subs'    => $r['subscribers'] !== null ? (int)$r['subscribers'] : null,
        'streams' => $r['income_streams'] !== null ? (int)$r['income_streams'] : null,
        'meeting' => $r['last_meeting'] ?: null,
    ];
}

function get_goals(int $cid): array
{
    $sql = 'SELECT target_revenue, target_subs, target_streams FROM creator_goals WHERE creator_id=?';
    $g = q($sql, [$cid])->fetch();
    if (!$g) {
        upsert('creator_goals', ['creator_id'], ['creator_id' => $cid, 'updated_at' => now()]);
        $g = q($sql, [$cid])->fetch();
    }
    return array_map('floatval', $g);
}

/* ---------- activity log ---------- */

/** Logs an action; repeated edits of the same kind within 15 min collapse into one line. */
function log_activity(int $cid, int $actor, string $action, ?string $month, string $detail = ''): void
{
    if (!empty($_SESSION['impersonation']['actor_id'])) $actor=(int)$_SESSION['impersonation']['actor_id'];
    $last = q('SELECT id, created_at FROM activity WHERE creator_id=? AND actor_id=? AND action=? AND month' . ($month === null ? ' IS NULL' : '=?') . ' ORDER BY id DESC LIMIT 1',
        array_values(array_filter([$cid, $actor, $action, $month], fn($v) => $v !== null)))->fetch();
    if ($last && strtotime($last['created_at']) > time() - 900) {
        q('UPDATE activity SET created_at=?, detail=? WHERE id=?', [now(), $detail, $last['id']]);
        return;
    }
    q('INSERT INTO activity (creator_id, actor_id, month, action, detail, created_at) VALUES (?,?,?,?,?,?)',
      [$cid, $actor, $month, $action, $detail, now()]);
}

function activity_verb(array $r): string
{
    $m = $r['month'] ? month_name($r['month']) : '';
    return match ($r['action']) {
        'entry'    => "updated $m numbers",
        'status'   => 'moved a step' . ($r['detail'] ? ': ' . $r['detail'] : ''),
        'note'     => 'edited ' . ($r['detail'] ?: 'phase') . " notes ($m)",
        'goals'    => 'changed goal targets',
        'profile' => 'updated creator details',
        'impersonation' => $r['detail'] ?: 'opened a creator account',
        'meeting' => $r['detail'] ?: 'updated a meeting log',
        'social' => 'updated social channels' . ($r['detail'] ? ': ' . $r['detail'] : ''),
        'plan' => 'updated destinations and path',
        'goal_result' => 'updated a destination result' . ($r['detail'] ? ': ' . $r['detail'] : ''),
        'feedback' => "left feedback on $m",
        default    => $r['action'],
    };
}

function activity_feed(?int $cid = null, int $limit = 12): array
{
    $sql = 'SELECT a.*, ac.name AS actor_name, ac.role AS actor_role, c.name AS creator_name
            FROM activity a LEFT JOIN users ac ON ac.id = a.actor_id JOIN users c ON c.id = a.creator_id'
         . ($cid ? ' WHERE a.creator_id = ?' : '') . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int)$limit;
    $rows = q($sql, $cid ? [$cid] : [])->fetchAll();
    return array_map(fn($r) => [
        'actor'     => $r['actor_name'] ?: 'Removed user',
        'actorId'   => (int)$r['actor_id'],
        'actorRole' => $r['actor_role'] ?: '',
        'creator'   => $r['creator_name'],
        'creatorId' => (int)$r['creator_id'],
        'self'      => (int)$r['actor_id'] === (int)$r['creator_id'],
        'text'      => activity_verb($r),
        'month'     => $r['month'],
        'ago'       => time_ago($r['created_at']),
    ], $rows);
}

/* ---------- feedback ---------- */

function feedback_item(array $r, array $me): array
{
    return [
        'id'        => (int)$r['id'],
        'body'      => $r['body'],
        'author'    => $r['author_name'] ?: 'Removed user',
        'authorId'  => (int)$r['author_id'],
        'role'      => $r['author_role'] ?: '',
        'initials'  => initials($r['author_name'] ?: '?'),
        'hue'       => hue((int)$r['author_id']),
        'ago'       => time_ago($r['created_at']),
        'canDelete' => (int)$r['author_id'] === (int)$me['id'] || $me['role'] === 'superadmin',
    ];
}

function feedback_list(int $cid, string $month, array $me): array
{
    $rows = q('SELECT f.*, u.name AS author_name, u.role AS author_role FROM feedback f LEFT JOIN users u ON u.id = f.author_id
               WHERE f.creator_id=? AND f.month=? ORDER BY f.created_at, f.id', [$cid, $month])->fetchAll();
    return array_map(fn($r) => feedback_item($r, $me), $rows);
}

/* ---------- dashboard ---------- */

function dashboard_data(string $month): array
{
    $creators = q("SELECT id, name, email, is_active, handle, niche, start_month FROM users WHERE role='creator' ORDER BY name")->fetchAll();
    $from = month_shift($month, -5);
    $prevMonth = month_shift($month, -1);

    // 6-month window of entries for sparklines + month-over-month totals
    $win = [];
    foreach (q('SELECT creator_id, month, sponsor_rev, info_rev, subscribers FROM monthly_entries WHERE month BETWEEN ? AND ?', [$from, $month])->fetchAll() as $r) {
        $win[(int)$r['creator_id']][$r['month']] = ['rev' => (float)$r['sponsor_rev'] + (float)$r['info_rev'], 'subs' => $r['subscribers'] !== null ? (int)$r['subscribers'] : null];
    }
    $updated = [];
    foreach (q('SELECT creator_id, MAX(created_at) t FROM activity GROUP BY creator_id')->fetchAll() as $r) $updated[(int)$r['creator_id']] = $r['t'];
    $fb = [];
    foreach (q('SELECT creator_id, COUNT(*) c FROM feedback WHERE month=? GROUP BY creator_id', [$month])->fetchAll() as $r) $fb[(int)$r['creator_id']] = (int)$r['c'];

    $months = [];
    for ($i = 5; $i >= 0; $i--) $months[] = month_shift($month, -$i);

    $cards = [];
    $kpi = ['rev' => 0, 'revPrev' => 0, 'subGrowth' => 0, 'logged' => 0, 'active' => 0, 'progressSum' => 0, 'phaseCounts' => []];
    foreach ($creators as $c) {
        $cid = (int)$c['id'];
        [$map] = get_statuses($cid, $month);
        $values = tracker_values($cid, $month);
        $prog  = compute_progress($map, $cid, $values);
        $entry = get_entry($cid, $month);

        $rev = $entry ? (float)$entry['sponsor_rev'] + (float)$entry['info_rev'] : null;
        $subG = null;
        if ($entry && $entry['subscribers'] !== null) {
            $prev = q('SELECT subscribers FROM monthly_entries WHERE creator_id=? AND month<? AND subscribers IS NOT NULL ORDER BY month DESC LIMIT 1', [$cid, $month])->fetchColumn();
            $subG = $prev !== false ? (int)$entry['subscribers'] - (int)$prev : null;
        }
        $lm = q('SELECT last_meeting FROM monthly_entries WHERE creator_id=? AND month<=? AND last_meeting IS NOT NULL ORDER BY month DESC LIMIT 1', [$cid, $month])->fetchColumn();

        $loggedMeeting = q('SELECT MAX(meeting_date) FROM creator_meetings WHERE creator_id=? AND meeting_date<=?', [$cid, date('Y-m-t', strtotime($month . '-01'))])->fetchColumn();
        if ($loggedMeeting && (!$lm || $loggedMeeting > $lm)) $lm = $loggedMeeting;

        $spark = array_map(fn($m) => $win[$cid][$m]['rev'] ?? null, $months);
        $sparkSubs = array_map(fn($m) => $win[$cid][$m]['subs'] ?? null, $months);
        $programMonth = $c['start_month'] ? months_between($c['start_month'], $month) + 1 : null;

        $active = (int)$c['is_active'] === 1;
        if ($active) {
            $kpi['active']++;
            $kpi['progressSum'] += $prog['overall'];
            $kpi['phaseCounts'][$prog['current']['k']] = ($kpi['phaseCounts'][$prog['current']['k']] ?? 0) + 1;
            if ($entry) { $kpi['logged']++; $kpi['rev'] += $rev; }
            $kpi['revPrev'] += $win[$cid][$prevMonth]['rev'] ?? 0;
            if ($subG !== null) $kpi['subGrowth'] += $subG;
        }

        $cards[] = [
            'id'           => $cid,
            'name'         => $c['name'],
            'email'        => $c['email'],
            'handle'       => $c['handle'],
            'niche'        => $c['niche'],
            'initials'     => initials($c['name']),
            'hue'          => hue($cid),
            'active'       => $active,
            'phases'       => $prog['phases'],
            'socials'      => social_dashboard_data($cid),
            'goals'        => creator_goal_progress($cid, $month, $entry, $values),
            'overall'      => $prog['overall'],
            'current'      => $prog['current'],
            'rev'          => $rev,
            'subs'         => $entry && $entry['subscribers'] !== null ? (int)$entry['subscribers'] : null,
            'subGrowth'    => $subG,
            'lastMeeting'  => $lm ? short_date($lm) : null,
            'logged'       => (bool)$entry,
            'spark'        => $spark,
            'sparkSubs'    => $sparkSubs,
            'sparkLabels'  => array_map(fn($m) => month_label($m, true), $months),
            'programMonth' => $programMonth && $programMonth > 0 ? $programMonth : null,
            'feedback'     => $fb[$cid] ?? 0,
            'updatedAt'    => isset($updated[$cid]) ? strtotime($updated[$cid]) : 0,
            'updatedAgo'   => isset($updated[$cid]) ? time_ago($updated[$cid]) : null,
            'url'          => 'creator.php?id=' . $cid . '&m=' . $month,
        ];
    }
    $kpi['avgProgress'] = $kpi['active'] ? (int)round($kpi['progressSum'] / $kpi['active']) : 0;
    unset($kpi['progressSum']);
    return ['cards' => $cards, 'kpi' => $kpi];
}
