CREATE TABLE IF NOT EXISTS senda_attention_sequences (
    year SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE senda_attentions DROP FOREIGN KEY fk_senda_attentions_person;

ALTER TABLE senda_attentions
    CHANGE COLUMN person_id senda_person_id INT UNSIGNED NULL,
    CHANGE COLUMN attended_on attention_date DATE NOT NULL;

ALTER TABLE senda_attentions
    ADD COLUMN attention_number VARCHAR(32) NULL AFTER id,
    ADD COLUMN attention_time TIME NOT NULL DEFAULT '00:00:00' AFTER attention_date,
    ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
    ADD COLUMN referral_institution_type ENUM('centro_salud', 'centro_convenio', 'otra_institucion', 'otras') NULL AFTER entry_type,
    ADD COLUMN referral_institution_name VARCHAR(180) NULL AFTER referral_institution_type,
    ADD COLUMN referral_person VARCHAR(180) NULL AFTER referral_institution_name,
    ADD COLUMN referral_phone VARCHAR(30) NULL AFTER referral_person,
    ADD COLUMN referral_email VARCHAR(150) NULL AFTER referral_phone,
    ADD COLUMN referral_notes TEXT NULL AFTER referral_email;

ALTER TABLE senda_attentions
    ADD CONSTRAINT fk_senda_attentions_person
        FOREIGN KEY (senda_person_id) REFERENCES senda_people(id) ON DELETE RESTRICT;

UPDATE senda_attentions
SET attention_number = CONCAT('SENDA-', YEAR(attention_date), '-', LPAD(id, 6, '0'))
WHERE attention_number IS NULL;

ALTER TABLE senda_attentions
    MODIFY attention_number VARCHAR(32) NOT NULL;

ALTER TABLE senda_attentions
    ADD UNIQUE INDEX uq_senda_attentions_number (attention_number);

ALTER TABLE senda_attentions
    ADD INDEX idx_senda_attentions_deleted_at (deleted_at);

INSERT INTO senda_attention_sequences (year, last_number)
SELECT YEAR(attention_date), MAX(id)
FROM senda_attentions
GROUP BY YEAR(attention_date);
