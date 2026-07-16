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
        $targetDate = now()->addDays(3)->toDateString();

        $quotations = Quotation::query()
            ->whereDate('expiry_date', $targetDate)
            ->whereHas('status', fn ($query) => $query->whereNotIn('name', ['Accepted', 'Rejected']))
            ->where(function ($query): void {
                $query->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<', now()->subDays(7));
            })
            ->with(['authorizedBy', 'customer', 'status'])
            ->get();

        $sent = 0;

        foreach ($quotations as $quotation) {
            if ($quotation->authorizedBy === null) {
                continue;
            }

            $emailService->sendExpiryReminder($quotation, $quotation->authorizedBy);
            $quotation->update(['reminder_sent_at' => now()]);
            $sent++;
        }

        Log::info('Quotation expiry reminders sent.', ['matched' => $quotations->count(), 'sent' => $sent]);
        $this->info('Sent reminders for '.$sent.' quotations.');

        return self::SUCCESS;
    }
}
