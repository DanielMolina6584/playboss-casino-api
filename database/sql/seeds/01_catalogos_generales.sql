-- =============================================================================
-- PLAYBOSS — SEED 01: Catálogos generales
-- Países, posiciones, estados, servicio/mercados de apuesta, métodos de pago.
-- No depende de ningún otro seed. Usa INSERT IGNORE: reejecutar es seguro.
-- =============================================================================

-- Países (nacionalidades de jugadores más comunes en el fútbol colombiano)
INSERT IGNORE INTO pais (codigo_iso, nombre) VALUES
    ('CO', 'Colombia'),
    ('AR', 'Argentina'),
    ('UY', 'Uruguay'),
    ('BR', 'Brasil'),
    ('VE', 'Venezuela'),
    ('EC', 'Ecuador'),
    ('PY', 'Paraguay'),
    ('CL', 'Chile'),
    ('PA', 'Panamá'),
    ('PE', 'Perú'),
    ('US', 'Estados Unidos'),
    ('ES', 'España');

-- Posiciones de juego
INSERT IGNORE INTO posicion (codigo, nombre) VALUES
    ('POR', 'Portero'),
    ('DEF', 'Defensa'),
    ('MED', 'Mediocampista'),
    ('DEL', 'Delantero');

-- Estados de un partido
INSERT IGNORE INTO estado_partido (codigo, nombre) VALUES
    ('PROG', 'Programado'),
    ('VIVO', 'En vivo'),
    ('FIN', 'Finalizado'),
    ('SUSP', 'Suspendido');

-- Estados de una apuesta
INSERT IGNORE INTO estado_apuesta (codigo, nombre) VALUES
    ('PEND', 'Pendiente'),
    ('GAN', 'Ganada'),
    ('PERD', 'Perdida'),
    ('ANUL', 'Anulada');

-- Servicio/línea de apuestas (liga y mercado cuelgan de aquí)
INSERT IGNORE INTO servicio (codigo, nombre, activo) VALUES
    ('FUTBOL', 'Fútbol', TRUE),
    ('BALONCE', 'Baloncesto', FALSE),
    ('TENIS', 'Tenis', FALSE);

-- Métodos de pago habituales en Colombia
INSERT IGNORE INTO metodo_pago (codigo, nombre, activo) VALUES
    ('PSE', 'PSE', TRUE),
    ('TDC', 'Tarjeta débito/crédito', TRUE),
    ('NEQUI', 'Nequi', TRUE),
    ('DAVIPLAT', 'Daviplata', TRUE),
    ('EFECTY', 'Efecty', TRUE);

-- Mercados de apuesta para el servicio de fútbol
INSERT IGNORE INTO mercado (codigo, nombre, servicio_codigo) VALUES
    ('1X2', 'Resultado del partido', 'FUTBOL'),
    ('DOBLE', 'Doble oportunidad', 'FUTBOL'),
    ('OU25', 'Más/menos de 2.5 goles', 'FUTBOL'),
    ('BTTS', 'Ambos equipos anotan', 'FUTBOL');

-- Reglas de negocio por mercado (montos en COP)
INSERT IGNORE INTO regla_apuesta (mercado_codigo, monto_minimo, monto_maximo, ganancia_maxima, edad_minima, activa, fecha_vigencia_desde) VALUES
    ('1X2', 2000, 5000000, 20000000, 18, TRUE, '2026-01-01'),
    ('DOBLE', 2000, 3000000, 10000000, 18, TRUE, '2026-01-01'),
    ('OU25', 2000, 3000000, 10000000, 18, TRUE, '2026-01-01'),
    ('BTTS', 2000, 3000000, 10000000, 18, TRUE, '2026-01-01');
