INSERT INTO cctv_equipment (slug, name, sort_order, is_active, deleted_at) VALUES
    ('celular', 'Celular', 10, 1, NULL),
    ('computador', 'Computador', 20, 1, NULL),
    ('monitores', 'Monitores', 30, 1, NULL),
    ('joystick', 'Joystick', 40, 1, NULL),
    ('sistema_cctv', 'Sistema CCTV', 50, 1, NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active),
    deleted_at = NULL,
    updated_at = NOW();
