ALTER TABLE senda_assisted_referrals
    ADD COLUMN applicant_kind VARCHAR(80) NULL AFTER destination_commune;
