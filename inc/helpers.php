<?php
defined('FL') or exit;

function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function redirect(string $url): never { header('Location: ' . $url); exit; }

/* ---------- months & dates ---------- */
function valid_month(?string $m): ?string
{
    return ($m && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) ? $m : null;
}
function month_shift(string $m, int $by): string
{
    return date('Y-m', strtotime($m . '-01 ' . ($by >= 0 ? '+' : '') . $by . ' month'));
}
function month_label(string $m, bool $short = false): string
{
    return date($short ? 'M' : 'F Y', strtotime($m . '-01'));
}
function month_name(string $m): string { return date('F', strtotime($m . '-01')); }
function months_between(string $from, string $to): int
{
    [$y1, $m1] = array_map('intval', explode('-', $from));
    [$y2, $m2] = array_map('intval', explode('-', $to));
    return ($y2 - $y1) * 12 + ($m2 - $m1);
}
function short_date(?string $d): string
{
    return $d ? date('M j', strtotime($d)) : '—';
}
function time_ago(?string $dt): string
{
    if (!$dt) return '—';
    $s = time() - strtotime($dt);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . 'm ago';
    if ($s < 86400) return floor($s / 3600) . 'h ago';
    if ($s < 604800) return floor($s / 86400) . 'd ago';
    return date('M j', strtotime($dt));
}

/* ---------- misc ---------- */
function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function js_json($data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function role_label(string $r): string
{
    return ['superadmin' => 'Super Admin', 'admin' => 'Admin', 'creator' => 'Creator'][$r] ?? $r;
}

function num_or_null($v, bool $int = false)
{
    if ($v === null || $v === '') return null;
    $v = (float)preg_replace('/[^0-9.\-]/', '', (string)$v);
    return $int ? (int)round($v) : round($v, 2);
}

function clean_url(?string $u): ?string
{
    $u = trim((string)$u);
    if ($u === '') return null;
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    return filter_var($u, FILTER_VALIDATE_URL) ? mb_substr($u, 0, 255) : null;
}

function clean_handle(?string $h): ?string
{
    $h = trim((string)$h);
    if ($h === '') return null;
    return mb_substr('@' . ltrim($h, '@'), 0, 120);
}

function app_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/' . ltrim($path, '/');
}

/* ---------- avatars ---------- */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    return $i ?: '?';
}
function hue(int $id): int { return ($id * 47 + 190) % 360; }
function avatar(array $u, string $size = ''): string
{
    $id = (int)($u['id'] ?? 0);
    return '<span class="av ' . e($size) . '" style="--h:' . hue($id) . '">' . e(initials($u['name'] ?? '?')) . '</span>';
}

/* ---------- icons (lucide-style, stroke) ---------- */
function icon_paths(): array
{
    return [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'download' => '<path d="M12 3v12m0 0-4-4m4 4 4-4M4 21h16"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'logout'   => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
        'check'    => '<path d="M5 13l4 4L19 7"/>',
        'trash'    => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'arrow'    => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'back'     => '<path d="M19 12H5m6 6-6-6 6-6"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'users'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 3.5a4 4 0 0 1 0 9M22 21a7 7 0 0 0-4-6.3"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'trend'    => '<path d="M3 17l6-6 4 4 8-8M14 7h7v7"/>',
        'target'   => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'pulse'    => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'send'     => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/>',
        'dollar'   => '<path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'layers'   => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
        'flag'     => '<path d="M5 21V4M5 4h12l-2 4 2 4H5"/>',
    ];
}
function icon(string $name, string $cls = ''): string
{
    $p = icon_paths()[$name] ?? '';
    return '<svg class="ic ' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
