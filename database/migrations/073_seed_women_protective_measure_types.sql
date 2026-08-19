INSERT INTO women_protective_measure_types (slug, name, sort_order) VALUES
('orden_proteccion', 'Orden de protección / alejamiento', 10),
('medida_cautelar', 'Medida cautelar', 20),
('retiro_domicilio', 'Retiro del domicilio común', 30),
('prohibicion_acercamiento', 'Prohibición de acercamiento', 40),
('entrega_efectos', 'Entrega de efectos personales', 50),
('otra', 'Otra', 60)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);
