INSERT INTO women_referral_institutions (slug, name, sort_order) VALUES
('salud_mental', 'Salud mental / SAPU', 10),
('cesfam', 'CESFAM / APS', 20),
('hospital', 'Hospital / urgencia', 30),
('carabineros', 'Carabineros', 40),
('fiscalia', 'Fiscalía / Ministerio Público', 50),
('juzgado', 'Juzgado de Familia', 60),
('serviu', 'SERVIU / vivienda', 70),
('programas_sociales', 'Programas sociales municipales', 80),
('otra', 'Otra institución', 90)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);
