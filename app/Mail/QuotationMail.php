<?php

namespace App\Mail;

use App\Models\Quotation;
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
        return $this->subject($this->subjectText)
            ->view('emails.quotation')
            ->with([
                'quotation' => $this->quotation,
                'body' => $this->body,
            ])
            ->attach($this->pdfPath, [
                'as' => $this->quotation->quotation_number.'.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
