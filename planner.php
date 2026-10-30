<?php
require __DIR__ . '/inc/bootstrap.php';
$u = require_login(['superadmin', 'admin']);
$cid = (int)($_GET['id'] ?? 0);
$creator = q("SELECT id, name FROM users WHERE id=? AND role='creator'", [$cid])->fetch();
if (!$creator) { http_response_code(404); exit('Creator not found.'); }
$month = valid_month($_GET['m'] ?? '') ?? date('Y-m');
$plan = creator_plan($cid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if ((int)($_POST['revision'] ?? -1) !== $plan['revision']) throw new InvalidArgumentException('This plan changed in another tab. Refresh and try again.');
        $section = (string)($_POST['section'] ?? '');
        $act = (string)($_POST['act'] ?? '');
        $key = (string)($_POST['key'] ?? '');
        $parent = (string)($_POST['parent'] ?? '');
        if (!in_array($section, ['goals', 'phases', 'tasks'], true) || !in_array($act, ['add', 'edit', 'delete', 'up', 'down'], true)) throw new InvalidArgumentException('Invalid change.');
        $item = [];
        if (in_array($act, ['add', 'edit'], true)) {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 120) throw new InvalidArgumentException('Enter a title of 1–120 characters.');
            $type = (string)($_POST['type'] ?? 'check');
            if (!isset(PLAN_TYPES[$type])) throw new InvalidArgumentException('Choose a valid value type.');
            $target = typed_value($type, $_POST['target'] ?? '');
            $item = ['type' => $type, 'target' => $target, 'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 3000)];
            if ($section === 'goals') $item['title'] = $title;
            elseif ($section === 'phases') $item = ['name' => $title, 'short' => $title];
            else $item['t'] = $title;
        }
        $change = function (array &$items) use ($act, $key, $item, $section): void {
            if ($act === 'add') {
                $new = ['key' => 'custom_' . bin2hex(random_bytes(10))] + $item;
                if ($section === 'phases') $new['miles'] = [];
                if ($section === 'goals') $new['source'] = 'manual';
                $items[] = $new;
                return;
            }
            $index = array_search($key, array_column($items, 'key'), true);
            if ($index === false) throw new InvalidArgumentException('Item not found. Refresh the page.');
            if ($act === 'delete') array_splice($items, $index, 1);
            elseif ($act === 'edit') {
                if ($section === 'goals' && ($items[$index]['source'] ?? 'manual') !== 'manual' && $items[$index]['type'] !== $item['type']) {
                    throw new InvalidArgumentException('A goal linked to monthly numbers must keep its original type. Add a custom goal for another type.');
                }
                $items[$index] = array_replace($items[$index], $item);
            } else {
                $next = $index + ($act === 'up' ? -1 : 1);
                if (isset($items[$next])) [$items[$index], $items[$next]] = [$items[$next], $items[$index]];
            }
        };
        if ($section === 'tasks') {
            if (!edit_task_list($plan['phases'], $parent, $change)) throw new InvalidArgumentException('Parent task not found.');
        } else $change($plan[$section]);
        $revision = $plan['revision']; unset($plan['revision']);
        $json = json_encode($plan, JSON_THROW_ON_ERROR);
        if ($revision === 0) {
            q('INSERT INTO creator_plans (creator_id, definition, revision) VALUES (?,?,1)', [$cid, $json]);
        } else {
            $saved = q('UPDATE creator_plans SET definition=?, revision=revision+1 WHERE creator_id=? AND revision=?', [$json, $cid, $revision]);
            if (!$saved->rowCount()) throw new InvalidArgumentException('Another admin updated this plan. Refresh and try again.');
        }
        log_activity($cid, (int)$u['id'], 'plan', null, 'Updated destinations and path');
        flash('Creator plan saved.');
        redirect('planner.php?id=' . $cid . '&m=' . $month);
    } catch (InvalidArgumentException $e) { flash($e->getMessage(), 'err'); redirect('planner.php?id=' . $cid . '&m=' . $month); }
}

