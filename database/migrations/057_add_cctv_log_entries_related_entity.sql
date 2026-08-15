ALTER TABLE cctv_log_entries
    ADD COLUMN related_entity_type VARCHAR(40) NULL AFTER status,
    ADD COLUMN related_entity_id INT UNSIGNED NULL AFTER related_entity_type,
    ADD INDEX idx_cctv_log_entries_related_entity (related_entity_type, related_entity_id);
