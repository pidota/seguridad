CREATE TABLE IF NOT EXISTS senda_attentions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_type ENUM('derivacion', 'demanda_espontanea') NOT NULL,
    person_id INT UNSIGNED NULL,
    attended_on DATE NOT NULL,
    summary TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_senda_attentions_entry_type (entry_type),
    INDEX idx_senda_attentions_attended_on (attended_on),
    CONSTRAINT fk_senda_attentions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
