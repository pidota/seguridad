CREATE TABLE IF NOT EXISTS senda_follow_ups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    senda_attention_id INT UNSIGNED NOT NULL,
    follow_up_date DATE NOT NULL,
    follow_up_time TIME NULL,
    contact_type VARCHAR(40) NOT NULL,
    contact_type_other VARCHAR(180) NULL,
    result VARCHAR(40) NOT NULL,
    result_other VARCHAR(180) NULL,
    notes TEXT NULL,
    requires_follow_up TINYINT(1) NOT NULL DEFAULT 0,
    next_follow_up_date DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_senda_follow_ups_attention (senda_attention_id),
    INDEX idx_senda_follow_ups_date (follow_up_date),
    INDEX idx_senda_follow_ups_next_date (next_follow_up_date),
    INDEX idx_senda_follow_ups_deleted_at (deleted_at),
    CONSTRAINT fk_senda_follow_ups_attention
        FOREIGN KEY (senda_attention_id) REFERENCES senda_attentions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_senda_follow_ups_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
