<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        Currency::updateOrCreate(
            ['code' => 'RWF'],
            [
                'name' => 'Rwandan Franc',
                'symbol' => 'RWF',
                'decimal_places' => 0,
                'exchange_rate' => 1,
                'is_default' => true,
                'is_active' => true,
            ]
        );

        Currency::updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'exchange_rate' => 1300,
                'is_default' => false,
                'is_active' => true,
            ]
        );

        Currency::updateOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'exchange_rate' => 1450,
                'is_default' => false,
                'is_active' => true,
            ]
        );
    }
}
