<?php

namespace App\Console\Commands;

use App\Models\Usuario\CredencialUsuario;
use App\Service\Demo\EscudoSvg;
use App\Service\Demo\GeneradorActividad;
use App\Service\Demo\GeneradorCatalogo;
use App\Service\Demo\LigaColombiana;
use App\Service\Demo\NubeDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Carga la Liga BetPlay 2026-II completa en los 4 motores, Drive y Firestore usando
 * los flujos reales de la API (registro, depósitos, apuestas, liquidación y retiros).
 */
class CargarDatosDemoCommand extends Command
{
    protected $signature = 'playboss:demo-data
        {--fresh : Borra las 4 bases locales (y lo subido a Drive/Firestore) antes de cargar}
        {--usuarios=40 : Cantidad de clientes ficticios además del cliente principal}
        {--semilla=2026 : Semilla aleatoria; la misma semilla genera los mismos datos}';
    protected $description = 'Carga la Liga BetPlay completa con apuestas de todas las modalidades usando los flujos reales de la API';

    private const CONEXIONES = ['sqlsrv', 'mysql', 'pgsql', 'mongodb'];

    public function __construct(
        private readonly GeneradorCatalogo $catalogo,
        private readonly GeneradorActividad $actividad,
        private readonly NubeDemo $nube,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('fresh')) {
            if (app()->environment('production')) {
                $this->components->error('--fresh no está permitido en producción.');
                return self::FAILURE;
            }
            $this->reiniciar();
        }

        if (CredencialUsuario::query()->where('correo', GeneradorActividad::CORREO_CLIENTE)->exists()) {
            $this->components->warn('Los datos demo ya están cargados. Usa --fresh para borrarlos y volver a cargarlos.');
            return self::SUCCESS;
        }

        mt_srand((int) $this->option('semilla'));
        $inicio = microtime(true);

        $drive = ['logo' => null, 'escudos' => []];
        $this->components->task('Google Drive: logo de la liga y 20 escudos', function () use (&$drive): bool {
            $drive = $this->subirImagenes();
            return $drive['logo'] !== null;
        });

        $catalogo = [];
        $this->components->task('MySQL: liga, equipos, jugadores y calendario (190 partidos)', function () use (&$catalogo, $drive): void {
            $catalogo = $this->catalogo->crearCatalogo($drive['logo'], $drive['escudos']);
        });

        $mongo = [];
        $this->components->task('MongoDB: mercados con cuotas y reglas de apuesta', function () use (&$mongo, $catalogo): void {
            $mongo = $this->catalogo->crearMercados($catalogo['partidos'], $catalogo['equipos']);
        });

        $usuarios = [];
        $this->components->task('SQL Server: usuarios, sesiones y códigos de recuperación', function () use (&$usuarios): void {
            $usuarios = $this->actividad->crearUsuarios((int) $this->option('usuarios'));
        });

        $depositos = [];
        $this->components->task('PostgreSQL: cuentas y depósitos', function () use (&$depositos, $usuarios): void {
            $depositos = $this->actividad->crearDepositos($usuarios['clientes']);
        });

        $apuestas = [];
        $this->components->task('PostgreSQL: apuestas simples y combinadas', function () use (&$apuestas, $usuarios, $catalogo, $mongo): void {
            $apuestas = $this->actividad->crearApuestas($usuarios['clientes'], $catalogo['partidos'], $mongo['mercados']);
        });

        $tabla = [];
        $this->components->task('Liquidación de las jornadas jugadas (marcador, estadísticas y alineaciones)', function () use (&$tabla, $catalogo, $mongo, $usuarios): void {
            $tabla = $this->actividad->liquidarJugados($catalogo['partidos'], $mongo['modelos'], $catalogo['equipos'], $catalogo['jugadores'], $catalogo['temporada'], $usuarios['admin']);
        });

        $extras = [];
        $this->components->task('Anulaciones del admin, retiros y fechas reales', function () use (&$extras, $usuarios, $catalogo): void {
            $extras['anuladas'] = $this->actividad->anularPendientes($usuarios['admin'], 3);
            $extras['retiros'] = $this->actividad->crearRetiros($usuarios['clientes']);
            $this->actividad->ajustarFechas($catalogo['partidos']);
        });

        $this->components->task('Firebase Firestore: tabla de posiciones y resumen', function () use ($tabla, $apuestas, $depositos, $catalogo, $drive): bool {
            return $this->guardarEnFirestore($tabla, $apuestas, $depositos, $catalogo, $drive);
        });

        $this->mostrarResumen($tabla, $apuestas, $depositos, $extras, microtime(true) - $inicio);

