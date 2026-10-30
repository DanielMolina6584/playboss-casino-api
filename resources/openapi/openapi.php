<?php

/**
 * Especificación OpenAPI 3.0.3 de la API PlayBoss (fuente única de la documentación Swagger).
 *
 * Es un archivo PHP que devuelve un array: así se reutilizan esquemas, respuestas de error y
 * ejemplos sin duplicar. Se sirve como JSON en GET /api/openapi.json y la UI en GET /api/documentation.
 * Al agregar/cambiar un endpoint, edita este archivo (ver README, "Documentación Swagger").
 * Las rutas se escriben SIN el prefijo /api (ese prefijo está en `servers`).
 */

// ---------------------------------------------------------------- helpers
$ref = fn (string $n): array => ['$ref' => '#/components/schemas/' . $n];
$s = fn (string $type, array $extra = []): array => ['type' => $type] + $extra;
$str = fn (string $ex = '', array $extra = []): array => ['type' => 'string'] + ($ex !== '' ? ['example' => $ex] : []) + $extra;
$int = fn (mixed $ex = null, array $extra = []): array => ['type' => 'integer'] + ($ex !== null ? ['example' => $ex] : []) + $extra;
$obj = fn (array $props, array $required = [], array $extra = []): array => ['type' => 'object', 'properties' => $props] + ($required ? ['required' => $required] : []) + $extra;
$arr = fn (array $items, array $extra = []): array => ['type' => 'array', 'items' => $items] + $extra;
$nullable = fn (array $schema): array => $schema + ['nullable' => true];

/** Sobre estándar {error, mensaje[], data} con el esquema de `data` indicado. */
$envelope = fn (array $data): array => [
    'type' => 'object',
    'required' => ['error', 'mensaje', 'data'],
    'properties' => [
        'error' => ['type' => 'integer', 'enum' => [0, 1], 'description' => '0 = correcto, 1 = error.', 'example' => 0],
        'mensaje' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Mensajes informativos o de error (en español).', 'example' => []],
        'data' => $data,
    ],
];
$json = fn (array $schema, mixed $example = null): array => ['application/json' => ['schema' => $schema] + ($example !== null ? ['example' => $example] : [])];
$ok = fn (string $desc, array $dataSchema, mixed $dataExample = null, array $mensaje = []): array => [
    'description' => $desc,
    'content' => $json($envelope($dataSchema), $dataExample !== null ? ['error' => 0, 'mensaje' => $mensaje, 'data' => $dataExample] : null),
];
$paged = fn (string $schemaName): array => [
    'type' => 'object',
    'required' => ['items', 'meta'],
    'properties' => ['items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/' . $schemaName]], 'meta' => ['$ref' => '#/components/schemas/PaginacionMeta']],
];
$meta = fn (int $total = 42): array => ['page' => 1, 'per_page' => 20, 'total' => $total, 'last_page' => (int) ceil($total / 20)];
$errR = fn (string $name): array => ['$ref' => '#/components/responses/' . $name];
$errEx = fn (string $desc, array $mensaje, mixed $data = [], int $codeCheck = 0): array => [
    'description' => $desc,
    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error'], 'example' => ['error' => 1, 'mensaje' => $mensaje, 'data' => $data]]],
];

$p = fn (string $name, string $in, array $schema, string $desc, bool $required = false, mixed $example = null): array => ['name' => $name, 'in' => $in, 'required' => $required || $in === 'path', 'description' => $desc, 'schema' => $schema] + ($example !== null ? ['example' => $example] : []);
$pageParams = [
    $p('page', 'query', $int(1, ['minimum' => 1, 'default' => 1]), 'Página (desde 1).'),
    $p('per_page', 'query', $int(20, ['minimum' => 1, 'maximum' => 100, 'default' => 20]), 'Elementos por página (1-100).'),
];
$idPath = fn (string $desc = 'Identificador numérico.'): array => $p('id', 'path', $int(12, ['minimum' => 1]), $desc);
$desde = $p('desde', 'query', ['type' => 'string', 'format' => 'date'], 'Fecha inicial inclusiva (YYYY-MM-DD).', false, '2026-11-01');
$hasta = $p('hasta', 'query', ['type' => 'string', 'format' => 'date'], 'Fecha final inclusiva (YYYY-MM-DD); debe ser >= `desde`.', false, '2026-11-30');
$q = fn (string $campo): array => $p('q', 'query', $str('', ['maxLength' => 80]), "Búsqueda parcial por $campo (LIKE).");
$filtroId = fn (string $n, string $d): array => $p($n, 'query', $int(null, ['minimum' => 1]), $d);

$sec = [['bearerAuth' => []]];
$bodyJson = fn (string $schema, mixed $example, bool $required = true, string $desc = ''): array => ['required' => $required] + ($desc ? ['description' => $desc] : []) + ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/' . $schema], 'example' => $example]]];

$usuarioEj = ['id_usuario' => 7, 'rol_codigo' => 'CLIENTE', 'estado_codigo' => 'ACT', 'fecha_registro' => '2026-10-30T14:05:11.000000Z', 'primer_nombre' => 'Laura', 'segundo_nombre' => null, 'primer_apellido' => 'Gómez', 'segundo_apellido' => 'Pérez', 'tipo_documento_codigo' => 'CC', 'numero_documento' => '1020304050', 'celular' => '3001234567', 'fecha_nacimiento' => '1995-04-12', 'correo' => 'laura@example.com'];
$mercadoEj = ['id' => '665f1c2e8f1b2a0012345678', 'code' => '1X2', 'name' => 'Resultado final', 'version' => 3, 'selections' => [['code' => '1', 'label' => 'Local', 'odd' => 2.1], ['code' => 'X', 'label' => 'Empate', 'odd' => 3.2], ['code' => '2', 'label' => 'Visitante', 'odd' => 3.5]]];
$apuestaEj = ['id' => 105, 'tipo' => 'COMBINADA', 'estado' => 'PEND', 'monto' => '10000.00', 'cuota_total' => '3.8850', 'pago_potencial' => '38850.00', 'creada_en' => '2026-10-30T15:01:00.000000Z', 'liquidada_en' => null, 'selecciones' => [
    ['partido_id' => 12, 'market_id' => '665f1c2e8f1b2a0012345678', 'selection_code' => '1', 'etiqueta' => 'Local', 'cuota' => '2.1000', 'resultado' => null],
    ['partido_id' => 14, 'market_id' => '665f1c2e8f1b2a0012345999', 'selection_code' => 'OVER', 'etiqueta' => 'Más de 2.5', 'cuota' => '1.8500', 'resultado' => null],
]];
$checkoutEj = ['provider' => 'wompi', 'public_key' => 'pub_test_AbCdEf123456', 'reference' => 'PB-31-K8QZ4M2T', 'amount_in_cents' => 5000000, 'currency' => 'COP', 'signature_integrity' => '4b3e1f0c7a9d2e6b8c5a1f3d0e9b7a6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a', 'redirect_url' => 'http://localhost:5173/pagos/resultado', 'checkout_url' => 'https://checkout.wompi.co/p/?public-key=pub_test_AbCdEf123456&currency=COP&amount-in-cents=5000000&reference=PB-31-K8QZ4M2T&signature%3Aintegrity=4b3e1f0c7a9d2e6b8c5a1f3d0e9b7a6c5d4e3f2a1b0c9d8e7f6a5b4c3d2e1f0a&redirect-url=http%3A%2F%2Flocalhost%3A5173%2Fpagos%2Fresultado'];
$transEj = ['referencia' => 'PB-31-K8QZ4M2T', 'estado' => 'PENDIENTE', 'monto' => '50000.00', 'moneda' => 'COP', 'tipo' => 'DEPOSITO', 'creada_en' => '2026-10-30T15:10:00.000000Z', 'confirmada_en' => null];

$e401 = $errR('NoAutenticado');
$e403 = $errR('Prohibido');
$e404 = $errR('NoEncontrado');
$e422 = $errR('Validacion');
$e429 = $errR('DemasiadasSolicitudes');
$e500 = $errR('ErrorInterno');
$e502 = $errR('PasarelaNoDisponible');
$e409 = $errR('Conflicto');

// Combinaciones de errores frecuentes por tipo de endpoint.
$authErr = ['401' => $e401, '403' => $e403, '429' => $e429, '500' => $e500];
$adminErr = ['401' => $e401, '403' => $errEx('Usuario inactivo o sin rol `ADMIN`.', ['No tienes permisos para esta acción.']), '429' => $e429, '500' => $e500];
$catalogoErr = ['422' => $e422, '429' => $e429, '500' => $e500];

$listado = fn (string $tag, string $summary, string $desc, string $schema, array $params, mixed $item): array => [
    'tags' => [$tag], 'operationId' => 'catalogo' . $schema . 'Listar', 'summary' => $summary,
    'description' => $desc . "\n\nRespuesta paginada: `data.items` y `data.meta {page, per_page, total, last_page}`. Límite: 120 solicitudes/min por IP.",
    'security' => [],
    'parameters' => array_merge($pageParams, $params),
    'responses' => ['200' => $ok('Página de resultados.', $paged($schema), ['items' => [$item], 'meta' => $meta()])] + $catalogoErr,
];

$limites = "\n\n**Límite:** ";

// ---------------------------------------------------------------- paths
$paths = [];

