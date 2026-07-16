<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateHealthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('app.health_token');
        $provided = (string) $request->header('X-Health-Token');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
