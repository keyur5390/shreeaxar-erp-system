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
            [
                'name' => 'Shree Axar Studio',
                'email' => 'info@shreeaxar.com',
                'phone' => '+250 798 113 262',
                'website' => 'https://shreeaxar.com',
                'address' => 'Near Flyover, Kicukiro Kigali Centre, Kigali, Rwanda',
            ]
        );
    }
}