// ---- Auth
$paths['/auth/registro'] = ['post' => [
    'tags' => ['Auth'], 'operationId' => 'authRegistro', 'summary' => 'Registrar usuario',
    'description' => "Crea un usuario con rol `CLIENTE` y devuelve su token Bearer (Sanctum). Debe ser mayor de edad al apostar (se valida luego en `POST /apuestas`, no aquí). La combinación `tipo_documento_codigo` + `numero_documento` y el `correo` deben ser únicos.\n\nLos errores 422 traen los textos en `mensaje[]` y el detalle por campo en `data.errores`. `celular` (opcional) debe cumplir `^[0-9+\\s\\-]{7,20}$`, igual que en `PUT /perfil`.{$limites}5 solicitudes/min por IP (bucket `recuperacion`, compartido con `/auth/recuperar` y `/auth/restablecer`).",
    'security' => [],
    'requestBody' => $bodyJson('RegistroRequest', ['primer_nombre' => 'Laura', 'segundo_nombre' => null, 'primer_apellido' => 'Gómez', 'segundo_apellido' => 'Pérez', 'tipo_documento_codigo' => 'CC', 'numero_documento' => '1020304050', 'correo' => 'laura@example.com', 'celular' => '3001234567', 'fecha_nacimiento' => '1995-04-12', 'contrasena' => 'Clave-segura-1', 'contrasena_confirmation' => 'Clave-segura-1']),
    'responses' => [
        '201' => $ok('Usuario creado.', $obj(['usuario' => $ref('Usuario'), 'token' => $str('12|aBcD...', ['description' => 'Token Bearer en texto plano (se muestra una sola vez).'])], ['usuario', 'token']), ['usuario' => $usuarioEj, 'token' => '12|k3Yx9QpL0aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567']),
        '422' => $errEx('Validación fallida (campos faltantes, correo/documento duplicado, contraseña sin confirmar...).', ['El correo ya está registrado.', 'La contraseña debe tener al menos 8 caracteres.'], ['errores' => ['correo' => ['El correo ya está registrado.'], 'contrasena' => ['La contraseña debe tener al menos 8 caracteres.']]]),
        '429' => $e429, '500' => $errEx('No fue posible registrar el usuario.', ['No fue posible registrar el usuario.']),
    ],
]];
$paths['/auth/login'] = ['post' => [
    'tags' => ['Auth'], 'operationId' => 'authLogin', 'summary' => 'Iniciar sesión',
    'description' => "Valida credenciales y devuelve un token Bearer. Cada intento (exitoso o no) se audita. Cuentas inactivas reciben el mismo 401 genérico.{$limites}5/min por correo+IP y 30/min por IP (devuelve 429 con `data.retry_after`). Los errores 422 traen `mensaje[]` y `data.errores` por campo.",
    'security' => [],
    'requestBody' => $bodyJson('LoginRequest', ['correo' => 'laura@example.com', 'contrasena' => 'Clave-segura-1']),
    'responses' => [
        '200' => $ok('Sesión iniciada.', $obj(['usuario' => $ref('Usuario'), 'token' => $str('13|xyz...')], ['usuario', 'token']), ['usuario' => $usuarioEj, 'token' => '13|Q1w2E3r4T5y6U7i8O9p0AaSsDdFfGgHhJjKkLlZzXx']),
        '401' => $errEx('Credenciales incorrectas o cuenta inactiva.', ['Correo o contraseña incorrectos.']),
        '422' => $errEx('Faltan campos o el correo no es válido.', ['El campo correo es obligatorio.'], ['errores' => ['correo' => ['El campo correo es obligatorio.']]]),
        '429' => $e429, '500' => $e500,
    ],
]];
$paths['/auth/logout'] = ['post' => [
    'tags' => ['Auth'], 'operationId' => 'authLogout', 'summary' => 'Cerrar sesión',
    'description' => 'Revoca el token Bearer con el que se hizo la petición (las demás sesiones siguen activas).', 'security' => $sec,
    'responses' => ['200' => $ok('Token revocado.', ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0], [])] + $authErr,
]];
$paths['/auth/me'] = ['get' => [
    'tags' => ['Auth'], 'operationId' => 'authMe', 'summary' => 'Usuario autenticado',
    'description' => 'Devuelve el usuario del token (datos de cuenta + perfil + correo).', 'security' => $sec,
    'responses' => ['200' => $ok('Usuario actual.', $obj(['usuario' => $ref('Usuario')], ['usuario']), ['usuario' => $usuarioEj])] + $authErr,
]];
$paths['/auth/recuperar'] = ['post' => [
    'tags' => ['Auth'], 'operationId' => 'authRecuperar', 'summary' => 'Solicitar código de recuperación',
    'description' => "Si el correo existe y la cuenta está activa, envía un código de 6 dígitos (vigente 15 min, un solo uso; invalida los anteriores). **Siempre responde 200 con el mismo mensaje** para no revelar si el correo existe. En local `MAIL_MAILER=log` escribe el código en `storage/logs/laravel.log`.{$limites}5/min por IP (bucket `recuperacion`).",
    'security' => [],
    'requestBody' => $bodyJson('RecuperarRequest', ['correo' => 'laura@example.com']),
    'responses' => ['200' => $ok('Solicitud aceptada (exista o no el correo).', ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0], [], ['Si el correo existe, recibirás un código de recuperación.']), '422' => $e422, '429' => $e429, '500' => $e500],
]];
$paths['/auth/restablecer'] = ['post' => [
    'tags' => ['Auth'], 'operationId' => 'authRestablecer', 'summary' => 'Restablecer contraseña con código',
    'description' => "Valida el código de 6 dígitos y cambia la contraseña. Revoca **todos** los tokens del usuario; hay que iniciar sesión de nuevo.{$limites}5/min por IP (bucket `recuperacion`).",
    'security' => [],
    'requestBody' => $bodyJson('RestablecerRequest', ['correo' => 'laura@example.com', 'codigo' => '482913', 'contrasena' => 'Nueva-clave-2', 'contrasena_confirmation' => 'Nueva-clave-2']),
    'responses' => [
        '200' => $ok('Contraseña cambiada.', ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0], [], ['Contraseña restablecida. Inicia sesión.']),
        '422' => $errEx('Validación fallida, o código inválido/vencido/usado (mensaje genérico).', ['Código inválido o vencido.'], ['errores' => []]),
        '429' => $e429, '500' => $e500,
    ],
]];

// ---- Catálogo
$paths['/deportes'] = ['get' => [
    'tags' => ['Catálogo'], 'operationId' => 'catalogoDeportes', 'summary' => 'Listar deportes (servicios) activos',
    'description' => "Lista completa (sin paginar) de deportes activos ordenados por nombre. Público.{$limites}120/min por IP.", 'security' => [],
    'responses' => ['200' => $ok('Deportes activos.', $arr($ref('Servicio')), [['codigo' => 'FUTBOL', 'nombre' => 'Fútbol', 'activo' => true]]), '429' => $e429, '500' => $e500],
]];
$paths['/ligas'] = ['get' => $listado('Catálogo', 'Listar ligas activas', 'Ordenadas por nombre; incluye el país.', 'Liga', [
    $p('servicio_codigo', 'query', $str('FUTBOL', ['maxLength' => 10]), 'Código del deporte.'),
    $p('pais_codigo', 'query', $str('CO', ['minLength' => 2, 'maxLength' => 2]), 'Código ISO-3166 alfa-2 del país.'),
    $q('nombre'),
], ['id_liga' => 1, 'servicio_codigo' => 'FUTBOL', 'pais_codigo' => 'CO', 'nombre' => 'Liga BetPlay', 'logo_drive_id' => null, 'activa' => true, 'pais' => ['codigo_iso' => 'CO', 'nombre' => 'Colombia']])];
$paths['/temporadas'] = ['get' => $listado('Catálogo', 'Listar temporadas', 'Ordenadas por fecha de inicio descendente.', 'Temporada', [$filtroId('liga_id', 'Filtra por liga.')], ['id_temporada' => 3, 'liga_id' => 1, 'nombre' => '2026', 'fecha_inicio' => '2026-01-20', 'fecha_fin' => '2026-12-15'])];
$paths['/equipos'] = ['get' => $listado('Catálogo', 'Listar equipos', 'Ordenados por nombre; incluye ciudad y estadio.', 'Equipo', [$q('nombre'), $filtroId('ciudad_id', 'Filtra por ciudad.'), $filtroId('temporada_id', 'Solo equipos inscritos en la temporada.')], ['id_equipo' => 5, 'nombre' => 'Atlético Nacional', 'escudo_drive_id' => null, 'ciudad_id' => 2, 'estadio_id' => 4, 'fecha_fundacion' => '1947-03-07', 'ciudad' => ['id_ciudad' => 2, 'nombre' => 'Medellín', 'pais_codigo' => 'CO'], 'estadio' => ['id_estadio' => 4, 'nombre' => 'Atanasio Girardot', 'ciudad_id' => 2, 'capacidad' => 45000, 'direccion' => null]])];
$paths['/estadios'] = ['get' => $listado('Catálogo', 'Listar estadios', 'Ordenados por nombre; incluye la ciudad.', 'Estadio', [$q('nombre'), $filtroId('ciudad_id', 'Filtra por ciudad.')], ['id_estadio' => 4, 'nombre' => 'Atanasio Girardot', 'ciudad_id' => 2, 'capacidad' => 45000, 'direccion' => 'Cra. 74 #48', 'ciudad' => ['id_ciudad' => 2, 'nombre' => 'Medellín', 'pais_codigo' => 'CO']])];
$paths['/jugadores'] = ['get' => $listado('Catálogo', 'Listar jugadores', 'Ordenados por apellido y nombre; incluye equipo, posición y país. `q` busca en primer nombre o primer apellido.', 'Jugador', [$q('nombre/apellido'), $filtroId('equipo_id', 'Equipo actual.'), $p('pais_codigo', 'query', $str('CO', ['minLength' => 2, 'maxLength' => 2]), 'Nacionalidad (ISO alfa-2).')], ['id_jugador' => 88, 'primer_nombre' => 'Juan', 'segundo_nombre' => null, 'primer_apellido' => 'Pérez', 'segundo_apellido' => null, 'fecha_nacimiento' => '1998-06-01', 'pais_codigo' => 'CO', 'posicion_codigo' => 'DEL', 'equipo_actual_id' => 5, 'dorsal' => 9, 'foto_drive_id' => null])];
$paths['/jornadas'] = ['get' => $listado('Catálogo', 'Listar jornadas', 'Ordenadas por temporada y número.', 'Jornada', [$filtroId('temporada_id', 'Filtra por temporada.')], ['id_jornada' => 21, 'temporada_id' => 3, 'numero' => 10, 'fecha_inicio' => '2026-11-07', 'fecha_fin' => '2026-11-09'])];
$paths['/partidos'] = ['get' => $listado('Catálogo', 'Listar partidos', 'Incluye local, visitante, estadio, estado y jornada→temporada→liga. Orden por `fecha_hora` (`orden`, por defecto `asc`).', 'Partido', [
    $filtroId('liga_id', 'Filtra por liga.'), $filtroId('temporada_id', 'Filtra por temporada.'), $filtroId('jornada_id', 'Filtra por jornada.'),
    $filtroId('equipo_id', 'Partidos donde el equipo es local o visitante.'),
    $p('estado', 'query', $str('PROG', ['maxLength' => 4, 'enum' => ['PROG', 'ENJ', 'FIN', 'SUSP']]), 'Código de estado (se convierte a mayúsculas). Valores fuera del enum responden 422.'),
    $desde, $hasta,
    $p('orden', 'query', $str('asc', ['enum' => ['asc', 'desc'], 'default' => 'asc']), 'Orden por fecha y hora.'),
], ['id_partido' => 12, 'jornada_id' => 21, 'equipo_local_id' => 5, 'equipo_visitante_id' => 6, 'estadio_id' => 4, 'fecha_hora' => '2026-11-08T20:00:00.000000Z', 'estado_codigo' => 'PROG', 'goles_local' => null, 'goles_visitante' => null, 'local' => ['id_equipo' => 5, 'nombre' => 'Atlético Nacional'], 'visitante' => ['id_equipo' => 6, 'nombre' => 'Millonarios'], 'estado' => ['codigo' => 'PROG', 'nombre' => 'Programado']])];
$paths['/partidos/{id}'] = ['get' => [
    'tags' => ['Catálogo'], 'operationId' => 'catalogoPartido', 'summary' => 'Detalle de un partido',
    'description' => "Incluye local, visitante, estadio, estado y jornada→temporada→liga.{$limites}120/min por IP.", 'security' => [],
    'parameters' => [$idPath('Id del partido (`id_partido`).')],
    'responses' => ['200' => $ok('Partido.', $ref('Partido'), ['id_partido' => 12, 'jornada_id' => 21, 'equipo_local_id' => 5, 'equipo_visitante_id' => 6, 'estadio_id' => 4, 'fecha_hora' => '2026-11-08T20:00:00.000000Z', 'estado_codigo' => 'PROG', 'goles_local' => null, 'goles_visitante' => null]), '404' => $e404, '429' => $e429, '500' => $e500],
]];
$paths['/partidos/{id}/mercados'] = ['get' => [
    'tags' => ['Catálogo'], 'operationId' => 'catalogoMercados', 'summary' => 'Mercados y cuotas activas de un partido',
    'description' => "Devuelve solo mercados `ACTIVE` (MongoDB) con sus opciones y cuotas. El `id` del mercado y el `code` de la opción son los que se envían en `POST /apuestas` (`market_id`, `selection_code`). Sin mercados: lista vacía.{$limites}120/min por IP.", 'security' => [],
    'parameters' => [$idPath('Id del partido.')],
    'responses' => ['200' => $ok('Mercados activos.', $arr($ref('MercadoPublico')), [$mercadoEj]), '404' => $e404, '429' => $e429, '500' => $e500],
]];

