INSERT INTO permissions (slug, name, module, description)
SELECT slug, name, module, description
FROM (
    SELECT 'senda.statistics.export' AS slug, 'Exportar estadísticas SENDA' AS name, 'senda' AS module, 'Descargar indicadores agregados para rendición' AS description
    UNION ALL SELECT 'women.statistics.export', 'Exportar estadísticas Oficina de la Mujer', 'women', 'Descargar indicadores agregados para rendición'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.slug = seed.slug
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug IN ('superadministrador', 'administrador_seguridad', 'senda', 'oficina_mujer')
  AND p.slug IN ('senda.statistics.export', 'women.statistics.export')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug = 'consulta'
  AND p.slug IN ('senda.statistics.export', 'women.statistics.export')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
