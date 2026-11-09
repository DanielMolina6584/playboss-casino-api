<?php

namespace App\Providers;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Firestore;
use App\Contracts\PaymentGateway;
use App\Service\Payment\FirebaseFunctionPaymentGateway;
use App\Service\Payment\WompiPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\Usuario\PersonalAccessToken;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            return match (config('services.payment_gateway.driver')) {
                'wompi' => $app->make(WompiPaymentGateway::class),
                'firebase_function' => $app->make(FirebaseFunctionPaymentGateway::class),
                default => throw new InvalidArgumentException('PAYMENT_GATEWAY_DRIVER no está soportado.'),
            };
        });

        $this->app->singleton(Firestore::class, function (): Firestore {
            $projectId = config('services.firebase.project_id');

            if (!is_string($projectId) || trim($projectId) === '') {
                throw new RuntimeException('FIREBASE_PROJECT_ID no está configurado.');
            }

            $credentialsPath = $this->credentialFilePath(
                config('services.firebase.credentials'),
                'FIREBASE_CREDENTIALS'
            );
            $client = new GoogleClient();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(Firestore::DATASTORE);

            return new Firestore($client);
        });

        $this->app->singleton(Drive::class, function (): Drive {
            $credentialsPath = $this->credentialFilePath(
                config('services.google_drive.credentials'),
                'GOOGLE_DRIVE_CREDENTIALS'
            );

            $client = new GoogleClient();
            $client->setAuthConfig($credentialsPath);
            // DRIVE_FILE solo ve archivos creados por la app; la carpeta destino se comparte desde otra cuenta.
            $client->addScope(Drive::DRIVE);

            // Modo OAuth (Gmail personal): las cuentas de servicio no tienen cuota en "Mi unidad".
            $refreshToken = config('services.google_drive.refresh_token');
            if (is_string($refreshToken) && $refreshToken !== '') {
                $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (isset($token['error'])) {
                    throw new RuntimeException(
                        'Google Drive rechazó GOOGLE_DRIVE_REFRESH_TOKEN (' . $token['error']
                        . '). Genera uno nuevo con php artisan playboss:drive-token.'
                    );
                }
            }

            return new Drive($client);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $porUsuarioOIp = fn (Request $r) => (string) ($r->user()?->getAuthIdentifier() ?? $r->ip());
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $r->input('correo')) . '|' . $r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
        RateLimiter::for('recuperacion', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('apuestas', fn (Request $r) => Limit::perMinute(20)->by($porUsuarioOIp($r)));
        RateLimiter::for('pagos', fn (Request $r) => Limit::perMinute(10)->by($porUsuarioOIp($r)));
        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
    }

    private function credentialFilePath(mixed $path, string $environmentVariable): string
    {
        if (!is_string($path) || trim($path) === '') {
            throw new RuntimeException($environmentVariable . ' no está configurado.');
        }

        $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? $path
            : base_path($path);

        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw new RuntimeException(
                'No se puede leer el archivo de credenciales configurado en ' . $environmentVariable . '.'
            );
        }

        return $resolvedPath;
    }
}