// ---- Perfil
$paths['/perfil'] = [
    'get' => ['tags' => ['Perfil'], 'operationId' => 'perfilShow', 'summary' => 'Ver mi perfil', 'description' => 'Datos de cuenta, perfil y correo del usuario autenticado.', 'security' => $sec,
        'responses' => ['200' => $ok('Perfil.', $obj(['usuario' => $ref('Usuario')], ['usuario']), ['usuario' => $usuarioEj])] + $authErr],
    'put' => ['tags' => ['Perfil'], 'operationId' => 'perfilUpdate', 'summary' => 'Actualizar mi perfil (parcial)',
        'description' => "Actualización **parcial**: solo se modifican los campos enviados. No se puede cambiar documento, correo ni fecha de nacimiento. `celular` debe cumplir `^[0-9+\\s\\-]{7,20}\$`.",
        'security' => $sec,
        'requestBody' => $bodyJson('ActualizarPerfilRequest', ['primer_nombre' => 'Laura', 'segundo_apellido' => 'Pérez', 'celular' => '+57 300 123 4567']),
        'responses' => ['200' => $ok('Perfil actualizado.', $obj(['usuario' => $ref('Usuario')], ['usuario']), ['usuario' => $usuarioEj]), '422' => $e422] + $authErr],
];
$paths['/perfil/contrasena'] = ['put' => [
    'tags' => ['Perfil'], 'operationId' => 'perfilContrasena', 'summary' => 'Cambiar mi contraseña',
    'description' => 'Exige la contraseña actual. La nueva debe ser distinta, de 8 a 100 caracteres y confirmada. Revoca los **demás** tokens y conserva el actual.', 'security' => $sec,
    'requestBody' => $bodyJson('CambiarContrasenaRequest', ['contrasena_actual' => 'Clave-segura-1', 'contrasena' => 'Nueva-clave-2', 'contrasena_confirmation' => 'Nueva-clave-2']),
    'responses' => [
        '200' => $ok('Contraseña actualizada.', ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0], [], ['Contraseña actualizada.']),
        '422' => $errEx('Validación fallida o contraseña actual incorrecta (en ese caso `data` va vacío).', ['La contraseña actual no es correcta.']),
    ] + $authErr,
]];

// ---- Cuenta
$paths['/cuenta'] = ['get' => [
    'tags' => ['Cuenta'], 'operationId' => 'cuentaShow', 'summary' => 'Saldo de mi cuenta',
    'description' => 'Abre la cuenta (la crea en saldo 0 si no existe) y devuelve saldos. `saldo_retenido` es el dinero bloqueado por apuestas pendientes. Montos como string decimal con 2 decimales.', 'security' => $sec,
    'responses' => ['200' => $ok('Saldos.', $ref('Cuenta'), ['saldo_disponible' => '120000.00', 'saldo_retenido' => '10000.00', 'moneda' => 'COP'])] + $authErr,
]];
$paths['/cuenta/movimientos'] = ['get' => [
    'tags' => ['Cuenta'], 'operationId' => 'cuentaMovimientos', 'summary' => 'Historial de movimientos',
    'description' => 'Movimientos contables del usuario, más recientes primero (por `creado_en` y luego por id). Paginado.', 'security' => $sec,
    'parameters' => array_merge($pageParams, [
        $p('tipo', 'query', $str('DEPOSITO', ['maxLength' => 30]), 'Tipo (se convierte a mayúsculas). Valores conocidos: `DEPOSITO`, `APUESTA_RESERVADA`, `APUESTA_ANULADA`, además de los tipos de cierre de liquidación (premio/pérdida) y `RETIRO`.'),
        $desde, $hasta,
    ]),
    'responses' => ['200' => $ok('Página de movimientos.', $paged('Movimiento'), ['items' => [['id' => 310, 'tipo' => 'DEPOSITO', 'monto' => '50000.00', 'saldo_disponible' => '120000.00', 'saldo_retenido' => '10000.00', 'apuesta_id' => null, 'descripcion' => 'DEPOSITO', 'fecha' => '2026-10-30T15:12:00.000000Z']], 'meta' => $meta()]), '422' => $e422] + $authErr,
]];

// ---- Apuestas
$idemHeader = $p('Idempotency-Key', 'header', $str('3f6c2b9e-1d4a-4b7e-9c11-0a2b3c4d5e6f', ['minLength' => 8, 'maxLength' => 100]), 'Clave de idempotencia (alternativa a `idempotency_key` del cuerpo; si ambos vienen, **gana el cuerpo**). Repetir la misma clave con la misma petición devuelve la apuesta/depósito original (200 en lugar de 201) sin duplicar. **Si no se envía ninguna, el servidor deriva una clave determinista** (`auto-` + hash de usuario + ruta + payload normalizado + ventana de 30 s): un doble envío idéntico dentro de esa ventana devuelve el recurso original en vez de duplicarlo. Fuera de la ventana (o con payload distinto) se crea uno nuevo; para reintentos seguros envía siempre tu propia clave.');
$paths['/apuestas'] = [
    'get' => ['tags' => ['Apuestas'], 'operationId' => 'apuestasIndex', 'summary' => 'Listar mis apuestas',
        'description' => 'Apuestas del usuario autenticado, más recientes primero. Paginado.', 'security' => $sec,
        'parameters' => array_merge($pageParams, [
            $p('estado', 'query', $str('PEND', ['maxLength' => 4, 'enum' => ['PEND', 'GAN', 'PER', 'ANU', 'CASH']]), 'Estado de la apuesta (se convierte a mayúsculas). Valores fuera del enum responden 422.'), $desde, $hasta,
        ]),
        'responses' => ['200' => $ok('Página de apuestas.', $paged('Apuesta'), ['items' => [$apuestaEj], 'meta' => $meta()]), '422' => $e422] + $authErr],
    'post' => ['tags' => ['Apuestas'], 'operationId' => 'apuestasStore', 'summary' => 'Crear apuesta (simple o combinada)',
        'description' => "Crea una apuesta y retiene el monto del saldo disponible. El servidor verifica: usuario activo y mayor de edad (`BET_MIN_AGE` y `min_age` de la regla), partido existente en `PROG` y con fecha futura, mercado `ACTIVE`, opción existente, cuota igual a `odd` si se envía (si cambió: **409** con `data.selecciones.odd_actual`), regla vigente (monto mín/máx y pago máx), sin selecciones duplicadas, **un solo pick por partido**, máximo `BET_MAX_SELECTIONS` (10) selecciones, saldo suficiente y límites diarios. Pago potencial = monto x producto de cuotas (incluye el monto apostado), calculado en servidor con enteros.\n\n`tipo` es opcional: `SIMPLE` exige exactamente 1 selección y `COMBINADA` al menos 2; la respuesta lo deduce siempre del número de selecciones.\n\n**Idempotencia:** envía `Idempotency-Key` o `idempotency_key`. Respuesta 201 si se creó; 200 si la clave ya existía (se devuelve la apuesta original).{$limites}20/min por usuario.",
        'security' => $sec, 'parameters' => [$idemHeader],
        'requestBody' => $bodyJson('CrearApuestaRequest', ['monto' => '10000', 'tipo' => 'COMBINADA', 'idempotency_key' => '3f6c2b9e-1d4a-4b7e-9c11-0a2b3c4d5e6f', 'selecciones' => [['match_id' => 12, 'market_id' => '665f1c2e8f1b2a0012345678', 'selection_code' => '1', 'odd' => 2.1], ['match_id' => 14, 'market_id' => '665f1c2e8f1b2a0012345999', 'selection_code' => 'OVER', 'odd' => 1.85]]]),
        'responses' => [
            '201' => $ok('Apuesta creada.', $ref('Apuesta'), $apuestaEj),
            '200' => $ok('Reintento idempotente: se devuelve la apuesta ya creada con esa clave.', $ref('Apuesta'), $apuestaEj),
            '409' => $errEx('La cuota cambió, o la clave de idempotencia pertenece a otra apuesta.', ['La cuota cambió; actualiza tu boleta.'], ['selecciones' => ['market_id' => '665f1c2e8f1b2a0012345678', 'selection_code' => '1', 'odd_actual' => 2.05]]),
            '422' => $errEx('Validación o regla de negocio (monto fuera de rango, saldo insuficiente, partido ya iniciado, menor de edad, selecciones repetidas, límite diario...).', ['El monto debe estar entre 1000.00 y 1000000.00.'], ['errores' => ['monto' => ['El monto debe estar entre 1000.00 y 1000000.00.']]]),
        ] + $authErr],
];
$paths['/apuestas/{id}'] = ['get' => [
    'tags' => ['Apuestas'], 'operationId' => 'apuestasShow', 'summary' => 'Detalle de mi apuesta',
    'description' => 'Solo apuestas propias; una ajena responde 404 (no revela su existencia).', 'security' => $sec,
    'parameters' => [$idPath('Id de la apuesta.')],
    'responses' => ['200' => $ok('Apuesta.', $ref('Apuesta'), $apuestaEj), '404' => $errEx('No existe o no es del usuario.', ['Apuesta no encontrada.'])] + $authErr,
]];
$paths['/apuestas/{id}/cancelar'] = ['post' => [
    'tags' => ['Apuestas'], 'operationId' => 'apuestasCancelar', 'summary' => 'Cancelar mi apuesta',
    'description' => "Solo apuestas `PEND` cuyos partidos aún no han comenzado. Libera el monto retenido (movimiento `APUESTA_ANULADA`) y deja la apuesta en `ANU`.{$limites}20/min por usuario.", 'security' => $sec,
    'parameters' => [$idPath('Id de la apuesta.')],
    'responses' => ['200' => $ok('Apuesta cancelada.', $ref('Apuesta'), array_merge($apuestaEj, ['estado' => 'ANU'])), '404' => $errEx('No existe o no es del usuario.', ['Apuesta no encontrada.']), '409' => $errEx('No cancelable.', ['Solo se pueden cancelar apuestas pendientes.', 'Un partido de la apuesta ya comenzó; no se puede cancelar.'])] + $authErr,
]];

