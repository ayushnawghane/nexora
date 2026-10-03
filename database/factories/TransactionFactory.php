<?php

namespace Database\Factories;

use App\Enums\Origin;
use App\Models\Company;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vertical;
use App\Models\VerticalTeam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A DT draft with just its basics. Issue details, contacts and fees are added through the
 * actions in tests, the way the wizard adds them.
 *
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => fn () => Product::query()->firstOrCreate(['code' => Product::DEBENTURE_TRUSTEE], ['name' => 'Debenture Trustee'])->id,
            'company_id' => Company::factory(),
            'vertical_team_id' => fn () => VerticalTeam::query()->firstOrCreate(
                ['name' => 'DT Team'],
                ['vertical_id' => Vertical::query()->firstOrCreate(['code' => 'DT'], ['name' => 'Debenture Trustee'])->id],
            )->id,
            'relationship_manager_id' => User::factory(),
            'origin' => Origin::BusinessDevelopment,
            'created_by' => User::factory(),
        ];
    }
}
