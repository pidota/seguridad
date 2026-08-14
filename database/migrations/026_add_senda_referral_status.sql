ALTER TABLE senda_assisted_referrals
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER is_completed;

UPDATE senda_assisted_referrals
    SET status = IF(is_completed = 1, 'completed', 'draft');

ALTER TABLE senda_assisted_referrals
    ADD INDEX idx_senda_assisted_referrals_status (status);
