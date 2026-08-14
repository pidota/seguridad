CREATE TABLE IF NOT EXISTS camera_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    shift VARCHAR(20) NOT NULL,
    classification VARCHAR(40) NOT NULL,
    classification_other VARCHAR(180) NULL,
    location VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    actions_taken TEXT NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_camera_events_date (event_date),
    INDEX idx_camera_events_shift (shift),
    INDEX idx_camera_events_classification (classification),
    INDEX idx_camera_events_created_by (created_by),
    INDEX idx_camera_events_deleted_at (deleted_at),
    CONSTRAINT fk_camera_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_camera_events_updated_by
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
