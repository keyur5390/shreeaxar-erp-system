<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Mail\QuotationMail;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    public function __construct(private QuotationPdfService $quotationPdfService) {}

    public function sendQuotationEmail(Quotation $quotation, string $to, array $cc, string $subject, string $body): void
    {
        $pdfPath = $this->quotationPdfService->generate($quotation);

        try {
            $this->sendWithRetry(function () use ($quotation, $to, $cc, $subject, $body, $pdfPath): void {
                Mail::to($to)
                    ->cc($cc)
                    ->send(new QuotationMail($quotation, $to, $cc, $subject, $body, $pdfPath));
            });
        } finally {
            if (is_file($pdfPath)) {
                @unlink($pdfPath);
            }
        }
    }

    public function generateQuotationPdf(Quotation $quotation): string
    {
        return $this->quotationPdfService->generate($quotation);
    }

    public function sendOtpEmail(string $email, string $otp): void
    {
        $this->sendWithRetry(function () use ($email, $otp): void {
            Mail::to($email)->send(new OtpMail($otp));
        });
    }

    public function sendExpiryReminder(Quotation $quotation, User $user): void
    {
        $subject = 'Quotation expiry reminder: '.$quotation->quotation_number;
        $body = 'This is a reminder that quotation '.$quotation->quotation_number.' is approaching its expiry date.';
        $this->sendQuotationEmail($quotation, $user->email, [], $subject, $body);
    }

    private function sendWithRetry(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            sleep(5);

            try {
                $callback();
            } catch (Throwable) {
                throw $exception;
            }
        }
    }
}
