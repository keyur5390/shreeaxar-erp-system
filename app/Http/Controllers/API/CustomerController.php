<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\CustomerStoreRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Http\Requests\QuickCreateCustomerRequest;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view customers')->only([
            'index',
            'show',
            'search',
            'checkEmail',
            'checkTin',
            'stats',
        ]);
        $this->middleware('permission:create customers')->only(['store', 'quickCreate']);
        $this->middleware('permission:edit customers')->only(['update', 'toggleStatus']);
        $this->middleware('permission:delete customers')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $query = Customer::query()
            ->withCount(['addresses', 'quotations'])
            ->orderBy('company_name');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tin_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = $query->paginate($this->resolvePerPage($request, 10));

        return $this->paginatedResponse($paginator, fn (Customer $customer): array => $this->formatCustomerListItem($customer));
    }

    public function show(string $id): JsonResponse
    {
        $customer = Customer::with([
            'addresses.addressType',
            'addresses.country',
            'addresses.state',
        ])->findOrFail($id);

        return $this->successResponse($this->formatCustomer($customer, includeAddresses: true));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->value();
        $limit = min(max((int) $request->input('limit', 10), 1), 50);

        if (strlen($query) < 2) {
            return $this->successResponse([]);
        }

        $customers = Customer::query()
            ->where(function ($builder) use ($query): void {
                $builder->where('company_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('tin_number', 'like', "%{$query}%");
            })
            ->orderBy('company_name')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'company_name' => $customer->company_name,
                'tin_number' => $customer->tin_number,
                'email' => $customer->email,
                'contact_number' => $customer->contact_number,
                'is_active' => $customer->is_active,
            ])
            ->values()
            ->all();

        return $this->successResponse($customers);
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'exclude_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $query = Customer::query()->where('email', $validated['email']);

        if (! empty($validated['exclude_id'])) {
            $query->where('id', '!=', $validated['exclude_id']);
        }

        return $this->successResponse(['available' => ! $query->exists()]);
    }

    public function checkTin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tin' => ['required', 'string', 'max:100'],
            'exclude_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $query = Customer::query()->where('tin_number', $validated['tin']);

        if (! empty($validated['exclude_id'])) {
            $query->where('id', '!=', $validated['exclude_id']);
        }

        return $this->successResponse(['available' => ! $query->exists()]);
    }

    public function store(CustomerStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $addresses = $validated['addresses'];

        $customer = DB::transaction(function () use ($validated, $addresses): Customer {
            $customer = Customer::create([
                'company_name' => $validated['company_name'],
                'email' => $validated['email'] ?? null,
                'secondary_email' => $validated['secondary_email'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'secondary_contact' => $validated['secondary_contact'] ?? null,
                'tin_number' => $validated['tin_number'] ?? null,
                'is_active' => true,
            ]);

            $this->createCustomerAddresses($customer, $addresses);

            return $customer;
        });

        $customer->load([
            'addresses.addressType',
            'addresses.country',
            'addresses.state',
        ]);

        return $this->successResponse(
            $this->formatCustomer($customer, includeAddresses: true),
            'Customer created successfully.',
            201
        );
    }

    public function update(CustomerUpdateRequest $request, string $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $validated = $request->validated();

        DB::transaction(function () use ($customer, $validated, $request): void {
            $customer->update(
                collect($validated)
                    ->only([
                        'company_name',
                        'email',
                        'secondary_email',
                        'contact_number',
                        'secondary_contact',
                        'tin_number',
                    ])
                    ->all()
            );

            if ($request->has('addresses')) {
                $this->deleteAndRecreateCustomerAddresses($customer, $validated['addresses'] ?? []);
            }
        });

        $customer->refresh()->load([
            'addresses.addressType',
            'addresses.country',
            'addresses.state',
        ]);

        return $this->successResponse(
            $this->formatCustomer($customer, includeAddresses: true),
            'Customer updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $quotationCount = $customer->quotations()->count();

        if ($quotationCount > 0) {
            $customer->update(['is_active' => false]);

            return $this->successResponse([
                'soft_deleted' => true,
                'message' => "Customer deactivated (has {$quotationCount} quotations).",
            ]);
        }

        DB::transaction(function () use ($customer): void {
            $customer->addresses()->detach();

            Address::query()
                ->whereDoesntHave('users')
                ->whereDoesntHave('customers')
                ->delete();

            $customer->delete();
        });

        return $this->successResponse(['deleted' => true]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $newStatus = ! $customer->is_active;

        $customer->update(['is_active' => $newStatus]);

        return $this->successResponse(
            ['id' => $customer->id, 'is_active' => $newStatus],
            $newStatus ? 'Customer activated successfully.' : 'Customer deactivated successfully.'
        );
    }

    public function quickCreate(QuickCreateCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! empty($validated['email'])) {
            $existing = Customer::query()->where('email', $validated['email'])->first();

            if ($existing !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer with this email exists.',
                    'data' => [
                        'existing_customer' => [
                            'id' => $existing->id,
                            'company_name' => $existing->company_name,
                            'email' => $existing->email,
                        ],
                    ],
                ], 409);
            }
        }

        $customer = Customer::create([
            'company_name' => $validated['company_name'],
            'email' => $validated['email'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'is_active' => true,
        ]);

        return $this->successResponse(
            $this->formatCustomer($customer),
            'Customer created successfully.',
            201
        );
    }

    public function stats(string $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);

        $byStatus = $customer->quotations()
            ->join('quotation_statuses', 'quotations.status_id', '=', 'quotation_statuses.id')
            ->selectRaw('quotation_statuses.name as status_name, count(*) as count')
            ->groupBy('quotation_statuses.name', 'quotation_statuses.sort_order')
            ->orderBy('quotation_statuses.sort_order')
            ->get()
            ->map(fn ($row): array => [
                'status_name' => $row->status_name,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $acceptedCount = $customer->quotations()
            ->whereHas('status', fn ($builder) => $builder->where('name', 'Accepted'))
            ->count();

        return $this->successResponse([
            'total_quotations' => $customer->quotations()->count(),
            'total_value' => (float) $customer->quotations()->sum('total_amount'),
            'accepted_count' => $acceptedCount,
            'last_quotation_date' => $customer->quotations()->max('quotation_date'),
            'by_status' => $byStatus,
        ]);
    }

    private function createCustomerAddresses(Customer $customer, array $addresses): void
    {
        foreach ($addresses as $addressData) {
            $address = Address::create($addressData);
            $customer->addresses()->attach($address->id);
        }
    }

    private function deleteAndRecreateCustomerAddresses(Customer $customer, array $addresses): void
    {
        $customer->addresses()->detach();

        Address::query()
            ->whereDoesntHave('users')
            ->whereDoesntHave('customers')
            ->delete();

        if ($addresses !== []) {
            $this->createCustomerAddresses($customer, $addresses);
        }
    }

    private function formatCustomerListItem(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'company_name' => $customer->company_name,
            'email' => $customer->email,
            'secondary_email' => $customer->secondary_email,
            'contact_number' => $customer->contact_number,
            'secondary_contact' => $customer->secondary_contact,
            'tin_number' => $customer->tin_number,
            'is_active' => $customer->is_active,
            'addresses_count' => $customer->addresses_count,
            'quotations_count' => $customer->quotations_count,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];
    }

    private function formatCustomer(Customer $customer, bool $includeAddresses = false): array
    {
        $data = [
            'id' => $customer->id,
            'company_name' => $customer->company_name,
            'email' => $customer->email,
            'secondary_email' => $customer->secondary_email,
            'contact_number' => $customer->contact_number,
            'secondary_contact' => $customer->secondary_contact,
            'tin_number' => $customer->tin_number,
            'is_active' => $customer->is_active,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];

        if ($includeAddresses) {
            $data['addresses'] = $customer->addresses->map(fn (Address $address): array => $this->formatAddress($address))->values()->all();
        }

        return $data;
    }

    private function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'address_type_id' => $address->address_type_id,
            'address_type_name' => $address->addressType?->name,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'country_id' => $address->country_id,
            'country_name' => $address->country?->name,
            'state_id' => $address->state_id,
            'state_name' => $address->state?->name,
            'city' => $address->city,
            'postal_code' => $address->postal_code,
        ];
    }
}
