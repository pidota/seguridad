ALTER TABLE senda_people
    ADD COLUMN motivo TEXT NULL AFTER occupation,
    ADD COLUMN orientaciones TEXT NULL AFTER motivo,
    ADD COLUMN gestion TEXT NULL AFTER orientaciones;
