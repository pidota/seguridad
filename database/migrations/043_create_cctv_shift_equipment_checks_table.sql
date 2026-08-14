CREATE TABLE IF NOT EXISTS cctv_shift_equipment_checks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cctv_shift_id INT UNSIGNED NOT NULL,
    cctv_equipment_id INT UNSIGNED NOT NULL,
    check_phase VARCHAR(20) NOT NULL,
    status VARCHAR(30) NOT NULL,
    observations TEXT NULL,
    checked_at DATETIME NOT NULL,
    checked_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cctv_shift_equipment_phase (cctv_shift_id, cctv_equipment_id, check_phase),
    INDEX idx_cctv_shift_checks_shift (cctv_shift_id),
    INDEX idx_cctv_shift_checks_equipment (cctv_equipment_id),
    INDEX idx_cctv_shift_checks_phase (check_phase),
    INDEX idx_cctv_shift_checks_status (status),
    CONSTRAINT fk_cctv_shift_checks_shift
        FOREIGN KEY (cctv_shift_id) REFERENCES cctv_shifts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cctv_shift_checks_equipment
        FOREIGN KEY (cctv_equipment_id) REFERENCES cctv_equipment(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cctv_shift_checks_checked_by
        FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
