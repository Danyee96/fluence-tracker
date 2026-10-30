<?php
defined('FL') or exit;

const PLAN_TYPES = ['check' => 'Checkbox', 'text' => 'Text', 'money' => 'Money / budget', 'number' => 'Number'];

function plan_schema(bool $sqlite): array
{
    $id = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $text = $sqlite ? 'TEXT' : 'LONGTEXT';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return [
        "CREATE TABLE IF NOT EXISTS creator_plans (creator_id $id PRIMARY KEY, definition $text NOT NULL, revision INTEGER NOT NULL DEFAULT 1, FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
        "CREATE TABLE IF NOT EXISTS tracker_values (creator_id $id NOT NULL, month CHAR(7) NOT NULL, item_key VARCHAR(60) NOT NULL, value TEXT NULL, status VARCHAR(4) NOT NULL DEFAULT 'todo', PRIMARY KEY (creator_id, month, item_key), FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE)$engine",
    ];
}

function creator_plan(int $cid): array
{
    $row = q('SELECT definition, revision FROM creator_plans WHERE creator_id=?', [$cid])->fetch();
    if ($row) {
        $plan = json_decode($row['definition'], true, 512, JSON_THROW_ON_ERROR);
        // Retire the original Scale phase from saved plans; keep historical results intact.
        $plan['phases'] = array_values(array_filter($plan['phases'], static fn(array $phase): bool => $phase['key'] !== 'scale'));
        // Refresh the original sponsor wording without replacing custom admin titles.
        $labels = [
            'recurring_sponsor' => ['First recurring sponsor', 'Recurring Retainer Sponsors'],
            'affiliates' => ['Affiliate deals set up', 'Affiliate Sponsors'],
            'one_off' => ['One-off placements', 'One Off Deal Flow'],
        ];
        foreach ($plan['phases'] as &$phase) {
            if ($phase['key'] !== 'sponsors') continue;
            foreach ($phase['miles'] as &$task) {
                $label = $labels[$task['key']] ?? null;
                if ($label && $task['t'] === $label[0]) $task['t'] = $label[1];
            }
            unset($task);
        }
        unset($phase);
        return $plan + ['revision' => (int)$row['revision']];
    }
    $g = get_goals($cid);
    return ['revision' => 0, 'goals' => [
        ['key' => 'target_revenue', 'title' => 'Monthly revenue', 'type' => 'money', 'target' => (string)$g['target_revenue'], 'source' => 'revenue', 'description' => ''],
        ['key' => 'target_subs', 'title' => 'Subscribers', 'type' => 'number', 'target' => (string)$g['target_subs'], 'source' => 'subs', 'description' => ''],
        ['key' => 'target_streams', 'title' => 'Income streams', 'type' => 'number', 'target' => (string)$g['target_streams'], 'source' => 'streams', 'description' => ''],
    ], 'phases' => require __DIR__ . '/phases.php'];
}

function task_nodes(array $nodes): array
{
    $out = [];
    foreach ($nodes as $node) {
        $out[] = $node;
        array_push($out, ...task_nodes($node['children'] ?? []));
    }
    return $out;
}

function task_leaves(array $nodes): array
{
    $out = [];
    foreach ($nodes as $node) {
        if (!empty($node['children'])) array_push($out, ...task_leaves($node['children']));
        else $out[] = $node;
    }
    return $out;
}

function plan_item(int $cid, string $key): ?array
{
    $plan = creator_plan($cid);
    foreach ($plan['goals'] as $goal) if ($goal['key'] === $key) return $goal + ['kind' => 'goal'];
    foreach ($plan['phases'] as $phase) foreach (task_nodes($phase['miles']) as $node) {
        if ($node['key'] === $key) return $node + ['kind' => 'task'];
    }
    return null;
}

function tracker_values(int $cid, string $month): array
{
    // Goal results are monthly. Task evidence carries forward independently.
    $rows = q('SELECT item_key, value, status, month FROM tracker_values WHERE creator_id=? AND month<=? ORDER BY month', [$cid, $month])->fetchAll();
    $goalKeys = array_column(creator_plan($cid)['goals'], 'key');
    $out = [];
    foreach ($rows as $row) {
        if (in_array($row['item_key'], $goalKeys, true) && $row['month'] !== $month) continue;
        $out[$row['item_key']] = ['value' => $row['value'], 'status' => $row['status']];
    }
    return $out;
}

