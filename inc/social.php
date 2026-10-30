<?php
defined('FL') or exit;

const SOCIAL_PLATFORMS = [
    'instagram' => ['label' => 'Instagram', 'actor' => 'apify~instagram-profile-scraper', 'metric' => 'Followers'],
    'youtube' => ['label' => 'YouTube', 'actor' => 'streamers~youtube-channel-scraper', 'metric' => 'Subscribers'],
    'tiktok' => ['label' => 'TikTok', 'actor' => 'clockworks~tiktok-profile-scraper', 'metric' => 'Followers'],
    'x' => ['label'=>'X (Twitter)', 'actor'=>'apidojo~twitter-user-scraper', 'metric'=>'Followers'],
    'facebook' => ['label' => 'Facebook', 'actor' => 'apify~facebook-pages-scraper', 'metric' => 'Followers'],
];

function social_schema(bool $sqlite): array
{
    $id = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $count = $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return [
        "CREATE TABLE IF NOT EXISTS social_accounts (
            id $auto, creator_id $id NOT NULL, platform VARCHAR(20) NOT NULL,
            handle VARCHAR(120) NOT NULL, profile_url VARCHAR(255) NOT NULL, display_name VARCHAR(255) NULL,
            is_active INTEGER NOT NULL DEFAULT 1, sync_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            run_id VARCHAR(80) NULL, last_attempt DATETIME NULL, last_synced DATETIME NULL,
            next_sync DATETIME NULL, locked_until DATETIME NULL, last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL, UNIQUE (creator_id, platform, handle),
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        )$engine",
        "CREATE TABLE IF NOT EXISTS social_snapshots (
            account_id $id NOT NULL, snapshot_date CHAR(10) NOT NULL, followers $count NULL,
            posts $count NULL, likes $count NULL, views $count NULL, captured_at DATETIME NOT NULL,
            PRIMARY KEY (account_id, snapshot_date), FOREIGN KEY (account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
        )$engine",
    ];
}

function social_config(): array
{
    global $CONFIG;
    return $CONFIG['apify'] ?? [];
}

function social_enabled(): bool { return trim((string)(social_config()['token'] ?? '')) !== ''; }
function can_manage_social(array $u, int $cid): bool { return is_staff($u) || ($u['role'] === 'creator' && (int)$u['id'] === $cid); }
function social_interval(): int { return max(6, min(168, (int)(social_config()['refresh_hours'] ?? 24))) * 3600; }
function social_time(int $seconds): string { return date('Y-m-d H:i:s', time() + $seconds); }

