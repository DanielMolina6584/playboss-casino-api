<?php

use App\Exceptions\ReglaNegocioException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        // API pura: no hay ruta web de login a la cual redirigir a un guest no autenticado.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());

        // Sobre de error uniforme: {error:1, mensaje:[...], data:{...}}
        $exceptions->render(function (Throwable $e, $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }
            $json = fn (array $mensajes, int $status, array $data = []) => response()->json(['error' => 1, 'mensaje' => $mensajes, 'data' => $data], $status);

            return match (true) {
                $e instanceof ValidationException => $json($e->validator->errors()->all(), 422, ['errores' => $e->errors()]),
                $e instanceof AuthenticationException => $json(['No autenticado.'], 401),
                $e instanceof AuthorizationException => $json(['No tienes permisos para esta acción.'], 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => $json(['Recurso no encontrado.'], 404),
                $e instanceof ReglaNegocioException => $json([$e->getMessage()], $e->status, $e->errors),
                $e instanceof TooManyRequestsHttpException => $json(['Demasiadas solicitudes. Intenta más tarde.'], 429, ['retry_after' => $e->getHeaders()['Retry-After'] ?? null]),
                $e instanceof RequestException, $e instanceof ConnectionException => $json(['La pasarela de pagos no está disponible. Intenta nuevamente.'], 502),
                $e instanceof HttpExceptionInterface => $json([$e->getMessage() ?: 'Error de la solicitud.'], $e->getStatusCode()),
                default => $json([config('app.debug') ? $e->getMessage() : 'Error interno del servidor.'], 500),
            };
        });
    })->create();
