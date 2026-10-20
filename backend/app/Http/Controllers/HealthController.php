<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'database' => 'down',
                'message' => 'La base de datos no responde.',
                'errors' => [],
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'database' => 'ok',
            'message' => 'Servicio disponible.',
            'errors' => [],
        ]);
    }
}
