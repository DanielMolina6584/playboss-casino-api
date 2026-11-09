<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Console\Command;

class GenerarTokenDriveCommand extends Command
{
    protected $signature = 'playboss:drive-token';
    protected $description = 'Autoriza una cuenta de Google (OAuth) y muestra el GOOGLE_DRIVE_REFRESH_TOKEN';

    public function handle(): int
    {
        $path = config('services.google_drive.credentials');
        if (!is_string($path) || $path === '') {
            $this->components->error('Configura GOOGLE_DRIVE_CREDENTIALS con el JSON del cliente OAuth (tipo "App de escritorio").');
            return self::FAILURE;
        }

        $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        if (!is_readable($resolvedPath)) {
            $this->components->error('No se puede leer el archivo de GOOGLE_DRIVE_CREDENTIALS.');
            return self::FAILURE;
        }

        $client = new GoogleClient();
        $client->setAuthConfig($resolvedPath);
        $client->addScope(Drive::DRIVE);
        $client->setRedirectUri('http://localhost');
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $this->line('1. Abre esta URL en el navegador e inicia sesión con la cuenta dueña de la carpeta:');
        $this->newLine();
        $this->line($client->createAuthUrl());
        $this->newLine();
        $this->line('2. Acepta los permisos. El navegador terminará en una página de error en http://localhost: es normal.');
        $respuesta = (string) $this->ask('3. Pega aquí la URL completa de esa página (o solo el valor de "code")');

        $code = $this->extraerCodigo($respuesta);
        if ($code === '') {
            $this->components->error('No se encontró el código de autorización.');
            return self::FAILURE;
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error']) || empty($token['refresh_token'])) {
            $this->components->error('Google no entregó refresh token: ' . ($token['error_description'] ?? $token['error'] ?? 'sin detalle'));
            return self::FAILURE;
        }

        $this->components->info('Autorizado. Agrega esta línea a tu .env:');
        $this->line('GOOGLE_DRIVE_REFRESH_TOKEN=' . $token['refresh_token']);

        return self::SUCCESS;
    }

    /**
     * Obtiene el parámetro "code" de la URL de redirección, o devuelve el texto si ya es el código.
     */
    private function extraerCodigo(string $respuesta): string
    {
        $respuesta = trim($respuesta);
        $query = parse_url($respuesta, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            return isset($params['code']) && is_string($params['code']) ? $params['code'] : '';
        }

        return $respuesta;
    }
}
