<?php

namespace Database\Seeders;

use App\Enums\AddressType;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\ContactType;
use App\Models\State;
use Database\Factories\CompanyGstinFactory;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Made-up companies, GSTINs, addresses and contacts so screens can be reviewed with realistic
 * content. Local only:  php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoSeeder only runs in the local environment.');
        }

        $this->call(StateSeeder::class);

        $types = collect(['Management', 'Finance', 'Accounts', 'Compliance', 'Operations'])
            ->map(fn (string $name) => ContactType::query()->firstOrCreate(['name' => $name]));
        $states = State::query()->whereIn('gst_code', ['27', '29', '07', '33', '24', '36'])->get();

        Company::factory()->count(24)->create()->each(function (Company $company, int $i) use ($types, $states) {
            $home = $states[$i % $states->count()];
            $gstins = collect([$home, $states[($i + 2) % $states->count()]])
                ->take($i % 3 === 0 ? 2 : 1)
                ->values()
                ->map(fn (State $state, int $n) => CompanyGstin::factory()->for($company)->create([
                    'state_id' => $state->id,
                    'gstin' => CompanyGstinFactory::gstinFor($state->gst_code, (string) $company->pan, (string) ($n + 1)),
                    'legal_name' => strtoupper($company->name),
                ]));

            CompanyAddress::factory()->for($company)->create([
                'type' => AddressType::Registered,
                'state_id' => $home->id,
                'city' => fake()->city(),
                'pincode' => (string) fake()->numberBetween(110001, 699999),
            ]);
            foreach ($gstins as $gstin) {
                CompanyAddress::factory()->for($company)->create([
                    'state_id' => $gstin->state_id,
                    'company_gstin_id' => $gstin->id,
                    'billing_name' => $i % 4 === 0 ? strtoupper($company->name).' (BILLING)' : null,
                    'city' => fake()->city(),
                    'pincode' => (string) fake()->numberBetween(110001, 699999),
                ]);
            }

            CompanyContact::factory()->for($company)->count(fake()->numberBetween(1, 4))->create([
                'contact_type_id' => fn () => $types->random()->id,
                'salutation' => fn () => fake()->randomElement(['Mr', 'Ms', 'Dr', null]),
                'designation' => fn () => fake()->randomElement(['Company Secretary', 'CFO', 'Manager - Finance', 'Director']),
            ]);
        });

        Company::factory()->other()->count(4)->create();
        Company::query()->inRandomOrder()->limit(3)->get()->each->update(['is_active' => false]);
    }
}
