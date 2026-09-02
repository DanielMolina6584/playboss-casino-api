-- =============================================================================
-- SEED 06 — Cuotas (mercado 1X2) para los partidos programados de jornada 2.
-- Valores DEMO/DESARROLLO (no cuotas reales de ninguna casa de apuestas).
-- =============================================================================

INSERT IGNORE INTO cuota (partido_id, mercado_codigo, seleccion, valor)
SELECT pa.id_partido, '1X2', c.seleccion, c.valor
FROM partido pa
JOIN equipo el ON el.id_equipo = pa.equipo_local_id
JOIN equipo ev ON ev.id_equipo = pa.equipo_visitante_id
JOIN (
  SELECT 'Alianza Valledupar' AS local, 'Deportivo Pereira' AS visitante, 'Local' AS seleccion, 2.02 AS valor
  UNION ALL SELECT 'Alianza Valledupar', 'Deportivo Pereira', 'Empate', 3.31
  UNION ALL SELECT 'Alianza Valledupar', 'Deportivo Pereira', 'Visitante', 3.68
  UNION ALL
  SELECT 'Internacional de Bogotá' AS local, 'Llaneros' AS visitante, 'Local' AS seleccion, 1.82 AS valor
  UNION ALL SELECT 'Internacional de Bogotá', 'Llaneros', 'Empate', 3.11
  UNION ALL SELECT 'Internacional de Bogotá', 'Llaneros', 'Visitante', 3.10
  UNION ALL
  SELECT 'Boyacá Chicó' AS local, 'Fortaleza' AS visitante, 'Local' AS seleccion, 2.46 AS valor
  UNION ALL SELECT 'Boyacá Chicó', 'Fortaleza', 'Empate', 3.47
  UNION ALL SELECT 'Boyacá Chicó', 'Fortaleza', 'Visitante', 3.22
  UNION ALL
  SELECT 'Millonarios' AS local, 'América de Cali' AS visitante, 'Local' AS seleccion, 2.90 AS valor
  UNION ALL SELECT 'Millonarios', 'América de Cali', 'Empate', 2.96
  UNION ALL SELECT 'Millonarios', 'América de Cali', 'Visitante', 2.45
  UNION ALL
  SELECT 'Independiente Medellín' AS local, 'Atlético Nacional' AS visitante, 'Local' AS seleccion, 1.63 AS valor
  UNION ALL SELECT 'Independiente Medellín', 'Atlético Nacional', 'Empate', 3.03
  UNION ALL SELECT 'Independiente Medellín', 'Atlético Nacional', 'Visitante', 3.02
  UNION ALL
  SELECT 'Deportivo Cali' AS local, 'Atlético Bucaramanga' AS visitante, 'Local' AS seleccion, 2.46 AS valor
  UNION ALL SELECT 'Deportivo Cali', 'Atlético Bucaramanga', 'Empate', 3.58
  UNION ALL SELECT 'Deportivo Cali', 'Atlético Bucaramanga', 'Visitante', 3.21
  UNION ALL
  SELECT 'Deportes Tolima' AS local, 'Deportivo Pasto' AS visitante, 'Local' AS seleccion, 1.85 AS valor
  UNION ALL SELECT 'Deportes Tolima', 'Deportivo Pasto', 'Empate', 3.43
  UNION ALL SELECT 'Deportes Tolima', 'Deportivo Pasto', 'Visitante', 3.81
  UNION ALL
  SELECT 'Águilas Doradas' AS local, 'Jaguares' AS visitante, 'Local' AS seleccion, 1.84 AS valor
  UNION ALL SELECT 'Águilas Doradas', 'Jaguares', 'Empate', 3.14
  UNION ALL SELECT 'Águilas Doradas', 'Jaguares', 'Visitante', 3.82
  UNION ALL
  SELECT 'Once Caldas' AS local, 'Junior' AS visitante, 'Local' AS seleccion, 2.46 AS valor
  UNION ALL SELECT 'Once Caldas', 'Junior', 'Empate', 3.04
  UNION ALL SELECT 'Once Caldas', 'Junior', 'Visitante', 3.96
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS local, 'Independiente Santa Fe' AS visitante, 'Local' AS seleccion, 2.37 AS valor
  UNION ALL SELECT 'Cúcuta Deportivo', 'Independiente Santa Fe', 'Empate', 3.54
  UNION ALL SELECT 'Cúcuta Deportivo', 'Independiente Santa Fe', 'Visitante', 2.98
) c ON c.local = el.nombre AND c.visitante = ev.nombre
WHERE pa.estado_codigo = 'PROG';
