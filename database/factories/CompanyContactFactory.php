<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyContact>
 */
class CompanyContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '+919'.fake()->numerify('#########'),
            'is_active' => true,
        ];
    }
}
