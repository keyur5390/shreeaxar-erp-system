<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('quotation.health_token');

        if ($token && ! hash_equals((string) $token, (string) $request->header('X-Health-Token'))) {
            return response()->json(['message' => 'Invalid health token.'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'db_connected' => $this->isDatabaseConnected(),
            'uptime' => (int) floor(microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)))),
            'version' => config('app.version'),
        ]);
    }

    private function isDatabaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
