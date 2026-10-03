<?php

namespace Database\Factories;

use App\Enums\DealStatus;
use App\Enums\IssueType;
use App\Enums\Listing;
use App\Enums\Origin;
use App\Enums\TransactionStatus;
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

    /**
     * A deal whose engagement letter was issued on 1 Apr 2025, now at $status, with a ₹200 crore
     * issue. Saves going through approval and letter issue in tests that start from a live deal.
     */
    public function deal(DealStatus $status = DealStatus::Preliminary, Listing $listing = Listing::Unlisted): static
    {
        return $this->state(fn () => [
            'status' => $status->isFinal() ? TransactionStatus::Closed : TransactionStatus::Active,
            'deal_status' => $status,
            'deal_status_since' => '2025-04-01',
            'el_number' => 'BTL/DEB/EL/25-26/'.fake()->unique()->numberBetween(1, 999999),
            'el_date' => '2025-04-01',
            'approved_at' => '2025-03-28 10:00:00',
        ])->afterCreating(function (Transaction $deal) use ($status, $listing) {
            $deal->forceFill(['deal_code' => "DEB/25-26/{$deal->id}"])->save();
            $deal->issueDetail()->create([
                'listing' => $listing, 'issue_type' => IssueType::PrivatePlacement, 'is_secured' => true, 'is_rated' => false,
                'base_issue_size' => '2000000000', 'green_shoe_size' => '0', 'total_issue_size' => '2000000000',
                'tenure_months' => 36, 'tenure_days' => 0,
            ]);
            $deal->statusChanges()->create(['to_status' => $status, 'effective_on' => '2025-04-01', 'changed_by' => $deal->created_by]);
        });
    }
}
