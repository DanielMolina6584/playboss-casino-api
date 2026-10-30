<?php

return [
    'currency' => env('PLAYBOSS_CURRENCY', 'COP'),

    // Swagger UI en /api/documentation y spec en /api/openapi.json. Por defecto solo en local.
    'docs_enabled' => filter_var(env('API_DOCS_ENABLED', env('APP_ENV', 'production') === 'local'), FILTER_VALIDATE_BOOL),

    'bets' => [
        'max_selections' => (int) env('BET_MAX_SELECTIONS', 10),
        // Edad legal mínima; las reglas de Mongo pueden exigir más, nunca menos.
        'min_age' => (int) env('BET_MIN_AGE', 18),
        // Tope de monto apostado por usuario en el día calendario (en pesos).
        'daily_stake_limit' => env('BET_DAILY_STAKE_LIMIT', '5000000.00'),
        'daily_bet_count_limit' => (int) env('BET_DAILY_COUNT_LIMIT', 100),
    ],
];
