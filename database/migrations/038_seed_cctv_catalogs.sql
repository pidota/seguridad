INSERT INTO cctv_log_types (slug, name, description, tone, sort_order, requires_incident)
VALUES
    ('novedad', 'Novedad', 'Registro general de una novedad observada en monitoreo', 'alert', 10, 0),
    ('incidente', 'Incidente', 'Hecho que requiere clasificación de incidente', 'alert', 20, 1),
    ('novedad_tecnica', 'Novedad Técnica', 'Falla, intermitencia o problema técnico de equipos', 'technical', 30, 0),
    ('comunicacion_coordinacion', 'Comunicación / Coordinación', 'Contacto o coordinación con otros servicios', 'support', 40, 0),
    ('recepcion_entrega', 'Recepción / Entrega', 'Recepción o entrega de información, equipos o materiales', 'routine', 50, 0),
    ('otro', 'Otro', 'Tipo de registro no listado', 'other', 90, 0)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    requires_incident = VALUES(requires_incident);

INSERT INTO cctv_incident_types (slug, name, description, tone, sort_order, allows_other)
VALUES
    ('consumo_alcohol_via_publica', 'Consumo de alcohol en vía pública', NULL, 'alert', 10, 0),
    ('rina_via_publica', 'Riña en vía pública', NULL, 'alert', 20, 0),
    ('violencia', 'Violencia', NULL, 'alert', 30, 0),
    ('vehiculo_mal_estacionado', 'Vehículo mal estacionado', NULL, 'traffic', 40, 0),
    ('situacion_sospechosa', 'Situación sospechosa', NULL, 'alert', 50, 0),
    ('danos', 'Daños', NULL, 'crime', 60, 0),
    ('accidente', 'Accidente', NULL, 'traffic', 70, 0),
    ('emergencia', 'Emergencia', NULL, 'medical', 80, 0),
    ('otro', 'Otro', NULL, 'other', 900, 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    tone = VALUES(tone),
    sort_order = VALUES(sort_order),
    allows_other = VALUES(allows_other);
