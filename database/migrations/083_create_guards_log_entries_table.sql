CREATE TABLE IF NOT EXISTS guards_log_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guards_shift_id INT UNSIGNED NOT NULL,
    guards_log_type_id INT UNSIGNED NOT NULL,
    sector_id INT UNSIGNED NULL DEFAULT NULL,
    occurred_at DATETIME NOT NULL,
    observations TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'registrado',
    cctv_log_entry_id INT UNSIGNED NULL DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_guards_log_entries_shift
        FOREIGN KEY (guards_shift_id) REFERENCES guards_shifts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_guards_log_entries_type
        FOREIGN KEY (guards_log_type_id) REFERENCES guards_log_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_guards_log_entries_sector
        FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE SET NULL,
    CONSTRAINT fk_guards_log_entries_cctv_log
        FOREIGN KEY (cctv_log_entry_id) REFERENCES cctv_log_entries(id) ON DELETE SET NULL,
    CONSTRAINT fk_guards_log_entries_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_guards_log_entries_shift (guards_shift_id),
    INDEX idx_guards_log_entries_occurred (occurred_at),
    INDEX idx_guards_log_entries_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