/** Canonicalize handles/URLs without ever requesting user-supplied URLs. */
function social_identity(string $platform, string $raw): array
{
    if (!isset(SOCIAL_PLATFORMS[$platform])) throw new InvalidArgumentException('Choose a supported platform.');
    $raw = trim($raw);
    if (preg_match('~^(?:www\.)?(instagram\.com|youtube\.com|tiktok\.com|facebook\.com|x\.com|twitter\.com)/~i', $raw)) $raw = 'https://' . $raw;
    $hosts = ['x'=>['x.com','www.x.com','twitter.com','www.twitter.com','mobile.twitter.com'], 'instagram'=>['instagram.com','www.instagram.com'], 'youtube'=>['youtube.com','www.youtube.com','m.youtube.com'], 'tiktok'=>['tiktok.com','www.tiktok.com'], 'facebook'=>['facebook.com','www.facebook.com','m.facebook.com']];
    $channelId = false;
    if (str_contains($raw, '://')) {
        $url = parse_url($raw);
        if (!$url || !in_array(strtolower($url['host'] ?? ''), $hosts[$platform], true) || !in_array($url['scheme'] ?? '', ['https', 'http'], true) || isset($url['user']) || isset($url['port'])) {
            throw new InvalidArgumentException('Enter a handle or a profile URL from the selected platform.');
        }
        $path = trim(rawurldecode($url['path'] ?? ''), '/');
        if ($platform === 'facebook' && $path === 'profile.php') {
            parse_str($url['query'] ?? '', $query);
            $raw = (string)($query['id'] ?? '');
        } elseif ($platform === 'youtube' && preg_match('~^channel/(UC[A-Za-z0-9_-]{22})/?$~', $path, $m)) {
            $raw = $m[1]; $channelId = true;
        } else {
            if (str_contains($path, '/')) throw new InvalidArgumentException('Use the profile URL, rather than a post or video URL.');
            $raw = $path;
        }
    }
    $handle = ltrim($raw, '@');
    if ($platform === 'youtube' && preg_match('/^UC[A-Za-z0-9_-]{22}$/', $handle)) $channelId = true;
    $valid = $platform === 'youtube' ? preg_match('/^[\p{L}\p{N}_.\-·]{3,100}$/u', $handle) : preg_match('/^[a-zA-Z0-9_.-]{1,100}$/', $handle);
    if ($platform === 'x') $valid = preg_match('/^[a-zA-Z0-9_]{1,15}$/', $handle);
    $reserved = ['x'=>['home','explore','search','i','login','intent','settings','notifications','messages','compose','signup','tos','privacy'], 'instagram'=>['p','reel','reels','stories','explore'], 'youtube'=>['watch','shorts','playlist','feed','results'], 'tiktok'=>['video','discover','explore'], 'facebook'=>['watch','reel','reels','groups','share','login','profile.php']];
    if (!$valid || in_array(strtolower($handle), $reserved[$platform], true)) throw new InvalidArgumentException('Enter a valid account handle.');
    $handle = mb_strtolower($handle);
    // YouTube channel IDs are case-sensitive.
    if ($channelId) $handle = ltrim($raw, '@');
    $profile = match ($platform) {
        'x' => 'https://x.com/' . rawurlencode($handle),
        'instagram' => 'https://www.instagram.com/' . rawurlencode($handle) . '/',
        'youtube' => 'https://www.youtube.com/' . ($channelId ? 'channel/' : '@') . rawurlencode($handle),
        'tiktok' => 'https://www.tiktok.com/@' . rawurlencode($handle),
        'facebook' => ctype_digit($handle) ? 'https://www.facebook.com/profile.php?id=' . $handle : 'https://www.facebook.com/' . rawurlencode($handle),
    };
    return ['handle'=>$handle, 'profile_url'=>$profile];
}

/** All provider traffic stays on a single fixed HTTPS host; tokens never enter URLs/logs. */
function apify_request(string $method, string $path, ?array $body = null, array $query = []): array
{
    $token = trim((string)(social_config()['token'] ?? ''));
    if ($token === '') throw new RuntimeException('Automatic stats are not configured. Ask your admin to connect Apify.');
    if (!preg_match('~^/(acts/[A-Za-z0-9_\~.-]+/runs|actor-runs/[A-Za-z0-9_-]+(?:/abort)?|datasets/[A-Za-z0-9_-]+/items)$~', $path)) throw new RuntimeException('Invalid provider request.');
    $url = 'https://api.apify.com/v2' . $path . ($query ? '?' . http_build_query($query) : '');
    // Injectable transport only for isolated integration tests.
    if (isset($GLOBALS['social_test_transport'])) {
        [$code, $response] = ($GLOBALS['social_test_transport'])($method, $path, $body, $query);
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_TIMEOUT=>20,
            CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw === false) throw new RuntimeException('Provider connection failed. The next scheduled sync will retry.');
        $response = json_decode($raw, true);
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException(match ((int)$code) {
            401, 403 => 'Apify access was refused. Ask your admin to check the token and actor access.',
            402 => 'Apify credits or actor subscription required. Ask your admin to check billing.',
            404 => 'The stats provider is unavailable. Ask your admin to check the integration.',
            429 => 'The stats provider is busy. The next scheduled sync will retry.',
            default => 'The stats provider could not complete this request. Please try later.',
        });
    }
    if (!is_array($response)) throw new RuntimeException('The stats provider returned an invalid response.');
    return $response;
}

function social_actor_input(array $account): array
{
    return match ($account['platform']) {
        'x' => ['twitterHandles'=>[$account['handle']], 'getFollowers'=>false, 'getFollowing'=>false, 'getRetweeters'=>false, 'getAbout'=>false, 'maxItems'=>1],
        'instagram' => ['usernames'=>[$account['handle']]],
        'tiktok' => ['profiles'=>[$account['handle']], 'resultsPerPage'=>1, 'shouldDownloadVideos'=>false, 'shouldDownloadCovers'=>false, 'shouldDownloadSlideshowImages'=>false, 'shouldDownloadSubtitles'=>false],
        'youtube' => ['startUrls'=>[['url'=>$account['profile_url']]], 'maxResults'=>1, 'maxResultsShorts'=>0, 'maxResultStreams'=>0],
        'facebook' => ['startUrls'=>[['url'=>$account['profile_url']]]],
    };
}

