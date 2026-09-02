<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API pura: no hay ruta web de login a la cual redirigir a un guest no autenticado.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API pura: nunca redirigir a una ruta "login" web, responder siempre en JSON.
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json(['error' => 1, 'mensaje' => ['No autenticado.'], 'data' => []], 401);
        });
    })->create();
