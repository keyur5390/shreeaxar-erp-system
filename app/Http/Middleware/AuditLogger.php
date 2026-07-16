<?php

namespace App\Http\Middleware;

use App\Jobs\CreateAuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditLogger
{
    private const AUDITED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! in_array($request->method(), self::AUDITED_METHODS, true) || ! $response->isSuccessful()) {
            return;
        }

        try {
            $user = $request->user();
            $segments = $request->segments();
            $module = $segments[1] ?? $segments[0] ?? 'api';

            CreateAuditLog::dispatch([
                'user_id' => $user?->getAuthIdentifier(),
                'user_email' => $user?->email,
                'action' => strtolower($request->method()),
                'module' => $module,
                'record_id' => $this->routeRecordId($request),
                'new_values' => $this->safeInput($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable) {
            // Audit failures must never affect the request lifecycle.
        }
    }

    private function routeRecordId(Request $request): ?string
    {
        $parameters = $request->route()?->parameters() ?? [];
        $lastParameter = end($parameters);

        return is_scalar($lastParameter) ? (string) $lastParameter : null;
    }

    private function safeInput(Request $request): array
    {
        return $this->removeSensitiveFields($request->all());
    }

    private function removeSensitiveFields(array $input): array
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'new_password_confirmation',
            'token',
            'otp',
            'reset_token',
            'remember_me',
        ];

        foreach ($input as $key => $value) {
            if (in_array($key, $sensitiveFields, true)) {
                unset($input[$key]);
                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->removeSensitiveFields($value);
            }
        }

        return $input;
    }
}
