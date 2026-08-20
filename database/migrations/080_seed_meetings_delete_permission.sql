INSERT INTO permissions (slug, name, module, description)
VALUES (
    'meetings.delete',
    'Eliminar reuniones',
    'meetings',
    'Eliminar registros de reunión sin confirmación de asistencia externa'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    module = VALUES(module),
    description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'meetings.delete'
WHERE r.slug IN ('senda', 'administrador_seguridad');
