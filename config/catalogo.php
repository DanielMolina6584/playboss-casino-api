<?php

return [
    // Segundos máximos de caché para lecturas públicas del catálogo (ligas, partidos, mercados).
    // 0 = sin caché; vacío = valores por defecto (ligas 300 s, partidos 30 s, mercados 20 s).
    'cache_ttl' => env('CATALOGO_CACHE_TTL') === null || env('CATALOGO_CACHE_TTL') === '' ? null : (int) env('CATALOGO_CACHE_TTL'),
];
