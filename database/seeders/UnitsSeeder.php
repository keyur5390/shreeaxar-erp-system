<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'EACH', 'name' => 'Each', 'is_default' => true],
            ['code' => 'SET', 'name' => 'Set', 'is_default' => false],
            ['code' => 'PCS', 'name' => 'Pieces', 'is_default' => false],
            ['code' => 'BOX', 'name' => 'Box', 'is_default' => false],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
