<?php

namespace Database\Seeders;

use App\Models\Counter;
use Illuminate\Database\Seeder;

class CounterSeeder extends Seeder
{
    public function run(): void
    {
        Counter::updateOrCreate(['key' => 'quotation_sequence'], ['value' => 0]);
    }
}
