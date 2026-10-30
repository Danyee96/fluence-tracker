<?php
require __DIR__ . '/inc/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok'=>false], 405);
$u = current_user();
if (!$u) json_out(['ok'=>false], 401);
if (!csrf_ok($_SERVER['HTTP_X_CSRF'] ?? null)) json_out(['ok'=>false], 419);
$cid = (int)($_POST['cid'] ?? 0);
if (!can_manage_social($u, $cid)) json_out(['ok'=>false], 403);
// Due jobs start only on explicit authenticated page sync requests.
social_process_creator($cid, ($_POST['sync_due']??'')==='1');
$manage=($_POST['manage']??'1')==='1';
$items = [];
foreach (social_accounts($cid) as $account) {
    ob_start(); social_card($account, $manage); $html = ob_get_clean();
    $items[] = ['id'=>(int)$account['id'], 'pending'=>social_enabled() && social_needs_work($account), 'html'=>$html];
}
json_out(['ok'=>true, 'enabled'=>social_enabled(), 'accounts'=>$items, 'socials'=>social_dashboard_data($cid)]);
