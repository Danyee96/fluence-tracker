<?php
defined('FL') or exit;

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $row = q('SELECT id, name, email, role, is_active, handle, niche, channel_url, start_month FROM users WHERE id = ?', [$_SESSION['uid']])->fetch();
        if (!empty($_SESSION['impersonation'])) {
            $actor = impersonation_actor();
            if (!$actor) {unset($_SESSION['uid'],$_SESSION['impersonation']);return null;}
            if (!$row || !(int)$row['is_active'] || $row['role']!=='creator' || (int)$row['id']!==(int)$_SESSION['impersonation']['creator_id']) {
                unset($_SESSION['impersonation']);$_SESSION['uid']=(int)$actor['id'];
                session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
                flash('The creator session ended. You are back in your admin account.');
                return $u=$actor;
            }
        }
        if ($row && (int)$row['is_active'] === 1) $u = $row;
        else unset($_SESSION['uid']);
    }
    return $u;
}

/** Resolve the real staff account on every impersonated request. */
function impersonation_actor(): ?array
{
    $id=(int)($_SESSION['impersonation']['actor_id']??0);
    if (!$id) return null;
    $row=q("SELECT id,name,email,role,is_active,handle,niche,channel_url,start_month FROM users WHERE id=? AND is_active=1 AND role IN ('superadmin','admin')",[$id])->fetch();
    return $row?:null;
}

/** Require a logged-in user, optionally limited to some roles. */
function require_login(array $roles = []): array
{
    $u = current_user();
    if (!$u) redirect('login.php');
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        page_header('No access');
        echo '<div class="empty-state">' . icon('eye') . '<h2>No access</h2><p>Your account can\'t open this page.</p><a class="btn" href="index.php">Go home</a></div>';
        page_footer();
        exit;
    }
    return $u;
}

function is_staff(array $u): bool { return in_array($u['role'], ['superadmin', 'admin'], true); }

function can_view_creator(array $u, int $cid): bool
{
    return is_staff($u) || ($u['role'] === 'creator' && (int)$u['id'] === $cid);
}

function can_edit_creator(array $u, int $cid): bool
{
    global $CONFIG;
    if ($u['role'] === 'superadmin') return true;
    if ($u['role'] === 'admin') return !empty($CONFIG['admin_can_edit']);
    return (int)$u['id'] === $cid;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_ok(?string $t): bool
{
    return $t && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function csrf_check(): void
{
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Session expired — go back and refresh the page.');
    }
}

function home_for(array $u): string
{
    return $u['role'] === 'creator' ? 'creator.php' : 'dashboard.php';
}

function random_password(int $len = 12): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
    return $out;
}
