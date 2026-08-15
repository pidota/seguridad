CREATE TABLE IF NOT EXISTS cctv_recording_request_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recording_request_id INT UNSIGNED NOT NULL,
    previous_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    changed_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cctv_recording_request_history_request (recording_request_id),
    INDEX idx_cctv_recording_request_history_created_at (created_at),
    CONSTRAINT fk_cctv_recording_request_history_request
        FOREIGN KEY (recording_request_id) REFERENCES cctv_recording_requests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_recording_request_history_changed_by
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
