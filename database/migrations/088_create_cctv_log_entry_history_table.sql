CREATE TABLE IF NOT EXISTS cctv_log_entry_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_entry_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    description TEXT NOT NULL,
    user_id INT UNSIGNED NULL,
    shift_id INT UNSIGNED NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cctv_log_entry_history_entry (log_entry_id, created_at),
    INDEX idx_cctv_log_entry_history_action (action_type),
    CONSTRAINT fk_cctv_log_entry_history_entry
        FOREIGN KEY (log_entry_id) REFERENCES cctv_log_entries(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_entry_history_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_entry_history_shift
        FOREIGN KEY (shift_id) REFERENCES cctv_shifts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
