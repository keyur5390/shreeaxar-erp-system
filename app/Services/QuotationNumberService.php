<?php

namespace App\Services;

use App\Models\Counter;
use Illuminate\Support\Facades\DB;

class QuotationNumberService
{
    /**
     * Generate the next quotation number using a pessimistic row-level lock.
     *
     * The counters table must use an engine that supports row-level locks, such
     * as InnoDB. Sequence gaps are acceptable: once the counter value is
     * incremented, failed downstream work may leave an unused quotation number.
     */
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $counter = Counter::where('key', 'quotation_sequence')
                ->lockForUpdate()
                ->firstOrCreate(['key' => 'quotation_sequence'], ['value' => 0]);

            $counter->increment('value');
            $counter->refresh();

            return 'QT-'.date('Y').'-'.str_pad((string) $counter->value, 6, '0', STR_PAD_LEFT);
        }, 5);
    }
}
