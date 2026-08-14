ALTER TABLE senda_assisted_referrals
    ADD COLUMN previous_treatments_count SMALLINT UNSIGNED NULL AFTER has_previous_treatments,
    ADD COLUMN previous_treatment_modality VARCHAR(80) NULL AFTER previous_treatments_count,
    ADD COLUMN previous_treatment_stay VARCHAR(80) NULL AFTER previous_treatment_modality,
    ADD COLUMN previous_treatment_completed VARCHAR(10) NULL AFTER previous_treatment_stay,
    ADD COLUMN previous_treatment_center VARCHAR(180) NULL AFTER previous_treatment_completed,
    ADD COLUMN previous_treatment_commune VARCHAR(120) NULL AFTER previous_treatment_center;