/** Unknown, hidden or malformed values stay NULL, never a misleading zero. */
function social_count(mixed $raw): ?int
{
    if (is_int($raw)) return $raw >= 0 ? $raw : null;
    if (!is_string($raw) && !is_float($raw)) return null;
    $value = str_replace([',', ' ', "\xc2\xa0"], '', trim((string)$raw));
    if (!preg_match('/^(\d+(?:\.\d+)?)([kmb])?$/i', $value, $m)) return null;
    $n = (float)$m[1] * match (strtolower($m[2] ?? '')) { 'k'=>1000, 'm'=>1000000, 'b'=>1000000000, default=>1 };
    return is_finite($n) && $n < PHP_INT_MAX ? (int)round($n) : null;
}

function social_parse(array $account, array $rows): array
{
    foreach ($rows as $row) {
        if (!is_array($row) || !empty($row['error']) || !empty($row['errorCode'])) continue;
        $p = $account['platform'];
        $data = match ($p) { 'tiktok'=>$row['authorMeta'] ?? $row, 'youtube'=>$row['aboutChannelInfo'] ?? $row, default=>$row };
        $identity = match ($p) {
            'x'=>$data['userName'] ?? '',
            'instagram'=>$data['username'] ?? '', 'tiktok'=>$data['name'] ?? $data['uniqueId'] ?? '',
            'youtube'=>str_contains($account['profile_url'], '/channel/') ? ($data['channelId'] ?? $row['channelId'] ?? '') : ($data['channelUsername'] ?? $row['channelUsername'] ?? ''),
            'facebook'=>ctype_digit($account['handle']) ? (string)($data['pageId'] ?? $data['facebookId'] ?? '') : ($data['pageName'] ?? ''),
        };
        if (mb_strtolower(ltrim((string)$identity, '@')) !== mb_strtolower($account['handle'])) continue;
        $followers = social_count(match ($p) {
            'x'=>$data['followers'] ?? null,
            'instagram'=>$data['followersCount'] ?? null, 'tiktok'=>$data['fans'] ?? $data['followerCount'] ?? null,
            'youtube'=>$data['numberOfSubscribers'] ?? $row['numberOfSubscribers'] ?? null, 'facebook'=>$data['followers'] ?? null,
        });
        if ($followers === null) continue;
        return [
            'followers'=>$followers,
            'posts'=>social_count(match ($p) { 'x'=>$data['statusesCount'] ?? null, 'instagram'=>$data['postsCount'] ?? null, 'tiktok'=>$data['video'] ?? $data['videoCount'] ?? null, 'youtube'=>$data['channelTotalVideos'] ?? null, default=>null }),
            'likes'=>social_count(match ($p) { 'tiktok'=>$data['heart'] ?? $data['heartCount'] ?? null, 'facebook'=>$data['likes'] ?? null, default=>null }),
            'views'=>$p === 'youtube' ? social_count($data['channelTotalViews'] ?? null) : null,
            'display_name'=>mb_substr((string)match ($p) { 'x'=>$data['name'] ?? $identity, 'instagram'=>$data['fullName'] ?? $identity, 'tiktok'=>$data['nickName'] ?? $identity, 'youtube'=>$data['channelName'] ?? $row['channelName'] ?? $identity, 'facebook'=>$data['title'] ?? $identity }, 0, 255),
        ];
    }
    throw new RuntimeException('No public follower count was returned for this account. Check the handle and profile visibility.');
}

function social_claim(int $id): ?array
{
    $changed = q('UPDATE social_accounts SET locked_until=? WHERE id=? AND is_active=1 AND sync_status<>\'archived\' AND creator_id IN (SELECT id FROM users WHERE is_active=1 AND role=\'creator\') AND (locked_until IS NULL OR locked_until<?)', [social_time(90), $id, now()]);
    return $changed->rowCount() ? (q('SELECT * FROM social_accounts WHERE id=?', [$id])->fetch() ?: null) : null;
}

