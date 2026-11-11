<?php

use App\Http\Controllers\Api\Admin\EquipoAdminController;
use App\Http\Controllers\Api\Admin\LigaAdminController;
use App\Http\Controllers\Api\Admin\LiquidacionController;
use App\Http\Controllers\Api\Admin\MercadoAdminController;
use App\Http\Controllers\Api\Admin\PartidoAdminController;
use App\Http\Controllers\Api\Admin\ReglaApuestaAdminController;
use App\Http\Controllers\Api\Admin\RetiroAdminController;
use App\Http\Controllers\Api\Admin\UsuarioAdminController;
use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\ContactoController;
use App\Http\Controllers\Api\LimiteJuegoController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\RetiroController;
use App\Http\Controllers\Api\ApuestaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\CuentaController;
use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PerfilController;
use App\Http\Controllers\Api\RecuperacionController;
use App\Http\Middleware\EnsureMfaRol;
use App\Http\Middleware\EnsureRol;
use App\Http\Middleware\EnsureUsuarioActivo;
use App\Http\Middleware\VerificaRecaptcha;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/registro', [AuthController::class, 'registro'])->middleware(['throttle:recuperacion', VerificaRecaptcha::class . ':registro']);
    Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:login', VerificaRecaptcha::class . ':login']);
    Route::post('/google', [GoogleAuthController::class, 'login'])->middleware('throttle:login');
    // Segundo paso del login: canjea mfa_token + código por el token real (el bloqueo por intentos está en SvcMfa).
    Route::post('/mfa/verificar', [MfaController::class, 'verificar'])->middleware('throttle:mfa-verificar');
    Route::post('/recuperar', [RecuperacionController::class, 'solicitar'])->middleware(['throttle:recuperacion', VerificaRecaptcha::class . ':recuperar']);
    Route::post('/restablecer', [RecuperacionController::class, 'restablecer'])->middleware(['throttle:recuperacion', VerificaRecaptcha::class . ':restablecer']);

    Route::middleware(['auth:sanctum', EnsureUsuarioActivo::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/google/completar', [GoogleAuthController::class, 'completar']);

        Route::get('/mfa', [MfaController::class, 'estado']);
        Route::post('/mfa/configurar', [MfaController::class, 'configurar'])->middleware('throttle:10,1');
        Route::post('/mfa/activar', [MfaController::class, 'activar'])->middleware('throttle:mfa');
        Route::post('/mfa/desactivar', [MfaController::class, 'desactivar'])->middleware('throttle:mfa');
        Route::post('/mfa/recovery-codes', [MfaController::class, 'regenerarCodigos'])->middleware('throttle:mfa');
    });
});

// Catálogo público (lectura)
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/deportes', [CatalogoController::class, 'deportes']);
    Route::get('/ligas', [CatalogoController::class, 'ligas']);
    Route::get('/temporadas', [CatalogoController::class, 'temporadas']);
    Route::get('/equipos', [CatalogoController::class, 'equipos']);
    Route::get('/estadios', [CatalogoController::class, 'estadios']);
    Route::get('/jugadores', [CatalogoController::class, 'jugadores']);
    Route::get('/jornadas', [CatalogoController::class, 'jornadas']);
    Route::get('/partidos', [CatalogoController::class, 'partidos']);
    Route::get('/partidos/{id}', [CatalogoController::class, 'partido'])->whereNumber('id');
    Route::get('/partidos/{id}/mercados', [CatalogoController::class, 'mercados'])->whereNumber('id');
    Route::get('/configuracion/limites', [ConfiguracionController::class, 'limites']);
    Route::get('/media/equipos/{id}/escudo', [MediaController::class, 'escudo'])->whereNumber('id')->name('media.equipo');
    Route::get('/media/ligas/{id}/logo', [MediaController::class, 'logo'])->whereNumber('id')->name('media.liga');
});

// Formulario de contacto público (guarda en MongoDB y envía correo)
Route::post('/contacto', [ContactoController::class, 'store'])->middleware('throttle:5,1');

