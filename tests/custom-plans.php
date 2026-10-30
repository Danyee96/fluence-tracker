<?php
/** Run: php tests/custom-plans.php — isolated in-memory SQLite, no application data changed. */
define('FL', true);
$CONFIG = ['db' => ['driver' => 'sqlite', 'sqlite_path' => ':memory:'], 'admin_can_edit' => false];
// Optional local MySQL verification: FL_TEST_MYSQL=1 php tests/custom-plans.php
if (getenv('FL_TEST_MYSQL') === '1') {
    $host = getenv('FL_TEST_DB_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('FL_TEST_DB_PORT') ?: 8889);
    $user = getenv('FL_TEST_DB_USER') ?: 'root';
    $pass = getenv('FL_TEST_DB_PASS') ?: 'root';
    $server = new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $database = 'fluence_test_' . bin2hex(random_bytes(6));
    $server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4");
    register_shutdown_function(function () use ($server, $database) { $server->exec("DROP DATABASE `$database`"); });
    $CONFIG['db'] = ['driver'=>'mysql', 'host'=>$host, 'port'=>$port, 'name'=>$database, 'user'=>$user, 'pass'=>$pass];
}

foreach (['db', 'plans', 'social', 'meetings', 'creator-details', 'schema', 'helpers', 'auth', 'progress'] as $file) require __DIR__ . '/../inc/' . $file . '.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
migrate(); migrate();
foreach (['One', 'Two'] as $name) q("INSERT INTO users (name,email,password_hash,role,created_at) VALUES (?,?,?,'creator',?)", [$name, $name . '@test.local', 'unused', now()]);
$default = creator_plan(1);
check(count($default['goals']) === 3 && count($default['phases']) === 3, 'Legacy defaults missing');
check(count(milestone_keys(1)) === 10, 'Legacy keys changed');
save_status(1, '2026-09', 'audio', 'cp');
[$map, $from] = get_statuses(1, '2026-10');
check($from === '2026-09' && $map['audio'] === 'cp', 'Legacy carry-forward broken');
// Previously saved plans must also omit the retired phase without deleting its history.
$legacy = $default;
$legacy['phases'][] = ['key'=>'scale','name'=>'Scale & Systemize','miles'=>[['key'=>'systems','t'=>'Systems & team in place']]];
q('INSERT INTO creator_plans (creator_id,definition) VALUES (?,?)', [2,json_encode($legacy)]);
upsert('tracker_values',['creator_id','month','item_key'],['creator_id'=>2,'month'=>'2026-10','item_key'=>'systems','value'=>'1','status'=>'cp']);
check(count(creator_plan(2)['phases']) === 3 && plan_item(2,'systems') === null, 'Saved Scale phase still visible');
check(compute_progress(['audio'=>'cp','systems'=>'cp'],2)['overall'] === 10, 'Retired phase affects progress');
check(q("SELECT value FROM tracker_values WHERE creator_id=2 AND item_key='systems'")->fetchColumn() === '1', 'Historical Scale results lost');
q('DELETE FROM creator_plans WHERE creator_id=2');
$plan = $default; unset($plan['revision']);
$plan['goals'][] = ['key' => 'budget', 'title' => 'Budget', 'type' => 'money', 'target' => '500', 'source' => 'manual'];
$plan['phases'] = [['key' => 'custom_phase', 'name' => 'Launch', 'short' => 'Launch', 'miles' => [
 ['key' => 'parent', 't' => 'Launch offer', 'children' => [
  ['key' => 'revenue', 't' => 'Sales', 'type' => 'money', 'target'=>'1000'],
  ['key' => 'nested', 't' => 'Marketing', 'children' => [['key' => 'leads', 't' => 'Leads', 'type' => 'number', 'target'=>'10']]],
 ]],
]]];
q('INSERT INTO creator_plans (creator_id,definition) VALUES (?,?)', [1, json_encode($plan)]);
check(milestone_keys(1) === ['revenue', 'leads'], 'Nested progress must count leaves only');
check(plan_item(1, 'leads')['kind'] === 'task' && plan_item(2, 'leads') === null, 'Creator isolation broken');
check(count(creator_plan(2)['phases']) === 3, 'Customization affected another creator');
check(compute_progress(['revenue' => 'cp'], 1, ['revenue'=>['value'=>'1000']])['overall'] === 50, 'Nested progress calculation failed');
check(compute_progress(['revenue' => 'cp', 'leads' => 'sk'], 1, ['revenue'=>['value'=>'1000']])['overall'] === 100, 'Skipped tasks calculation failed');
check(typed_value('money', '12.345') === '12.35' && typed_value('number', '0') === '0', 'Numeric validation failed');
foreach (['-1', 'NaN', '5abc', '1e99', []] as $bad) {
    try { typed_value('number', $bad); throw new RuntimeException('Invalid value accepted'); }
    catch (InvalidArgumentException $expected) {}
}
upsert('tracker_values', ['creator_id','month','item_key'], ['creator_id'=>1,'month'=>'2026-09','item_key'=>'budget','value'=>'100','status'=>'ip']);
upsert('tracker_values', ['creator_id','month','item_key'], ['creator_id'=>1,'month'=>'2026-09','item_key'=>'revenue','value'=>'250','status'=>'cp']);
$values = tracker_values(1, '2026-10');
check(!isset($values['budget']) && $values['revenue']['value'] === '250', 'Goal/task month semantics failed');
db()->beginTransaction(); save_status(1, '2026-10', 'revenue', 'cp'); db()->rollBack();
check(!q('SELECT 1 FROM milestone_status WHERE month=?', ['2026-10'])->fetchColumn(), 'Task transaction not atomic');
check(edit_task_list($plan['phases'], 'nested', function (&$nodes) { $nodes[] = ['key'=>'extra','t'=>'Extra']; }), 'Nested editing failed');
check(count(task_leaves($plan['phases'][0]['miles'])) === 3, 'Nested insertion failed');
$plan['phases'] = [['key'=>'empty', 'name'=>'Empty','short'=>'Empty','miles'=>[]]];
q('UPDATE creator_plans SET definition=? WHERE creator_id=1', [json_encode($plan)]);
check(compute_progress([], 1)['current']['k'] === 'new', 'Empty phase treated as complete');
$plan['phases'] = [];
q('UPDATE creator_plans SET definition=? WHERE creator_id=1', [json_encode($plan)]);
check(compute_progress([], 1)['overall'] === 0, 'Empty plan failed');
check(!can_edit_creator(['id'=>3,'role'=>'creator'], 1) && !can_view_creator(['id'=>3,'role'=>'creator'], 1), 'Creator ownership failed');
check(!can_edit_creator(['id'=>3,'role'=>'admin'], 1) && is_staff(['role'=>'admin']), 'Staff permissions changed');
echo "Custom plans: defaults, isolation, nested progress, validation, transactions, month carry-forward and permissions passed.\n";
