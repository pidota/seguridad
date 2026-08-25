INSERT INTO guards_log_types (slug, name, description, tone, sort_order, is_active)
VALUES
    ('novedad', 'Novedad', 'Observación general en terreno', 'other', 10, 1),
    ('ronda', 'Ronda', 'Registro de ronda o recorrido', 'support', 20, 1),
    ('incidencia', 'Incidencia', 'Situación o evento en terreno', 'incident', 30, 1),
    ('coordinacion', 'Coordinación', 'Coordinación con otros servicios', 'support', 40, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);
