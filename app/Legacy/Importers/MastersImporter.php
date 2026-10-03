<?php

namespace App\Legacy\Importers;

use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\Arranger;
use App\Models\Bank;
use App\Models\ContactType;
use App\Models\LeadSource;
use App\Models\Pincode;
use App\Models\State;
use App\Models\TransactionType;
use App\Support\IndianIdentifiers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lead sources, arrangers, banks, contact types, transaction types and pincodes.
 * Names that contain markup (test entries such as "<script>…") are rejected.
 */
class MastersImporter extends Importer
{
    /** Nexora model => [legacy table, name column, report label, max length]. */
    private const SIMPLE = [
        LeadSource::class => ['master_lead', 'lead_name', 'lead sources', 150],
        ContactType::class => ['master_contact_type', 'contact_type', 'contact types', 150],
        TransactionType::class => ['master_transaction_type', 'transaction_type', 'transaction types', 150],
        Arranger::class => ['master_arranger', 'arranger_name', 'arrangers', 200],
        Bank::class => ['master_bank', 'bank_name', 'banks', 200],
    ];

    public function area(): string
    {
        return 'masters';
    }

    public function description(): string
    {
        return 'Lead sources, contact types, transaction types, arrangers, banks and pincodes';
    }

    protected function import(): void
    {
        foreach (self::SIMPLE as $class => [$table, $column, $label, $max]) {
            $this->simple($class, $table, $column, $label, $max);
        }
        $this->pincodes();
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function simple(string $class, string $table, string $column, string $label, int $max): void
    {
        $hasCin = in_array($class, [Arranger::class, Bank::class], true);
        $cinColumn = $class === Arranger::class ? 'arranger_cin' : 'cin';
        $names = [];

        foreach (LegacyRecord::from($table)->orderBy('id')->get() as $row) {
            $this->report->read($label);
            $name = (string) $row->text($column);

            if ($name === '' || $name !== strip_tags($name)) {
                $this->report->reject($label, $row->id, $name === '' ? 'No name.' : "Name \"{$name}\" contains markup.");

                continue;
            }
            $key = Str::lower($name);
            if (isset($names[$key])) {
                $this->report->reject($label, $row->id, "Same name as legacy #{$names[$key]}.");

                continue;
            }
            $names[$key] = $row->id;

            $attributes = ['name' => Str::limit($name, $max, ''), 'is_active' => $row->isLegacyActive()];
            if ($hasCin) {
                $cin = IndianIdentifiers::normalise($row->text($cinColumn));
                if ($cin !== null && ! preg_match(IndianIdentifiers::CIN_PATTERN, $cin)) {
                    $this->report->warn($label, $row->id, "CIN \"{$cin}\" isn't a valid CIN; left empty.");
                    $cin = null;
                }
                $attributes['cin'] = $cin;
            }

            $this->claim($class, $row->id, 'name', $attributes['name']);
            $this->upsert($class, $row->id, $attributes, $label);
        }
    }

    private function pincodes(): void
    {
        $states = State::query()->get()->mapWithKeys(fn (State $s) => [$this->stateKey($s->name) => $s->id]);
        $seen = [];

        LegacyRecord::from('master_pincode')->orderBy('id')->chunk(2000, function ($rows) use ($states, &$seen) {
            foreach ($rows as $row) {
                $this->report->read('pincodes');
                $pincode = (string) $row->text('pincode');
                $city = Str::limit((string) $row->text('city'), 120, '');
                $state = $states[$this->stateKey((string) $row->text('state'))] ?? null;

                if (! preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
                    $this->report->reject('pincodes', $row->id, "\"{$pincode}\" isn't a 6-digit Indian pincode.");

                    continue;
                }
                if ($state === null) {
                    $this->report->reject('pincodes', $row->id, "Unknown state \"{$row->text('state')}\".");

                    continue;
                }
                if ($city === '') {
                    $this->report->reject('pincodes', $row->id, 'No city.');

                    continue;
                }
                $key = $pincode.'|'.Str::lower($city);
                if (isset($seen[$key])) {
                    $this->report->reject('pincodes', $row->id, "Duplicate of legacy #{$seen[$key]}.");

                    continue;
                }
                $seen[$key] = $row->id;

                // A pincode added in Nexora before the import is linked rather than duplicated.
                Pincode::query()->whereNull('legacy_id')->where('pincode', $pincode)->where('city', $city)
                    ->first()?->forceFill(['legacy_id' => $row->id])->save();

                $this->upsert(Pincode::class, $row->id, [
                    'pincode' => $pincode, 'city' => $city, 'state_id' => $state, 'is_active' => $row->isLegacyActive(),
                ], 'pincodes');
            }
        });
    }

    /** "The Dadra And Nagar Haveli…" and "Dadra and Nagar Haveli…" are the same state. */
    private function stateKey(string $name): string
    {
        return (string) preg_replace('/^the /', '', Str::lower(trim($name)));
    }
}
