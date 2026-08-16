ALTER TABLE cctv_cameras
    ADD COLUMN latitude DECIMAL(10, 7) NULL AFTER location,
    ADD COLUMN longitude DECIMAL(10, 7) NULL AFTER latitude,
    ADD INDEX idx_cctv_cameras_coordinates (latitude, longitude);
