<?php

namespace Database\Seeders;

use App\Models\BankDetail;
use Illuminate\Database\Seeder;

class BankDetailSeeder extends Seeder
{
    public function run(): void
    {
        BankDetail::updateOrCreate(
            ['account_number' => '000123456789'],
            [
                'bank_name' => 'Bank of Kigali',
                'account_number' => '000123456789',
                'account_holder_name' => 'Shree Axar Furniture',
                'branch_name' => 'Kigali Main Branch',
                'swift_code' => 'BKIGRWRW',
                'is_active' => true,
                'is_primary' => true,
            ]
        );
    }
}
