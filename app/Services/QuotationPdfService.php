<?php

namespace App\Services;

use App\Models\CompanyDetail;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotationPdfService
{
    public function __construct(private StorageService $storageService) {}

    public function generate(Quotation $quotation): string
    {
        $quotation->loadMissing([
            'customer',
            'status',
            'authorizedBy',
            'items' => fn ($query) => $query->orderBy('sort_order'),
            'items.product:id,title',
        ]);

        $company = CompanyDetail::query()->firstOrCreate(
            ['id' => 'singleton'],
            ['name' => 'Shree Axar Furniture']
        );

        Storage::disk('public')->makeDirectory('temp');

        $pdfPath = storage_path('app/public/temp/quotation-'.$quotation->getKey().'-'.time().'.pdf');

        $logoBase64 = $this->storageService->resolveBrandLogoBase64($company->logo);

        Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'company' => $company,
            'logoBase64' => $logoBase64,
            'bank' => $quotation->bank_snapshot ?? null,
        ])
            ->setPaper('a4')
            ->save($pdfPath);

        return $pdfPath;
    }
}
