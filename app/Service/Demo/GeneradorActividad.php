<?php

namespace App\Service\Demo;

use App\Exceptions\ReglaNegocioException;
use App\Models\Account\Cuenta;
use App\Models\Payment\Transaccion;
use App\Models\Usuario\CodigoRecuperacion;
use App\Models\Usuario\Usuario;
use App\Service\Account\AccountService;
use App\Service\Bet\BetService;
use App\Service\Bet\SettlementService;
use App\Service\Payment\PagoService;
use App\Service\Storage\AuditLogService;
use App\Service\SvcUsuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Genera la actividad de la demo con los servicios reales de la API: registro de usuarios,
 * depósitos, apuestas simples y combinadas, cancelaciones, liquidación y retiros.
 */
class GeneradorActividad
{
    public const CORREO_CLIENTE = 'cliente@playboss.test';
    public const CORREO_ADMIN = 'admin@playboss.test';
    public const CONTRASENA = 'Demo1234!';

    /** Popularidad de cada mercado entre los apostadores. */
    private const PESO_MERCADOS = [
        '1X2' => 30, 'DC' => 8, 'DNB' => 4, 'BTTS' => 10, 'OU_0_5' => 1, 'OU_1_5' => 4, 'OU_2_5' => 12, 'OU_3_5' => 4, 'OU_4_5' => 1,
        'HOME_OU_0_5' => 2, 'HOME_OU_1_5' => 2, 'AWAY_OU_0_5' => 2, 'AWAY_OU_1_5' => 2, 'ODD_EVEN' => 2, 'HCP_M1' => 3, 'HCP_P1' => 2,
        'HT_1X2' => 3, 'HTFT' => 2, 'RES_BTTS' => 3, 'CS' => 3, 'CORNERS_1X2' => 2, 'CORNERS_OU_8_5' => 2, 'CORNERS_OU_9_5' => 4,
        'CORNERS_OU_10_5' => 2, 'CARDS_OU_3_5' => 2, 'CARDS_OU_4_5' => 3, 'CARDS_OU_5_5' => 1, 'SHOTS_OU_21_5' => 2, 'SHOTS_OU_24_5' => 1,
        'SOT_OU_7_5' => 2, 'SOT_OU_9_5' => 1,
    ];
    private const PAGO_MAXIMO = ['principal' => 50000000, 'especial' => 20000000, 'estadisticas' => 20000000];
    private const MONTOS_SIMPLE = [2000, 5000, 10000, 10000, 20000, 20000, 30000, 50000, 100000];
    private const MONTOS_COMBINADA = [2000, 3000, 5000, 5000, 10000, 20000];

    public function __construct(
        private readonly SvcUsuario $usuarios,
        private readonly AuditLogService $auditoria,
        private readonly AccountService $cuentas,
        private readonly PagoService $pagos,
        private readonly BetService $apuestas,
        private readonly SettlementService $liquidacion,
        private readonly GeneradorCatalogo $catalogo,
    ) {
    }

