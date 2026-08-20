ALTER TABLE meeting_participants
    ADD COLUMN attendance_token CHAR(64) NULL AFTER external_email,
    ADD COLUMN attendance_status ENUM('pending', 'confirmed', 'declined') NOT NULL DEFAULT 'pending' AFTER attendance_token,
    ADD COLUMN attendance_responded_at DATETIME NULL AFTER attendance_status,
    ADD COLUMN attendance_email_sent_at DATETIME NULL AFTER attendance_responded_at,
    ADD COLUMN attendance_response_ip VARCHAR(45) NULL AFTER attendance_email_sent_at,
    ADD UNIQUE KEY uk_meeting_participants_attendance_token (attendance_token);
