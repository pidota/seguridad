CREATE TABLE IF NOT EXISTS senda_assist_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assisted_referral_id INT UNSIGNED NOT NULL,
    substance VARCHAR(40) NOT NULL,
    score SMALLINT UNSIGNED NULL,
    risk_level VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_senda_assist_results_referral_substance (assisted_referral_id, substance),
    INDEX idx_senda_assist_results_referral (assisted_referral_id),
    CONSTRAINT fk_senda_assist_results_referral
        FOREIGN KEY (assisted_referral_id) REFERENCES senda_assisted_referrals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