    /**
     * Registra el admin, el cliente principal y clientes ficticios (dos bloqueados y un menor de edad).
     * @return array{admin:int,clientes:list<array{id:int,correo:string,estado:string,menor:bool}>}
     */
    public function crearUsuarios(int $cantidad): array
    {
        DB::connection('sqlsrv')->table('estado_usuario')->updateOrInsert(['codigo' => 'BLQ'], ['nombre' => 'Bloqueado', 'descripcion' => 'Cuenta bloqueada por administración']);
        DB::connection('sqlsrv')->table('estado_usuario')->updateOrInsert(['codigo' => 'SUS'], ['nombre' => 'Suspendido', 'descripcion' => 'Autoexclusión temporal por juego responsable']);

        $admin = $this->registrar(self::CORREO_ADMIN, 'ADMIN', 'ACT', 'Daniel', 'Torres', '1995-01-15');
        $clientes = [['id' => $this->registrar(self::CORREO_CLIENTE, 'CLIENTE', 'ACT', 'Laura', 'Martínez', '1999-05-20'), 'correo' => self::CORREO_CLIENTE, 'estado' => 'ACT', 'menor' => false]];

        for ($i = 1; $i <= $cantidad; $i++) {
            $mujer = mt_rand(0, 2) === 0;
            $nombre = $this->elegir($mujer ? LigaColombiana::NOMBRES_MUJER : LigaColombiana::NOMBRES);
            $apellido = $this->elegir(LigaColombiana::APELLIDOS);
            $correo = Str::slug("{$nombre}.{$apellido}", '.') . $i . '@playboss.test';
            $estado = match ($i) { 7, 23 => 'BLQ', 31 => 'SUS', default => 'ACT' };
            $menor = $i === 15;
            $nacimiento = $menor
                ? CarbonImmutable::now()->subYears(16)->subDays(mt_rand(1, 300))->toDateString()
                : sprintf('%d-%02d-%02d', mt_rand(1965, 2006), mt_rand(1, 12), mt_rand(1, 28));
            $clientes[] = ['id' => $this->registrar($correo, 'CLIENTE', $estado, $nombre, $apellido, $nacimiento), 'correo' => $correo, 'estado' => $estado, 'menor' => $menor];
        }

        $this->registrarSesiones($admin, $clientes);

        return ['admin' => $admin, 'clientes' => $clientes];
    }

    /**
     * Depósitos aprobados (flujo del webhook), algunos rechazados y algunos pendientes.
     * @param list<array{id:int,estado:string}> $clientes
     * @return array{aprobados:int,rechazados:int,pendientes:int}
     */
    public function crearDepositos(array $clientes): array
    {
        $resumen = ['aprobados' => 0, 'rechazados' => 0, 'pendientes' => 0];
        foreach ($clientes as $indice => $cliente) {
            if ($cliente['estado'] !== 'ACT') {
                continue;
            }
            $this->cuentas->abrir($cliente['id']);
            $montos = $indice === 0 ? [500000, 300000] : array_map(fn () => $this->elegir([50000, 100000, 100000, 200000, 300000, 500000]), range(1, mt_rand(1, 3)));
            foreach ($montos as $n => $monto) {
                $this->deposito($cliente['id'], $monto, "aprobado-{$n}", 'APPROVED');
                $resumen['aprobados']++;
            }
            if (mt_rand(1, 100) <= 30) {
                $this->deposito($cliente['id'], $this->elegir([100000, 200000, 1000000]), 'rechazado', $this->elegir(['DECLINED', 'DECLINED', 'FAILED', 'CANCELLED']));
                $resumen['rechazados']++;
            }
            if (mt_rand(1, 100) <= 15) {
                $this->deposito($cliente['id'], $this->elegir([50000, 150000]), 'pendiente', null);
                $resumen['pendientes']++;
            }
        }

        return $resumen;
    }

    /**
     * Apuestas simples y combinadas con BetService; algunas se cancelan.
     * @param list<array{id:int,estado:string,menor:bool}> $clientes
     * @param list<array> $partidos
     * @param array<int,array<string,array>> $mercados
     * @return array{creadas:int,simples:int,combinadas:int,canceladas:int,rechazadas:array<string,int>}
     */
    public function crearApuestas(array $clientes, array $partidos, array $mercados): array
    {
        $jugados = array_values(array_filter($partidos, fn (array $p): bool => $p['jugado']));
        $proximos = array_values(array_filter($partidos, fn (array $p): bool => !$p['jugado']));
        $resumen = ['creadas' => 0, 'simples' => 0, 'combinadas' => 0, 'canceladas' => 0, 'rechazadas' => []];

        foreach ($clientes as $indice => $cliente) {
            if ($cliente['estado'] !== 'ACT') {
                continue;
            }
            $cantidad = $cliente['menor'] ? 2 : ($indice === 0 ? 35 : mt_rand(8, 28));
            for ($n = 1; $n <= $cantidad; $n++) {
                $combinada = mt_rand(1, 100) <= 45;
                $piernas = $combinada ? $this->elegir([2, 2, 2, 3, 3, 3, 4, 4, 5, 6]) : 1;
                $selecciones = $this->armarSelecciones($piernas, $jugados, $proximos, $mercados, $combinada);
                $monto = $this->ajustarMonto($cliente['id'], $selecciones, $combinada);
                if ($monto === null) {
                    break; // sin saldo suficiente
                }

                try {
                    $apuesta = $this->apuestas->crear($cliente['id'], $monto . '.00', array_column($selecciones, 'seleccion'), "demo-{$cliente['id']}-{$n}");
                    $resumen['creadas']++;
                    $resumen[$combinada ? 'combinadas' : 'simples']++;
                    if (mt_rand(1, 100) <= 4) {
                        $this->apuestas->cancelar($cliente['id'], (int) $apuesta->id_apuesta);
                        $resumen['canceladas']++;
                    }
                } catch (ValidationException | ReglaNegocioException $e) {
                    $motivo = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                    $resumen['rechazadas'][$motivo] = ($resumen['rechazadas'][$motivo] ?? 0) + 1;
                }
            }
        }

        return $resumen;
    }