function typed_value(string $type, mixed $raw): string
{
    if (!is_scalar($raw) && $raw !== null) throw new InvalidArgumentException('Enter a valid value.');
    $value = trim((string)$raw);
    if (mb_strlen($value) > 3000) throw new InvalidArgumentException('Use 3,000 characters or fewer.');
    if (in_array($type, ['money', 'number'], true) && $value !== '') {
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0 || (float)$value > 999999999999) {
            throw new InvalidArgumentException('Enter a positive number or zero (up to 999,999,999,999).');
        }
        $value = (string)round((float)$value, 2);
    }
    if ($type === 'check') {
        if (!in_array($raw, [true, false, 0, 1, '0', '1', '', null], true)) throw new InvalidArgumentException('Choose checked or unchecked.');
        return in_array($raw, [true, 1, '1'], true) ? '1' : '0';
    }
    return $value;
}

/** Apply a callback to one sibling list, preserving stable keys and other branches. */
function edit_task_list(array &$phases, string $parent, callable $edit): bool
{
    $walk = function (array &$nodes) use (&$walk, $parent, $edit): bool {
        foreach ($nodes as &$node) {
            if ($node['key'] === $parent) { $node['children'] ??= []; $edit($node['children']); return true; }
            if (!empty($node['children']) && $walk($node['children'])) return true;
        }
        return false;
    };
    foreach ($phases as &$phase) {
        if ($phase['key'] === $parent) { $edit($phase['miles']); return true; }
        if ($walk($phase['miles'])) return true;
    }
    return false;
}

/** Shared automatic status rules for values, dashboard progress and tracker responses. */
function item_progress(array $item, mixed $value, string $legacy = 'todo'): array
{
    $type = $item['type'] ?? 'check';
    if ($type === 'check') {
        if ($value === null || $value === '') return ['status'=>$legacy, 'percent'=>$legacy === 'cp' ? 100.0 : 0.0];
        $done = in_array($value, [true, 1, '1'], true);
        return ['status'=>$done ? 'cp' : 'todo', 'percent'=>$done ? 100.0 : 0.0];
    }
    if ($value === null && $legacy === 'sk') return ['status'=>'sk', 'percent'=>0.0];
    $value = trim((string)($value ?? ''));
    if ($value === '' || (in_array($type, ['money','number'], true) && (float)$value <= 0)) return ['status'=>'todo', 'percent'=>0.0];
    $target = trim((string)($item['target'] ?? ''));
    if ($type === 'text') {
        return ['status'=>'cp', 'percent'=>100.0];
    }
    if ($target === '' || (float)$target <= 0) return ['status'=>'ip', 'percent'=>0.0];
    $percent = min(100.0, (float)$value / (float)$target * 100);
    return ['status'=>$percent >= 100 ? 'cp' : 'ip', 'percent'=>$percent];
}

function progress_percent(float $percent, bool $complete): int
{
    return $complete ? 100 : min(99, (int)round($percent));
}

/** Read-only Destination summaries for the selected dashboard month. */
function creator_goal_progress(int $cid, string $month, ?array $entry = null, ?array $values = null): array
{
    $values ??= tracker_values($cid, $month);
    $goals = [];
    foreach (creator_plan($cid)['goals'] as $goal) {
        $source = $goal['source'] ?? 'manual';
        $saved = $values[$goal['key']] ?? [];
        $value = match ($source) {
            'revenue' => $entry ? (float)$entry['sponsor_rev'] + (float)$entry['info_rev'] : null,
            'subs' => $entry['subscribers'] ?? null,
            'streams' => $entry['income_streams'] ?? null,
            default => $saved['value'] ?? null,
        };
        $progress = item_progress($goal, $value, $saved['status'] ?? 'todo');
        $goals[] = [
            'key'=>$goal['key'], 'title'=>$goal['title'], 'type'=>$goal['type'],
            'current'=>$value, 'target'=>$goal['target'] ?? '',
            'status'=>$progress['status'], 'percent'=>progress_percent($progress['percent'], $progress['status'] === 'cp'),
            'measurable'=>!in_array($goal['type'], ['money','number'], true) || (float)($goal['target'] ?? 0) > 0,
        ];
    }
    return $goals;
}

/** Keep task notes separate from phase notes within the existing 60-character key. */
function task_note_key(string $key): string
{
    return 'task_' . sha1($key);
}
