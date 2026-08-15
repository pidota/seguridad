CREATE TABLE IF NOT EXISTS cctv_recording_request_cameras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recording_request_id INT UNSIGNED NOT NULL,
    camera_id INT UNSIGNED NOT NULL,
    review_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cctv_recording_request_cameras (recording_request_id, camera_id),
    INDEX idx_cctv_recording_request_cameras_status (review_status),
    CONSTRAINT fk_cctv_recording_request_cameras_request
        FOREIGN KEY (recording_request_id) REFERENCES cctv_recording_requests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_recording_request_cameras_camera
        FOREIGN KEY (camera_id) REFERENCES cctv_cameras(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_recording_request_cameras_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
