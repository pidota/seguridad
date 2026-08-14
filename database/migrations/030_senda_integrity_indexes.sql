ALTER TABLE senda_attentions
    MODIFY senda_person_id INT UNSIGNED NOT NULL;

ALTER TABLE senda_assisted_referrals
    DROP INDEX idx_senda_assisted_referrals_attention,
    ADD UNIQUE INDEX uq_senda_assisted_referrals_attention (senda_attention_id);

ALTER TABLE senda_follow_ups
    ADD INDEX idx_senda_follow_ups_schedule (deleted_at, requires_follow_up, next_follow_up_date);
