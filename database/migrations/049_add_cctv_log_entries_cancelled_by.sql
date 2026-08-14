ALTER TABLE cctv_log_entries
    ADD COLUMN cancelled_by INT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX idx_cctv_log_entries_cancelled_by (cancelled_by),
    ADD CONSTRAINT fk_cctv_log_entries_cancelled_by
        FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE RESTRICT;
