<?php

namespace App\Mail;

use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $otp) {}

    public function build(): self
    {
        return $this->subject('Your Shree Axar ERP OTP')
            ->view('emails.otp')
            ->with([
                'otp' => $this->otp,
                'logoBase64' => app(StorageService::class)->resolveBrandLogoBase64(),
            ]);
    }
}
