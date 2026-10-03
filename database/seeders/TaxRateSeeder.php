<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * The standard 18% GST on trusteeship services since GST began (1 July 2017). Only seeded into an
 * empty table; the business confirms the rate in Tax settings (see docs/PLAN.md §6).
 */
class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        if (TaxRate::query()->exists()) {
            return;
        }

        TaxRate::query()->create(['effective_from' => '2017-07-01', 'cgst' => '9.00', 'sgst' => '9.00', 'igst' => '18.00']);
    }
}
