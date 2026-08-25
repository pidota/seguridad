INSERT INTO permissions (slug, name, module, description)
SELECT * FROM (
    SELECT 'cctv.handovers.view' AS slug, 'Ver traspasos CCTV' AS name, 'cctv' AS module, 'Consultar pendientes recibidos entre turnos' AS description
    UNION ALL
    SELECT 'cctv.handovers.accept', 'Aceptar continuidad CCTV', 'cctv', 'Aceptar procedimientos traspasados al turno actual'
    UNION ALL
    SELECT 'cctv.handovers.decline', 'Declinar continuidad CCTV', 'cctv', 'Finalizar procedimientos traspasados con motivo y justificación'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.slug = seed.slug
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE p.slug IN ('cctv.handovers.view', 'cctv.handovers.accept', 'cctv.handovers.decline')
  AND r.slug IN ('superadministrador', 'administrador_seguridad', 'operador_camaras')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
