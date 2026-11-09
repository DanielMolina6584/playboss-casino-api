<?php

namespace App\Service\Demo;

use App\Models\Market\Mercado;
use App\Models\Market\ReglaApuesta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea la Liga BetPlay 2026-II en MySQL (catálogo y calendario todos contra todos)
 * y los mercados con cuotas y reglas de apuesta en MongoDB.
 */
class GeneradorCatalogo
{
    public const TEMPORADA = '2026-II';
    private const INICIO_TEMPORADA = '2026-07-17';
    private const ZONA = 'America/Bogota';
    /** Día (desde el viernes) y hora de los 10 partidos de cada jornada. */
    private const HORARIOS = [[0, '20:00'], [1, '14:00'], [1, '16:10'], [1, '18:20'], [2, '14:00'], [2, '16:10'], [2, '18:20'], [2, '20:30'], [3, '18:00'], [3, '20:10']];
    private const PLANTILLA = ['POR' => 2, 'DEF' => 6, 'MED' => 6, 'DEL' => 4];
    private const PAISES = ['CO' => 'Colombia', 'AR' => 'Argentina', 'UY' => 'Uruguay', 'VE' => 'Venezuela', 'EC' => 'Ecuador', 'PA' => 'Panamá', 'BR' => 'Brasil', 'PY' => 'Paraguay'];

    /**
     * Inserta todo el catálogo deportivo.
     * Los partidos ya jugados se guardan con fecha futura temporal para poder apostar en ellos
     * antes de liquidarlos; restaurarFechas() les pone su fecha real.
     * @param array<string,string> $escudos slug => id de Drive
     * @return array{liga:int,temporada:int,equipos:array<string,array>,jugadores:array<int,list<array{id:int,posicion:string}>>,partidos:list<array>}
     */
    public function crearCatalogo(?string $logoLigaId, array $escudos): array
    {
        $db = DB::connection('mysql');

        return $db->transaction(function () use ($db, $logoLigaId, $escudos): array {
            foreach (self::PAISES as $codigo => $nombre) {
                $db->table('pais')->updateOrInsert(['codigo_iso' => $codigo], ['nombre' => $nombre]);
            }
            foreach (['POR' => 'Portero', 'DEF' => 'Defensa', 'MED' => 'Mediocampista', 'DEL' => 'Delantero'] as $codigo => $nombre) {
                $db->table('posicion')->updateOrInsert(['codigo' => $codigo], ['nombre' => $nombre]);
            }

            $ciudades = [];
            foreach (LigaColombiana::ciudades() as $clave => $nombre) {
                $ciudades[$clave] = $db->table('ciudad')->insertGetId(['nombre' => $nombre, 'pais_codigo' => 'CO']);
            }
            $estadios = [];
            foreach (LigaColombiana::estadios() as $clave => $estadio) {
                $estadios[$clave] = $db->table('estadio')->insertGetId([
                    'nombre' => $estadio['nombre'], 'ciudad_id' => $ciudades[$estadio['ciudad']], 'capacidad' => $estadio['capacidad'],
                ]);
            }

            $liga = $db->table('liga')->insertGetId([
                'servicio_codigo' => 'FUTBOL', 'pais_codigo' => 'CO', 'nombre' => 'Liga BetPlay DIMAYOR', 'logo_drive_id' => $logoLigaId, 'activa' => true,
            ]);
            $temporada = $db->table('temporada')->insertGetId([
                'liga_id' => $liga, 'nombre' => self::TEMPORADA, 'fecha_inicio' => self::INICIO_TEMPORADA, 'fecha_fin' => '2026-12-20',
            ]);

            $equipos = [];
            foreach (LigaColombiana::equipos() as $slug => $equipo) {
                $id = $db->table('equipo')->insertGetId([
                    'nombre' => $equipo['nombre'], 'escudo_drive_id' => $escudos[$slug] ?? null, 'ciudad_id' => $ciudades[$equipo['ciudad']],
                    'estadio_id' => $estadios[$equipo['estadio']], 'fecha_fundacion' => $equipo['fundacion'],
                ]);
                $db->table('equipo_liga_temporada')->insert([
                    'equipo_id' => $id, 'temporada_id' => $temporada, 'fecha_inscripcion' => '2026-06-30', 'puntos' => 0,
                ]);
                $equipos[$slug] = $equipo + ['id' => $id, 'estadio_id' => $estadios[$equipo['estadio']]];
            }

            return [
                'liga' => $liga,
                'temporada' => $temporada,
                'equipos' => $equipos,
                'jugadores' => $this->crearJugadores($equipos),
                'partidos' => $this->crearCalendario($temporada, $equipos),
            ];
        });
    }

