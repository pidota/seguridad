CREATE TABLE IF NOT EXISTS cctv_log_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cctv_log_entry_id INT UNSIGNED NOT NULL,
    contact_kind VARCHAR(40) NOT NULL,
    contact_name VARCHAR(150) NULL,
    contact_reference VARCHAR(180) NULL,
    contacted_at DATETIME NULL,
    outcome VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_cctv_log_contacts_entry (cctv_log_entry_id),
    INDEX idx_cctv_log_contacts_kind (contact_kind),
    INDEX idx_cctv_log_contacts_contacted_at (contacted_at),
    INDEX idx_cctv_log_contacts_deleted_at (deleted_at),
    CONSTRAINT fk_cctv_log_contacts_entry
        FOREIGN KEY (cctv_log_entry_id) REFERENCES cctv_log_entries(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_contacts_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