function plan_form(array $plan, string $section, string $parent = '', ?array $item = null): void
{
    $title = $item['title'] ?? $item['name'] ?? $item['t'] ?? '';
    ?>
    <form method="post" class="form plan-form">
      <?= csrf_field() ?>
      <input type="hidden" name="revision" value="<?= $plan['revision'] ?>">
      <input type="hidden" name="section" value="<?= e($section) ?>">
      <input type="hidden" name="parent" value="<?= e($parent) ?>">
      <input type="hidden" name="key" value="<?= e($item['key'] ?? '') ?>">
      <label>Title<input name="title" value="<?= e($title) ?>" maxlength="120" required></label>
      <?php if ($section !== 'phases'): ?>
      <div class="plan-fields">
        <label>Value type<select name="type"><?php foreach (PLAN_TYPES as $key => $label): ?><option value="<?= $key ?>" <?= ($item['type'] ?? 'check') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Target / expected result <small>(optional)</small><input name="target" value="<?= e($item['target'] ?? '') ?>" maxlength="3000"></label>
      </div>
      <label>Instructions <small>(optional)</small><textarea name="description" rows="2" maxlength="3000"><?= e($item['description'] ?? '') ?></textarea></label>
      <?php if (!empty($item['source']) && $item['source'] !== 'manual'): ?><p class="hint">Result comes from monthly numbers.</p><?php endif; ?>
      <?php endif; ?>
      <div class="plan-actions">
        <button class="btn primary sm" name="act" value="<?= $item ? 'edit' : 'add' ?>"><?= $item ? 'Save changes' : 'Add ' . ($section === 'goals' ? 'goal' : ($section === 'phases' ? 'phase' : 'task')) ?></button>
        <?php if ($item): ?>
        <button class="btn sm" name="act" value="up" formnovalidate aria-label="Move up">↑</button>
        <button class="btn sm" name="act" value="down" formnovalidate aria-label="Move down">↓</button>
        <button class="btn danger sm" name="act" value="delete" formnovalidate onclick="return confirm('Remove this item and its subtasks from the plan? Saved history will be retained.')">Remove</button>
        <?php endif; ?>
      </div>
    </form>
    <?php
}

function plan_tasks(array $plan, array $nodes, string $parent): void
{
    foreach ($nodes as $node) { ?>
      <details class="plan-node"><summary><?= e($node['t']) ?><span class="muted"><?= e(PLAN_TYPES[$node['type'] ?? 'check']) ?> · <?= count($node['children'] ?? []) ?> subtasks</span></summary>
        <div class="plan-node-body"><?php plan_form($plan, 'tasks', $parent, $node); plan_tasks($plan, $node['children'] ?? [], $node['key']); ?></div>
      </details>
    <?php }
    ?>
    <details class="plan-add"><summary>+ Add <?= str_starts_with($parent, 'custom_') ? 'task / subtask' : 'task' ?></summary><?php plan_form($plan, 'tasks', $parent); ?></details>
    <?php
}

page_header('Edit creator plan', 'dashboard');
?>
<a class="back" href="creator.php?id=<?= $cid ?>&m=<?= e($month) ?>"><?= icon('back') ?>Back to tracker</a>
<div class="dash-head"><div><p class="eyebrow">Creator plan</p><h1><?= e($creator['name']) ?></h1><p class="sub">Set their destinations and build their path. Creators enter results and mark tasks complete.</p></div></div>
<div class="note-bar info">Changes apply to this creator across all months. Progress is based on the smallest tasks; parent tasks summarize their subtasks.</div>
<section class="sec" id="destinations"><div class="section-h"><h2>Destination goals</h2></div>
<?php foreach ($plan['goals'] as $goal): ?>
<details class="plan-node"><summary><?= e($goal['title']) ?><span class="muted"><?= e(PLAN_TYPES[$goal['type']]) ?></span></summary><div class="plan-node-body"><?php plan_form($plan, 'goals', '', $goal); ?></div></details>
<?php endforeach; ?>
<details class="plan-add"><summary>+ Add destination goal</summary><?php plan_form($plan, 'goals'); ?></details></section>
<section class="sec"><div class="section-h"><h2>Path phases</h2></div>
<?php foreach ($plan['phases'] as $phase): ?>
<details class="plan-node plan-phase"><summary><?= e($phase['name']) ?><span class="muted"><?= count($phase['miles']) ?> tasks</span></summary><div class="plan-node-body"><?php plan_form($plan, 'phases', '', $phase); plan_tasks($plan, $phase['miles'], $phase['key']); ?></div></details>
<?php endforeach; ?>
<details class="plan-add"><summary>+ Add phase</summary><?php plan_form($plan, 'phases'); ?></details></section>
<?php page_footer(); ?>