// ---- Pagos
$paths['/pagos/depositos'] = ['post' => [
    'tags' => ['Pagos'], 'operationId' => 'pagosDeposito', 'summary' => 'Iniciar depósito (Wompi)',
    'description' => "Crea una transacción `PENDIENTE` con referencia única `PB-<id>-<rand>` y devuelve `data.checkout` con lo necesario para el Widget/Web Checkout de Wompi (llave pública, referencia, monto en centavos, moneda y firma de integridad `SHA256(referencia + centavos + moneda + secreto_integridad)`) más `checkout_url` listo para redirigir. El saldo **no** se acredita aquí: lo hace el webhook (o la sincronización de `GET /pagos/{referencia}`) cuando Wompi aprueba.\n\n`monto` es un string decimal (hasta 10 enteros y 2 decimales); un número JSON también se acepta y se convierte. Mínimo configurable (por defecto 1.00). Soporta `Idempotency-Key`/`idempotency_key` (reintentar devuelve la misma transacción con 201). Con el driver `firebase_function` `checkout` puede ser `null`.{$limites}10/min por usuario.",
    'security' => $sec, 'parameters' => [$idemHeader],
    'requestBody' => $bodyJson('DepositoRequest', ['monto' => '50000', 'idempotency_key' => '9b1c7e44-52f1-4d0a-8a7b-6c5d4e3f2a10']),
    'responses' => [
        '201' => $ok('Transacción creada con datos de checkout.', $ref('DepositoRespuesta'), $transEj + ['checkout' => $checkoutEj, 'checkout_url' => $checkoutEj['checkout_url']]),
        '409' => $errEx('La clave de idempotencia pertenece a otra transacción.', ['La clave de idempotencia ya pertenece a otra transacción.']),
        '422' => $errEx('Monto inválido o por debajo del mínimo.', ['El depósito está por debajo del mínimo permitido.'], ['errores' => ['monto' => ['El depósito está por debajo del mínimo permitido.']]]),
        '502' => $e502,
    ] + $authErr,
]];
$paths['/pagos/{referencia}'] = ['get' => [
    'tags' => ['Pagos'], 'operationId' => 'pagosEstado', 'summary' => 'Estado de un depósito',
    'description' => "Consulta una transacción propia por su referencia. Si sigue `PENDIENTE` y la pasarela es consultable (Wompi), el backend la sincroniza con la API de Wompi (verifica referencia, monto y moneda) antes de responder: sirve cuando el webhook no llega (p. ej. local sin túnel). Estados: `PENDIENTE`, `APROBADA`, `RECHAZADA`, `CANCELADA`, `FALLIDA` (según la pasarela).{$limites}10/min por usuario.",
    'security' => $sec,
    'parameters' => [$p('referencia', 'path', $str('PB-31-K8QZ4M2T'), 'Referencia externa devuelta al crear el depósito.')],
    'responses' => ['200' => $ok('Estado actual.', $ref('Transaccion'), array_merge($transEj, ['estado' => 'APROBADA', 'confirmada_en' => '2026-10-30T15:12:30.000000Z'])), '404' => $errEx('No existe o no es del usuario.', ['Transacción no encontrada.'])] + $authErr,
]];
$webhookEj = ['event' => 'transaction.updated', 'data' => ['transaction' => ['id' => '1234-1700000000-56789', 'status' => 'APPROVED', 'reference' => 'PB-31-K8QZ4M2T', 'amount_in_cents' => 5000000, 'currency' => 'COP', 'payment_method_type' => 'CARD']], 'environment' => 'test', 'signature' => ['properties' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'checksum' => '3476DDA50F64CD7CBD160689640506FEBEA93239BC524FC0469B2C68A3CC8D1A'], 'timestamp' => 1700000001, 'sent_at' => '2026-10-30T15:12:29.000Z'];
$paths['/pagos/wompi/webhook'] = ['post' => [
    'tags' => ['Pagos'], 'operationId' => 'pagosWebhookWompi', 'summary' => 'Webhook de eventos de Wompi',
    'description' => "**Público** (sin Bearer): lo invoca Wompi. La autenticidad se valida así:\n\n1. Si llega el header `X-Event-Checksum` y el cuerpo trae `signature.checksum`, deben coincidir (si no: 401 `Firma inválida.`).\n2. El checksum del evento: `SHA256(valores de signature.properties tomados de data + timestamp + WOMPI_EVENTS_SECRET)`; p. ej. `transaction.id + transaction.status + transaction.amount_in_cents + timestamp + secreto`. El `timestamp` es el de la **raíz** del evento (formato real de Wompi); por compatibilidad se acepta `signature.timestamp` si no hay uno en la raíz.\n3. Además se **consulta la transacción en la API de Wompi** y se compara referencia, monto y moneda con la local; no se confía en el cuerpo.\n\nSolo se procesa `event = transaction.updated`; cualquier otro evento responde 200 `Evento ignorado.`. Es idempotente (clave `wompi:<id>:<estado>`): si el estado es `APPROVED` se acredita el saldo y se crea el movimiento en una única transacción PostgreSQL una sola vez.{$limites}120/min por IP.",
    'security' => [],
    'parameters' => [$p('X-Event-Checksum', 'header', $str('3476DDA50F64CD7CBD160689640506FEBEA93239BC524FC0469B2C68A3CC8D1A'), 'Checksum del evento enviado por Wompi (opcional).')],
    'requestBody' => $bodyJson('WompiEvento', $webhookEj, true, 'Evento `transaction.updated` de Wompi.'),
    'responses' => [
        '200' => $ok('Evento procesado o ignorado.', ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0], [], ['Evento procesado.']),
        '401' => $errEx('Firma/checksum inválido o evento no verificado.', ['Firma inválida.']),
        '409' => $errEx('La transacción ya fue cerrada con otro estado.', ['La transacción ya fue cerrada con otro estado.']),
        '422' => $errEx('El evento no coincide con monto/moneda/tipo de la transacción local.', ['El evento no coincide con el monto, moneda o tipo de la transacción.']),
        '429' => $e429, '500' => $e500, '502' => $e502,
    ],
]];

// ---- Admin: CRUD genérico
$adminTag = 'Admin';
$crud = function (string $path, string $schema, string $createSchema, string $updateSchema, string $nombre, string $plural, mixed $createEj, mixed $item, array $filtros = [], string $idDesc = 'Id numérico del registro.', string $deleteNote = '', string $idType = 'int') use ($p, $int, $str, $ok, $paged, $ref, $meta, $pageParams, $sec, $bodyJson, $adminErr, $e404, $e422, $e409, $errEx, &$paths, $adminTag): void {
    $idParam = $p('id', 'path', $idType === 'int' ? $int(1, ['minimum' => 1]) : $str('665f1c2e8f1b2a0012345678'), $idDesc);
    $paths["/admin/$path"] = [
        'get' => ['tags' => [$adminTag], 'operationId' => "admin_{$path}_index", 'summary' => "Listar $plural",
            'description' => "Solo rol `ADMIN`. Paginado (`page`, `per_page` máx. 100), más recientes primero (por clave descendente).", 'security' => $sec,
            'parameters' => array_merge($pageParams, $filtros),
            'responses' => ['200' => $ok("Página de $plural.", $paged($schema), ['items' => [$item], 'meta' => $meta(1)]), '422' => $e422] + $adminErr],
        'post' => ['tags' => [$adminTag], 'operationId' => "admin_{$path}_store", 'summary' => "Crear $nombre",
            'description' => 'Solo rol `ADMIN`. Queda registrado en la auditoría (MongoDB).', 'security' => $sec,
            'requestBody' => $bodyJson($createSchema, $createEj),
            'responses' => ['201' => $ok("$nombre creado.", $ref($schema), $item), '422' => $e422] + $adminErr],
    ];
    $paths["/admin/$path/{id}"] = [
        'get' => ['tags' => [$adminTag], 'operationId' => "admin_{$path}_show", 'summary' => "Ver $nombre", 'description' => 'Solo rol `ADMIN`.', 'security' => $sec, 'parameters' => [$idParam],
            'responses' => ['200' => $ok($nombre, $ref($schema), $item), '404' => $e404] + $adminErr],
        'put' => ['tags' => [$adminTag], 'operationId' => "admin_{$path}_update", 'summary' => "Actualizar $nombre (parcial)",
            'description' => 'Solo rol `ADMIN`. Los campos obligatorios al crear pasan a opcionales (`sometimes`): solo se actualiza lo enviado. También responde a `PATCH` con el mismo comportamiento.', 'security' => $sec, 'parameters' => [$idParam],
            'requestBody' => $bodyJson($updateSchema, $createEj),
            'responses' => ['200' => $ok("$nombre actualizado.", $ref($schema), $item), '404' => $e404, '422' => $e422] + $adminErr],
        'delete' => ['tags' => [$adminTag], 'operationId' => "admin_{$path}_destroy", 'summary' => "Eliminar $nombre",
            'description' => 'Solo rol `ADMIN`. ' . ($deleteNote ?: 'Si el registro está referenciado por otros (FK) responde 422 `El registro está en uso y no puede eliminarse.`'), 'security' => $sec, 'parameters' => [$idParam],
            'responses' => ['200' => $ok('Eliminado.', ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0], [], ['Eliminado.']), '404' => $e404, '422' => $errEx('El registro está en uso.', ['El registro está en uso y no puede eliminarse.'], ['errores' => ['id' => ['El registro está en uso y no puede eliminarse.']]])] + $adminErr],
    ];
};

