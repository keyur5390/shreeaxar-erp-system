<?php

namespace App\Console\Commands;

use App\Models\OtpRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanExpiredOtps extends Command
{
    protected $signature = 'otp:clean';

    protected $description = 'Delete expired OTP records.';

    public function handle(): int
    {
        $count = OtpRecord::query()->where('expires_at', '<', now())->delete();

        Log::info('Expired OTP records cleaned.', ['count' => $count]);
        $this->info("Cleaned {$count} expired OTP record(s).");

        return self::SUCCESS;
    }
}
