INSERT INTO women_case_statuses (slug, name, sort_order) VALUES
('registered', 'Registrado', 10),
('active', 'En atención', 20),
('follow_up', 'En seguimiento', 30),
('referred', 'Derivado', 40),
('closed', 'Finalizado', 50),
('cancelled', 'Anulado', 60)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_report_channels (slug, name, allows_other, sort_order) VALUES
('presencial', 'Atención presencial', 0, 10),
('telefonico', 'Telefónico', 0, 20),
('derivacion', 'Derivación institucional', 0, 30),
('email', 'Correo electrónico', 0, 40),
('otro', 'Otro', 1, 50)
ON DUPLICATE KEY UPDATE name = VALUES(name), allows_other = VALUES(allows_other), sort_order = VALUES(sort_order);

INSERT INTO women_violence_types (slug, name, allows_other, sort_order) VALUES
('fisica', 'Violencia física', 0, 10),
('psicologica', 'Violencia psicológica', 0, 20),
('sexual', 'Violencia sexual', 0, 30),
('economica', 'Violencia económica/patrimonial', 0, 40),
('digital', 'Violencia digital', 0, 50),
('acoso', 'Acoso', 0, 60),
('amenazas', 'Amenazas', 0, 70),
('otra', 'Otra', 1, 80)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_relationship_types (slug, name, allows_other, sort_order) VALUES
('conyuge', 'Cónyuge', 0, 10),
('ex_conyuge', 'Ex cónyuge', 0, 20),
('conviviente', 'Conviviente', 0, 30),
('ex_conviviente', 'Ex conviviente', 0, 40),
('pareja', 'Pareja', 0, 50),
('ex_pareja', 'Ex pareja', 0, 60),
('familiar', 'Familiar', 0, 70),
('conocido', 'Conocido', 0, 80),
('companero_trabajo', 'Compañero de trabajo', 0, 90),
('desconocido', 'Desconocido', 0, 100),
('otro', 'Otro', 1, 110)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_risk_factors (slug, name, allows_other, sort_order) VALUES
('amenazas', 'Amenazas', 0, 10),
('escalada', 'Escalada reciente de violencia', 0, 20),
('armas', 'Acceso a armas informado', 0, 30),
('consumo', 'Consumo problemático de alcohol/drogas informado', 0, 40),
('control', 'Control o aislamiento', 0, 50),
('acoso', 'Acoso persistente', 0, 60),
('incumplimiento_medidas', 'Incumplimiento de medidas previas', 0, 70),
('dependencia_economica', 'Dependencia económica', 0, 80),
('convivencia', 'Convivencia con persona denunciada', 0, 90),
('otro', 'Otro', 1, 100)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_needs (slug, name, allows_other, sort_order) VALUES
('orientacion', 'Orientación', 0, 10),
('apoyo_juridico', 'Apoyo jurídico', 0, 20),
('apoyo_psicologico', 'Apoyo psicológico', 0, 30),
('apoyo_social', 'Apoyo social', 0, 40),
('proteccion', 'Protección', 0, 50),
('alojamiento', 'Alojamiento temporal', 0, 60),
('salud', 'Atención de salud', 0, 70),
('derivacion', 'Derivación', 0, 80),
('otra', 'Otra', 1, 90)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_action_types (slug, name, allows_other, sort_order) VALUES
('orientacion', 'Orientación', 0, 10),
('contencion', 'Contención inicial', 0, 20),
('derivacion', 'Derivación', 0, 30),
('contacto_telefonico', 'Contacto telefónico', 0, 40),
('coordinacion', 'Coordinación institucional', 0, 50),
('informacion', 'Entrega de información', 0, 60),
('otra', 'Otra', 1, 70)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_referral_statuses (slug, name, sort_order) VALUES
('pending', 'Pendiente', 10),
('done', 'Realizada', 20),
('accepted', 'Aceptada', 30),
('not_done', 'No concretada', 40)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_formal_report_institutions (slug, name, allows_other, sort_order) VALUES
('carabineros', 'Carabineros', 0, 10),
('pdi', 'PDI', 0, 20),
('fiscalia', 'Fiscalía', 0, 30),
('tribunal', 'Tribunal', 0, 40),
('otra', 'Otra', 1, 50)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_followup_contact_types (slug, name, allows_other, sort_order) VALUES
('telefonico', 'Telefónico', 0, 10),
('presencial', 'Presencial', 0, 20),
('email', 'Correo electrónico', 0, 30),
('coordinacion', 'Coordinación institucional', 0, 40),
('visita', 'Visita domiciliaria', 0, 50),
('otro', 'Otro', 1, 60)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_followup_results (slug, name, allows_other, sort_order) VALUES
('contacto', 'Contacto realizado', 0, 10),
('sin_contacto', 'No fue posible contactar', 0, 20),
('continua', 'Continúa atención', 0, 30),
('nueva_derivacion', 'Nueva derivación', 0, 40),
('requiere_atencion', 'Requiere nueva atención', 0, 50),
('finalizado', 'Seguimiento finalizado', 0, 60),
('otro', 'Otro', 1, 70)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_minor_age_ranges (slug, name, sort_order) VALUES
('0_5', '0 a 5 años', 10),
('6_11', '6 a 11 años', 20),
('12_17', '12 a 17 años', 30)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);

INSERT INTO women_education_levels (slug, name, sort_order) VALUES
('sin_estudios', 'Sin estudios formales', 10),
('basica', 'Educación básica', 20),
('media', 'Educación media', 30),
('tecnica', 'Educación técnica', 40),
('superior', 'Educación superior', 50),
('no_informa', 'No informa', 60)
ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order);
