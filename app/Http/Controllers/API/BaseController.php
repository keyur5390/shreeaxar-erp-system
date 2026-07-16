<?php

namespace App\Http\Controllers\API;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BaseController extends Controller
{
    protected function successResponse(mixed $data, string $message = '', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function errorResponse(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, ?callable $transformer = null): JsonResponse
    {
        $items = $paginator->items();

        if ($transformer !== null) {
            $items = array_map($transformer, $items);
        }

        return $this->successResponse([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    protected function resolvePerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $limit = $request->integer('limit', $request->integer('per_page', $default));

        return min(max($limit, 1), $max);
    }
}
