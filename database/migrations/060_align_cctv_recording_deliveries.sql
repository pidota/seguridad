ALTER TABLE cctv_recording_deliveries
    ADD COLUMN receiver_relationship VARCHAR(80) NULL AFTER receiver_rut,
    ADD COLUMN authorization_document VARCHAR(255) NULL AFTER receiver_relationship,
    ADD COLUMN public_notes TEXT NULL AFTER notes,
    ADD COLUMN internal_notes TEXT NULL AFTER public_notes,
    ADD COLUMN file_internal_name VARCHAR(180) NULL AFTER internal_notes,
    ADD COLUMN file_camera_id INT UNSIGNED NULL AFTER file_internal_name,
    ADD COLUMN file_video_date DATE NULL AFTER file_camera_id,
    ADD COLUMN file_time_from TIME NULL AFTER file_video_date,
    ADD COLUMN file_time_to TIME NULL AFTER file_time_from,
    ADD COLUMN file_size_bytes INT UNSIGNED NULL AFTER file_time_to,
    ADD COLUMN file_hash_sha256 CHAR(64) NULL AFTER file_size_bytes,
    ADD CONSTRAINT fk_cctv_recording_deliveries_file_camera
        FOREIGN KEY (file_camera_id) REFERENCES cctv_cameras(id) ON DELETE SET NULL;
