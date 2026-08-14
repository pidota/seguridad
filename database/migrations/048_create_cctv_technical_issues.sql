CREATE TABLE IF NOT EXISTS cctv_technical_issue_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    tone VARCHAR(20) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allows_other TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_cctv_technical_issue_types_slug (slug),
    INDEX idx_cctv_technical_issue_types_active (is_active),
    INDEX idx_cctv_technical_issue_types_sort (sort_order),
    INDEX idx_cctv_technical_issue_types_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cctv_technical_issue_types (slug, name, description, tone, sort_order, allows_other)
VALUES
    ('sin_senal', 'Sin señal', NULL, 'technical', 10, 0),
    ('imagen_congelada', 'Imagen congelada', NULL, 'technical', 20, 0),
    ('intermitencia', 'Intermitencia', NULL, 'technical', 30, 0),
    ('sin_video', 'Sin video', NULL, 'technical', 40, 0),
    ('equipo_sin_respuesta', 'Equipo sin respuesta', NULL, 'technical', 50, 0),
    ('otro', 'Otro', NULL, 'other', 900, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    allows_other = VALUES(allows_other);

ALTER TABLE cctv_log_entries
    ADD COLUMN cctv_technical_issue_type_id INT UNSIGNED NULL AFTER cctv_incident_type_id,
    ADD COLUMN technical_issue_other VARCHAR(180) NULL AFTER cctv_technical_issue_type_id,
    ADD COLUMN cctv_equipment_id INT UNSIGNED NULL AFTER cctv_camera_id,
    ADD COLUMN camera_status_applied VARCHAR(30) NULL AFTER cctv_equipment_id;

ALTER TABLE cctv_log_entries
    ADD CONSTRAINT fk_cctv_log_entries_technical_issue_type
        FOREIGN KEY (cctv_technical_issue_type_id) REFERENCES cctv_technical_issue_types(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cctv_log_entries_equipment
        FOREIGN KEY (cctv_equipment_id) REFERENCES cctv_equipment(id) ON DELETE SET NULL;

CREATE INDEX idx_cctv_log_entries_technical_issue_type ON cctv_log_entries (cctv_technical_issue_type_id);
CREATE INDEX idx_cctv_log_entries_equipment ON cctv_log_entries (cctv_equipment_id);
