ALTER TABLE cctv_shifts
    DROP FOREIGN KEY fk_cctv_shifts_opened_by;

ALTER TABLE cctv_shifts
    DROP FOREIGN KEY fk_cctv_shifts_closed_by;

ALTER TABLE cctv_shifts
    DROP INDEX idx_cctv_shifts_band;

ALTER TABLE cctv_shifts
    DROP COLUMN shift_band,
    DROP COLUMN opened_by,
    DROP COLUMN closed_by;
