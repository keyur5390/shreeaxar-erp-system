<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\StateRequest;
use App\Models\Address;
use App\Models\State;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StateController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view countries')->only(['index']);
        $this->middleware('permission:create countries')->only(['store']);
        $this->middleware('permission:edit countries')->only(['update']);
        $this->middleware('permission:delete countries')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $countryId = $request->string('country_id')->value();
        $cacheKey = $countryId !== '' ? "masters.states.{$countryId}" : 'masters.states';

        $states = MasterCache::remember($cacheKey, function () use ($request): array {
            $query = State::query()
                ->withCount('addresses')
                ->orderBy('name');

            if ($request->filled('country_id')) {
                $query->where('country_id', $request->string('country_id')->value());
            }

            return $query->get()
                ->map(fn (State $state): array => $this->formatState($state))
                ->all();
        });

        return $this->successResponse($states);
    }

    public function store(StateRequest $request): JsonResponse
    {
        $state = State::create($request->validated());
        $state->loadCount('addresses');
        $this->forgetStateCache($state->country_id);

        return $this->successResponse(
            $this->formatState($state),
            'State created successfully.',
            201
        );
    }

    public function update(StateRequest $request, string $id): JsonResponse
    {
        $state = State::query()->withCount('addresses')->findOrFail($id);
        $state->update($request->validated());
        $this->forgetStateCache($state->country_id);

        return $this->successResponse(
            $this->formatState($state),
            'State updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $state = State::query()->findOrFail($id);
        $addressCount = Address::query()->where('state_id', $id)->count();

        if ($addressCount > 0) {
            return $this->errorResponse("{$addressCount} addresses use this state.", 400);
        }

        $countryId = $state->country_id;
        $state->delete();
        $this->forgetStateCache($countryId);

        return $this->successResponse(null, 'State deleted successfully.');
    }

    private function forgetStateCache(?string $countryId = null): void
    {
        MasterCache::forget('masters.states');

        if ($countryId !== null) {
            MasterCache::forget("masters.states.{$countryId}");
        }
    }

    private function formatState(State $state): array
    {
        return [
            'id' => $state->id,
            'name' => $state->name,
            'country_id' => $state->country_id,
            'addresses_count' => $state->addresses_count ?? 0,
            'created_at' => $state->created_at,
            'updated_at' => $state->updated_at,
        ];
    }
}
