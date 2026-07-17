<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole('Super Admin')) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        return $next($request);
    }
}
