ALTER TABLE senda_attentions
    ADD CONSTRAINT fk_senda_attentions_person
        FOREIGN KEY (person_id) REFERENCES senda_people(id) ON DELETE RESTRICT;