    /**
     * Simula y liquida los partidos ya jugados, guarda alineaciones y calcula la tabla.
     * @param list<array> $partidos
     * @param array<int,ModeloPartido> $modelos
     * @param array<string,array> $equipos
     * @param array<int,list<array>> $jugadores
     * @return list<array<string,mixed>> tabla de posiciones ordenada
     */
    public function liquidarJugados(array $partidos, array $modelos, array $equipos, array $jugadores, int $temporadaId, int $adminId): array
    {
        $tabla = [];
        foreach ($equipos as $slug => $equipo) {
            $tabla[$slug] = ['equipo' => $equipo['nombre'], 'pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'gf' => 0, 'gc' => 0, 'pts' => 0];
        }

        foreach ($partidos as $partido) {
            if (!$partido['jugado']) {
                continue;
            }
            $resultado = $modelos[$partido['id']]->simular();
            $this->liquidacion->liquidarPartido($partido['id'], $resultado['goles_local'], $resultado['goles_visitante'], [], $adminId, $resultado['estadisticas']);

            $local = $equipos[$partido['local']];
            $visitante = $equipos[$partido['visitante']];
            $this->catalogo->crearAlineacion($partido, $local['id'], $visitante['id'], $jugadores[$local['id']], $jugadores[$visitante['id']]);
            $this->sumarResultado($tabla[$partido['local']], $resultado['goles_local'], $resultado['goles_visitante']);
            $this->sumarResultado($tabla[$partido['visitante']], $resultado['goles_visitante'], $resultado['goles_local']);
        }

        $puntos = [];
        foreach ($tabla as $slug => $fila) {
            $puntos[$equipos[$slug]['id']] = $fila['pts'];
        }
        $this->catalogo->guardarPuntos($temporadaId, $puntos);

        $tabla = array_values($tabla);
        usort($tabla, fn (array $a, array $b): int => [$b['pts'], $b['gf'] - $b['gc'], $b['gf']] <=> [$a['pts'], $a['gf'] - $a['gc'], $a['gf']]);
        foreach ($tabla as $i => &$fila) {
            $fila = ['posicion' => $i + 1] + $fila + ['dg' => $fila['gf'] - $fila['gc']];
        }

        return $tabla;
    }

    /**
     * El admin anula algunas apuestas pendientes (p. ej. por error en la cuota).
     */
    public function anularPendientes(int $adminId, int $cantidad): int
    {
        $ids = DB::connection('pgsql')->table('apuesta')->where('estado_codigo', 'PEND')->orderBy('id_apuesta')->limit($cantidad)->pluck('id_apuesta');
        foreach ($ids as $id) {
            $this->liquidacion->anularApuesta((int) $id, $adminId);
        }

        return $ids->count();
    }

    /**
     * Retiros aprobados de clientes con saldo.
     * @param list<array{id:int,estado:string}> $clientes
     */
    public function crearRetiros(array $clientes): int
    {
        $retiros = 0;
        foreach ($clientes as $cliente) {
            $cuenta = Cuenta::query()->find($cliente['id']);
            if (!$cuenta || $cliente['estado'] !== 'ACT' || (float) $cuenta->saldo_disponible < 100000 || mt_rand(1, 100) > 30) {
                continue;
            }
            $monto = (int) min(300000, floor((float) $cuenta->saldo_disponible * 0.5 / 10000) * 10000);
            $clave = 'demo-retiro-' . $cliente['id'];
            Transaccion::query()->create([
                'usuario_id' => $cliente['id'], 'metodo_pago_codigo' => 'WOMPI', 'tipo' => 'RETIRO', 'monto' => $monto . '.00',
                'moneda' => 'COP', 'estado' => 'APROBADA', 'clave_idempotencia' => $clave, 'referencia_externa' => 'RET-' . $cliente['id'],
                'fecha_hora' => now(),
            ]);
            $this->cuentas->debitar($cliente['id'], $monto . '.00', $clave);
            $retiros++;
        }

        return $retiros;
    }

    /**
     * Ajusta las fechas de apuestas, movimientos y pagos para que coincidan con el calendario real.
     * @param list<array> $partidos
     */
    public function ajustarFechas(array $partidos): void
    {
        $pg = DB::connection('pgsql');
        $ahora = CarbonImmutable::now();
        $fechas = array_column($partidos, 'fecha', 'id');

        $detalles = $pg->table('apuesta_detalle')->get(['apuesta_id', 'partido_id_mysql'])->groupBy('apuesta_id');
        $administrativas = $pg->table('movimiento')->where('referencia_idempotencia', 'like', 'liquidacion:%')
            ->where('tipo', 'APUESTA_ANULADA')->pluck('apuesta_id')->flip();

        foreach ($pg->table('apuesta')->get(['id_apuesta', 'estado_codigo', 'liquidada_en']) as $apuesta) {
            $fechasPartidos = collect($detalles[$apuesta->id_apuesta] ?? [])->map(fn ($d) => $fechas[$d->partido_id_mysql]);
            $primera = $fechasPartidos->min();
            $jugadas = $fechasPartidos->filter(fn (CarbonImmutable $f): bool => $f->lessThan($ahora));
            $creada = $primera->lessThan($ahora)
                ? $primera->subMinutes(mt_rand(120, 5760))
                : $ahora->subMinutes(mt_rand(30, 4320));

            $liquidada = match (true) {
                $apuesta->liquidada_en === null => null,
                $apuesta->estado_codigo === 'ANU' && !isset($administrativas[$apuesta->id_apuesta]) => $creada->addMinutes(mt_rand(20, 180)),
                $jugadas->isNotEmpty() && $apuesta->estado_codigo !== 'ANU' => $jugadas->max()->addMinutes(mt_rand(115, 140)),
                default => $ahora->subMinutes(mt_rand(10, 60)),
            };

            $pg->table('apuesta')->where('id_apuesta', $apuesta->id_apuesta)->update(['creada_en' => $creada, 'liquidada_en' => $liquidada]);
            $pg->table('movimiento')->where('apuesta_id', $apuesta->id_apuesta)->where('tipo', 'APUESTA_RESERVADA')->update(['creado_en' => $creada]);
            if ($liquidada !== null) {
                $pg->table('movimiento')->where('apuesta_id', $apuesta->id_apuesta)->where('tipo', '<>', 'APUESTA_RESERVADA')->update(['creado_en' => $liquidada]);
            }
        }

        foreach ($pg->table('transaccion')->get(['id_transaccion', 'tipo']) as $transaccion) {
            $fecha = $transaccion->tipo === 'DEPOSITO'
                ? CarbonImmutable::parse('2026-07-01 08:00')->addMinutes(mt_rand(0, 11 * 24 * 60))
                : $ahora->subMinutes(mt_rand(60, 5 * 24 * 60));
            $pg->table('transaccion')->where('id_transaccion', $transaccion->id_transaccion)->update(['fecha_hora' => $fecha]);
            $pg->table('transaccion')->where('id_transaccion', $transaccion->id_transaccion)->whereNotNull('confirmada_en')->update(['confirmada_en' => $fecha->addMinutes(3)]);
            $pg->table('evento_pago')->where('transaccion_id', $transaccion->id_transaccion)->update(['recibido_en' => $fecha->addMinutes(3)]);
            $pg->table('movimiento')->where('transaccion_id', $transaccion->id_transaccion)->update(['creado_en' => $fecha->addMinutes(3)]);
        }
        $pg->table('movimiento')->where('tipo', 'RETIRO')->update(['creado_en' => $ahora->subMinutes(mt_rand(60, 600))]);

        $this->catalogo->restaurarFechas($partidos);
    }

    /**
     * Elige partidos distintos y, en cada uno, un mercado y una opción (las favoritas son más probables).
     * @return list<array{seleccion:array,categoria:string,cuota:float}>
     */
    private function armarSelecciones(int $piernas, array $jugados, array $proximos, array $mercados, bool $combinada): array
    {
        $usados = [];
        $selecciones = [];
        while (count($selecciones) < $piernas) {
            $pool = mt_rand(1, 100) <= 88 ? $jugados : $proximos;
            $partido = $pool[mt_rand(0, count($pool) - 1)];
            if (isset($usados[$partido['id']])) {
                continue;
            }
            $usados[$partido['id']] = true;

            $codigo = $this->elegirPonderado(self::PESO_MERCADOS, $combinada ? ['CS', 'HTFT'] : []);
            $mercado = $mercados[$partido['id']][$codigo];
            $pesos = [];
            foreach ($mercado['cuotas'] as $opcion => $cuota) {
                $pesos[(string) $opcion] = (int) round(1000 * (1 / $cuota) ** 1.3);
            }
            $opcion = $this->elegirPonderado($pesos);
            $selecciones[] = [
                'seleccion' => ['match_id' => $partido['id'], 'market_id' => $mercado['id'], 'selection_code' => $opcion],
                'categoria' => $mercado['categoria'],
                'cuota' => (float) $mercado['cuotas'][$opcion],
            ];
        }

        return $selecciones;
    }

    /**
     * Monto que respeta el saldo y el pago máximo; null si el saldo no alcanza el mínimo.
     */
    private function ajustarMonto(int $usuarioId, array $selecciones, bool $combinada): ?int
    {
        $disponible = (int) floor((float) (Cuenta::query()->find($usuarioId)?->saldo_disponible ?? 0));
        $monto = min($this->elegir($combinada ? self::MONTOS_COMBINADA : self::MONTOS_SIMPLE), (int) (floor($disponible / 1000) * 1000));
        $cuota = array_product(array_column($selecciones, 'cuota'));
        $pagoMaximo = min(array_map(fn (array $s): int => self::PAGO_MAXIMO[$s['categoria']], $selecciones));
        if ($monto * $cuota > $pagoMaximo) {
            $monto = (int) (floor($pagoMaximo / $cuota / 1000) * 1000);
        }

        return $monto >= 1000 ? $monto : null;
    }

    private function deposito(int $usuarioId, int $monto, string $sufijo, ?string $estadoFinal): void
    {
        $referencia = "DEMO-{$usuarioId}-{$sufijo}";
        Transaccion::query()->create([
            'usuario_id' => $usuarioId, 'metodo_pago_codigo' => 'WOMPI', 'tipo' => 'DEPOSITO', 'monto' => $monto . '.00',
            'moneda' => 'COP', 'estado' => 'PENDIENTE', 'clave_idempotencia' => "demo-deposito-{$usuarioId}-{$sufijo}",
            'referencia_externa' => $referencia, 'url_redireccion' => 'https://checkout.wompi.co/l/demo', 'fecha_hora' => now(),
        ]);
        if ($estadoFinal !== null) {
            $this->pagos->aplicarEventoVerificado([
                'event_id' => "evt-{$referencia}", 'reference' => $referencia, 'status' => $estadoFinal,
                'amount' => $monto . '.00', 'currency' => 'COP',
            ]);
        }
    }

    private function registrar(string $correo, string $rol, string $estado, string $nombre, string $apellido, string $nacimiento): int
    {
        $usuario = $this->usuarios->registrar([
            'rol_codigo' => $rol, 'estado_codigo' => $estado, 'correo' => $correo, 'contrasena' => GeneradorActividad::CONTRASENA,
            'primer_nombre' => $nombre, 'primer_apellido' => $apellido, 'segundo_apellido' => $this->elegir(LigaColombiana::APELLIDOS),
            'tipo_documento_codigo' => mt_rand(1, 20) === 1 ? 'CE' : 'CC', 'numero_documento' => (string) mt_rand(1000000000, 1999999999),
            'celular' => '3' . mt_rand(0, 2) . mt_rand(0, 9) . mt_rand(1000000, 9999999), 'fecha_nacimiento' => $nacimiento,
        ]);
        if (!$usuario) {
            throw new RuntimeException("No se pudo registrar {$correo}; revisa storage/logs/laravel.log.");
        }

        return (int) $usuario->id_usuario;
    }

    /**
     * Tokens de sesión, logins exitosos y fallidos en auditoría y códigos de recuperación.
     * @param list<array{id:int,correo:string,estado:string}> $clientes
     */
    private function registrarSesiones(int $adminId, array $clientes): void
    {
        $token = Usuario::query()->findOrFail($adminId)->createToken('playboss-api');
        $this->auditoria->registrarAutenticacion($adminId, self::CORREO_ADMIN, true, '127.0.0.1', 'Chrome / Windows', null, $token->accessToken->getKey());

        foreach ($clientes as $i => $cliente) {
            $dispositivo = $this->elegir(['Chrome / Windows', 'Safari / iPhone', 'Chrome / Android', 'Firefox / Linux', 'Edge / Windows']);
            $ip = '186.' . mt_rand(80, 155) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
            if ($cliente['estado'] === 'ACT' && mt_rand(1, 100) <= 70) {
                $token = Usuario::query()->findOrFail($cliente['id'])->createToken('playboss-api');
                $this->auditoria->registrarAutenticacion($cliente['id'], $cliente['correo'], true, $ip, $dispositivo, null, $token->accessToken->getKey());
            }
            if (mt_rand(1, 100) <= 20 || $cliente['estado'] !== 'ACT') {
                $motivo = $cliente['estado'] === 'ACT' ? 'credenciales_invalidas' : 'usuario_inactivo';
                $this->auditoria->registrarAutenticacion($cliente['id'], $cliente['correo'], false, $ip, $dispositivo, $motivo);
            }
            if ($i % 9 === 4) {
                CodigoRecuperacion::query()->create([
                    'usuario_id' => $cliente['id'], 'codigo_hash' => Hash::make('123456'),
                    'fecha_expiracion' => now()->addMinutes(15), 'usado_en' => $i % 2 === 0 ? now()->subMinutes(5) : null,
                ]);
            }
        }
    }

    /**
     * Suma un resultado a la fila de la tabla de posiciones.
     * @param array<string,mixed> $fila
     */
    private function sumarResultado(array &$fila, int $favor, int $contra): void
    {
        $fila['pj']++;
        $fila['gf'] += $favor;
        $fila['gc'] += $contra;
        if ($favor > $contra) {
            $fila['pg']++;
            $fila['pts'] += 3;
        } elseif ($favor === $contra) {
            $fila['pe']++;
            $fila['pts']++;
        } else {
            $fila['pp']++;
        }
    }

    /**
     * @param array<string,int> $pesos
     * @param list<string> $excluir
     */
    private function elegirPonderado(array $pesos, array $excluir = []): string
    {
        $pesos = array_diff_key($pesos, array_flip($excluir));
        $tiro = mt_rand(1, max(1, array_sum($pesos)));
        foreach ($pesos as $clave => $peso) {
            $tiro -= $peso;
            if ($tiro <= 0) {
                return (string) $clave;
            }
        }

        return (string) array_key_last($pesos);
    }

    private function elegir(array $opciones): mixed
    {
        return $opciones[mt_rand(0, count($opciones) - 1)];
    }
}
