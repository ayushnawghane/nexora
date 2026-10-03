<?php

namespace Database\Factories;

use App\Enums\AddressType;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs the states table seeded (StateSeeder).
 *
 * @extends Factory<CompanyAddress>
 */
class CompanyAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => AddressType::Billing,
            'line1' => fake()->streetAddress(),
            'city' => 'Mumbai',
            'pincode' => '400001',
            'state_id' => fn () => State::query()->where('gst_code', '27')->value('id'),
            'is_active' => true,
        ];
    }
}
