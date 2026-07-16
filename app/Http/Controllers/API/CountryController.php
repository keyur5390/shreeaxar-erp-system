<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\CountryRequest;
use App\Models\Address;
use App\Models\Country;
use App\Models\State;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountryController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view countries')->only(['index', 'states']);
        $this->middleware('permission:create countries')->only(['store']);
        $this->middleware('permission:edit countries')->only(['update']);
        $this->middleware('permission:delete countries')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $query = Country::query()
            ->withCount('states as states_count')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('iso_code', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($this->resolvePerPage($request, 15));

        return $this->paginatedResponse($paginator, fn (Country $country): array => $this->formatCountry($country));
    }

    public function states(string $id): JsonResponse
    {
        $country = Country::query()->findOrFail($id);

        $states = MasterCache::remember("masters.states.{$country->id}", fn (): array => State::query()
            ->where('country_id', $country->id)
            ->withCount('addresses')
            ->orderBy('name')
            ->get()
            ->map(fn (State $state): array => $this->formatState($state))
            ->all());

        return $this->successResponse($states);
    }

    public function store(CountryRequest $request): JsonResponse
    {
        $country = Country::create($request->validated());
        $country->loadCount('states as states_count');
        MasterCache::forget('masters.states');

        return $this->successResponse(
            $this->formatCountry($country),
            'Country created successfully.',
            201
        );
    }

    public function update(CountryRequest $request, string $id): JsonResponse
    {
        $country = Country::query()->withCount('states as states_count')->findOrFail($id);
        $country->update($request->validated());
        MasterCache::forget('masters.states');
        MasterCache::forget("masters.states.{$country->id}");

        return $this->successResponse(
            $this->formatCountry($country),
            'Country updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $country = Country::query()->with('states')->findOrFail($id);
        $stateIds = $country->states->pluck('id');

        if ($stateIds->isNotEmpty()) {
            $hasAddressReferences = Address::query()
                ->whereIn('state_id', $stateIds)
                ->exists();

            if ($hasAddressReferences) {
                return $this->errorResponse('States have address references. Remove addresses first.', 400);
            }
        }

        $countryId = $country->id;

        DB::transaction(function () use ($country, $stateIds): void {
            if ($stateIds->isNotEmpty()) {
                State::query()->whereIn('id', $stateIds)->delete();
            }

            $country->delete();
        });

        MasterCache::forget('masters.states');
        MasterCache::forget("masters.states.{$countryId}");

        return $this->successResponse(null, 'Country deleted successfully.');
    }

    private function formatCountry(Country $country): array
    {
        return [
            'id' => $country->id,
            'name' => $country->name,
            'iso_code' => $country->iso_code,
            'states_count' => $country->states_count ?? 0,
            'created_at' => $country->created_at,
            'updated_at' => $country->updated_at,
        ];
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
