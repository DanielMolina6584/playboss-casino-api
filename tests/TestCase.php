<?php

namespace Tests;

use Google\Service\Drive;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas no deben depender de las credenciales reales de Drive del .env local.
        $this->app->singleton(Drive::class, fn () => Mockery::mock(Drive::class));
    }

    /**
     * El Route conserva su controlador entre peticiones de una misma prueba y los controladores legacy
     * (AuthController: `$this->request`, datos de respuesta acumulados) guardan estado de la petición anterior.
     * En producción hay una petición por proceso; aquí se descarta el controlador antes de cada llamada HTTP.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        foreach ($this->app['router']->getRoutes() as $ruta) {
            $ruta->flushController();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
