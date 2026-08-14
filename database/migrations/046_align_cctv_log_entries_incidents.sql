UPDATE cctv_log_entries
SET status = 'registrado'
WHERE status = 'recorded';

ALTER TABLE cctv_log_entries
    ADD COLUMN coordination_notified TINYINT(1) NULL AFTER police_arrival_time,
    ADD COLUMN incident_type_other VARCHAR(180) NULL AFTER cctv_incident_type_id,
    MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'registrado';