function social_fail(int $id, string $message, bool $clearRun = true): void
{
    q('UPDATE social_accounts SET sync_status=?, last_error=?, next_sync=?, locked_until=NULL' . ($clearRun ? ', run_id=NULL' : '') . ' WHERE id=? AND sync_status<>\'archived\'',
        ['error', mb_substr($message, 0, 255), social_time(3600), $id]);
}

/** One bounded provider call to start a background run; no long-running web requests. */
function social_start(int $id): void
{
    $account = social_claim($id);
    if (!$account) return;
    try {
        if ($account['run_id']) return;
        // An hourly minimum also protects manual refreshes from duplicate charges.
        if ($account['last_attempt'] && strtotime($account['last_attempt']) > time() - 3600) return;
        q('UPDATE social_accounts SET last_attempt=?, next_sync=? WHERE id=?', [now(), social_time(social_interval()), $id]);
        $data = apify_request('POST', '/acts/' . SOCIAL_PLATFORMS[$account['platform']]['actor'] . '/runs', social_actor_input($account), [
            'timeout'=>180, 'maxItems'=>1, 'maxTotalChargeUsd'=>max(0.01, min(1, (float)(social_config()['max_run_cost_usd'] ?? 0.05))),
        ])['data'] ?? [];
        if (empty($data['id']) || !preg_match('/^[a-zA-Z0-9_-]+$/', $data['id'])) throw new RuntimeException('The provider did not return a valid sync job.');
        q('UPDATE social_accounts SET run_id=?, sync_status=?, last_error=NULL WHERE id=? AND sync_status<>\'archived\'', [$data['id'], 'syncing', $id]);
    } catch (Throwable $e) {
        social_fail($id, $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'Could not start stats sync. Please retry later.');
    } finally { q('UPDATE social_accounts SET locked_until=NULL WHERE id=?', [$id]); }
}