$crud('equipos', 'Equipo', 'EquipoAdminRequest', 'EquipoAdminRequest', 'equipo', 'equipos',
    ['nombre' => 'Atlético Nacional', 'ciudad_id' => 2, 'estadio_id' => 4, 'escudo_drive_id' => null, 'fecha_fundacion' => '1947-03-07'],
    ['id_equipo' => 5, 'nombre' => 'Atlético Nacional', 'escudo_drive_id' => null, 'ciudad_id' => 2, 'estadio_id' => 4, 'fecha_fundacion' => '1947-03-07', 'ciudad' => ['id_ciudad' => 2, 'nombre' => 'Medellín', 'pais_codigo' => 'CO'], 'estadio' => ['id_estadio' => 4, 'nombre' => 'Atanasio Girardot']]);
$crud('ligas', 'Liga', 'LigaAdminRequest', 'LigaAdminRequest', 'liga', 'ligas',
    ['nombre' => 'Liga BetPlay', 'servicio_codigo' => 'FUTBOL', 'pais_codigo' => 'CO', 'logo_drive_id' => null, 'activa' => true],
    ['id_liga' => 1, 'servicio_codigo' => 'FUTBOL', 'pais_codigo' => 'CO', 'nombre' => 'Liga BetPlay', 'logo_drive_id' => null, 'activa' => true, 'pais' => ['codigo_iso' => 'CO', 'nombre' => 'Colombia'], 'servicio' => ['codigo' => 'FUTBOL', 'nombre' => 'Fútbol', 'activo' => true]]);
$crud('partidos', 'Partido', 'PartidoAdminRequest', 'PartidoAdminRequest', 'partido', 'partidos',
    ['jornada_id' => 21, 'equipo_local_id' => 5, 'equipo_visitante_id' => 6, 'estadio_id' => 4, 'fecha_hora' => '2026-11-08 20:00:00', 'estado_codigo' => 'PROG'],
    ['id_partido' => 12, 'jornada_id' => 21, 'equipo_local_id' => 5, 'equipo_visitante_id' => 6, 'estadio_id' => 4, 'fecha_hora' => '2026-11-08T20:00:00.000000Z', 'estado_codigo' => 'PROG', 'goles_local' => null, 'goles_visitante' => null, 'estado' => ['codigo' => 'PROG', 'nombre' => 'Programado']]);
$crud('reglas', 'ReglaApuesta', 'ReglaApuestaAdminRequest', 'ReglaApuestaAdminRequest', 'regla de apuesta', 'reglas de apuesta',
    ['code' => 'R_1X2_STD', 'market_code' => '1X2', 'min_stake' => 1000, 'max_stake' => 1000000, 'max_payout' => 50000000, 'min_age' => 18, 'active' => true, 'effective_from' => '2026-10-30 00:00:00'],
    ['_id' => '665f1c2e8f1b2a0012340001', 'code' => 'R_1X2_STD', 'market_code' => '1X2', 'min_stake' => '1000.00', 'max_stake' => '1000000.00', 'max_payout' => '50000000.00', 'min_age' => 18, 'active' => true, 'effective_from' => '2026-10-30T00:00:00.000000Z', 'version' => 1],
    [], 'Id (ObjectId de MongoDB, 24 caracteres hex).', 'Solo rol `ADMIN`. Borra el documento de MongoDB.', 'str');
// Reglas (Mongo) usan ObjectId: el id de ruta no es entero.
$paths['/admin/mercados'] = [
    'get' => ['tags' => [$adminTag], 'operationId' => 'admin_mercados_index', 'summary' => 'Listar mercados',
        'description' => 'Solo rol `ADMIN`. Mercados y cuotas de MongoDB (todos los estados), ordenados por `match_id`. Paginado.', 'security' => $sec,
        'parameters' => array_merge($pageParams, [$filtroId('match_id', 'Filtra por partido.'), $p('status', 'query', $str('ACTIVE', ['enum' => ['ACTIVE', 'SUSPENDED', 'SETTLED']]), 'Estado del mercado.')]),
        'responses' => ['200' => $ok('Página de mercados.', $paged('MercadoAdmin'), ['items' => [['_id' => '665f1c2e8f1b2a0012345678', 'match_id' => 12, 'code' => '1X2', 'name' => 'Resultado final', 'status' => 'ACTIVE', 'version' => 3, 'selections' => $mercadoEj['selections'], 'updated_at' => '2026-10-30T14:00:00.000000Z']], 'meta' => $meta(1)]), '422' => $e422] + $adminErr],
    'post' => ['tags' => [$adminTag], 'operationId' => 'admin_mercados_store', 'summary' => 'Crear mercado',
        'description' => 'Solo rol `ADMIN`. Crea el mercado en `ACTIVE` (o `SUSPENDED` si se indica), versión 1. El partido no puede estar `FIN`, el par (`match_id`, `code`) debe ser único y los `code` de opciones no pueden repetirse.', 'security' => $sec,
        'requestBody' => $bodyJson('MercadoCrearRequest', ['match_id' => 12, 'code' => 'OU_2_5', 'name' => 'Más/Menos 2.5 goles', 'selections' => [['code' => 'OVER', 'label' => 'Más de 2.5', 'odd' => 1.85], ['code' => 'UNDER', 'label' => 'Menos de 2.5', 'odd' => 1.95]]]),
        'responses' => ['201' => $ok('Mercado creado.', $ref('MercadoAdmin'), ['_id' => '665f1c2e8f1b2a0012345999', 'match_id' => 12, 'code' => 'OU_2_5', 'name' => 'Más/Menos 2.5 goles', 'status' => 'ACTIVE', 'version' => 1, 'selections' => [['code' => 'OVER', 'label' => 'Más de 2.5', 'odd' => 1.85], ['code' => 'UNDER', 'label' => 'Menos de 2.5', 'odd' => 1.95]], 'updated_at' => '2026-10-30T14:00:00.000000Z']), '404' => $e404, '422' => $e422] + $adminErr],
];
$mid = $p('id', 'path', $str('665f1c2e8f1b2a0012345678'), 'Id del mercado (ObjectId de MongoDB).');
$paths['/admin/mercados/{id}'] = [
    'get' => ['tags' => [$adminTag], 'operationId' => 'admin_mercados_show', 'summary' => 'Ver mercado', 'description' => 'Solo rol `ADMIN`.', 'security' => $sec, 'parameters' => [$mid],
        'responses' => ['200' => $ok('Mercado.', $ref('MercadoAdmin'), ['_id' => '665f1c2e8f1b2a0012345678', 'match_id' => 12, 'code' => '1X2', 'name' => 'Resultado final', 'status' => 'ACTIVE', 'version' => 3, 'selections' => $mercadoEj['selections']]), '404' => $e404] + $adminErr],
    'put' => ['tags' => [$adminTag], 'operationId' => 'admin_mercados_update', 'summary' => 'Actualizar nombre, estado o cuotas',
        'description' => 'Solo rol `ADMIN`. Parcial; cada actualización sube `version`. Si se envía `selections` reemplaza la lista completa (mín. 2, códigos únicos). Un mercado `SETTLED` no se puede editar (422). No se puede cambiar `match_id` ni `code`. También responde a `PATCH`.', 'security' => $sec, 'parameters' => [$mid],
        'requestBody' => $bodyJson('MercadoActualizarRequest', ['status' => 'SUSPENDED', 'selections' => [['code' => '1', 'label' => 'Local', 'odd' => 2.05], ['code' => 'X', 'label' => 'Empate', 'odd' => 3.3], ['code' => '2', 'label' => 'Visitante', 'odd' => 3.6]]]),
        'responses' => ['200' => $ok('Mercado actualizado.', $ref('MercadoAdmin'), ['_id' => '665f1c2e8f1b2a0012345678', 'match_id' => 12, 'code' => '1X2', 'name' => 'Resultado final', 'status' => 'SUSPENDED', 'version' => 4, 'selections' => $mercadoEj['selections']]), '404' => $e404, '422' => $e422] + $adminErr],
    'delete' => ['tags' => [$adminTag], 'operationId' => 'admin_mercados_destroy', 'summary' => 'Suspender mercado (no se borra)',
        'description' => 'Solo rol `ADMIN`. **No elimina**: cambia `status` a `SUSPENDED` porque las apuestas referencian el id. Devuelve el mercado con `status: SUSPENDED` (no `[]` como los demás DELETE); es idempotente y repetirlo no falla.', 'security' => $sec, 'parameters' => [$mid],
        'responses' => ['200' => $ok('Mercado suspendido.', $ref('MercadoAdmin'), ['_id' => '665f1c2e8f1b2a0012345678', 'match_id' => 12, 'code' => '1X2', 'name' => 'Resultado final', 'status' => 'SUSPENDED', 'version' => 3, 'selections' => $mercadoEj['selections']]), '404' => $e404] + $adminErr],
];
$paths['/admin/partidos/{id}/liquidar'] = ['post' => [
    'tags' => [$adminTag], 'operationId' => 'adminPartidoLiquidar', 'summary' => 'Liquidar un partido',
    'description' => "Solo rol `ADMIN`. Marca el partido `FIN` con el marcador, determina la opción ganadora de los mercados estándar (`1X2`, `BTTS`, `OU_<n>_<d>`, p. ej. `OU_2_5`) y guarda el resultado por selección. Para otros mercados (o líneas enteras) indica `ganadoras: {\"<market_id o code>\": \"<opción>\" | \"VOID\"}`; los no resueltos quedan pendientes (`mercados_sin_resolver`). Una apuesta pierde apenas una selección pierde; gana cuando todas ganan (anuladas cuentan con cuota 1). Cada cierre libera lo retenido y abona el premio con referencia única `liquidacion:apuesta:{id}`: repetir la llamada con el mismo marcador no paga dos veces; con otro marcador responde 409.",
    'security' => $sec, 'parameters' => [$idPath('Id del partido.')],
    'requestBody' => $bodyJson('LiquidarPartidoRequest', ['goles_local' => 2, 'goles_visitante' => 1, 'ganadoras' => ['665f1c2e8f1b2a0012345999' => 'VOID', 'CORNERS_OU_9_5' => 'OVER']]),
    'responses' => ['200' => $ok('Partido liquidado.', $ref('LiquidacionResultado'), ['partido_id' => 12, 'apuestas_liquidadas' => 37, 'mercados_sin_resolver' => []]), '404' => $errEx('Partido no encontrado.', ['Partido no encontrado.']), '409' => $errEx('Ya liquidado con otro marcador.', ['El partido ya fue liquidado con otro marcador.']), '422' => $e422] + $adminErr,
]];
$paths['/admin/apuestas/{id}/anular'] = ['post' => [
    'tags' => [$adminTag], 'operationId' => 'adminApuestaAnular', 'summary' => 'Anular una apuesta',
    'description' => 'Solo rol `ADMIN`. Anula una apuesta pendiente y devuelve el monto retenido al saldo disponible. Si ya estaba `ANU` es idempotente (200). Si ya fue liquidada (`GAN`/`PER`): 409.', 'security' => $sec,
    'parameters' => [$idPath('Id de la apuesta.')],
    'responses' => ['200' => $ok('Apuesta anulada.', $obj(['id' => $int(105), 'estado' => $str('ANU')], ['id', 'estado']), ['id' => 105, 'estado' => 'ANU']), '404' => $errEx('Apuesta no encontrada.', ['Apuesta no encontrada.']), '409' => $errEx('Ya liquidada.', ['La apuesta ya fue liquidada.'])] + $adminErr,
]];

