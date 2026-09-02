-- =============================================================================
-- SEED 07 — jugador_equipo_historico (vínculo actual jugador/equipo) y
-- alineacion (titulares, formación 4-4-2) de los partidos finalizados de
-- jornada 1. Datos sintéticos, ver comentario de 04_jugadores.sql.
-- =============================================================================

INSERT IGNORE INTO jugador_equipo_historico (jugador_id, equipo_id, fecha_inicio, tipo_vinculo)
SELECT j.id_jugador, j.equipo_actual_id, '2025-01-15', 'Contrato'
FROM jugador j
WHERE j.equipo_actual_id IS NOT NULL;

INSERT IGNORE INTO alineacion (partido_id, jugador_id, equipo_id, titular)
SELECT pa.id_partido, jg.id_jugador, eq.id_equipo, 1
FROM (
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 64 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 24 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 41 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 43 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 60 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 21 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 31 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 33 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 49 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pereira' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 54 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 90 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 79 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 10 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 25 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 36 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 80 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 14 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 35 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 40 AS dorsal
  UNION ALL
  SELECT 'Llaneros' AS equipo, 'Deportivo Pereira' AS local, 'Llaneros' AS visitante, 19 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 59 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 50 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 55 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 87 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 78 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 90 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 94 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 13 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 62 AS dorsal
  UNION ALL
  SELECT 'Fortaleza' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 20 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 64 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 81 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 96 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 39 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 31 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 19 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 76 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 67 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 33 AS dorsal
  UNION ALL
  SELECT 'Alianza Valledupar' AS equipo, 'Fortaleza' AS local, 'Alianza Valledupar' AS visitante, 2 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 81 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 75 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 64 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 78 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 50 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 73 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 83 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 3 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 18 AS dorsal
  UNION ALL
  SELECT 'América de Cali' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 8 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 25 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 38 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 77 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 3 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 91 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 63 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 59 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 23 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 95 AS dorsal
  UNION ALL
  SELECT 'Internacional de Bogotá' AS equipo, 'América de Cali' AS local, 'Internacional de Bogotá' AS visitante, 14 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 95 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 55 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 62 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 26 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 75 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 72 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 11 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 82 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 59 AS dorsal
  UNION ALL
  SELECT 'Atlético Nacional' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 69 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 75 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 50 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 5 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 55 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 92 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 23 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 51 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 21 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 88 AS dorsal
  UNION ALL
  SELECT 'Boyacá Chicó' AS equipo, 'Atlético Nacional' AS local, 'Boyacá Chicó' AS visitante, 28 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 96 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 12 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 15 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 28 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 49 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 97 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 31 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 65 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 24 AS dorsal
  UNION ALL
  SELECT 'Atlético Bucaramanga' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 33 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 14 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 43 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 55 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 21 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 88 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 71 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 89 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 30 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 50 AS dorsal
  UNION ALL
  SELECT 'Millonarios' AS equipo, 'Atlético Bucaramanga' AS local, 'Millonarios' AS visitante, 56 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 27 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 31 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 85 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 21 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 32 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 81 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 65 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 79 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 40 AS dorsal
  UNION ALL
  SELECT 'Deportivo Pasto' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 66 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 87 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 80 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 27 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 15 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 94 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 81 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 85 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 9 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 36 AS dorsal
  UNION ALL
  SELECT 'Independiente Medellín' AS equipo, 'Deportivo Pasto' AS local, 'Independiente Medellín' AS visitante, 26 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 4 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 90 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 41 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 91 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 59 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 34 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 96 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 21 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 52 AS dorsal
  UNION ALL
  SELECT 'Jaguares' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 12 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 14 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 18 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 46 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 17 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 19 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 93 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 27 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 69 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 10 AS dorsal
  UNION ALL
  SELECT 'Deportivo Cali' AS equipo, 'Jaguares' AS local, 'Deportivo Cali' AS visitante, 56 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 28 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 9 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 54 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 58 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 70 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 3 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 36 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 7 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 26 AS dorsal
  UNION ALL
  SELECT 'Junior' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 10 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 47 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 72 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 79 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 76 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 39 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 8 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 54 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 33 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 58 AS dorsal
  UNION ALL
  SELECT 'Deportes Tolima' AS equipo, 'Junior' AS local, 'Deportes Tolima' AS visitante, 22 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 26 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 60 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 52 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 43 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 68 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 77 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 14 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 81 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 39 AS dorsal
  UNION ALL
  SELECT 'Independiente Santa Fe' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 73 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 25 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 21 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 50 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 16 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 10 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 39 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 66 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 27 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 3 AS dorsal
  UNION ALL
  SELECT 'Águilas Doradas' AS equipo, 'Independiente Santa Fe' AS local, 'Águilas Doradas' AS visitante, 42 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 30 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 3 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 58 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 97 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 90 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 43 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 50 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 78 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 71 AS dorsal
  UNION ALL
  SELECT 'Cúcuta Deportivo' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 63 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 1 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 32 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 12 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 10 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 41 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 71 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 11 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 39 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 15 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 69 AS dorsal
  UNION ALL
  SELECT 'Once Caldas' AS equipo, 'Cúcuta Deportivo' AS local, 'Once Caldas' AS visitante, 64 AS dorsal
) x
JOIN equipo eq ON eq.nombre = x.equipo
JOIN jugador jg ON jg.equipo_actual_id = eq.id_equipo AND jg.dorsal = x.dorsal
JOIN equipo el ON el.nombre = x.local
JOIN equipo ev ON ev.nombre = x.visitante
JOIN partido pa ON pa.equipo_local_id = el.id_equipo AND pa.equipo_visitante_id = ev.id_equipo;