        return self::SUCCESS;
    }

    /**
     * Borra lo subido a Drive/Firestore por la demo y deja las 4 bases vacías y migradas.
     */
    private function reiniciar(): void
    {
        $this->components->task('Limpiando Drive y Firestore', function (): void {
            try {
                $this->nube->borrarArchivosDemo();
                $this->nube->vaciarColeccion(NubeDemo::COLECCION_RESUMEN);
                $this->nube->vaciarColeccion(NubeDemo::COLECCION_TABLA);
            } catch (Throwable $e) {
                $this->components->warn('No se pudo limpiar la nube: ' . $e->getMessage());
            }
        });

        $this->components->task('Vaciando y migrando las 4 bases', function (): void {
            foreach (self::CONEXIONES as $conexion) {
                Artisan::call('db:wipe', ['--database' => $conexion, '--force' => true]);
            }
            Artisan::call('playboss:migrate', ['--force' => true]);
        });
    }

    /**
     * Sube el logo de la liga y los escudos generados; continúa sin Drive si falla.
     * @return array{logo:?string,escudos:array<string,string>}
     */
    private function subirImagenes(): array
    {
        try {
            $logo = $this->nube->subirArchivo('liga-betplay-logo.png', (string) file_get_contents(resource_path('demo/liga-logo.png')), 'image/png');
            $escudos = [];
            foreach (LigaColombiana::equipos() as $clave => $equipo) {
                $slug = GeneradorCatalogo::slug($equipo['nombre']);
                // Escudo oficial si está en storage/app/private/escudos (fuera de git); si no, uno generado.
                $oficial = storage_path("app/private/escudos/{$slug}.png");
                $escudos[$clave] = is_file($oficial)
                    ? $this->nube->subirArchivo("escudo-{$slug}.png", (string) file_get_contents($oficial), 'image/png')
                    : $this->nube->subirArchivo("escudo-{$slug}.svg", EscudoSvg::generar($slug, $equipo['siglas'], $equipo['colores']), 'image/svg+xml');
            }

            return ['logo' => $logo, 'escudos' => $escudos];
        } catch (Throwable $e) {
            $this->components->warn('Drive no disponible: ' . $e->getMessage());
            return ['logo' => null, 'escudos' => []];
        }
    }

    private function guardarEnFirestore(array $tabla, array $apuestas, array $depositos, array $catalogo, array $drive): bool
    {
        try {
            $this->nube->guardarDocumento(NubeDemo::COLECCION_TABLA, 'liga-betplay-' . GeneradorCatalogo::TEMPORADA, [
                'liga' => 'Liga BetPlay DIMAYOR',
                'temporada' => GeneradorCatalogo::TEMPORADA,
                'liga_id_mysql' => $catalogo['liga'],
                'actualizado_en' => now()->utc(),
                'equipos' => $tabla,
            ]);
            $this->nube->guardarDocumento(NubeDemo::COLECCION_RESUMEN, now()->format('Ymd-His'), [
                'descripcion' => 'Carga demo Liga BetPlay ' . GeneradorCatalogo::TEMPORADA,
                'partidos' => count($catalogo['partidos']),
                'apuestas' => $apuestas['creadas'],
                'combinadas' => $apuestas['combinadas'],
                'depositos_aprobados' => $depositos['aprobados'],
                'logo_drive_id' => $drive['logo'],
                'creado_en' => now()->utc(),
            ]);

            return true;
        } catch (Throwable $e) {
            $this->components->warn('Firestore no disponible: ' . $e->getMessage());
            return false;
        }
    }

    private function mostrarResumen(array $tabla, array $apuestas, array $depositos, array $extras, float $segundos): void
    {
        $this->newLine();
        $this->components->info(sprintf('Datos demo cargados en %.0f s.', $segundos));
        $this->components->twoColumnDetail('Cliente principal', GeneradorActividad::CORREO_CLIENTE . ' / ' . GeneradorActividad::CONTRASENA);
        $this->components->twoColumnDetail('Administrador', GeneradorActividad::CORREO_ADMIN . ' / ' . GeneradorActividad::CONTRASENA);
        $this->components->twoColumnDetail('Otros clientes', '*.@playboss.test / ' . GeneradorActividad::CONTRASENA);
        $this->components->twoColumnDetail('Apuestas', "{$apuestas['creadas']} ({$apuestas['simples']} simples, {$apuestas['combinadas']} combinadas, {$apuestas['canceladas']} canceladas, {$extras['anuladas']} anuladas por el admin)");
        $this->components->twoColumnDetail('Depósitos', "{$depositos['aprobados']} aprobados, {$depositos['rechazados']} rechazados, {$depositos['pendientes']} pendientes; {$extras['retiros']} retiros");
        foreach ($apuestas['rechazadas'] as $motivo => $cantidad) {
            $this->components->twoColumnDetail('Rechazadas por reglas', "{$cantidad} × {$motivo}");
        }

        $this->newLine();
        $this->line('  <options=bold>Tabla de posiciones (top 8)</>');
        $this->table(['#', 'Equipo', 'PJ', 'PG', 'PE', 'PP', 'GF', 'GC', 'DG', 'PTS'], array_map(
            fn (array $f): array => [$f['posicion'], $f['equipo'], $f['pj'], $f['pg'], $f['pe'], $f['pp'], $f['gf'], $f['gc'], $f['dg'], $f['pts']],
            array_slice($tabla, 0, 8)
        ));
    }
}
