<?php

namespace Database\Seeders;

use App\Models\QuotationStatus;
use Illuminate\Database\Seeder;

class QuotationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Requested for Quotation', 'color' => '#0EA5E9', 'is_default' => false, 'is_system' => true, 'sort_order' => 0],
            ['name' => 'Drafted', 'color' => '#6B7280', 'is_default' => true, 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Sent', 'color' => '#3B82F6', 'is_default' => false, 'is_system' => true, 'sort_order' => 2],
            ['name' => 'Awaiting Customer Response', 'color' => '#F59E0B', 'is_default' => false, 'is_system' => false, 'sort_order' => 3],
            ['name' => 'Under Negotiation', 'color' => '#8B5CF6', 'is_default' => false, 'is_system' => false, 'sort_order' => 4],
            ['name' => 'Approved', 'color' => '#10B981', 'is_default' => false, 'is_system' => true, 'sort_order' => 5],
            ['name' => 'Accepted', 'color' => '#059669', 'is_default' => false, 'is_system' => true, 'sort_order' => 6],
            ['name' => 'Rejected', 'color' => '#EF4444', 'is_default' => false, 'is_system' => true, 'sort_order' => 7],
        ];

        foreach ($statuses as $status) {
            QuotationStatus::updateOrCreate(['name' => $status['name']], $status);
        }
    }
}
