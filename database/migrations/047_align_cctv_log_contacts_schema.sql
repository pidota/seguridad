ALTER TABLE cctv_log_contacts
    CHANGE COLUMN contact_kind contact_type VARCHAR(40) NOT NULL;

ALTER TABLE cctv_log_contacts
    DROP FOREIGN KEY fk_cctv_log_contacts_created_by;

ALTER TABLE cctv_log_contacts
    DROP COLUMN contact_reference,
    DROP COLUMN outcome,
    DROP COLUMN updated_at,
    DROP COLUMN deleted_at,
    DROP COLUMN created_by;

DROP INDEX idx_cctv_log_contacts_kind ON cctv_log_contacts;

CREATE INDEX idx_cctv_log_contacts_type ON cctv_log_contacts (contact_type);
