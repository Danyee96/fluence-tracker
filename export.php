<?php
/** CSV export of every creator for one month (staff only). */
require __DIR__ . '/inc/bootstrap.php';
require_login(['superadmin', 'admin']);

$month = valid_month($_GET['m'] ?? '') ?? date('Y-m');
$data  = dashboard_data($month);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="fluence-' . $month . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8

$head = ['Creator', 'Email', 'Handle', 'Niche', 'Status', 'Current phase', 'Overall %'];
$phaseColumns = [];
foreach ($data['cards'] as $card) foreach ($card['phases'] as $p) $phaseColumns[$p['key']] = $p['label'];
foreach ($phaseColumns as $label) $head[] = $label . ' %';
array_push($head, 'Sponsor $', 'Info $', 'Total $', 'Subscribers', 'Sub growth', 'Income streams', 'Last meeting', 'Logged this month');
fputcsv($out, $head);

foreach ($data['cards'] as $c) {
    $e = get_entry($c['id'], $month);
    $row = [$c['name'], $c['email'], $c['handle'], $c['niche'], $c['active'] ? 'Active' : 'Disabled', $c['current']['label'], $c['overall']];
    $phaseValues = array_column($c['phases'], 'pct', 'key');
    foreach ($phaseColumns as $key => $label) $row[] = $phaseValues[$key] ?? '';
    array_push($row,
        $e ? $e['sponsor_rev'] : '', $e ? $e['info_rev'] : '', $c['rev'] ?? '',
        $c['subs'] ?? '', $c['subGrowth'] ?? '',
        $e ? $e['income_streams'] : '',
        $c['lastMeeting'] ?? '', $c['logged'] ? 'Yes' : 'No');
    fputcsv($out, $row);
}
fclose($out);
