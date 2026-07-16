<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\CompanyDetailRequest;
use App\Models\CompanyDetail;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyDetailController extends BaseController
{
    public function __construct(private readonly StorageService $storageService)
    {
        $this->middleware('permission:view company_detail')->only(['show']);
        $this->middleware('permission:edit company_detail')->only(['update', 'uploadLogo']);
    }

    public function show(): JsonResponse
    {
        $company = $this->getSingleton();

        return $this->successResponse($this->formatCompany($company));
    }

    public function update(CompanyDetailRequest $request): JsonResponse
    {
        $company = $this->getSingleton();
        $company->update($request->validated());

        return $this->successResponse(
            $this->formatCompany($company),
            'Company details updated successfully.'
        );
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $company = $this->getSingleton();
        $oldLogo = $company->logo;

        $path = $this->storageService->resizeAndStore($request->file('logo'), 'company', 400);
        $company->update(['logo' => $path]);
        $this->storageService->deleteImage($oldLogo);

        return $this->successResponse(
            $this->formatCompany($company),
            'Company logo updated successfully.'
        );
    }

    private function getSingleton(): CompanyDetail
    {
        return CompanyDetail::query()->firstOrCreate(
            ['id' => 'singleton'],
            ['name' => 'Shree Axar Furniture']
        );
    }

    private function formatCompany(CompanyDetail $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'logo' => $company->logo,
            'logo_url' => $this->storageService->getUrl($company->logo),
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'tin_number' => $company->tin_number,
            'vat_number' => $company->vat_number,
            'website' => $company->website,
            'updated_at' => $company->updated_at,
        ];
    }
}
