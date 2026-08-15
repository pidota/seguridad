ALTER TABLE cctv_office_visits
    ADD COLUMN visit_reason VARCHAR(40) NULL AFTER visitor_type,
    ADD COLUMN visit_reason_other VARCHAR(180) NULL AFTER visit_reason,
    ADD COLUMN authorized_by INT UNSIGNED NULL AFTER organization,
    ADD COLUMN internal_notes TEXT NULL AFTER reason,
    ADD INDEX idx_cctv_office_visits_departure (departure_time),
    ADD INDEX idx_cctv_office_visits_current (visit_date, departure_time),
    ADD CONSTRAINT fk_cctv_office_visits_authorized_by
        FOREIGN KEY (authorized_by) REFERENCES users(id) ON DELETE SET NULL;
