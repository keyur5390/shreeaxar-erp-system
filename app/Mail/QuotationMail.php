<?php

namespace App\Mail;

use App\Models\CompanyDetail;
use App\Models\Quotation;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public string $toAddress,
        public array $ccAddresses,
        public string $subjectText,
        public string $body,
        public string $pdfPath,
    ) {}

    public function build(): self
    {
        $company = CompanyDetail::query()->first();
        $logoBase64 = app(StorageService::class)->resolveBrandLogoBase64($company?->logo);

        return $this->subject($this->subjectText)
            ->view('emails.quotation')
            ->with([
                'quotation' => $this->quotation,
                'body' => $this->body,
                'logoBase64' => $logoBase64,
            ])
            ->attach($this->pdfPath, [
                'as' => $this->quotation->quotation_number.'.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
