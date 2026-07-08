<?php

namespace Database\Seeders;

use App\Models\CompanyDetail;
use Illuminate\Database\Seeder;

class CompanyDetailSeeder extends Seeder
{
    public function run(): void
    {
        CompanyDetail::updateOrCreate(
            ['id' => 'singleton'],
            ['name' => 'Shree Axar Furniture', 'email' => 'info@shreeaxar.com', 'phone' => '+250 788 000 000']
        );
    }
}
