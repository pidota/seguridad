CREATE TABLE IF NOT EXISTS cctv_recording_deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recording_request_id INT UNSIGNED NOT NULL,
    delivered_at DATETIME NOT NULL,
    delivered_by INT UNSIGNED NOT NULL,
    receiver_name VARCHAR(180) NOT NULL,
    receiver_rut VARCHAR(20) NOT NULL,
    delivery_medium VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cctv_recording_deliveries_request (recording_request_id),
    INDEX idx_cctv_recording_deliveries_delivered_at (delivered_at),
    CONSTRAINT fk_cctv_recording_deliveries_request
        FOREIGN KEY (recording_request_id) REFERENCES cctv_recording_requests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_recording_deliveries_delivered_by
        FOREIGN KEY (delivered_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