    /**
     * Crea la regla de cada mercado y los mercados con cuotas de todos los partidos.
     * @param list<array> $partidos
     * @param array<string,array> $equipos
     * @return array{mercados:array<int,array<string,array>>,modelos:array<int,ModeloPartido>}
     */
    public function crearMercados(array $partidos, array $equipos): array
    {
        $codigos = [];
        $documentos = [];
        $modelos = [];
        foreach ($partidos as $partido) {
            $local = $equipos[$partido['local']];
            $visitante = $equipos[$partido['visitante']];
            $modelo = ModeloPartido::entre($local, $visitante);
            $modelos[$partido['id']] = $modelo;

            foreach (CatalogoMercados::definiciones($local['nombre'], $visitante['nombre']) as $definicion) {
                $codigos[$definicion['code']] = $definicion['categoria'];
                $cuotas = $modelo->cuotas($definicion['code'], array_keys($definicion['opciones']), CatalogoMercados::esEspecial($definicion['categoria']));
                $selecciones = [];
                foreach ($definicion['opciones'] as $codigo => $etiqueta) {
                    $selecciones[] = ['code' => (string) $codigo, 'label' => $etiqueta, 'odd' => $cuotas[$codigo]];
                }
                $documentos[] = [
                    'match_id' => $partido['id'], 'code' => $definicion['code'], 'name' => $definicion['name'],
                    'status' => 'ACTIVE', 'selections' => $selecciones, 'version' => 1, 'updated_at' => now(),
                ];
            }
        }

        foreach ($codigos as $codigo => $categoria) {
            ReglaApuesta::query()->create(CatalogoMercados::regla($codigo, $categoria) + ['effective_from' => CarbonImmutable::parse('2026-06-01')]);
        }
        foreach (array_chunk($documentos, 1000) as $lote) {
            Mercado::query()->insert($lote);
        }

        $mercados = [];
        foreach (Mercado::query()->get(['_id', 'match_id', 'code', 'selections']) as $mercado) {
            $mercados[(int) $mercado->match_id][$mercado->code] = [
                'id' => (string) $mercado->getKey(),
                'categoria' => $codigos[$mercado->code],
                'cuotas' => array_column((array) $mercado->selections, 'odd', 'code'),
            ];
        }

        return ['mercados' => $mercados, 'modelos' => $modelos];
    }

    /**
     * Pone su fecha real a los partidos que se crearon con fecha temporal.
     * @param list<array> $partidos
     */
    public function restaurarFechas(array $partidos): void
    {
        foreach ($partidos as $partido) {
            if ($partido['jugado']) {
                DB::connection('mysql')->table('partido')->where('id_partido', $partido['id'])->update(['fecha_hora' => $partido['fecha']]);
            }
        }
    }

    /**
     * Guarda la alineación (11 titulares y 3 cambios por equipo) de un partido jugado.
     * @param list<array{id:int,posicion:string}> $plantillaLocal
     * @param list<array{id:int,posicion:string}> $plantillaVisitante
     */
    public function crearAlineacion(array $partido, int $localId, int $visitanteId, array $plantillaLocal, array $plantillaVisitante): void
    {
        $filas = [];
        foreach ([[$localId, $plantillaLocal], [$visitanteId, $plantillaVisitante]] as [$equipoId, $plantilla]) {
            $porPosicion = [];
            foreach ($plantilla as $jugador) {
                $porPosicion[$jugador['posicion']][] = $jugador['id'];
            }
            foreach ($porPosicion as &$ids) {
                shuffle($ids);
            }
            unset($ids);

            $titulares = array_merge(
                array_slice($porPosicion['POR'], 0, 1),
                array_slice($porPosicion['DEF'], 0, 4),
                array_slice($porPosicion['MED'], 0, 4),
                array_slice($porPosicion['DEL'], 0, 2)
            );
            $suplentes = [$porPosicion['DEF'][4], $porPosicion['MED'][4], $porPosicion['DEL'][2]];
            // Tres titulares de campo salen; cada suplente entra en el minuto del que sale.
            $salen = [];
            foreach (array_rand(array_flip(array_slice($titulares, 1)), 3) as $i => $jugadorId) {
                $salen[$jugadorId] = 55 + $i * 12 + mt_rand(0, 6);
            }

            foreach ($titulares as $jugadorId) {
                $filas[] = [
                    'partido_id' => $partido['id'], 'jugador_id' => $jugadorId, 'equipo_id' => $equipoId, 'titular' => true,
                    'minuto_entra' => 0, 'minuto_sale' => $salen[$jugadorId] ?? null,
                ];
            }
            foreach (array_values($salen) as $i => $minuto) {
                $filas[] = [
                    'partido_id' => $partido['id'], 'jugador_id' => $suplentes[$i], 'equipo_id' => $equipoId, 'titular' => false,
                    'minuto_entra' => $minuto, 'minuto_sale' => null,
                ];
            }
        }

        DB::connection('mysql')->table('alineacion')->insert($filas);
    }

