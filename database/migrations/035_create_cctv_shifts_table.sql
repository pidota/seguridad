CREATE TABLE IF NOT EXISTS cctv_shifts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id INT UNSIGNED NOT NULL,
    shift_band VARCHAR(40) NOT NULL,
    shift_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    opening_notes TEXT NULL,
    closing_notes TEXT NULL,
    opened_by INT UNSIGNED NULL,
    closed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_cctv_shifts_operator (operator_id),
    INDEX idx_cctv_shifts_date (shift_date),
    INDEX idx_cctv_shifts_band (shift_band),
    INDEX idx_cctv_shifts_status (status),
    INDEX idx_cctv_shifts_started_at (started_at),
    INDEX idx_cctv_shifts_operator_status (operator_id, status),
    INDEX idx_cctv_shifts_deleted_at (deleted_at),
    CONSTRAINT fk_cctv_shifts_operator
        FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_shifts_opened_by
        FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_shifts_closed_by
        FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
