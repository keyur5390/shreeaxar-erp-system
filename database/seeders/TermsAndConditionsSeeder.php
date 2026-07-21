<?php

namespace Database\Seeders;

use App\Models\TermsAndCondition;
use Illuminate\Database\Seeder;

class TermsAndConditionsSeeder extends Seeder
{
    public function run(): void
    {
        TermsAndCondition::updateOrCreate(
            ['name' => 'Standard Terms'],
            [
                'content' => implode("\n", [
                    '1. Prices are valid for 30 days from the quotation date unless otherwise stated.',
                    '2. Payment terms: 50% advance, balance before delivery.',
                    '3. Delivery timeline will be confirmed upon order confirmation.',
                    '4. Goods remain the property of Shree Axar Furniture until full payment is received.',
                    '5. Any customization requests may affect pricing and delivery schedule.',
                ]),
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }
}
