ALTER TABLE senda_assisted_referrals
    ADD COLUMN screening_used TINYINT(1) NULL AFTER risk_notes;
