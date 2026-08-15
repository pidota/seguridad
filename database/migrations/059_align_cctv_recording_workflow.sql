ALTER TABLE cctv_recording_requests
    ADD COLUMN received_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN complaint_verified_by INT UNSIGNED NULL AFTER complaint_document_size,
    ADD COLUMN complaint_verified_at DATETIME NULL AFTER complaint_verified_by,
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER reviewed_at,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
    ADD COLUMN assigned_to INT UNSIGNED NULL AFTER approved_at,
    ADD COLUMN recording_preserved TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_to,
    ADD COLUMN preserved_by INT UNSIGNED NULL AFTER recording_preserved,
    ADD COLUMN preserved_at DATETIME NULL AFTER preserved_by,
    ADD COLUMN rejection_reason VARCHAR(80) NULL AFTER preserved_at,
    ADD COLUMN rejection_notes TEXT NULL AFTER rejection_reason,
    ADD COLUMN not_found_reason VARCHAR(80) NULL AFTER rejection_notes,
    ADD COLUMN not_found_notes TEXT NULL AFTER not_found_reason,
    ADD COLUMN not_found_cameras_reviewed TEXT NULL AFTER not_found_notes,
    ADD COLUMN retention_until DATE NULL AFTER not_found_cameras_reviewed,
    ADD COLUMN cancelled_by INT UNSIGNED NULL AFTER retention_until,
    ADD COLUMN cancelled_at DATETIME NULL AFTER cancelled_by,
    ADD COLUMN cancellation_reason TEXT NULL AFTER cancelled_at,
    ADD COLUMN public_notes TEXT NULL AFTER cancellation_reason,
    ADD COLUMN internal_notes TEXT NULL AFTER public_notes,
    ADD CONSTRAINT fk_cctv_recording_requests_received_by
        FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cctv_recording_requests_complaint_verified_by
        FOREIGN KEY (complaint_verified_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cctv_recording_requests_approved_by
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cctv_recording_requests_assigned_to
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cctv_recording_requests_preserved_by
        FOREIGN KEY (preserved_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_cctv_recording_requests_cancelled_by
        FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE cctv_recording_request_history
    ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT 'status_change' AFTER recording_request_id,
    ADD INDEX idx_cctv_recording_request_history_event (event_type);
