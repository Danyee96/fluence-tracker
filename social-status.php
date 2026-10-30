<?php
require __DIR__ . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false], 405);
$u = current_user();
if (!$u) json_out(['ok'=>false], 401);
if (!csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) json_out(['ok'=>false], 419);
$cid = (int)($_POST['cid'] ?? 0);
if (!can_manage_social($u, $cid)) json_out(['ok'=>false], 403);
// Polling only collects runs already started; it cannot trigger paid jobs.
$pending = q("SELECT id FROM social_accounts WHERE creator_id=? AND is_active=1 AND sync_status='syncing' AND run_id IS NOT NULL LIMIT 8", [$cid])->fetchAll();
foreach ($pending as $item) social_collect((int)$item['id']);
$items = [];
foreach (social_accounts($cid) as $account) {
    ob_start(); social_card($account, true); $html = ob_get_clean();
    $items[] = ['id'=>(int)$account['id'], 'pending'=>(bool)($account['is_active'] && $account['run_id'] && $account['sync_status'] === 'syncing'), 'html'=>$html];
}
json_out(['ok'=>true, 'accounts'=>$items, 'socials'=>social_dashboard_data($cid)]);
