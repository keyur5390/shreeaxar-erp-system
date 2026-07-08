<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->sanitize($request->all()));

        return $next($request);
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->sanitize($item), $value);
        }

        if (is_string($value)) {
            return strip_tags($value);
        }

        return $value;
    }
}
