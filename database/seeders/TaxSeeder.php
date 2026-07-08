<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        Tax::updateOrCreate(['name' => 'VAT'], ['rate' => 18.00, 'is_default' => true, 'is_fixed' => true]);
    }
}
