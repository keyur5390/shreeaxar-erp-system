<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\AddressType;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Product;
use App\Models\State;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $unit = Unit::query()->where('code', 'EACH')->firstOrFail();

        $products = [
            ['title' => 'Office Chair', 'rate' => 45000, 'model_number' => 'SAF-CHAIR-001'],
            ['title' => 'Executive Desk', 'rate' => 180000, 'model_number' => 'SAF-DESK-001'],
            ['title' => 'Bookshelf', 'rate' => 75000, 'model_number' => 'SAF-SHELF-001'],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['model_number' => $product['model_number']],
                $product + ['unit_id' => $unit->id, 'is_active' => true]
            );
        }

        $country = Country::query()->where('iso_code', 'RWA')->firstOrFail();
        $state = State::query()->where('country_id', $country->id)->where('name', 'Kigali City')->firstOrFail();
        $billingType = AddressType::query()->where('name', 'Billing Address')->firstOrFail();
        $shippingType = AddressType::query()->where('name', 'Shipping Address')->firstOrFail();

        $customers = [
            [
                'customer' => ['company_name' => 'Kigali Office Solutions', 'email' => 'procurement@kigalioffice.example', 'contact_number' => '+250 788 111 111', 'tin_number' => 'TIN-SAMPLE-001', 'is_active' => true],
                'address' => ['address_type_id' => $billingType->id, 'address_line_1' => 'KG 9 Avenue, Nyarutarama', 'country_id' => $country->id, 'state_id' => $state->id, 'city' => 'Kigali', 'postal_code' => '00000'],
            ],
            [
                'customer' => ['company_name' => 'Rwanda Workspace Ltd', 'email' => 'orders@rwandaworkspace.example', 'contact_number' => '+250 788 222 222', 'tin_number' => 'TIN-SAMPLE-002', 'is_active' => true],
                'address' => ['address_type_id' => $shippingType->id, 'address_line_1' => 'KN 3 Road, Kiyovu', 'country_id' => $country->id, 'state_id' => $state->id, 'city' => 'Kigali', 'postal_code' => '00000'],
            ],
        ];

        foreach ($customers as $data) {
            $customer = Customer::updateOrCreate(['email' => $data['customer']['email']], $data['customer']);
            $address = Address::updateOrCreate(
                ['address_line_1' => $data['address']['address_line_1'], 'country_id' => $country->id],
                $data['address']
            );
            $customer->addresses()->syncWithoutDetaching([$address->id]);
        }
    }
}
