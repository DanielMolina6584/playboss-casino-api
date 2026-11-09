<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** La documentación Swagger debe cubrir exactamente las rutas /api (resources/openapi/openapi.php). */
class OpenApiDocsTest extends TestCase
{
    private function spec(): array
    {
        return require resource_path('openapi/openapi.php');
    }

    /** @return array<string, true> claves "METODO /ruta/{}" sin prefijo /api */
    private function rutasReales(): array
    {
        $rutas = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/') || in_array($uri, ['api/documentation', 'api/openapi.json'], true)) {
                continue;
            }
            $metodos = array_diff($route->methods(), ['HEAD', 'PATCH', 'OPTIONS']);
            foreach ($metodos as $m) {
                $rutas[strtolower($m) . ' ' . preg_replace('/\{[^}]+\}/', '{}', substr($uri, 3))] = true;
            }
        }

        return $rutas;
    }

    private function rutasDocumentadas(): array
    {
        $docs = [];
        foreach ($this->spec()['paths'] as $path => $ops) {
            foreach (array_keys($ops) as $m) {
                $docs[$m . ' ' . preg_replace('/\{[^}]+\}/', '{}', $path)] = true;
            }
        }

        return $docs;
    }

    public function test_toda_ruta_api_esta_documentada(): void
    {
        $faltan = array_keys(array_diff_key($this->rutasReales(), $this->rutasDocumentadas()));
        $this->assertSame([], $faltan, 'Rutas /api sin documentar en resources/openapi/openapi.php: ' . implode(', ', $faltan));
    }

    public function test_no_hay_endpoints_documentados_que_no_existen(): void
    {
        $sobran = array_keys(array_diff_key($this->rutasDocumentadas(), $this->rutasReales()));
        $this->assertSame([], $sobran, 'Endpoints documentados que no existen: ' . implode(', ', $sobran));
    }

    public function test_cada_operacion_tiene_tag_resumen_y_respuestas(): void
    {
        foreach ($this->spec()['paths'] as $path => $ops) {
            foreach ($ops as $m => $op) {
                $this->assertNotEmpty($op['tags'] ?? null, "$m $path sin tags");
                $this->assertNotEmpty($op['summary'] ?? null, "$m $path sin summary");
                $this->assertNotEmpty($op['responses'] ?? null, "$m $path sin responses");
            }
        }
    }

    public function test_sirve_ui_y_spec_cuando_esta_habilitada(): void
    {
        config(['playboss.docs_enabled' => true]);
        $this->get('/api/documentation')->assertOk()->assertSee('swagger-ui', false);
        $this->getJson('/api/openapi.json')->assertOk()->assertJsonPath('openapi', '3.0.3')->assertJsonPath('info.title', 'PlayBoss API');
    }

    public function test_docs_deshabilitadas_responden_404(): void
    {
        config(['playboss.docs_enabled' => false]);
        $this->get('/api/documentation')->assertNotFound();
        $this->getJson('/api/openapi.json')->assertNotFound();
    }
}
