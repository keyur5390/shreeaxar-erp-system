<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ValidateTokenVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $expectedAbility = 'token_version:'.$user->token_version;

        if (! in_array($expectedAbility, $token->abilities ?? [], true)) {
            $token->delete();

            throw new AuthenticationException('Session expired. Please log in again.');
        }

        return $next($request);
    }
}
