CREATE TABLE IF NOT EXISTS guards_shifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guard_id INT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL DEFAULT NULL,
    opening_notes TEXT NULL,
    closing_notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_guards_shifts_guard
        FOREIGN KEY (guard_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_guards_shifts_guard_status (guard_id, status),
    INDEX idx_guards_shifts_date (shift_date),
    INDEX idx_guards_shifts_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
