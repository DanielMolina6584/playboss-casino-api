<?php

namespace App\Providers;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Firestore;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
            $client->addScope(Drive::DRIVE_FILE);

            return new Drive($client);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
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
