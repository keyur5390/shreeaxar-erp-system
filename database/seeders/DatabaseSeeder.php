<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentsSeeder::class,
            UnitsSeeder::class,
            TaxSeeder::class,
            AddressTypesSeeder::class,
            CountriesAndStatesSeeder::class,
            QuotationStatusSeeder::class,
            CompanyDetailSeeder::class,
            CounterSeeder::class,
            SettingsSeeder::class,
            BankDetailSeeder::class,
            UserSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
}
