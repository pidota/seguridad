CREATE TABLE IF NOT EXISTS senda_referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    senda_attention_id INT UNSIGNED NOT NULL,
    senda_person_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_senda_referrals_attention (senda_attention_id),
    INDEX idx_senda_referrals_person (senda_person_id),
    INDEX idx_senda_referrals_deleted_at (deleted_at),
    CONSTRAINT fk_senda_referrals_attention FOREIGN KEY (senda_attention_id) REFERENCES senda_attentions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_senda_referrals_person FOREIGN KEY (senda_person_id) REFERENCES senda_people(id) ON DELETE SET NULL,
    CONSTRAINT fk_senda_referrals_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
