<?php
defined('FL') or exit;

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    global $CONFIG;
    $c = $CONFIG['db'];
    try {
        if (($c['driver'] ?? 'mysql') === 'sqlite') {
            $dir = dirname($c['sqlite_path']);
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $pdo = new PDO('sqlite:' . $c['sqlite_path']);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_EMULATE_PREPARES => false]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection failed. Check config.php. (' . htmlspecialchars($e->getMessage()) . ')');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function is_sqlite(): bool
{
    global $CONFIG;
    return ($CONFIG['db']['driver'] ?? 'mysql') === 'sqlite';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Insert or update a row identified by $keys (must match a UNIQUE/PRIMARY key). */
function upsert(string $table, array $keys, array $data): void
{
    $cols = array_keys($data);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $upd  = array_values(array_diff($cols, $keys));
    $sql  = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES ($ph)";
    if ($upd) {
        if (is_sqlite()) {
            $sql .= ' ON CONFLICT(' . implode(',', $keys) . ') DO UPDATE SET '
                  . implode(',', array_map(fn($c) => "$c=excluded.$c", $upd));
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', array_map(fn($c) => "$c=VALUES($c)", $upd));
        }
    }
    q($sql, array_values($data));
}

function now(): string { return date('Y-m-d H:i:s'); }
