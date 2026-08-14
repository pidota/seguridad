ALTER TABLE senda_assisted_referrals
    ADD COLUMN demand_origin VARCHAR(80) NULL AFTER request_date,
    ADD COLUMN receiving_officer VARCHAR(180) NULL AFTER demand_origin,
    ADD COLUMN demand_area VARCHAR(120) NULL AFTER receiving_officer;
