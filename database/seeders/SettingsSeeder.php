<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Settings::updateOrCreate(['key' => 'quotation_default_expiry_days'], ['value' => '30']);
    }
}
