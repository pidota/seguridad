UPDATE cctv_log_types
SET is_active = 0,
    deleted_at = COALESCE(deleted_at, NOW())
WHERE deleted_at IS NULL;

UPDATE cctv_incident_types
SET is_active = 0,
    deleted_at = COALESCE(deleted_at, NOW())
WHERE deleted_at IS NULL;

INSERT INTO cctv_log_types (slug, name, description, tone, sort_order, is_active, requires_incident, deleted_at)
VALUES
    ('novedad', 'Novedad', 'Registro general de una novedad observada en monitoreo', 'alert', 10, 1, 0, NULL),
    ('incidente', 'Incidente', 'Hecho que requiere clasificación de incidente', 'alert', 20, 1, 1, NULL),
    ('novedad_tecnica', 'Novedad Técnica', 'Falla, intermitencia o problema técnico de equipos', 'technical', 30, 1, 0, NULL),
    ('comunicacion_coordinacion', 'Comunicación / Coordinación', 'Contacto o coordinación con otros servicios', 'support', 40, 1, 0, NULL),
    ('recepcion_entrega', 'Recepción / Entrega', 'Recepción o entrega de información, equipos o materiales', 'routine', 50, 1, 0, NULL),
    ('otro', 'Otro', 'Tipo de registro no listado', 'other', 90, 1, 0, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    is_active = 1,
    requires_incident = VALUES(requires_incident),
    deleted_at = NULL;

INSERT INTO cctv_incident_types (slug, name, description, tone, sort_order, is_active, allows_other, deleted_at)
VALUES
    ('consumo_alcohol_via_publica', 'Consumo de alcohol en vía pública', NULL, 'alert', 10, 1, 0, NULL),
    ('rina_via_publica', 'Riña en vía pública', NULL, 'alert', 20, 1, 0, NULL),
    ('violencia', 'Violencia', NULL, 'alert', 30, 1, 0, NULL),
    ('vehiculo_mal_estacionado', 'Vehículo mal estacionado', NULL, 'traffic', 40, 1, 0, NULL),
    ('situacion_sospechosa', 'Situación sospechosa', NULL, 'alert', 50, 1, 0, NULL),
    ('danos', 'Daños', NULL, 'crime', 60, 1, 0, NULL),
    ('accidente', 'Accidente', NULL, 'traffic', 70, 1, 0, NULL),
    ('emergencia', 'Emergencia', NULL, 'medical', 80, 1, 0, NULL),
    ('otro', 'Otro', NULL, 'other', 900, 1, 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    is_active = 1,
    allows_other = VALUES(allows_other),
    deleted_at = NULL;
