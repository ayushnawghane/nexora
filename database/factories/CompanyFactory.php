<?php

namespace Database\Factories;

use App\Enums\CompanyCategory;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * A private unlisted company with a valid, consistent CIN and company PAN.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(1990, 2020);

        return [
            'entity_type' => EntityType::Company,
            'cin' => 'U'.fake()->numerify('#####').'MH'.$year.'PTC'.fake()->unique()->numerify('######'),
            'name' => fake()->company().' Private Limited',
            'pan' => fake()->unique()->regexify('[A-Z]{3}C[A-Z][0-9]{4}[A-Z]'),
            'company_class' => CompanyClass::Private,
            'category' => CompanyCategory::LimitedByShares,
            'incorporated_on' => fake()->dateTimeBetween("{$year}-01-01", "{$year}-12-31")->format('Y-m-d'),
            'is_listed' => false,
            'is_active' => true,
        ];
    }

    public function other(): static
    {
        return $this->state(fn () => [
            'entity_type' => EntityType::Other,
            'cin' => null,
            'name' => fake()->company().' Trust',
            'pan' => null,
            'company_class' => null,
            'category' => null,
            'incorporated_on' => null,
        ]);
    }
}
