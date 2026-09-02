-- =============================================================================
-- SEED 09 — Usuarios de demostración (SOLO desarrollo/QA).
-- Contraseñas de prueba: "Demo12345!" para los clientes, "Admin12345!" para
-- el admin. Los hashes ya están generados con Hash::make() (bcrypt), no son
-- texto plano. Requiere que tipo_documento/rol/estado_usuario ya estén
-- sembrados (TipoDocumentoSeeder/RolSeeder/EstadoUsuarioSeeder de Laravel).
-- =============================================================================

INSERT IGNORE INTO usuario
    (primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
     tipo_documento_codigo, numero_documento, rol_codigo, correo, celular,
     fecha_nacimiento, estado_codigo, contrasena_hash)
VALUES
    ('Daniel', 'Andrés', 'Molina', 'Castañeda', 'CC', '1017123456', 'ADMIN',
     'admin@playboss.com', '3001234567', '1994-03-12', 'ACT',
     '$2y$12$GQlkRJvcr3uJApbwz5k8F.emmZBIwHxNWY79FXfig4oYAl.AFoCk2'),

    ('Valentina', NULL, 'Ramírez', 'Gómez', 'CC', '1020456789', 'CLIENTE',
     'valentina.ramirez@playboss.demo', '3012345678', '1998-07-21', 'ACT',
     '$2y$12$v3aOyi5P4In3.z3hWTeCRuZcU9.sl0OVcjr.bEdmfx2E9oka9BXVa'),

    ('Santiago', 'Andrés', 'Torres', 'López', 'CC', '1030567890', 'CLIENTE',
     'santiago.torres@playboss.demo', '3023456789', '1996-11-05', 'ACT',
     '$2y$12$v3aOyi5P4In3.z3hWTeCRuZcU9.sl0OVcjr.bEdmfx2E9oka9BXVa'),

    ('Camila', NULL, 'Rodríguez', 'Pérez', 'CE', '1040678901', 'CLIENTE',
     'camila.rodriguez@playboss.demo', '3034567890', '2000-02-18', 'ACT',
     '$2y$12$v3aOyi5P4In3.z3hWTeCRuZcU9.sl0OVcjr.bEdmfx2E9oka9BXVa'),

    ('Juan', 'Pablo', 'Hernández', 'Suárez', 'CC', '1050789012', 'CLIENTE',
     'juanpablo.hernandez@playboss.demo', '3045678901', '1992-09-30', 'INA',
     '$2y$12$v3aOyi5P4In3.z3hWTeCRuZcU9.sl0OVcjr.bEdmfx2E9oka9BXVa');

-- Billetera para cada usuario demo (saldo inicial de cortesía, solo dev).
INSERT IGNORE INTO cuenta (usuario_id, saldo_disponible, saldo_retenido)
SELECT id_usuario, 500000.00, 0 FROM usuario WHERE correo = 'admin@playboss.com';

INSERT IGNORE INTO cuenta (usuario_id, saldo_disponible, saldo_retenido)
SELECT id_usuario, 150000.00, 0 FROM usuario WHERE correo = 'valentina.ramirez@playboss.demo';

INSERT IGNORE INTO cuenta (usuario_id, saldo_disponible, saldo_retenido)
SELECT id_usuario, 80000.00, 0 FROM usuario WHERE correo = 'santiago.torres@playboss.demo';

INSERT IGNORE INTO cuenta (usuario_id, saldo_disponible, saldo_retenido)
SELECT id_usuario, 0.00, 0 FROM usuario WHERE correo = 'camila.rodriguez@playboss.demo';

INSERT IGNORE INTO cuenta (usuario_id, saldo_disponible, saldo_retenido)
SELECT id_usuario, 20000.00, 0 FROM usuario WHERE correo = 'juanpablo.hernandez@playboss.demo';
