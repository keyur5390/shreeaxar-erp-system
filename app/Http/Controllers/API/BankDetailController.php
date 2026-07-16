<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\BankDetailRequest;
use App\Models\BankDetail;
use App\Models\Quotation;
use App\Support\MasterCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BankDetailController extends BaseController
{
    public function __construct()
    {
        $this->middleware('permission:view bank_details')->only(['index']);
        $this->middleware('permission:create bank_details')->only(['store']);
        $this->middleware('permission:edit bank_details')->only(['update', 'setPrimary']);
        $this->middleware('permission:delete bank_details')->only(['destroy']);
    }

    public function index(): JsonResponse
    {
        $banks = MasterCache::remember('masters.bank_details', fn (): array => BankDetail::query()
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('bank_name')
            ->get()
            ->map(fn (BankDetail $bank): array => $this->formatBank($bank))
            ->all());

        return $this->successResponse($banks);
    }

    public function store(BankDetailRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = true;

        if (! BankDetail::query()->exists()) {
            $data['is_primary'] = true;
        } elseif (! isset($data['is_primary'])) {
            $data['is_primary'] = false;
        }

        $bank = BankDetail::create($data);

        if ($data['is_primary'] ?? false) {
            $this->setPrimaryBank($bank);
            $bank->refresh();
        }

        MasterCache::forget('masters.bank_details');

        return $this->successResponse(
            $this->formatBank($bank),
            'Bank detail created successfully.',
            201
        );
    }

    public function update(BankDetailRequest $request, string $id): JsonResponse
    {
        $bank = BankDetail::query()->findOrFail($id);
        $data = $request->validated();

        if (($data['is_primary'] ?? false) === true) {
            DB::transaction(function () use ($bank, $data): void {
                BankDetail::query()
                    ->where('id', '!=', $bank->id)
                    ->update(['is_primary' => false]);

                $bank->update(array_merge($data, ['is_primary' => true]));
            });

            $bank->refresh();
        } else {
            unset($data['is_primary']);
            $bank->update($data);
        }

        MasterCache::forget('masters.bank_details');

        return $this->successResponse(
            $this->formatBank($bank),
            'Bank detail updated successfully.'
        );
    }

    public function setPrimary(string $id): JsonResponse
    {
        $bank = BankDetail::query()->findOrFail($id);
        $this->setPrimaryBank($bank);
        $bank->refresh();
        MasterCache::forget('masters.bank_details');

        return $this->successResponse(
            $this->formatBank($bank),
            'Primary bank updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $bank = BankDetail::query()->findOrFail($id);
        $quotationCount = Quotation::query()->where('bank_detail_id', $id)->count();

        if ($quotationCount > 0) {
            $wasPrimary = $bank->is_primary;
            $bank->update(['is_active' => false, 'is_primary' => false]);

            if ($wasPrimary) {
                BankDetail::query()
                    ->where('is_active', true)
                    ->where('id', '!=', $bank->id)
                    ->orderBy('bank_name')
                    ->first()
                    ?->update(['is_primary' => true]);
            }

            MasterCache::forget('masters.bank_details');

            return $this->successResponse(
                $this->formatBank($bank->fresh()),
                'Bank detail deactivated because it is used by existing quotations.'
            );
        }

        if ($bank->is_primary) {
            BankDetail::query()
                ->where('is_active', true)
                ->where('id', '!=', $bank->id)
                ->orderBy('bank_name')
                ->first()
                ?->update(['is_primary' => true]);
        }

        $bank->delete();
        MasterCache::forget('masters.bank_details');

        return $this->successResponse(null, 'Bank detail deleted successfully.');
    }

    private function setPrimaryBank(BankDetail $bank): void
    {
        DB::transaction(function () use ($bank): void {
            BankDetail::query()
                ->where('id', '!=', $bank->id)
                ->update(['is_primary' => false]);

            $bank->update(['is_primary' => true, 'is_active' => true]);
        });
    }

    private function formatBank(BankDetail $bank): array
    {
        return [
            'id' => $bank->id,
            'bank_name' => $bank->bank_name,
            'account_number' => $bank->account_number,
            'account_holder_name' => $bank->account_holder_name,
            'branch_name' => $bank->branch_name,
            'swift_code' => $bank->swift_code,
            'is_active' => $bank->is_active,
            'is_primary' => $bank->is_primary,
            'created_at' => $bank->created_at,
            'updated_at' => $bank->updated_at,
        ];
    }
}
