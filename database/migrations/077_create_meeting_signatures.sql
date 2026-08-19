CREATE TABLE IF NOT EXISTS user_signatures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_signatures_user (user_id),
    KEY idx_user_signatures_active (user_id, is_active),
    CONSTRAINT fk_user_signatures_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_signatures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT UNSIGNED NOT NULL,
    participant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    signature_snapshot_path VARCHAR(255) NULL,
    signed_at DATETIME NULL,
    signed_ip VARCHAR(45) NULL,
    content_hash_at_signing CHAR(64) NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,
    invalidated_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_meeting_signatures_participant (participant_id),
    KEY idx_meeting_signatures_meeting (meeting_id),
    KEY idx_meeting_signatures_user_status (user_id, status),
    CONSTRAINT fk_meeting_signatures_meeting FOREIGN KEY (meeting_id) REFERENCES meetings (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_signatures_participant FOREIGN KEY (participant_id) REFERENCES meeting_participants (id) ON DELETE CASCADE,
    CONSTRAINT fk_meeting_signatures_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
