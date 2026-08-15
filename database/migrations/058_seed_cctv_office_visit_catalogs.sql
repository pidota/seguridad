INSERT INTO cctv_log_types (slug, name, description, tone, sort_order, requires_incident)
VALUES
    ('visita_oficina', 'Visita oficina', 'Persona concurre a la oficina de operadores CCTV', 'routine', 55, 0),
    ('solicitud_grabacion', 'Solicitud de grabación', 'Solicitud de revisión o entrega de grabación CCTV', 'support', 56, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    requires_incident = VALUES(requires_incident);

INSERT INTO cctv_recording_request_statuses (slug, name, description, tone, sort_order, is_terminal)
VALUES
    ('pending_complaint', 'Pendiente de denuncia', 'La solicitud aguarda registro de denuncia previa', 'warning', 10, 0),
    ('pending_review', 'Pendiente de revisión', 'La solicitud aguarda revisión operativa', 'info', 20, 0),
    ('under_review', 'En revisión', 'La grabación está siendo revisada', 'info', 30, 0),
    ('recording_found', 'Grabación localizada', 'Se localizó material asociado al hecho', 'success', 40, 0),
    ('recording_not_found', 'Grabación no encontrada', 'No se encontró material asociado al hecho', 'danger', 50, 1),
    ('approved', 'Autorizada para entrega', 'La entrega fue autorizada por un responsable', 'success', 60, 0),
    ('delivered', 'Entregada', 'La grabación fue entregada al solicitante', 'success', 70, 1),
    ('rejected', 'Rechazada', 'La solicitud fue rechazada', 'danger', 80, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    is_terminal = VALUES(is_terminal);