// ---------------------------------------------------------------- componentes
$monto = fn (string $ex): array => ['type' => 'string', 'pattern' => '^\d+(\.\d{1,2})?$', 'example' => $ex];
$fecha = ['type' => 'string', 'format' => 'date-time', 'example' => '2026-10-30T15:00:00.000000Z'];
$idem = $str('3f6c2b9e-1d4a-4b7e-9c11-0a2b3c4d5e6f', ['minLength' => 8, 'maxLength' => 100, 'description' => 'Clave de idempotencia. Si se omite (y tampoco llega el header `Idempotency-Key`), el servidor deriva una clave determinista (usuario + payload normalizado + ventana de 30 s). Si llegan ambos, gana el cuerpo.']);
$vacio = ['type' => 'array', 'items' => new stdClass(), 'maxItems' => 0];

$schemas = [
    'Envelope' => $envelope(['description' => 'Contenido de la respuesta (varía por endpoint).']),
    'Error' => [
        'type' => 'object', 'required' => ['error', 'mensaje', 'data'],
        'properties' => [
            'error' => ['type' => 'integer', 'enum' => [1], 'example' => 1],
            'mensaje' => $arr($str(), ['description' => 'Mensajes de error en español.', 'example' => ['Recurso no encontrado.']]),
            'data' => ['description' => 'Vacío (`[]`) o detalle: `errores` (422 de FormRequest/`validate`, mapa campo -> mensajes), `retry_after` (429), `selecciones.odd_actual` (409 de cuota).', 'additionalProperties' => true],
        ],
    ],
    'PaginacionMeta' => $obj(['page' => $int(1), 'per_page' => $int(20), 'total' => $int(42), 'last_page' => $int(3)], ['page', 'per_page', 'total', 'last_page']),
    'Usuario' => $obj([
        'id_usuario' => $int(7), 'rol_codigo' => $str('CLIENTE', ['enum' => ['CLIENTE', 'ADMIN']]), 'estado_codigo' => $str('ACT', ['description' => '`ACT` = activo; cualquier otro bloquea el acceso (403).']),
        'fecha_registro' => $fecha, 'primer_nombre' => $str('Laura'), 'segundo_nombre' => $nullable($str()), 'primer_apellido' => $str('Gómez'), 'segundo_apellido' => $nullable($str()),
        'tipo_documento_codigo' => $str('CC'), 'numero_documento' => $str('1020304050'), 'celular' => $nullable($str('3001234567')),
        'fecha_nacimiento' => ['type' => 'string', 'format' => 'date', 'example' => '1995-04-12'], 'correo' => ['type' => 'string', 'format' => 'email', 'example' => 'laura@example.com'],
    ], ['id_usuario', 'rol_codigo', 'estado_codigo', 'correo']),
    'RegistroRequest' => $obj([
        'primer_nombre' => $str('Laura', ['maxLength' => 50]), 'segundo_nombre' => $nullable($str('', ['maxLength' => 50])),
        'primer_apellido' => $str('Gómez', ['maxLength' => 50]), 'segundo_apellido' => $nullable($str('', ['maxLength' => 50])),
        'tipo_documento_codigo' => $str('CC', ['description' => 'Debe existir en `tipo_documento` (p. ej. `CC`).']),
        'numero_documento' => $str('1020304050', ['maxLength' => 20, 'description' => 'Único por tipo de documento.']),
        'correo' => ['type' => 'string', 'format' => 'email', 'maxLength' => 120, 'example' => 'laura@example.com', 'description' => 'Único.'],
        'celular' => $nullable($str('3001234567', ['maxLength' => 20, 'pattern' => '^[0-9+\s\-]{7,20}$'])),
        'fecha_nacimiento' => ['type' => 'string', 'format' => 'date', 'example' => '1995-04-12'],
        'contrasena' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'example' => 'Clave-segura-1'],
        'contrasena_confirmation' => ['type' => 'string', 'format' => 'password', 'description' => 'Debe ser igual a `contrasena`.', 'example' => 'Clave-segura-1'],
    ], ['primer_nombre', 'primer_apellido', 'tipo_documento_codigo', 'numero_documento', 'correo', 'fecha_nacimiento', 'contrasena', 'contrasena_confirmation']),
    'LoginRequest' => $obj(['correo' => ['type' => 'string', 'format' => 'email', 'example' => 'laura@example.com'], 'contrasena' => ['type' => 'string', 'format' => 'password', 'example' => 'Clave-segura-1']], ['correo', 'contrasena']),
    'RecuperarRequest' => $obj(['correo' => ['type' => 'string', 'format' => 'email', 'maxLength' => 120, 'example' => 'laura@example.com']], ['correo']),
    'RestablecerRequest' => $obj([
        'correo' => ['type' => 'string', 'format' => 'email', 'maxLength' => 120, 'example' => 'laura@example.com'],
        'codigo' => $str('482913', ['pattern' => '^\d{6}$', 'description' => 'Código de 6 dígitos recibido por correo.']),
        'contrasena' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'maxLength' => 100, 'example' => 'Nueva-clave-2'],
        'contrasena_confirmation' => ['type' => 'string', 'format' => 'password', 'example' => 'Nueva-clave-2'],
    ], ['correo', 'codigo', 'contrasena', 'contrasena_confirmation']),
    'ActualizarPerfilRequest' => $obj([
        'primer_nombre' => $str('Laura', ['maxLength' => 50]), 'segundo_nombre' => $nullable($str('', ['maxLength' => 50])),
        'primer_apellido' => $str('Gómez', ['maxLength' => 50]), 'segundo_apellido' => $nullable($str('', ['maxLength' => 50])),
        'celular' => $nullable($str('+57 300 123 4567', ['maxLength' => 20, 'pattern' => '^[0-9+\s\-]{7,20}$'])),
    ], [], ['description' => 'Todos los campos son opcionales; los enviados `primer_nombre`/`primer_apellido` no pueden ser vacíos.']),
    'CambiarContrasenaRequest' => $obj([
        'contrasena_actual' => ['type' => 'string', 'format' => 'password', 'example' => 'Clave-segura-1'],
        'contrasena' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'maxLength' => 100, 'description' => 'Distinta de `contrasena_actual`.', 'example' => 'Nueva-clave-2'],
        'contrasena_confirmation' => ['type' => 'string', 'format' => 'password', 'example' => 'Nueva-clave-2'],
    ], ['contrasena_actual', 'contrasena', 'contrasena_confirmation']),

    'Servicio' => $obj(['codigo' => $str('FUTBOL'), 'nombre' => $str('Fútbol'), 'activo' => ['type' => 'boolean', 'example' => true]]),
    'Pais' => $obj(['codigo_iso' => $str('CO'), 'nombre' => $str('Colombia')]),
    'Ciudad' => $obj(['id_ciudad' => $int(2), 'nombre' => $str('Medellín'), 'pais_codigo' => $str('CO')]),
    'Liga' => $obj(['id_liga' => $int(1), 'servicio_codigo' => $str('FUTBOL'), 'pais_codigo' => $str('CO'), 'nombre' => $str('Liga BetPlay'), 'logo_drive_id' => $nullable($str()), 'activa' => ['type' => 'boolean'], 'pais' => $ref('Pais'), 'servicio' => $ref('Servicio')], [], ['description' => '`pais` va en el catálogo; `pais` y `servicio` en el CRUD admin.']),
    'Temporada' => $obj(['id_temporada' => $int(3), 'liga_id' => $int(1), 'nombre' => $str('2026'), 'fecha_inicio' => ['type' => 'string', 'format' => 'date'], 'fecha_fin' => ['type' => 'string', 'format' => 'date']]),
    'Estadio' => $obj(['id_estadio' => $int(4), 'nombre' => $str('Atanasio Girardot'), 'ciudad_id' => $int(2), 'capacidad' => $nullable($int(45000)), 'direccion' => $nullable($str()), 'ciudad' => $ref('Ciudad')]),
    'Equipo' => $obj(['id_equipo' => $int(5), 'nombre' => $str('Atlético Nacional'), 'escudo_drive_id' => $nullable($str()), 'ciudad_id' => $int(2), 'estadio_id' => $nullable($int(4)), 'fecha_fundacion' => $nullable(['type' => 'string', 'format' => 'date']), 'ciudad' => $ref('Ciudad'), 'estadio' => $ref('Estadio')]),
    'Jugador' => $obj(['id_jugador' => $int(88), 'primer_nombre' => $str('Juan'), 'segundo_nombre' => $nullable($str()), 'primer_apellido' => $str('Pérez'), 'segundo_apellido' => $nullable($str()), 'fecha_nacimiento' => $nullable(['type' => 'string', 'format' => 'date']), 'pais_codigo' => $nullable($str('CO')), 'posicion_codigo' => $str('DEL'), 'equipo_actual_id' => $nullable($int(5)), 'dorsal' => $nullable($int(9)), 'foto_drive_id' => $nullable($str()), 'equipo' => $ref('Equipo'), 'posicion' => $obj(['codigo' => $str('DEL'), 'nombre' => $str('Delantero')]), 'pais' => $ref('Pais')]),
    'Jornada' => $obj(['id_jornada' => $int(21), 'temporada_id' => $int(3), 'numero' => $int(10), 'fecha_inicio' => ['type' => 'string', 'format' => 'date'], 'fecha_fin' => ['type' => 'string', 'format' => 'date']]),
    'Partido' => $obj([
        'id_partido' => $int(12), 'jornada_id' => $int(21), 'equipo_local_id' => $int(5), 'equipo_visitante_id' => $int(6), 'estadio_id' => $nullable($int(4)),
        'fecha_hora' => ['type' => 'string', 'format' => 'date-time'], 'estado_codigo' => $str('PROG', ['enum' => ['PROG', 'ENJ', 'FIN', 'SUSP'], 'description' => 'PROG programado, ENJ en juego, FIN finalizado, SUSP suspendido.']),
        'goles_local' => $nullable($int(2)), 'goles_visitante' => $nullable($int(1)),
        'local' => $ref('Equipo'), 'visitante' => $ref('Equipo'), 'estadio' => $ref('Estadio'), 'estado' => $obj(['codigo' => $str('PROG'), 'nombre' => $str('Programado')]),
        'jornada' => $obj(['id_jornada' => $int(21), 'numero' => $int(10), 'temporada' => $ref('Temporada')], [], ['description' => 'Anidada: jornada.temporada.liga.']),
    ]),
    'MercadoPublico' => $obj(['id' => $str('665f1c2e8f1b2a0012345678', ['description' => 'ObjectId del mercado: va en `market_id`.']), 'code' => $str('1X2'), 'name' => $str('Resultado final'), 'version' => $int(3), 'selections' => $arr($obj(['code' => $str('1'), 'label' => $str('Local'), 'odd' => ['type' => 'number', 'example' => 2.1, 'description' => 'Cuota decimal (> 1).']], ['code', 'label', 'odd']))], ['id', 'code', 'name', 'version', 'selections']),
    'MercadoAdmin' => $obj(['_id' => $str('665f1c2e8f1b2a0012345678'), 'match_id' => $int(12), 'code' => $str('1X2'), 'name' => $str('Resultado final'), 'status' => $str('ACTIVE', ['enum' => ['ACTIVE', 'SUSPENDED', 'SETTLED']]), 'version' => $int(3), 'selections' => $arr($obj(['code' => $str('1'), 'label' => $str('Local'), 'odd' => ['type' => 'number', 'example' => 2.1]])), 'updated_at' => $fecha]),

    'Cuenta' => $obj(['saldo_disponible' => $monto('120000.00'), 'saldo_retenido' => $monto('10000.00'), 'moneda' => $str('COP')], ['saldo_disponible', 'saldo_retenido', 'moneda']),
    'Movimiento' => $obj(['id' => $int(310), 'tipo' => $str('DEPOSITO'), 'monto' => $monto('50000.00'), 'saldo_disponible' => $monto('120000.00'), 'saldo_retenido' => $monto('10000.00'), 'apuesta_id' => $nullable($int(105)), 'descripcion' => $nullable($str('DEPOSITO')), 'fecha' => $fecha]),
    'ApuestaSeleccion' => $obj(['partido_id' => $int(12), 'market_id' => $str('665f1c2e8f1b2a0012345678'), 'selection_code' => $str('1'), 'etiqueta' => $str('Local'), 'cuota' => $str('2.1000', ['description' => 'Cuota congelada al apostar.']), 'resultado' => $nullable($str('GAN', ['description' => 'Resultado de la selección una vez liquidada (p. ej. GAN, PER, VOID); null mientras esté pendiente.']))]),
    'Apuesta' => $obj([
        'id' => $int(105), 'tipo' => $str('COMBINADA', ['enum' => ['SIMPLE', 'COMBINADA']]), 'estado' => $str('PEND', ['enum' => ['PEND', 'GAN', 'PER', 'ANU', 'CASH'], 'description' => 'PEND pendiente, GAN ganada, PER perdida, ANU anulada, CASH cobrada anticipadamente.']),
        'monto' => $monto('10000.00'), 'cuota_total' => $str('3.8850'), 'pago_potencial' => $monto('38850.00'), 'creada_en' => $fecha, 'liquidada_en' => $nullable($fecha),
        'selecciones' => $arr($ref('ApuestaSeleccion')),
    ], ['id', 'tipo', 'estado', 'monto', 'cuota_total', 'pago_potencial', 'selecciones']),
    'CrearApuestaRequest' => $obj([
        'monto' => $str('10000', ['pattern' => '^\d{1,12}(\.\d{1,2})?$', 'description' => 'Monto a apostar como **string** decimal (hasta 12 enteros y 2 decimales). Un número JSON falla la validación `string`.']),
        'tipo' => $nullable($str('COMBINADA', ['enum' => ['SIMPLE', 'COMBINADA']])) + ['description' => 'Opcional. SIMPLE = 1 selección exacta; COMBINADA = 2 o más.'],
        'idempotency_key' => $idem,
        'selecciones' => $arr($obj([
            'match_id' => $int(12, ['minimum' => 1]), 'market_id' => $str('665f1c2e8f1b2a0012345678', ['maxLength' => 64, 'description' => '`id` del mercado devuelto por `GET /partidos/{id}/mercados`.']),
            'selection_code' => $str('1', ['maxLength' => 40]), 'odd' => $nullable(['type' => 'number', 'minimum' => 1, 'example' => 2.1, 'description' => 'Debe ser > 1. Cuota que el usuario vio; si difiere de la actual, 409.']),
        ], ['match_id', 'market_id', 'selection_code']), ['minItems' => 1, 'maxItems' => 10, 'description' => 'Máximo `BET_MAX_SELECTIONS` (10 por defecto). Un solo pick por partido.']),
    ], ['monto', 'selecciones']),
    'DepositoRequest' => $obj([
        'monto' => $str('50000', ['pattern' => '^\d{1,10}(\.\d{1,2})?$', 'description' => 'String decimal (hasta 10 enteros y 2 decimales); un número JSON se convierte a string.']),
        'idempotency_key' => $idem,
    ], ['monto']),
    'Transaccion' => $obj(['referencia' => $str('PB-31-K8QZ4M2T'), 'estado' => $str('PENDIENTE', ['description' => 'PENDIENTE al crear; la pasarela la cambia a APROBADA / RECHAZADA / CANCELADA / FALLIDA.']), 'monto' => $monto('50000.00'), 'moneda' => $str('COP'), 'tipo' => $str('DEPOSITO'), 'creada_en' => $fecha, 'confirmada_en' => $nullable($fecha)]),
    'Checkout' => $obj([
        'provider' => $str('wompi'), 'public_key' => $str('pub_test_AbCdEf123456'), 'reference' => $str('PB-31-K8QZ4M2T'), 'amount_in_cents' => $int(5000000, ['description' => 'Monto x 100.']),
        'currency' => $str('COP'), 'signature_integrity' => $str('4b3e...', ['description' => 'SHA256(reference + amount_in_cents + currency + WOMPI_INTEGRITY_SECRET).']),
        'redirect_url' => $nullable($str('http://localhost:5173/pagos/resultado')), 'checkout_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'URL del Web Checkout con todos los parámetros ya firmados.'],
    ], ['provider', 'public_key', 'reference', 'amount_in_cents', 'currency', 'signature_integrity', 'checkout_url']),
    'DepositoRespuesta' => $obj([
        'referencia' => $str('PB-31-K8QZ4M2T'), 'estado' => $str('PENDIENTE'), 'monto' => $monto('50000.00'), 'moneda' => $str('COP'), 'tipo' => $str('DEPOSITO'), 'creada_en' => $fecha, 'confirmada_en' => $nullable($fecha),
        'checkout' => $ref('Checkout') + ['description' => '`null` si el driver de pagos no expone datos de checkout.'], 'checkout_url' => $nullable($str('https://checkout.wompi.co/p/?...')) + ['description' => 'Alias de `checkout.checkout_url` (campo `url_redireccion` de la transacción).'],
    ]),
    'WompiEvento' => $obj([
        'event' => $str('transaction.updated', ['description' => 'Solo se procesa `transaction.updated`.']),
        'data' => $obj(['transaction' => $obj(['id' => $str('1234-1700000000-56789'), 'status' => $str('APPROVED', ['enum' => ['APPROVED', 'DECLINED', 'VOIDED', 'ERROR', 'PENDING']]), 'reference' => $str('PB-31-K8QZ4M2T'), 'amount_in_cents' => $int(5000000), 'currency' => $str('COP')], [], ['additionalProperties' => true])], [], ['additionalProperties' => true]),
        'environment' => $str('test'),
        'signature' => $obj(['properties' => $arr($str(), ['example' => ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'], 'description' => 'Rutas dentro de `data` cuyos valores se concatenan.']), 'checksum' => $str('3476DDA5...', ['description' => 'SHA256(valores + timestamp + secreto de eventos), hexadecimal.'])], ['properties', 'checksum']),
        'timestamp' => $int(1700000001, ['description' => 'Epoch en segundos en la raíz, tal como lo envía Wompi; forma parte del checksum.']),
        'sent_at' => ['type' => 'string', 'format' => 'date-time'],
    ], ['event', 'data', 'signature'], ['additionalProperties' => true]),

    'EquipoAdminRequest' => $obj([
        'nombre' => $str('Atlético Nacional', ['maxLength' => 80]), 'ciudad_id' => $int(2, ['description' => 'Debe existir en `ciudad`.']), 'estadio_id' => $nullable($int(4, ['description' => 'Debe existir en `estadio`.'])),
        'escudo_drive_id' => $nullable($str('', ['maxLength' => 255])), 'fecha_fundacion' => $nullable(['type' => 'string', 'format' => 'date', 'description' => 'No puede ser futura.']),
    ], ['nombre', 'ciudad_id'], ['description' => '`nombre` y `ciudad_id` son obligatorios al crear (opcionales al actualizar).']),
    'LigaAdminRequest' => $obj([
        'nombre' => $str('Liga BetPlay', ['maxLength' => 80]), 'servicio_codigo' => $str('FUTBOL', ['description' => 'Debe existir en `servicio`.']),
        'pais_codigo' => $str('CO', ['minLength' => 2, 'maxLength' => 2, 'description' => 'ISO alfa-2; debe existir en `pais`.']), 'logo_drive_id' => $nullable($str('', ['maxLength' => 255])), 'activa' => ['type' => 'boolean', 'example' => true],
    ], ['nombre', 'servicio_codigo', 'pais_codigo'], ['description' => '`nombre`, `servicio_codigo` y `pais_codigo` obligatorios al crear.']),
    'PartidoAdminRequest' => $obj([
        'jornada_id' => $int(21), 'equipo_local_id' => $int(5), 'equipo_visitante_id' => $int(6, ['description' => 'Distinto del local.']), 'estadio_id' => $nullable($int(4)),
        'fecha_hora' => ['type' => 'string', 'format' => 'date-time', 'example' => '2026-11-08 20:00:00'],
        'estado_codigo' => $str('PROG', ['enum' => ['PROG', 'ENJ', 'SUSP'], 'default' => 'PROG', 'description' => '`FIN` y el marcador solo se fijan liquidando (`POST /admin/partidos/{id}/liquidar`). Un partido `FIN` no se puede editar.']),
    ], ['jornada_id', 'equipo_local_id', 'equipo_visitante_id', 'fecha_hora']),
    'ReglaApuesta' => $obj(['_id' => $str('665f1c2e8f1b2a0012340001'), 'code' => $str('R_1X2_STD'), 'market_code' => $str('1X2'), 'min_stake' => $monto('1000.00'), 'max_stake' => $monto('1000000.00'), 'max_payout' => $nullable($monto('50000000.00')), 'min_age' => $int(18), 'active' => ['type' => 'boolean'], 'effective_from' => $fecha, 'version' => $int(1)]),
    'ReglaApuestaAdminRequest' => $obj([
        'code' => $str('R_1X2_STD', ['maxLength' => 40, 'pattern' => '^[A-Za-z0-9_\-]+$', 'description' => 'Único.']), 'market_code' => $str('1X2', ['maxLength' => 30, 'description' => 'Código de mercado al que aplica (`Mercado.code`).']),
        'min_stake' => ['type' => 'number', 'minimum' => 0.01, 'maximum' => 1000000000, 'example' => 1000], 'max_stake' => ['type' => 'number', 'minimum' => 0.01, 'maximum' => 1000000000, 'example' => 1000000, 'description' => '>= `min_stake`.'],
        'max_payout' => $nullable(['type' => 'number', 'minimum' => 0.01, 'example' => 50000000]), 'min_age' => $int(18, ['minimum' => 18, 'maximum' => 100]),
        'active' => ['type' => 'boolean', 'default' => true], 'effective_from' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Por defecto, ahora.', 'example' => '2026-10-30 00:00:00'],
    ], ['code', 'market_code', 'min_stake', 'max_stake', 'min_age']),
    'MercadoSeleccion' => $obj(['code' => $str('OVER', ['maxLength' => 40]), 'label' => $str('Más de 2.5', ['maxLength' => 120]), 'odd' => ['type' => 'number', 'minimum' => 1, 'maximum' => 1000, 'example' => 1.85]], ['code', 'label', 'odd']),
    'MercadoCrearRequest' => $obj([
        'match_id' => $int(12, ['description' => 'Debe existir en `partido` y no estar `FIN`.']), 'code' => $str('OU_2_5', ['maxLength' => 30, 'pattern' => '^[A-Z0-9_]+$']),
        'name' => $str('Más/Menos 2.5 goles', ['maxLength' => 80]), 'status' => $str('ACTIVE', ['enum' => ['ACTIVE', 'SUSPENDED']]),
        'selections' => $arr($ref('MercadoSeleccion'), ['minItems' => 2, 'maxItems' => 20, 'description' => 'Códigos únicos.']),
    ], ['match_id', 'code', 'name', 'selections']),
    'MercadoActualizarRequest' => $obj([
        'name' => $str('Resultado final', ['maxLength' => 80]), 'status' => $str('ACTIVE', ['enum' => ['ACTIVE', 'SUSPENDED']]),
        'selections' => $arr($ref('MercadoSeleccion'), ['minItems' => 2, 'maxItems' => 20]),
    ]),
    'LiquidarPartidoRequest' => $obj([
        'goles_local' => $int(2, ['minimum' => 0, 'maximum' => 99]), 'goles_visitante' => $int(1, ['minimum' => 0, 'maximum' => 99]),
        'ganadoras' => ['type' => 'object', 'additionalProperties' => $str('OVER', ['maxLength' => 40]), 'description' => 'Mapa `{market_id | code: opción ganadora | "VOID"}` para mercados no estándar o líneas enteras. Los mercados estándar `1X2`, `BTTS`, `OU_<n>_<d>` se resuelven solos.', 'example' => ['CORNERS_OU_9_5' => 'OVER']],
    ], ['goles_local', 'goles_visitante']),
    'LiquidacionResultado' => $obj(['partido_id' => $int(12), 'apuestas_liquidadas' => $int(37), 'mercados_sin_resolver' => $arr($str('CORNERS_OU_9_5'), ['description' => 'Mercados que quedaron pendientes por falta de resolución.'])], ['partido_id', 'apuestas_liquidadas', 'mercados_sin_resolver']),
];

$respuestas = [
    'NoAutenticado' => $errEx('Falta el token Bearer o es inválido/expirado.', ['No autenticado.']),
    'Prohibido' => $errEx('Cuenta inactiva (estado distinto de `ACT`) o rol insuficiente.', ['No tienes permisos para esta acción.']),
    'NoEncontrado' => $errEx('El recurso no existe.', ['Recurso no encontrado.']),
    'Conflicto' => $errEx('Conflicto con el estado actual (idempotencia, estado de la apuesta/transacción, cuota cambiada).', ['Conflicto.']),
    'Validacion' => $errEx('Validación fallida. `mensaje[]` lista todos los errores y `data.errores` los agrupa por campo.', ['El campo monto es obligatorio.'], ['errores' => ['monto' => ['El campo monto es obligatorio.']]]),
    'DemasiadasSolicitudes' => $errEx('Límite de solicitudes excedido. Cabecera `Retry-After` y `data.retry_after` indican los segundos de espera.', ['Demasiadas solicitudes. Intenta más tarde.'], ['retry_after' => '42']),
    'PasarelaNoDisponible' => $errEx('La pasarela de pagos (Wompi / Firebase) no responde o falla.', ['La pasarela de pagos no está disponible. Intenta nuevamente.']),
    'ErrorInterno' => $errEx('Error inesperado (el detalle solo se muestra con `APP_DEBUG=true`).', ['Error interno del servidor.']),
];

// Tags
$tags = [
    ['name' => 'Auth', 'description' => 'Registro, login, sesión y recuperación de contraseña.'],
    ['name' => 'Catálogo', 'description' => 'Datos deportivos públicos de solo lectura (MySQL) y mercados/cuotas activas (MongoDB). 120 solicitudes/min por IP.'],
    ['name' => 'Perfil', 'description' => 'Datos personales y contraseña del usuario autenticado.'],
    ['name' => 'Cuenta', 'description' => 'Saldo y movimientos contables (PostgreSQL).'],
    ['name' => 'Apuestas', 'description' => 'Crear, consultar y cancelar apuestas. 20/min por usuario en operaciones de escritura. Admite `Idempotency-Key`.'],
    ['name' => 'Pagos', 'description' => 'Depósitos con Wompi (checkout + webhook). 10/min por usuario.'],
    ['name' => 'Admin', 'description' => 'Administración (rol `ADMIN`): CRUD de equipos, ligas, partidos, mercados y reglas; liquidación y anulación de apuestas.'],
];

// Reordena las rutas para una lectura estable: Auth, Catálogo, Perfil, Cuenta, Apuestas, Pagos, Admin ya vienen en ese orden.
return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'PlayBoss API',
        'version' => '1.0.0',
        'description' => <<<'MD'
API REST de apuestas deportivas PlayBoss (Laravel 12). Prefijo `/api`.

**Sobre de respuesta.** Todas las respuestas (éxito y error) usan `{ "error": 0|1, "mensaje": [], "data": ... }`. Los errores de validación (422) incluyen además `data.errores` por campo. Las listas paginadas devuelven `data.items` y `data.meta {page, per_page, total, last_page}` (`?page=&per_page=`, máx. 100).

**Autenticación.** `Authorization: Bearer <token>` (Laravel Sanctum). Obtén el token con `POST /auth/login` o `POST /auth/registro` y pulsa **Authorize** en Swagger UI (pega solo el token, sin el prefijo `Bearer`). Los endpoints de `/admin` exigen rol `ADMIN`. Una cuenta con estado distinto de `ACT` recibe 403 en todos los endpoints autenticados.

**Idempotencia.** `POST /apuestas` y `POST /pagos/depositos` aceptan el header `Idempotency-Key` (o `idempotency_key` en el cuerpo; si ambos vienen, gana el cuerpo): repetir la clave devuelve el recurso original sin duplicar. Sin ninguna clave, el servidor deriva una determinista (usuario + payload normalizado + ventana de 30 s) que amortigua dobles envíos idénticos; no sustituye a una clave propia del cliente.

**Códigos de error.** 401 no autenticado · 403 sin permisos/cuenta inactiva · 404 no encontrado · 409 conflicto (cuota cambiada, idempotencia, estado) · 422 validación o regla de negocio · 429 límite de solicitudes · 502 pasarela de pagos caída · 500 error interno.

**Límites de solicitudes (por minuto).** Login: 5 por correo+IP y 30 por IP · Registro/recuperar/restablecer: 5 por IP (bucket compartido) · Catálogo público: 120 por IP · Apuestas (crear/cancelar): 20 por usuario · Pagos (depósito/estado): 10 por usuario · Webhook Wompi: 120 por IP.

**Montos.** Strings decimales con 2 decimales (`"10000.00"`), moneda `COP`. Se envían como string en las peticiones.
MD,
    ],
    'servers' => [['url' => '/api', 'description' => 'Servidor actual (se reemplaza por la URL real al servir el spec)']],
    'tags' => $tags,
    'paths' => $paths,
    'components' => [
        'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Sanctum token', 'description' => 'Token de Laravel Sanctum devuelto por `/auth/login` o `/auth/registro`.']],
        'schemas' => $schemas,
        'responses' => $respuestas,
    ],
];
