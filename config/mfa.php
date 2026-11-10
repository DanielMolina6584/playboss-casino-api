<?php

/** Autenticación en dos pasos (TOTP, RFC 6238). Solo las tres primeras claves se configuran por .env. */
return [
    'enabled' => filter_var(env('MFA_ENABLED', true), FILTER_VALIDATE_BOOL),
    'issuer' => env('MFA_ISSUER', 'PlayBoss'),
    // Roles que no pueden usar /admin/* ni retirar sin 2FA activo (lista separada por comas).
    'required_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('MFA_REQUIRED_ROLES', 'ADMIN'))))),

    'digits' => 6,
    'period' => 30,
    'window' => 1,            // ±1 ventana de 30 s por deriva de reloj
    'pending_ttl' => 300,     // vida del mfa_token (s)
    'recovery_codes' => 10,

    // Bloqueo por fallos: por usuario+IP y global por usuario (varias IPs).
    'max_attempts' => 5,
    'decay' => 300,
    'user_max_attempts' => 15,
    'user_decay' => 900,
];