    /**
     * Actualiza los puntos de cada equipo en la temporada.
     * @param array<int,int> $puntos equipo_id => puntos
     */
    public function guardarPuntos(int $temporadaId, array $puntos): void
    {
        foreach ($puntos as $equipoId => $total) {
            DB::connection('mysql')->table('equipo_liga_temporada')
                ->where('temporada_id', $temporadaId)->where('equipo_id', $equipoId)->update(['puntos' => $total]);
        }
    }

    /**
     * Crea la plantilla ficticia de cada equipo.
     * @param array<string,array> $equipos
     * @return array<int,list<array{id:int,posicion:string}>>
     */
    private function crearJugadores(array $equipos): array
    {
        $db = DB::connection('mysql');
        $jugadores = [];
        $extranjeros = array_keys(array_diff_key(self::PAISES, ['CO' => true]));
        foreach ($equipos as $equipo) {
            $dorsal = 1;
            foreach (self::PLANTILLA as $posicion => $cantidad) {
                for ($i = 0; $i < $cantidad; $i++) {
                    $id = $db->table('jugador')->insertGetId([
                        'primer_nombre' => $this->elegir(LigaColombiana::NOMBRES),
                        'primer_apellido' => $this->elegir(LigaColombiana::APELLIDOS),
                        'segundo_apellido' => $this->elegir(LigaColombiana::APELLIDOS),
                        'fecha_nacimiento' => sprintf('%d-%02d-%02d', mt_rand(1992, 2007), mt_rand(1, 12), mt_rand(1, 28)),
                        'pais_codigo' => mt_rand(1, 100) <= 12 ? $this->elegir($extranjeros) : 'CO',
                        'posicion_codigo' => $posicion,
                        'equipo_actual_id' => $equipo['id'],
                        'dorsal' => $dorsal++,
                    ]);
                    $jugadores[$equipo['id']][] = ['id' => $id, 'posicion' => $posicion];
                }
            }
        }

        return $jugadores;
    }

    /**
     * Calendario todos contra todos (método del círculo): 19 jornadas de 10 partidos.
     * @param array<string,array> $equipos
     * @return list<array{id:int,jornada:int,local:string,visitante:string,fecha:CarbonImmutable,jugado:bool}>
     */
    private function crearCalendario(int $temporadaId, array $equipos): array
    {
        $db = DB::connection('mysql');
        $rotacion = array_keys($equipos);
        $cantidad = count($rotacion);
        $inicio = CarbonImmutable::parse(self::INICIO_TEMPORADA, self::ZONA);
        $ahora = CarbonImmutable::now();
        $partidos = [];
        $temporal = 0;

        for ($ronda = 0; $ronda < $cantidad - 1; $ronda++) {
            $viernes = $inicio->addWeeks($ronda);
            $jornadaId = $db->table('jornada')->insertGetId([
                'temporada_id' => $temporadaId, 'numero' => $ronda + 1,
                'fecha_inicio' => $viernes->toDateString(), 'fecha_fin' => $viernes->addDays(3)->toDateString(),
            ]);

            for ($i = 0; $i < $cantidad / 2; $i++) {
                [$a, $b] = [$rotacion[$i], $rotacion[$cantidad - 1 - $i]];
                [$local, $visitante] = ($ronda + $i) % 2 === 0 ? [$a, $b] : [$b, $a];
                [$dia, $hora] = self::HORARIOS[$i];
                $fecha = $viernes->addDays($dia)->setTimeFromTimeString($hora)->utc();
                $jugado = $fecha->lessThan($ahora);

                $id = $db->table('partido')->insertGetId([
                    'jornada_id' => $jornadaId, 'equipo_local_id' => $equipos[$local]['id'], 'equipo_visitante_id' => $equipos[$visitante]['id'],
                    'estadio_id' => $equipos[$local]['estadio_id'], 'estado_codigo' => 'PROG',
                    'fecha_hora' => $jugado ? $ahora->addDays(20)->addMinutes(++$temporal) : $fecha,
                ]);
                $partidos[] = ['id' => $id, 'jornada' => $ronda + 1, 'local' => $local, 'visitante' => $visitante, 'fecha' => $fecha, 'jugado' => $jugado];
            }

            // Rota todos menos el primero.
            $rotacion = array_merge([$rotacion[0]], [$rotacion[$cantidad - 1]], array_slice($rotacion, 1, $cantidad - 2));
        }

        return $partidos;
    }

    private function elegir(array $opciones): string
    {
        return $opciones[mt_rand(0, count($opciones) - 1)];
    }

    /**
     * Slug del equipo usado para el archivo del escudo (igual al del front).
     */
    public static function slug(string $nombre): string
    {
        return Str::slug($nombre);
    }
}
