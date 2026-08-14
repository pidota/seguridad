CREATE TABLE IF NOT EXISTS cctv_log_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cctv_shift_id INT UNSIGNED NOT NULL,
    cctv_log_type_id INT UNSIGNED NOT NULL,
    cctv_incident_type_id INT UNSIGNED NULL,
    incident_type_other VARCHAR(180) NULL,
    cctv_camera_id INT UNSIGNED NULL,
    sector_id INT UNSIGNED NULL,
    location VARCHAR(180) NULL,
    event_at DATETIME NOT NULL,
    description TEXT NOT NULL,
    actions_taken TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_cctv_log_entries_shift (cctv_shift_id),
    INDEX idx_cctv_log_entries_event_at (event_at),
    INDEX idx_cctv_log_entries_log_type (cctv_log_type_id),
    INDEX idx_cctv_log_entries_incident_type (cctv_incident_type_id),
    INDEX idx_cctv_log_entries_camera (cctv_camera_id),
    INDEX idx_cctv_log_entries_sector (sector_id),
    INDEX idx_cctv_log_entries_created_by (created_by),
    INDEX idx_cctv_log_entries_shift_event (cctv_shift_id, event_at),
    INDEX idx_cctv_log_entries_deleted_at (deleted_at),
    CONSTRAINT fk_cctv_log_entries_shift
        FOREIGN KEY (cctv_shift_id) REFERENCES cctv_shifts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_entries_log_type
        FOREIGN KEY (cctv_log_type_id) REFERENCES cctv_log_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_log_entries_incident_type
        FOREIGN KEY (cctv_incident_type_id) REFERENCES cctv_incident_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_entries_camera
        FOREIGN KEY (cctv_camera_id) REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_entries_sector
        FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_entries_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_cctv_log_entries_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
