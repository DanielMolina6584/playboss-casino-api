-- =============================================================================
-- SEED 03 — Geografía, liga colombiana, temporada 2026-I y sus 20 equipos.
-- DATOS REALES (investigados): ciudades, estadios, equipos y formato del
-- Torneo Apertura 2026 (Categoría Primera A / Liga BetPlay Dimayor).
-- Fuente: Wikipedia "Torneo Apertura 2026 (Colombia)".
-- fecha_fundacion se deja NULL a propósito: no se verificó dato por dato.
-- =============================================================================

INSERT IGNORE INTO ciudad (nombre, pais_codigo) VALUES
('Armenia', 'CO'),
('Villavicencio', 'CO'),
('Bogotá', 'CO'),
('Valledupar', 'CO'),
('Cali', 'CO'),
('Medellín', 'CO'),
('Tunja', 'CO'),
('Bucaramanga', 'CO'),
('Pasto', 'CO'),
('Montería', 'CO'),
('Palmira', 'CO'),
('Barranquilla', 'CO'),
('Ibagué', 'CO'),
('Cúcuta', 'CO'),
('Manizales', 'CO');

INSERT IGNORE INTO estadio (nombre, ciudad_id, capacidad) VALUES
('Centenario de Armenia', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Armenia' AND pais_codigo = 'CO'), 20716),
('Bello Horizonte - Rey Pelé', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Villavicencio' AND pais_codigo = 'CO'), 18000),
('Metropolitano de Techo', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bogotá' AND pais_codigo = 'CO'), 10000),
('Armando Maestre Pavajeau', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Valledupar' AND pais_codigo = 'CO'), 11500),
('Olímpico Pascual Guerrero', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Cali' AND pais_codigo = 'CO'), 37899),
('Atanasio Girardot', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Medellín' AND pais_codigo = 'CO'), 46000),
('La Independencia', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Tunja' AND pais_codigo = 'CO'), 20630),
('Américo Montanini', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bucaramanga' AND pais_codigo = 'CO'), 25000),
('Nemesio Camacho El Campín', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bogotá' AND pais_codigo = 'CO'), 36340),
('Departamental Libertad', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Pasto' AND pais_codigo = 'CO'), 20665),
('Jaraguay', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Montería' AND pais_codigo = 'CO'), 12000),
('Deportivo Cali', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Palmira' AND pais_codigo = 'CO'), 44000),
('Metropolitano Roberto Meléndez', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Barranquilla' AND pais_codigo = 'CO'), 46690),
('Manuel Murillo Toro', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Ibagué' AND pais_codigo = 'CO'), 28100),
('Cincuentenario', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Medellín' AND pais_codigo = 'CO'), 4000),
('General Santander', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Cúcuta' AND pais_codigo = 'CO'), 42000),
('Palogrande', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Manizales' AND pais_codigo = 'CO'), 28678);

INSERT IGNORE INTO liga (servicio_codigo, pais_codigo, nombre, activa) VALUES
('FUTBOL', 'CO', 'Categoría Primera A (Liga BetPlay Dimayor)', 1);

INSERT IGNORE INTO temporada (liga_id, nombre, fecha_inicio, fecha_fin)
SELECT id_liga, '2026-I', '2026-01-16', '2026-06-30'
FROM liga WHERE nombre = 'Categoría Primera A (Liga BetPlay Dimayor)';

INSERT IGNORE INTO equipo (nombre, ciudad_id, estadio_id) VALUES
('Deportivo Pereira', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Armenia' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Centenario de Armenia')),
('Llaneros', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Villavicencio' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Bello Horizonte - Rey Pelé')),
('Fortaleza', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bogotá' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Metropolitano de Techo')),
('Alianza Valledupar', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Valledupar' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Armando Maestre Pavajeau')),
('América de Cali', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Cali' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Olímpico Pascual Guerrero')),
('Internacional de Bogotá', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bogotá' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Metropolitano de Techo')),
('Atlético Nacional', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Medellín' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Atanasio Girardot')),
('Boyacá Chicó', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Tunja' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'La Independencia')),
('Atlético Bucaramanga', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bucaramanga' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Américo Montanini')),
('Millonarios', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bogotá' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Nemesio Camacho El Campín')),
('Deportivo Pasto', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Pasto' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Departamental Libertad')),
('Independiente Medellín', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Medellín' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Atanasio Girardot')),
('Jaguares', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Montería' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Jaraguay')),
('Deportivo Cali', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Palmira' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Deportivo Cali')),
('Junior', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Barranquilla' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Metropolitano Roberto Meléndez')),
('Deportes Tolima', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Ibagué' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Manuel Murillo Toro')),
('Independiente Santa Fe', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Bogotá' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Nemesio Camacho El Campín')),
('Águilas Doradas', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Medellín' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Cincuentenario')),
('Cúcuta Deportivo', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Cúcuta' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'General Santander')),
('Once Caldas', (SELECT id_ciudad FROM ciudad WHERE nombre = 'Manizales' AND pais_codigo='CO'), (SELECT id_estadio FROM estadio WHERE nombre = 'Palogrande'));

-- Inscripción de los 20 equipos a la temporada 2026-I (puntos = resultado real de la jornada 1).
INSERT IGNORE INTO equipo_liga_temporada (equipo_id, temporada_id, fecha_inscripcion, puntos)
SELECT e.id_equipo, t.id_temporada, '2026-01-10', pts.puntos
FROM equipo e
JOIN temporada t ON t.nombre = '2026-I'
JOIN (
  SELECT 'Deportivo Pereira' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Llaneros' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Fortaleza' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Alianza Valledupar' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'América de Cali' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Internacional de Bogotá' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Atlético Nacional' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Boyacá Chicó' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Millonarios' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Deportivo Pasto' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Independiente Medellín' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Jaguares' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Deportivo Cali' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Junior' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Deportes Tolima' AS nombre, 3 AS puntos
  UNION ALL
  SELECT 'Independiente Santa Fe' AS nombre, 1 AS puntos
  UNION ALL
  SELECT 'Águilas Doradas' AS nombre, 1 AS puntos
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS nombre, 0 AS puntos
  UNION ALL
  SELECT 'Once Caldas' AS nombre, 3 AS puntos
) pts ON pts.nombre = e.nombre;
