<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Sales', 'Finance', 'Management'] as $name) {
            Department::updateOrCreate(['name' => $name], ['name' => $name]);
        }
    }
}
