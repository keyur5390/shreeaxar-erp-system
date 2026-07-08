<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        if ($request instanceof Request && $request->expectsJson()) {
            return match (true) {
                $e instanceof AuthenticationException => response()->json(['message' => 'Unauthenticated.'], 401),
                $e instanceof AuthorizationException => response()->json(['message' => 'Insufficient permissions.'], 403),
                $e instanceof ModelNotFoundException => response()->json(['message' => 'Record not found.'], 404),
                $e instanceof ValidationException => response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422),
                $e instanceof ThrottleRequestsException => response()->json([
                    'message' => 'Too many requests.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429),
                default => response()->json([
                    'message' => config('app.debug') ? $e->getMessage() : 'An error occurred. Please try again.',
                ], 500),
            };
        }

        return parent::render($request, $e);
    }
}
