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
}
