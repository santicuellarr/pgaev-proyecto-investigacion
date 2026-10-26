<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verificación de correo en rutas sensibles
    |--------------------------------------------------------------------------
    |
    | Si es true, las rutas de negocio exigen email verificado (403 si no).
    | En tests se desactiva con PGAEV_REQUIRE_VERIFICATION=false.
    |
    */

    'require_verification' => env('PGAEV_REQUIRE_VERIFICATION', true),

    /*
    |--------------------------------------------------------------------------
    | Puntos de asistencia (objetivo 4)
    |--------------------------------------------------------------------------
    |
    | Presente y tarde otorgan puntos una sola vez por sesión. Ausente no suma.
    | La participación en foros usa activity.base_points del tipo foro.
    |
    */

    'points' => [
        'attendance_present' => (int) env('PGAEV_POINTS_ATTENDANCE_PRESENT', 10),
        'attendance_late' => (int) env('PGAEV_POINTS_ATTENDANCE_LATE', 5),
    ],

    'analytics' => [
        'inactivity_days' => (int) env('PGAEV_ANALYTICS_INACTIVITY_DAYS', 14),
        'low_completion_rate' => (int) env('PGAEV_ANALYTICS_LOW_COMPLETION_RATE', 50),
        'point_buckets' => [0, 50, 100, 150, 200, 300],
        'weeks' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta de lecturas (RNF-01)
    |--------------------------------------------------------------------------
    |
    | p95 local de GET /courses, /leaderboard, /me/progress y /analytics.
    | Medir con: php artisan pgaev:bench
    |
    */

    'perf' => [
        'p95_ms' => (int) env('PGAEV_PERF_P95_MS', 500),
        'error_rate_max' => (float) env('PGAEV_PERF_ERROR_RATE_MAX', 0.01),
    ],

];
