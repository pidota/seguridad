ALTER TABLE cctv_log_entries
    ADD COLUMN current_operator_id INT UNSIGNED NULL AFTER created_by;

ALTER TABLE cctv_log_entries
    ADD CONSTRAINT fk_cctv_log_entries_current_operator
        FOREIGN KEY (current_operator_id) REFERENCES users(id) ON DELETE SET NULL;

UPDATE cctv_log_entries
SET current_operator_id = created_by
WHERE current_operator_id IS NULL
  AND created_by IS NOT NULL;
