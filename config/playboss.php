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
        // Cotas globales informativas para el front; la regla de cada mercado (Mongo) puede ser más estricta.
        'min_stake' => env('BET_MIN_STAKE', '1000.00'),
        'max_stake' => env('BET_MAX_STAKE', '2000000.00'),
    ],

    'deposits' => [
        'max_amount' => env('DEPOSIT_MAX_AMOUNT', '10000000.00'),
    ],

    // Retiros: solicitud pendiente que revisa un administrador (el payout real no es automático).
    'withdrawals' => [
        'min_amount' => env('WITHDRAWAL_MIN_AMOUNT', '10000.00'),
        'max_amount' => env('WITHDRAWAL_MAX_AMOUNT', '5000000.00'),
        'max_pending' => (int) env('WITHDRAWAL_MAX_PENDING', 3),
    ],

    // Destino del formulario público POST /contacto (por defecto, MAIL_FROM_ADDRESS).
    'contact' => [
        'to' => env('CONTACT_TO_ADDRESS', env('MAIL_FROM_ADDRESS')),
    ],
];
