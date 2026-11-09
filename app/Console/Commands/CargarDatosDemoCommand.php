<?php

namespace App\Console\Commands;

use App\Models\Market\Mercado;
use App\Models\Market\ReglaApuesta;
use App\Models\Payment\Transaccion;
use App\Models\Usuario\CodigoRecuperacion;
use App\Models\Usuario\CredencialUsuario;
use App\Models\Usuario\Usuario;
use App\Service\Account\AccountService;
use App\Service\Bet\BetService;
use App\Service\Bet\SettlementService;
use App\Service\Payment\PagoService;
use App\Service\Storage\AuditLogService;
use App\Service\Storage\DriveImageStorage;
use App\Service\SvcUsuario;
use Google\Service\Firestore;
use Google\Service\Firestore\Document;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

/**
 * Carga datos de demostración en los 4 motores, Firestore y Drive usando los
 * servicios reales (registro, depósito, apuestas, cancelación y liquidación).
 */
class CargarDatosDemoCommand extends Command
{
    protected $signature = 'playboss:demo-data';
    protected $description = 'Carga datos de prueba en todas las tablas usando los flujos reales de la API';

    private const CORREO_CLIENTE = 'cliente@playboss.test';
    private const CORREO_ADMIN = 'admin@playboss.test';
    private const CONTRASENA = 'Demo1234!';

    public function __construct(
        private readonly SvcUsuario $usuarios,
        private readonly AuditLogService $auditoria,
        private readonly AccountService $cuentas,
        private readonly PagoService $pagos,
        private readonly BetService $apuestas,
        private readonly SettlementService $liquidacion,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (CredencialUsuario::query()->where('correo', self::CORREO_CLIENTE)->exists()) {
            $this->components->warn('Los datos demo ya están cargados (existe ' . self::CORREO_CLIENTE . ').');
            $this->line('Para empezar de cero ver "Reiniciar datos" en docs/SETUP-LOCAL.md.');
            return self::SUCCESS;
        }

        $logoId = null;
        $this->components->task('Google Drive: subir logo de la liga', function () use (&$logoId): bool {
            $logoId = $this->subirLogo();
            return $logoId !== null;
        });

        $catalogo = [];
        $this->components->task('MySQL: catálogo deportivo', function () use (&$catalogo, $logoId): void {
            $catalogo = $this->cargarCatalogo($logoId);
        });

        $mercados = [];
        $this->components->task('MongoDB: mercados y reglas de apuesta', function () use (&$mercados, $catalogo): void {
            $mercados = $this->cargarMercados($catalogo);
        });

        $usuarios = [];
        $this->components->task('SQL Server: usuarios, token y código de recuperación', function () use (&$usuarios): void {
            $usuarios = $this->cargarUsuarios();
        });

        $this->components->task('PostgreSQL: cuenta y depósito aprobado', function () use ($usuarios): void {
            $this->cargarDeposito($usuarios['cliente']);
        });

        $this->components->task('PostgreSQL: apuestas (pendiente, cancelada y ganada)', function () use ($usuarios, $catalogo, $mercados): void {
            $this->cargarApuestas($usuarios, $catalogo, $mercados);
        });

        $this->components->task('Firebase Firestore: resumen de la demo', function () use ($usuarios, $catalogo, $logoId): bool {
            return $this->registrarEnFirestore($usuarios, $catalogo, $logoId);
        });

        $this->newLine();
        $this->components->info('Datos demo cargados.');
        $this->components->twoColumnDetail('Cliente', self::CORREO_CLIENTE . ' / ' . self::CONTRASENA);
        $this->components->twoColumnDetail('Administrador', self::CORREO_ADMIN . ' / ' . self::CONTRASENA);

        return self::SUCCESS;
    }

