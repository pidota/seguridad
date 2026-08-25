CREATE TABLE IF NOT EXISTS cctv_log_handovers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_entry_id INT UNSIGNED NOT NULL,
    from_shift_id INT UNSIGNED NOT NULL,
    to_shift_id INT UNSIGNED NULL,
    from_operator_id INT UNSIGNED NOT NULL,
    to_operator_id INT UNSIGNED NULL,
    handover_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    handover_notes TEXT NULL,
    decision VARCHAR(20) NULL,
    decision_reason VARCHAR(120) NULL,
    decision_details TEXT NULL,
    approx_completion_time TIME NULL,
    delegated_institution VARCHAR(120) NULL,
    handed_over_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cctv_log_handovers_entry (log_entry_id),
    INDEX idx_cctv_log_handovers_status (handover_status),
    INDEX idx_cctv_log_handovers_to_shift (to_shift_id, handover_status),
    INDEX idx_cctv_log_handovers_from_shift (from_shift_id),
    CONSTRAINT fk_cctv_log_handovers_entry
        FOREIGN KEY (log_entry_id) REFERENCES cctv_log_entries(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_handovers_from_shift
        FOREIGN KEY (from_shift_id) REFERENCES cctv_shifts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_handovers_to_shift
        FOREIGN KEY (to_shift_id) REFERENCES cctv_shifts(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_handovers_from_operator
        FOREIGN KEY (from_operator_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_handovers_to_operator
        FOREIGN KEY (to_operator_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_handovers_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
