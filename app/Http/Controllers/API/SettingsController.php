<?php

namespace App\Http\Controllers\API;

use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends BaseController
{
    private const ALLOWED_KEYS = [
        'quotation_default_expiry_days',
    ];

    public function show(string $key): JsonResponse
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return $this->errorResponse('Invalid setting key.', 400);
        }

        $setting = Settings::query()->where('key', $key)->first();

        return $this->successResponse([
            'key' => $key,
            'value' => $setting?->value,
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return $this->errorResponse('Invalid setting key.', 400);
        }

        $validated = $request->validate([
            'value' => ['required', 'string', 'max:1000'],
        ]);

        $setting = Settings::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $validated['value']]
        );

        return $this->successResponse([
            'key' => $setting->key,
            'value' => $setting->value,
        ], 'Setting updated successfully.');
    }
}