/** Poll an existing run; never starts a second paid run. */
function social_collect(int $id): void
{
    $account = social_claim($id);
    if (!$account) return;
    $terminal = false;
    try {
        if (!$account['run_id']) return;
        $run = apify_request('GET', '/actor-runs/' . $account['run_id'])['data'] ?? [];
        $status = $run['status'] ?? '';
        if (in_array($status, ['READY','RUNNING','TIMING-OUT','ABORTING'], true)) return;
        if ($status !== 'SUCCEEDED') {
            $terminal = in_array($status, ['FAILED','TIMED-OUT','ABORTED'], true);
            throw new RuntimeException('The profile sync failed or timed out. A scheduled retry will follow.');
        }
        $dataset = (string)($run['defaultDatasetId'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dataset)) throw new RuntimeException('The provider did not return stats.');
        $rows = apify_request('GET', '/datasets/' . $dataset . '/items', null, ['clean'=>'true','limit'=>5]);
        try { $stats = social_parse($account, $rows); }
        catch (RuntimeException $e) { $terminal = true; throw $e; }
        db()->beginTransaction();
        $name = $stats['display_name']; unset($stats['display_name']);
        $applied=q('UPDATE social_accounts SET display_name=?, last_synced=?, sync_status=?, run_id=NULL, last_error=NULL, next_sync=? WHERE id=? AND run_id=? AND sync_status<>\'archived\'', [$name, now(), 'ready', social_time(social_interval()), $id, $account['run_id']]);
        if (!$applied->rowCount()) { db()->rollBack(); return; }
        upsert('social_snapshots', ['account_id','snapshot_date'], ['account_id'=>$id, 'snapshot_date'=>date('Y-m-d')] + $stats + ['captured_at'=>now()]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        // Keep a succeeded run on transient network errors, so a retry can collect it without charging again.
        social_fail($id, $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'Could not save stats. Please retry later.', $terminal);
    } finally { q('UPDATE social_accounts SET locked_until=NULL WHERE id=?', [$id]); }
}

function social_tick(int $limit = 20): int
{
    if (!social_enabled()) throw new RuntimeException('Apify token is not configured.');
    upsert('meta', ['k'], ['k'=>'social_cron_last_run', 'v'=>now()]);
    $due = q('SELECT s.id, s.run_id FROM social_accounts s JOIN users u ON u.id=s.creator_id WHERE s.is_active=1 AND u.is_active=1 AND u.role=\'creator\' AND s.sync_status<>\'archived\' AND (s.next_sync IS NULL OR s.next_sync<=? OR (s.sync_status=\'pending\' AND s.last_synced IS NULL) OR (s.run_id IS NOT NULL AND s.sync_status=\'syncing\')) ORDER BY s.last_attempt ASC LIMIT ' . max(1, min(100, $limit)), [now()])->fetchAll();
    foreach ($due as $row) { if ($row['run_id']) social_collect((int)$row['id']); else social_start((int)$row['id']); }
    return count($due);
}

function social_accounts(int $cid): array
{
    $accounts = q("SELECT * FROM social_accounts WHERE creator_id=? AND sync_status<>'archived' ORDER BY platform, id", [$cid])->fetchAll();
    foreach ($accounts as &$account) {
        $account['latest'] = q('SELECT * FROM social_snapshots WHERE account_id=? ORDER BY snapshot_date DESC LIMIT 1', [$account['id']])->fetch() ?: null;
        $history = q('SELECT snapshot_date, followers FROM social_snapshots WHERE account_id=? AND snapshot_date>=? ORDER BY snapshot_date', [$account['id'], date('Y-m-d', strtotime('-30 days'))])->fetchAll();
        $account['history'] = $history;
        $account['growth'] = count($history) >= 2 ? (int)end($history)['followers'] - (int)$history[0]['followers'] : null;
    }
    return $accounts;
}

/** Public dashboard projection: no provider run IDs, credentials, or raw scraped records. */
function social_dashboard_data(int $cid): array
{
    return array_map(function ($account) {
        $stats=$account['latest'];
        return [
            'id'=>(int)$account['id'],'platform'=>$account['platform'],'label'=>SOCIAL_PLATFORMS[$account['platform']]['label'],
            'metric'=>SOCIAL_PLATFORMS[$account['platform']]['metric'],'handle'=>$account['handle'],'url'=>$account['profile_url'],
            'configured'=>social_enabled(),'name'=>$account['display_name'],'active'=>(bool)$account['is_active'],'status'=>$account['sync_status'],
            'lastSynced'=>$account['last_synced'],'updatedAgo'=>$account['last_synced']?time_ago($account['last_synced']):'Never',
            'stale'=>!$account['last_synced'] || strtotime($account['last_synced']) < time()-social_interval(),
            'error'=>$account['last_error'],'followers'=>$stats ? (int)$stats['followers'] : null,
            'posts'=>$stats && $stats['posts']!==null ? (int)$stats['posts'] : null,
            'likes'=>$stats && $stats['likes']!==null ? (int)$stats['likes'] : null,
            'views'=>$stats && $stats['views']!==null ? (int)$stats['views'] : null,
            'growth'=>$account['growth'],'history'=>array_map(fn($day)=>['date'=>$day['snapshot_date'],'followers'=>(int)$day['followers']],$account['history']),
        ];
    },social_accounts($cid));
}

function social_needs_work(array $account): bool
{
    if (!$account['is_active'] || $account['sync_status']==='archived') return false;
    if ($account['run_id'] && $account['sync_status']==='syncing') return true;
    $due=(!$account['last_synced'] && $account['sync_status']==='pending') || !$account['next_sync'] || strtotime($account['next_sync'])<=time();
    if (!$due) return false;
    return (bool)$account['run_id'] || !$account['last_attempt'] || strtotime($account['last_attempt'])<=time()-3600;
}

/** Authorized page fallback for missed first jobs; cron still handles unattended updates. */
function social_process_creator(int $cid, bool $startDue = false): void
{
    if (!social_enabled()) return;
    $rows=q("SELECT s.* FROM social_accounts s JOIN users u ON u.id=s.creator_id WHERE s.creator_id=? AND s.is_active=1 AND s.sync_status<>'archived' AND u.role='creator' AND u.is_active=1 ORDER BY CASE WHEN s.run_id IS NOT NULL THEN 0 ELSE 1 END,s.last_attempt ASC",[$cid])->fetchAll();
    $processed=0;$started=microtime(true);
    foreach ($rows as $account) {
        if (!social_needs_work($account)) continue;
        if ($account['run_id']) social_collect((int)$account['id']);
        elseif ($startDue) social_start((int)$account['id']);
        else continue;
        if (++$processed>=2 || microtime(true)-$started>12) break;
    }
}
