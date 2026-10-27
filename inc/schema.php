<?php
defined('FL') or exit;

const SCHEMA_VERSION = 2;

/** Full table definitions (safe to re-run: CREATE ... IF NOT EXISTS). */
function schema_statements(bool $sqlite): array
{
    if ($sqlite) {
        return [
            "CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT NULL)",
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK (role IN ('superadmin','admin','creator')),
                is_active INTEGER NOT NULL DEFAULT 1,
                handle TEXT NULL,
                niche TEXT NULL,
                channel_url TEXT NULL,
                start_month TEXT NULL,
                last_login TEXT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS creator_goals (
                creator_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                target_revenue REAL NOT NULL DEFAULT 10000,
                target_subs INTEGER NOT NULL DEFAULT 100000,
                target_hours REAL NOT NULL DEFAULT 15,
                target_streams INTEGER NOT NULL DEFAULT 3,
                updated_at TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS monthly_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                month TEXT NOT NULL,
                sponsor_rev REAL NOT NULL DEFAULT 0,
                info_rev REAL NOT NULL DEFAULT 0,
                subscribers INTEGER NULL,
                hours_week REAL NULL,
                income_streams INTEGER NULL,
                last_meeting TEXT NULL,
                updated_by INTEGER NULL,
                updated_at TEXT NULL,
                UNIQUE (creator_id, month)
            )",
            "CREATE TABLE IF NOT EXISTS milestone_status (
                creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                month TEXT NOT NULL,
                milestone_key TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'todo',
                PRIMARY KEY (creator_id, month, milestone_key)
            )",
            "CREATE TABLE IF NOT EXISTS phase_notes (
                creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                month TEXT NOT NULL,
                phase_key TEXT NOT NULL,
                notes TEXT NULL,
                PRIMARY KEY (creator_id, month, phase_key)
            )",
            "CREATE TABLE IF NOT EXISTS feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                month TEXT NOT NULL,
                author_id INTEGER NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_feedback_cm ON feedback (creator_id, month)",
            "CREATE TABLE IF NOT EXISTS activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                actor_id INTEGER NULL,
                month TEXT NULL,
                action TEXT NOT NULL,
                detail TEXT NULL,
                created_at TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_activity_time ON activity (created_at)",
            "CREATE INDEX IF NOT EXISTS idx_activity_creator ON activity (creator_id, created_at)",
        ];
    }
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    return [
        "CREATE TABLE IF NOT EXISTS meta (k VARCHAR(60) NOT NULL PRIMARY KEY, v TEXT NULL) $e",
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('superadmin','admin','creator') NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            handle VARCHAR(120) NULL,
            niche VARCHAR(120) NULL,
            channel_url VARCHAR(255) NULL,
            start_month CHAR(7) NULL,
            last_login DATETIME NULL,
            created_at DATETIME NOT NULL
        ) $e",
        "CREATE TABLE IF NOT EXISTS creator_goals (
            creator_id INT UNSIGNED NOT NULL PRIMARY KEY,
            target_revenue DECIMAL(12,2) NOT NULL DEFAULT 10000,
            target_subs INT UNSIGNED NOT NULL DEFAULT 100000,
            target_hours DECIMAL(5,1) NOT NULL DEFAULT 15,
            target_streams INT UNSIGNED NOT NULL DEFAULT 3,
            updated_at DATETIME NULL,
            CONSTRAINT fk_goals_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) $e",
        "CREATE TABLE IF NOT EXISTS monthly_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            creator_id INT UNSIGNED NOT NULL,
            month CHAR(7) NOT NULL,
            sponsor_rev DECIMAL(12,2) NOT NULL DEFAULT 0,
            info_rev DECIMAL(12,2) NOT NULL DEFAULT 0,
            subscribers INT UNSIGNED NULL,
            hours_week DECIMAL(5,1) NULL,
            income_streams INT UNSIGNED NULL,
            last_meeting DATE NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NULL,
            UNIQUE KEY uq_creator_month (creator_id, month),
            CONSTRAINT fk_entries_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) $e",
        "CREATE TABLE IF NOT EXISTS milestone_status (
            creator_id INT UNSIGNED NOT NULL,
            month CHAR(7) NOT NULL,
            milestone_key VARCHAR(60) NOT NULL,
            status VARCHAR(4) NOT NULL DEFAULT 'todo',
            PRIMARY KEY (creator_id, month, milestone_key),
            CONSTRAINT fk_status_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) $e",
        "CREATE TABLE IF NOT EXISTS phase_notes (
            creator_id INT UNSIGNED NOT NULL,
            month CHAR(7) NOT NULL,
            phase_key VARCHAR(60) NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY (creator_id, month, phase_key),
            CONSTRAINT fk_notes_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) $e",
        "CREATE TABLE IF NOT EXISTS feedback (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            creator_id INT UNSIGNED NOT NULL,
            month CHAR(7) NOT NULL,
            author_id INT UNSIGNED NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_feedback_cm (creator_id, month),
            CONSTRAINT fk_feedback_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) $e",
        "CREATE TABLE IF NOT EXISTS activity (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            creator_id INT UNSIGNED NOT NULL,
            actor_id INT UNSIGNED NULL,
            month CHAR(7) NULL,
            action VARCHAR(30) NOT NULL,
            detail VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_activity_time (created_at),
            INDEX idx_activity_creator (creator_id, created_at),
            CONSTRAINT fk_activity_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        ) $e",
    ];
}

function table_exists(string $t): bool
{
    if (is_sqlite()) return (bool)q("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$t])->fetchColumn();
    return (bool)q('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$t])->fetchColumn();
}

function column_exists(string $t, string $c): bool
{
    if (is_sqlite()) {
        foreach (q("PRAGMA table_info($t)")->fetchAll() as $r) if ($r['name'] === $c) return true;
        return false;
    }
    return (bool)q('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$t, $c])->fetchColumn();
}

/** Brings any older install up to SCHEMA_VERSION. Also used by install.php. */
function migrate(): void
{
    $sqlite = is_sqlite();
    foreach (schema_statements($sqlite) as $sql) db()->exec($sql);
    $add = ['handle' => 'VARCHAR(120) NULL', 'niche' => 'VARCHAR(120) NULL', 'channel_url' => 'VARCHAR(255) NULL', 'start_month' => 'CHAR(7) NULL'];
    foreach ($add as $col => $def) {
        if (!column_exists('users', $col)) db()->exec("ALTER TABLE users ADD COLUMN $col " . ($sqlite ? 'TEXT NULL' : $def));
    }
    upsert('meta', ['k'], ['k' => 'schema_version', 'v' => (string)SCHEMA_VERSION]);
}

/** Cheap per-session check so deploying new code upgrades the DB automatically. */
function ensure_schema(): void
{
    if (($_SESSION['schema'] ?? 0) === SCHEMA_VERSION) return;
    try {
        if (!table_exists('users')) return; // not installed yet
        $v = table_exists('meta') ? (int)q("SELECT v FROM meta WHERE k='schema_version'")->fetchColumn() : 1;
        if ($v < SCHEMA_VERSION) migrate();
        $_SESSION['schema'] = SCHEMA_VERSION;
    } catch (PDOException $e) {
        error_log('[fluence migrate] ' . $e->getMessage());
    }
}
