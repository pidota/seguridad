ALTER TABLE cctv_cameras
    DROP FOREIGN KEY fk_cctv_cameras_created_by;

ALTER TABLE cctv_cameras
    DROP FOREIGN KEY fk_cctv_cameras_updated_by;

ALTER TABLE cctv_cameras
    CHANGE COLUMN location_description location VARCHAR(255) NULL,
    CHANGE COLUMN is_active active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN camera_type VARCHAR(40) NOT NULL DEFAULT 'fija' AFTER location,
    ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'operativa' AFTER camera_type;

ALTER TABLE cctv_cameras
    DROP COLUMN latitude,
    DROP COLUMN longitude,
    DROP COLUMN ip_address,
    DROP COLUMN notes,
    DROP COLUMN created_by,
    DROP COLUMN updated_by;

ALTER TABLE cctv_cameras
    DROP INDEX idx_cctv_cameras_active;

ALTER TABLE cctv_cameras
    ADD INDEX idx_cctv_cameras_active (active),
    ADD INDEX idx_cctv_cameras_type (camera_type),
    ADD INDEX idx_cctv_cameras_status (status);
