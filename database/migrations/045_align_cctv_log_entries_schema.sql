ALTER TABLE cctv_log_entries
    DROP FOREIGN KEY fk_cctv_log_entries_updated_by;

ALTER TABLE cctv_log_entries
    CHANGE COLUMN event_at occurred_at DATETIME NOT NULL,
    CHANGE COLUMN description observations TEXT NOT NULL,
    ADD COLUMN police_arrived TINYINT(1) NULL AFTER observations,
    ADD COLUMN police_arrival_time TIME NULL AFTER police_arrived,
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'recorded' AFTER police_arrival_time;

ALTER TABLE cctv_log_entries
    DROP COLUMN incident_type_other,
    DROP COLUMN location,
    DROP COLUMN actions_taken,
    DROP COLUMN updated_by;

ALTER TABLE cctv_log_entries
    DROP FOREIGN KEY fk_cctv_log_entries_created_by;

ALTER TABLE cctv_log_entries
    MODIFY COLUMN created_by INT UNSIGNED NOT NULL;

ALTER TABLE cctv_log_entries
    ADD CONSTRAINT fk_cctv_log_entries_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE cctv_log_entries
    DROP INDEX idx_cctv_log_entries_event_at,
    DROP INDEX idx_cctv_log_entries_shift_event;

ALTER TABLE cctv_log_entries
    ADD INDEX idx_cctv_log_entries_occurred_at (occurred_at),
    ADD INDEX idx_cctv_log_entries_status (status),
    ADD INDEX idx_cctv_log_entries_shift_occurred (cctv_shift_id, occurred_at);
