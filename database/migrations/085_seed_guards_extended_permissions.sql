INSERT INTO permissions (slug, name, module, description)
SELECT slug, name, module, description
FROM (
    SELECT 'guards.shifts.close' AS slug, 'Cerrar turnos de guardias' AS name, 'guards' AS module, 'Finalizar turno operativo en terreno' AS description
    UNION ALL SELECT 'guards.shifts.view_all', 'Ver todos los turnos de guardias', 'guards', 'Consultar turnos de todo el personal de guardia'
    UNION ALL SELECT 'guards.log.view', 'Ver bitácora de guardias', 'guards', 'Consultar novedades y rondas registradas'
    UNION ALL SELECT 'guards.log.create', 'Registrar novedades de guardias', 'guards', 'Agregar entradas a la bitácora de terreno'
    UNION ALL SELECT 'guards.log.link_cctv', 'Notificar monitoreo CCTV', 'guards', 'Enviar novedades de guardias a la bitácora de operadores CCTV'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.slug = seed.slug
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug IN ('superadministrador', 'administrador_seguridad', 'guardias')
  AND p.slug IN (
      'guards.shifts.close',
      'guards.shifts.view_all',
      'guards.log.view',
      'guards.log.create',
      'guards.log.link_cctv'
  )
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'consulta'
  AND p.slug IN ('guards.log.view', 'guards.shifts.view_all')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
