ALTER TABLE senda_assisted_referrals
    ADD COLUMN enrolled_health_center VARCHAR(10) NULL AFTER indigenous_people,
    ADD COLUMN cesfam_name VARCHAR(180) NULL AFTER enrolled_health_center;
