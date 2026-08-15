INSERT INTO cctv_recording_request_statuses (slug, name, description, tone, sort_order, is_terminal)
VALUES
    ('incomplete_documentation', 'Documentación incompleta', 'Existe denuncia informada pero faltan antecedentes o verificación', 'warning', 15, 0),
    ('cancelled', 'Anulada', 'Solicitud anulada administrativamente', 'danger', 85, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    is_terminal = VALUES(is_terminal);
