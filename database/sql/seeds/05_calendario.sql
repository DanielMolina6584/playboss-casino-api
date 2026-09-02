-- =============================================================================
-- SEED 05 — Calendario: 19 jornadas de la temporada 2026-I.
-- Jornada 1: RESULTADOS REALES (investigados). Jornada 2: partidos
-- generados algorítmicamente (round-robin/método del círculo) a partir de
-- la jornada 1 real, estado "Programado" — NO son fixtures oficiales
-- confirmados. Jornadas 3-19: solo el rango de fechas (sin partidos aún).
-- =============================================================================

INSERT IGNORE INTO jornada (temporada_id, numero, fecha_inicio, fecha_fin)
SELECT t.id_temporada, j.numero, j.fecha_inicio, j.fecha_fin FROM temporada t
JOIN (
  SELECT 1 AS numero, '2026-01-16' AS fecha_inicio, '2026-01-19' AS fecha_fin
  UNION ALL
  SELECT 2 AS numero, '2026-01-23' AS fecha_inicio, '2026-01-26' AS fecha_fin
  UNION ALL
  SELECT 3 AS numero, '2026-01-30' AS fecha_inicio, '2026-02-02' AS fecha_fin
  UNION ALL
  SELECT 4 AS numero, '2026-02-06' AS fecha_inicio, '2026-02-09' AS fecha_fin
  UNION ALL
  SELECT 5 AS numero, '2026-02-13' AS fecha_inicio, '2026-02-16' AS fecha_fin
  UNION ALL
  SELECT 6 AS numero, '2026-02-20' AS fecha_inicio, '2026-02-23' AS fecha_fin
  UNION ALL
  SELECT 7 AS numero, '2026-02-27' AS fecha_inicio, '2026-03-02' AS fecha_fin
  UNION ALL
  SELECT 8 AS numero, '2026-03-06' AS fecha_inicio, '2026-03-09' AS fecha_fin
  UNION ALL
  SELECT 9 AS numero, '2026-03-13' AS fecha_inicio, '2026-03-16' AS fecha_fin
  UNION ALL
  SELECT 10 AS numero, '2026-03-20' AS fecha_inicio, '2026-03-23' AS fecha_fin
  UNION ALL
  SELECT 11 AS numero, '2026-03-27' AS fecha_inicio, '2026-03-30' AS fecha_fin
  UNION ALL
  SELECT 12 AS numero, '2026-04-03' AS fecha_inicio, '2026-04-06' AS fecha_fin
  UNION ALL
  SELECT 13 AS numero, '2026-04-10' AS fecha_inicio, '2026-04-13' AS fecha_fin
  UNION ALL
  SELECT 14 AS numero, '2026-04-17' AS fecha_inicio, '2026-04-20' AS fecha_fin
  UNION ALL
  SELECT 15 AS numero, '2026-04-24' AS fecha_inicio, '2026-04-27' AS fecha_fin
  UNION ALL
  SELECT 16 AS numero, '2026-05-01' AS fecha_inicio, '2026-05-04' AS fecha_fin
  UNION ALL
  SELECT 17 AS numero, '2026-05-08' AS fecha_inicio, '2026-05-11' AS fecha_fin
  UNION ALL
  SELECT 18 AS numero, '2026-05-15' AS fecha_inicio, '2026-05-18' AS fecha_fin
  UNION ALL
  SELECT 19 AS numero, '2026-05-22' AS fecha_inicio, '2026-05-25' AS fecha_fin
) j ON t.nombre = '2026-I';

-- Partidos de jornada 1 (reales, finalizados) y jornada 2 (generados, programados).
INSERT IGNORE INTO partido
    (jornada_id, equipo_local_id, equipo_visitante_id, estadio_id, fecha_hora, estado_codigo, goles_local, goles_visitante)
SELECT
    (SELECT jo.id_jornada FROM jornada jo JOIN temporada te ON te.id_temporada = jo.temporada_id
        WHERE te.nombre = '2026-I' AND jo.numero = p.numero_jornada),
    el.id_equipo, ev.id_equipo, el.estadio_id,
    p.fecha_hora, p.estado_codigo, p.goles_local, p.goles_visitante
FROM (
  SELECT 1 AS numero_jornada, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, '2026-01-16 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 0 AS goles_local, 2 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, '2026-01-16 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 1 AS goles_local, 0 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, '2026-01-17 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 3 AS goles_local, 0 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, '2026-01-17 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 4 AS goles_local, 0 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, '2026-01-17 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 2 AS goles_local, 1 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, '2026-01-18 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 1 AS goles_local, 0 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, '2026-01-18 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 1 AS goles_local, 0 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Junior' AS local, 'Deportes Tolima' AS visitante, '2026-01-18 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 0 AS goles_local, 2 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, '2026-01-18 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 1 AS goles_local, 1 AS goles_visitante
  UNION ALL
  SELECT 1 AS numero_jornada, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, '2026-01-19 20:00:00' AS fecha_hora, 'FIN' AS estado_codigo, 1 AS goles_local, 2 AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Alianza Valledupar' AS local, 'Deportivo Pereira' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Internacional de Bogotá' AS local, 'Llaneros' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Boyacá Chicó' AS local, 'Fortaleza' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Millonarios' AS local, 'América de Cali' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Independiente Medellín' AS local, 'Atlético Nacional' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Deportivo Cali' AS local, 'Atlético Bucaramanga' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Deportes Tolima' AS local, 'Deportivo Pasto' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Águilas Doradas' AS local, 'Jaguares' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Once Caldas' AS local, 'Junior' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
  UNION ALL
  SELECT 2 AS numero_jornada, 'Cúcuta Deportivo' AS local, 'Independiente Santa Fe' AS visitante, '2026-01-24 20:00:00' AS fecha_hora, 'PROG' AS estado_codigo, NULL AS goles_local, NULL AS goles_visitante
) p
JOIN equipo el ON el.nombre = p.local
JOIN equipo ev ON ev.nombre = p.visitante;
