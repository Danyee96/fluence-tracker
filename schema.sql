-- Fluence MySQL schema (install.php runs this for you)

CREATE TABLE IF NOT EXISTS meta (k VARCHAR(60) NOT NULL PRIMARY KEY, v TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS creator_goals (
    creator_id INT UNSIGNED NOT NULL PRIMARY KEY,
    target_revenue DECIMAL(12,2) NOT NULL DEFAULT 10000,
    target_subs INT UNSIGNED NOT NULL DEFAULT 100000,
    target_hours DECIMAL(5,1) NOT NULL DEFAULT 15,
    target_streams INT UNSIGNED NOT NULL DEFAULT 3,
    updated_at DATETIME NULL,
    CONSTRAINT fk_goals_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS monthly_entries (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS milestone_status (
    creator_id INT UNSIGNED NOT NULL,
    month CHAR(7) NOT NULL,
    milestone_key VARCHAR(60) NOT NULL,
    status VARCHAR(4) NOT NULL DEFAULT 'todo',
    PRIMARY KEY (creator_id, month, milestone_key),
    CONSTRAINT fk_status_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS phase_notes (
    creator_id INT UNSIGNED NOT NULL,
    month CHAR(7) NOT NULL,
    phase_key VARCHAR(60) NOT NULL,
    notes TEXT NULL,
    PRIMARY KEY (creator_id, month, phase_key),
    CONSTRAINT fk_notes_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    creator_id INT UNSIGNED NOT NULL,
    month CHAR(7) NOT NULL,
    author_id INT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_feedback_cm (creator_id, month),
    CONSTRAINT fk_feedback_user FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO meta (k, v) VALUES ('schema_version', '2');
