<?php
use App\Http\Controllers\API\HealthController;
use Illuminate\Support\Facades\Route;
Route::middleware('throttle:api')->get('/health', HealthController::class);
Route::middleware(['auth:sanctum','throttle:api'])->get('/user', fn ($request) => $request->user());
