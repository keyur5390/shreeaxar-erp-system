<?php

namespace Database\Seeders;

use App\Models\AddressType;
use Illuminate\Database\Seeder;

class AddressTypesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Billing Address', 'Shipping Address', 'Office Address'] as $name) {
            AddressType::updateOrCreate(['name' => $name], ['name' => $name]);
        }
    }
}
