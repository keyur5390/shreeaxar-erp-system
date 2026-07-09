<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExpiryReminders extends Command
{
    protected $signature = 'quotations:send-reminders';

    protected $description = 'Send reminders for quotations expiring in three days.';

    public function handle(EmailService $emailService): int
    {
        $reminded = 0;

        Quotation::query()
            ->with(['authorizedBy', 'status'])
            ->whereDate('expiry_date', now()->addDays(3)->toDateString())
            ->where(function ($query): void {
                $query->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<', now()->subDays(7));
            })
            ->whereHas('authorizedBy')
            ->whereDoesntHave('status', function ($query): void {
                $query->whereIn('name', ['Accepted', 'Rejected']);
            })
            ->each(function (Quotation $quotation) use ($emailService, &$reminded): void {
                $emailService->sendExpiryReminder($quotation, $quotation->authorizedBy);
                $quotation->forceFill(['reminder_sent_at' => now()])->save();
                $reminded++;
            });

        Log::info('Quotation expiry reminders sent.', ['count' => $reminded]);
        $this->info("Sent {$reminded} quotation expiry reminder(s).");

        return self::SUCCESS;
    }
}
