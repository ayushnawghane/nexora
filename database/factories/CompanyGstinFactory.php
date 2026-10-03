<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyGstin;
use App\Models\State;
use App\Support\IndianIdentifiers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a GSTIN with a correct check character from the company's PAN and the state's GST code.
 * Needs the states table seeded (StateSeeder).
 *
 * @extends Factory<CompanyGstin>
 */
class CompanyGstinFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'state_id' => fn () => State::query()->where('gst_code', '27')->value('id'),
            'gstin' => function (array $attributes) {
                $pan = Company::query()->findOrFail($attributes['company_id'])->pan;
                $code = State::query()->findOrFail($attributes['state_id'])->gst_code;

                return self::gstinFor($code, (string) $pan, (string) fake()->numberBetween(1, 9));
            },
            'legal_name' => fake()->company(),
            'is_active' => true,
        ];
    }

    public static function gstinFor(string $stateCode, string $pan, string $entityNumber = '1'): string
    {
        $first14 = $stateCode.$pan.$entityNumber.'Z';

        return $first14.IndianIdentifiers::gstinCheckCharacter($first14);
    }
}