// Webhook público de Wompi (autenticidad: checksum + consulta a la API de Wompi)
Route::post('/pagos/wompi/webhook', [PagoController::class, 'webhookWompi'])->middleware('throttle:webhook');
// Webhook público de Mercado Pago (x-signature + consulta del pago con el Access Token)
Route::post('/pagos/mercadopago/webhook', [PagoController::class, 'webhookMercadoPago'])->middleware('throttle:webhook');

Route::middleware(['auth:sanctum', EnsureUsuarioActivo::class])->group(function () {
    Route::get('/perfil', [PerfilController::class, 'show']);
    Route::put('/perfil', [PerfilController::class, 'update']);
    Route::put('/perfil/contrasena', [PerfilController::class, 'cambiarContrasena']);
    Route::get('/perfil/foto', [PerfilController::class, 'foto']);
    Route::post('/perfil/foto', [PerfilController::class, 'subirFoto'])->middleware('throttle:10,1');

    Route::get('/cuenta', [CuentaController::class, 'show']);
    Route::get('/cuenta/movimientos', [CuentaController::class, 'movimientos']);

    Route::get('/apuestas', [ApuestaController::class, 'index']);
    Route::get('/apuestas/{id}', [ApuestaController::class, 'show'])->whereNumber('id');
    Route::post('/apuestas', [ApuestaController::class, 'store'])->middleware('throttle:apuestas');
    Route::post('/apuestas/{id}/cancelar', [ApuestaController::class, 'cancelar'])->whereNumber('id')->middleware('throttle:apuestas');

    Route::get('/perfil/limites', [LimiteJuegoController::class, 'show']);
    Route::put('/perfil/limites', [LimiteJuegoController::class, 'update'])->middleware('throttle:10,1');

    Route::post('/pagos/depositos', [PagoController::class, 'depositar'])->middleware('throttle:pagos');
    // Antes de /pagos/{referencia} para que "retiros" no se interprete como referencia.
    Route::get('/pagos/retiros', [RetiroController::class, 'index']);
    Route::post('/pagos/retiros', [RetiroController::class, 'store'])->middleware(['throttle:pagos', EnsureMfaRol::class]);
    Route::get('/pagos/{referencia}', [PagoController::class, 'estado'])->middleware('throttle:pagos');

    // Administración
    Route::prefix('admin')->middleware([EnsureRol::class . ':ADMIN', EnsureMfaRol::class])->group(function () {
        Route::apiResource('equipos', EquipoAdminController::class);
        Route::apiResource('ligas', LigaAdminController::class);
        Route::apiResource('partidos', PartidoAdminController::class);
        Route::apiResource('mercados', MercadoAdminController::class);
        Route::apiResource('reglas', ReglaApuestaAdminController::class);
        Route::post('partidos/{id}/liquidar', [LiquidacionController::class, 'partido'])->whereNumber('id');
        Route::post('apuestas/{id}/anular', [LiquidacionController::class, 'anularApuesta'])->whereNumber('id');
        Route::get('retiros', [RetiroAdminController::class, 'index']);
        Route::post('retiros/{id}/aprobar', [RetiroAdminController::class, 'aprobar'])->whereNumber('id');
        Route::post('retiros/{id}/rechazar', [RetiroAdminController::class, 'rechazar'])->whereNumber('id');
        Route::get('usuarios', [UsuarioAdminController::class, 'index']);
        Route::put('usuarios/{id}/estado', [UsuarioAdminController::class, 'cambiarEstado'])->whereNumber('id');
        Route::post('usuarios/{id}/mfa/restablecer', [UsuarioAdminController::class, 'restablecerMfa'])->whereNumber('id');
    });
});

// Documentación Swagger (se desactiva con API_DOCS_ENABLED=false)
Route::get('/documentation', [DocsController::class, 'ui']);
Route::get('/openapi.json', [DocsController::class, 'spec']);
