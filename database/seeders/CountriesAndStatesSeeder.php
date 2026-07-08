<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;

class CountriesAndStatesSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Rwanda', 'iso_code' => 'RWA', 'states' => ['Kigali City', 'Northern', 'Southern', 'Eastern', 'Western']],
            ['name' => 'Uganda', 'iso_code' => 'UGA', 'states' => ['Central', 'Eastern', 'Northern', 'Western']],
            ['name' => 'Kenya', 'iso_code' => 'KEN', 'states' => ['Nairobi', 'Mombasa', 'Kisumu']],
        ];

        foreach ($countries as $countryData) {
            $country = Country::updateOrCreate(
                ['iso_code' => $countryData['iso_code']],
                ['name' => $countryData['name'], 'iso_code' => $countryData['iso_code']]
            );

            foreach ($countryData['states'] as $stateName) {
                State::updateOrCreate(
                    ['name' => $stateName, 'country_id' => $country->id],
                    ['name' => $stateName, 'country_id' => $country->id]
                );
            }
        }
    }
}