    /**
     * Sube el logo de la liga a Drive; null si Drive no está disponible.
     */
    private function subirLogo(): ?string
    {
        try {
            $archivo = new UploadedFile(resource_path('demo/liga-logo.png'), 'liga-logo.png', 'image/png', null, true);
            return app(DriveImageStorage::class)->guardar($archivo, 'liga-betplay-logo.png')['id'];
        } catch (Throwable $e) {
            $this->components->warn('Drive no disponible: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Inserta país, ciudades, estadios, liga, temporada, equipos, jugadores, jornada, partidos y alineación.
     * @return array<string,int>
     */
    private function cargarCatalogo(?string $logoId): array
    {
        $db = DB::connection('mysql');

        return $db->transaction(function () use ($db, $logoId): array {
            $db->table('pais')->updateOrInsert(['codigo_iso' => 'CO'], ['nombre' => 'Colombia']);
            foreach ([['POR', 'Portero'], ['DEF', 'Defensa'], ['MED', 'Mediocampista'], ['DEL', 'Delantero']] as [$codigo, $nombre]) {
                $db->table('posicion')->updateOrInsert(['codigo' => $codigo], ['nombre' => $nombre]);
            }

            $bogota = $db->table('ciudad')->insertGetId(['nombre' => 'Bogotá', 'pais_codigo' => 'CO']);
            $medellin = $db->table('ciudad')->insertGetId(['nombre' => 'Medellín', 'pais_codigo' => 'CO']);
            $campin = $db->table('estadio')->insertGetId(['nombre' => 'Estadio El Campín', 'ciudad_id' => $bogota, 'capacidad' => 36343, 'direccion' => 'Cra. 30 # 57-60']);
            $atanasio = $db->table('estadio')->insertGetId(['nombre' => 'Estadio Atanasio Girardot', 'ciudad_id' => $medellin, 'capacidad' => 40943, 'direccion' => 'Cra. 74 # 48010']);

            $liga = $db->table('liga')->insertGetId([
                'servicio_codigo' => 'FUTBOL', 'pais_codigo' => 'CO', 'nombre' => 'Liga BetPlay', 'logo_drive_id' => $logoId, 'activa' => true,
            ]);
            $temporada = $db->table('temporada')->insertGetId([
                'liga_id' => $liga, 'nombre' => '2026-II', 'fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-12-15',
            ]);

            $equipos = [];
            foreach ([
                'millonarios' => ['Millonarios', $bogota, $campin, '1946-06-18'],
                'santafe' => ['Independiente Santa Fe', $bogota, $campin, '1941-02-28'],
                'nacional' => ['Atlético Nacional', $medellin, $atanasio, '1947-03-07'],
                'medellin' => ['Independiente Medellín', $medellin, $atanasio, '1913-11-14'],
            ] as $clave => [$nombre, $ciudad, $estadio, $fundacion]) {
                $equipos[$clave] = $db->table('equipo')->insertGetId([
                    'nombre' => $nombre, 'ciudad_id' => $ciudad, 'estadio_id' => $estadio, 'fecha_fundacion' => $fundacion,
                ]);
                $db->table('equipo_liga_temporada')->insert([
                    'equipo_id' => $equipos[$clave], 'temporada_id' => $temporada, 'fecha_inscripcion' => '2026-06-20', 'puntos' => 0,
                ]);
            }

            $jugadorSantaFe = $db->table('jugador')->insertGetId([
                'primer_nombre' => 'Carlos', 'primer_apellido' => 'Ramírez', 'fecha_nacimiento' => '1998-04-12',
                'pais_codigo' => 'CO', 'posicion_codigo' => 'DEL', 'equipo_actual_id' => $equipos['santafe'], 'dorsal' => 9,
            ]);
            $jugadorMedellin = $db->table('jugador')->insertGetId([
                'primer_nombre' => 'Andrés', 'primer_apellido' => 'Gómez', 'fecha_nacimiento' => '1995-09-30',
                'pais_codigo' => 'CO', 'posicion_codigo' => 'POR', 'equipo_actual_id' => $equipos['medellin'], 'dorsal' => 1,
            ]);

            $jornada = $db->table('jornada')->insertGetId([
                'temporada_id' => $temporada, 'numero' => 1,
                'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->addDays(7)->toDateString(),
            ]);

            $partidoAbierto = $db->table('partido')->insertGetId([
                'jornada_id' => $jornada, 'equipo_local_id' => $equipos['millonarios'], 'equipo_visitante_id' => $equipos['nacional'],
                'estadio_id' => $campin, 'fecha_hora' => now()->addDays(3)->setTime(20, 0), 'estado_codigo' => 'PROG',
            ]);
            $partidoLiquidar = $db->table('partido')->insertGetId([
                'jornada_id' => $jornada, 'equipo_local_id' => $equipos['santafe'], 'equipo_visitante_id' => $equipos['medellin'],
                'estadio_id' => $campin, 'fecha_hora' => now()->addDays(4)->setTime(18, 0), 'estado_codigo' => 'PROG',
            ]);

            $db->table('alineacion')->insert([
                ['partido_id' => $partidoLiquidar, 'jugador_id' => $jugadorSantaFe, 'equipo_id' => $equipos['santafe'], 'titular' => true, 'minuto_entra' => 0, 'minuto_sale' => 78],
                ['partido_id' => $partidoLiquidar, 'jugador_id' => $jugadorMedellin, 'equipo_id' => $equipos['medellin'], 'titular' => true, 'minuto_entra' => 0, 'minuto_sale' => null],
            ]);

            return ['liga' => $liga, 'partido_abierto' => $partidoAbierto, 'partido_liquidar' => $partidoLiquidar];
        });
    }

    /**
     * Crea un mercado 1X2 por partido y la regla de apuesta vigente.
     * @param array<string,int> $catalogo
     * @return array<string,string>
     */
    private function cargarMercados(array $catalogo): array
    {
        ReglaApuesta::query()->create([
            'code' => 'REGLA_1X2', 'market_code' => '1X2', 'min_stake' => '2000.00', 'max_stake' => '2000000.00',
            'max_payout' => '20000000.00', 'min_age' => 18, 'active' => true, 'effective_from' => now()->subDay(), 'version' => 1,
        ]);

        $mercados = [];
        foreach ([
            'partido_abierto' => ['Millonarios', 'Atlético Nacional', 2.10, 3.20, 3.40],
            'partido_liquidar' => ['Independiente Santa Fe', 'Independiente Medellín', 2.50, 3.00, 2.80],
        ] as $clave => [$local, $visitante, $cuota1, $cuotaX, $cuota2]) {
            Mercado::query()->create([
                'match_id' => $catalogo[$clave], 'code' => '1X2', 'name' => 'Ganador del partido', 'status' => 'ACTIVE', 'version' => 1,
                'selections' => [
                    ['code' => '1', 'label' => $local, 'odd' => $cuota1],
                    ['code' => 'X', 'label' => 'Empate', 'odd' => $cuotaX],
                    ['code' => '2', 'label' => $visitante, 'odd' => $cuota2],
                ],
                'updated_at' => now(),
            ]);
            // laravel-mongodb no devuelve el _id en el modelo recién creado: se relee por su llave única.
            $mercados[$clave] = (string) Mercado::query()
                ->where('match_id', $catalogo[$clave])
                ->where('code', '1X2')
                ->firstOrFail()
                ->getKey();
        }

        return $mercados;
    }

    /**
     * Registra cliente y admin, emite un token y genera un código de recuperación.
     * @return array<string,int>
     */
    private function cargarUsuarios(): array
    {
        DB::connection('sqlsrv')->table('estado_usuario')->updateOrInsert(
            ['codigo' => 'BLQ'],
            ['nombre' => 'Bloqueado', 'descripcion' => 'Cuenta bloqueada por administración']
        );

        $cliente = $this->registrarUsuario(self::CORREO_CLIENTE, 'CLIENTE', 'Laura', 'Martínez', '1010101010', '1999-05-20');
        $admin = $this->registrarUsuario(self::CORREO_ADMIN, 'ADMIN', 'Daniel', 'Torres', '2020202020', '1995-01-15');

        $token = $cliente->createToken('playboss-api');
        $this->auditoria->registrarAutenticacion($cliente->id_usuario, self::CORREO_CLIENTE, true, '127.0.0.1', 'playboss:demo-data', null, $token->accessToken->getKey());
        $this->auditoria->registrarAutenticacion($admin->id_usuario, self::CORREO_ADMIN, true, '127.0.0.1', 'playboss:demo-data');

        CodigoRecuperacion::query()->create([
            'usuario_id' => $cliente->id_usuario,
            'codigo_hash' => Hash::make('123456'),
            'fecha_expiracion' => now()->addMinutes(15),
        ]);

        return ['cliente' => (int) $cliente->id_usuario, 'admin' => (int) $admin->id_usuario];
    }

    /**
     * Registra un usuario con el servicio real de registro.
     */
    private function registrarUsuario(string $correo, string $rol, string $nombre, string $apellido, string $documento, string $nacimiento): Usuario
    {
        $usuario = $this->usuarios->registrar([
            'rol_codigo' => $rol, 'correo' => $correo, 'contrasena' => self::CONTRASENA,
            'primer_nombre' => $nombre, 'primer_apellido' => $apellido, 'tipo_documento_codigo' => 'CC',
            'numero_documento' => $documento, 'celular' => '3001234567', 'fecha_nacimiento' => $nacimiento,
        ]);
        if (!$usuario) {
            throw new RuntimeException("No se pudo registrar {$correo}; revisa storage/logs/laravel.log.");
        }

        return $usuario;
    }

    /**
     * Abre la cuenta y aprueba un depósito con el mismo flujo del webhook de pagos.
     */
    private function cargarDeposito(int $usuarioId): void
    {
        $this->cuentas->abrir($usuarioId);
        $referencia = 'DEMO-' . now()->format('YmdHis');

        Transaccion::query()->create([
            'usuario_id' => $usuarioId, 'metodo_pago_codigo' => 'WOMPI', 'tipo' => 'DEPOSITO', 'monto' => '200000.00',
            'moneda' => 'COP', 'estado' => 'PENDIENTE', 'clave_idempotencia' => 'demo-deposito-' . $usuarioId,
            'referencia_externa' => $referencia, 'fecha_hora' => now(),
        ]);

        $this->pagos->aplicarEventoVerificado([
            'event_id' => 'demo-evento-' . $referencia, 'reference' => $referencia,
            'status' => 'APPROVED', 'amount' => '200000.00', 'currency' => 'COP',
        ]);
    }

    /**
     * Crea tres apuestas: una queda pendiente, otra se cancela y la tercera se liquida como ganada.
     * @param array<string,int> $usuarios
     * @param array<string,int> $catalogo
     * @param array<string,string> $mercados
     */
    private function cargarApuestas(array $usuarios, array $catalogo, array $mercados): void
    {
        $cliente = $usuarios['cliente'];

        $this->apuestas->crear($cliente, '10000.00', [
            ['match_id' => $catalogo['partido_abierto'], 'market_id' => $mercados['partido_abierto'], 'selection_code' => '1'],
        ], 'demo-apuesta-pendiente');

        $cancelada = $this->apuestas->crear($cliente, '5000.00', [
            ['match_id' => $catalogo['partido_abierto'], 'market_id' => $mercados['partido_abierto'], 'selection_code' => 'X'],
        ], 'demo-apuesta-cancelada');
        $this->apuestas->cancelar($cliente, (int) $cancelada->id_apuesta);

        $this->apuestas->crear($cliente, '20000.00', [
            ['match_id' => $catalogo['partido_liquidar'], 'market_id' => $mercados['partido_liquidar'], 'selection_code' => '1'],
        ], 'demo-apuesta-ganadora');

        // Santa Fe 2 - 1 Medellín: gana la opción "1".
        $this->liquidacion->liquidarPartido($catalogo['partido_liquidar'], 2, 1, [], $usuarios['admin']);
    }

    /**
     * Guarda un documento resumen en Firestore; false si Firestore no está disponible.
     * @param array<string,int> $usuarios
     * @param array<string,int> $catalogo
     */
    private function registrarEnFirestore(array $usuarios, array $catalogo, ?string $logoId): bool
    {
        try {
            $parent = sprintf(
                'projects/%s/databases/%s/documents',
                config('services.firebase.project_id'),
                config('services.firebase.firestore_database')
            );
            app(Firestore::class)->projects_databases_documents->createDocument($parent, 'demo_cargas', new Document([
                'fields' => [
                    'descripcion' => ['stringValue' => 'Carga de datos demo de PlayBoss'],
                    'cliente_id_sqlserver' => ['integerValue' => (string) $usuarios['cliente']],
                    'liga_id_mysql' => ['integerValue' => (string) $catalogo['liga']],
                    'partido_liquidado_id_mysql' => ['integerValue' => (string) $catalogo['partido_liquidar']],
                    'logo_drive_id' => $logoId ? ['stringValue' => $logoId] : ['nullValue' => null],
                    'creado_en' => ['timestampValue' => now()->toIso8601ZuluString()],
                ],
            ]));

            return true;
        } catch (Throwable $e) {
            $this->components->warn('Firestore no disponible: ' . $e->getMessage());
            return false;
        }
    }
}
