<?php

namespace App\Services;

use App\Models\CompanyDetail;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\AmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QuotationPdfService
{
    public function __construct(private StorageService $storageService) {}

    public function generate(Quotation $quotation): string
    {
        $quotation->loadMissing([
            'customer',
            'status',
            'authorizedBy',
            'currency:id,code,symbol,decimal_places',
            'items' => fn ($query) => $query->orderBy('sort_order'),
            'items.product:id,title,primary_image',
        ]);

        $company = CompanyDetail::query()->firstOrCreate(
            ['id' => 'singleton'],
            [
                'name' => 'Shree Axar Studio',
                'email' => 'info@shreeaxar.com',
                'phone' => '+250 798 113 262',
                'website' => 'https://shreeaxar.com',
                'address' => 'Near Flyover, Kicukiro Kigali Centre, Kigali, Rwanda',
            ]
        );

        Storage::disk('public')->makeDirectory('temp');

        $pdfPath = storage_path('app/public/temp/quotation-'.$quotation->getKey().'-'.time().'.pdf');

        $hasItemImages = $this->quotationHasItemImages($quotation);
        $itemImages = $this->buildItemImages($quotation);
        $hasDiscount = $this->quotationHasDiscount($quotation);
        $logoPath = $this->storageService->resolveBrandLogoForPdf($company->logo);

        $this->assertPdfImageSupport($logoPath, $itemImages);

        $currencyCode = $quotation->currency_snapshot['code'] ?? $quotation->currency?->code ?? 'RWF';
        $decimalPlaces = (int) ($quotation->currency_snapshot['decimal_places'] ?? $quotation->currency?->decimal_places ?? 0);
        $itemColumnCount = 6 + ($hasItemImages ? 1 : 0) + ($hasDiscount ? 1 : 0);

        Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'company' => $company,
            'logoPath' => $logoPath,
            'bank' => $quotation->bank_snapshot ?? null,
            'amountInWords' => AmountInWords::format(
                (float) $quotation->total_amount,
                $currencyCode,
                $decimalPlaces
            ),
            'hasItemImages' => $hasItemImages,
            'itemImages' => $itemImages,
            'hasDiscount' => $hasDiscount,
            'itemColumnCount' => $itemColumnCount,
        ])
            ->setPaper('a4')
            ->setOption('chroot', realpath(base_path()) ?: base_path())
            ->save($pdfPath);

        return $pdfPath;
    }

    /**
     * @return array<string, string>
     */
    private function buildItemImages(Quotation $quotation): array
    {
        $images = [];

        foreach ($quotation->items as $item) {
            $path = $this->resolveItemImagePath($item);
            if ($path !== null) {
                $images[$item->id] = $path;
            }
        }

        return $images;
    }

    private function resolveItemImagePath(QuotationItem $item): ?string
    {
        if ($item->image_url) {
            $path = $this->storageService->preparePdfImage($item->image_url);
            if ($path !== null) {
                return $path;
            }
        }

        if ($item->product?->primary_image) {
            return $this->storageService->preparePdfImage($item->product->primary_image);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $itemImages
     */
    private function assertPdfImageSupport(?string $logoPath, array $itemImages): void
    {
        if ($logoPath === null && $itemImages === []) {
            return;
        }

        if (extension_loaded('gd') || extension_loaded('imagick')) {
            return;
        }

        $iniPath = php_ini_loaded_file() ?: 'unknown';

        throw new RuntimeException(
            'The PHP GD extension is required to embed logo and product images in quotation PDFs. '
            .'Loaded PHP '.PHP_VERSION.' using '.$iniPath.'. '
            .'In Laravel Herd, open Herd → PHP, confirm GD is enabled for PHP 8.4, run "herd restart", '
            .'then reload the site at http://shreeaxar-erp-system.test. '
            .'If you still use "php artisan serve", stop that process and start it again.'
        );
    }

    private function quotationHasItemImages(Quotation $quotation): bool
    {
        return $quotation->items->contains(function (QuotationItem $item): bool {
            if ($item->image_url !== null && trim($item->image_url) !== '') {
                return true;
            }

            return $item->product?->primary_image !== null
                && trim($item->product->primary_image) !== '';
        });
    }

    private function quotationHasDiscount(Quotation $quotation): bool
    {
        if ((float) $quotation->discount_amount > 0) {
            return true;
        }

        return $quotation->items->contains(
            fn (QuotationItem $item): bool => (float) $item->discount_rate > 0
        );
    }
}
