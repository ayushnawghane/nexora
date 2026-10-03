<?php

namespace App\Legacy\Importers;

use App\Enums\AddressType;
use App\Enums\CompanyCategory;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\ContactType;
use App\Models\Pincode;
use App\Models\State;
use App\Support\IndianIdentifiers;
use Illuminate\Support\Str;

/**
 * Companies (master_cin), their GSTINs (master_gst_no), billing addresses (company_address_master),
 * registered offices (master_cin.address) and contacts (company_contact_master). Needs the masters
 * area first (pincodes, contact types).
 *
 * Rules (see docs/PLAN.md, M6):
 *  - A valid CIN makes a company, a valid LLPIN an LLP; anything else ("NA", "1234", blank) an
 *    "other entity" without a number.
 *  - The same CIN entered twice is merged into one company; the extra legacy id becomes an alias,
 *    so transactions pointing at either copy find it.
 *  - Invalid or repeated PANs are dropped; a missing PAN is taken from the company's GSTIN.
 *  - Addresses take city and state from the pincode (or the GSTIN's state); one that can't be
 *    placed is rejected. Contacts need an email or a mobile number.
 */
class CompaniesImporter extends Importer
{
    /** @var array<string, int> PAN => Nexora company id */
    private array $pans = [];

    /** @var array<string, array{city: string, state_id: int}> pincode => first known place */
    private array $places = [];

    public function area(): string
    {
        return 'companies';
    }

    public function description(): string
    {
        return 'Companies, GSTINs, addresses and contacts';
    }

    protected function import(): void
    {
        $this->places = Pincode::query()->orderBy('id')->get(['pincode', 'city', 'state_id'])
            ->unique('pincode')
            ->mapWithKeys(fn (Pincode $p) => [$p->pincode => ['city' => $p->city, 'state_id' => $p->state_id]])
            ->all();

        $this->companies();
        $this->gstins();
        $this->billingAddresses();
        $this->registeredOffices();
        $this->contacts();

        $this->report->total('Companies in Nexora', (string) Company::query()->count());
        $this->report->total('Legacy companies merged into a duplicate', (string) collect($this->report->counts()['merged duplicates'] ?? [])->only(['created', 'updated', 'unchanged'])->sum());
    }

    private function companies(): void
    {
        $cins = [];
        // Records still in use first, so they keep a CIN or PAN shared with a deleted duplicate.
        $rows = LegacyRecord::from('master_cin')->orderBy('is_deleted')->orderBy('id')->get();

        foreach ($rows as $row) {
            $this->report->read('companies');
            $name = Str::limit((string) $row->text('company_name'), 255, '');
            if ($name === '') {
                $this->report->reject('companies', $row->id, 'No company name.');

                continue;
            }

            $raw = IndianIdentifiers::normalise($row->text('cin'));
            $type = match (true) {
                $raw !== null && IndianIdentifiers::isCin($raw) => EntityType::Company,
                $raw !== null && IndianIdentifiers::isLlpin($raw) => EntityType::Llp,
                default => EntityType::Other,
            };
            $cin = $type === EntityType::Other ? null : $raw;
            if ($cin === null && $raw !== null && ! in_array($raw, ['NA', 'N/A', 'NULL', '-'], true)) {
                $this->report->warn('companies', $row->id, "\"{$raw}\" isn't a CIN or LLPIN; imported as an other entity.");
            }

            if ($cin !== null && isset($cins[$cin])) {
                $this->alias(Company::class, $row->id, $cins[$cin], "Same CIN {$cin} as another legacy company", 'merged duplicates');

                continue;
            }

            $this->claim(Company::class, $row->id, 'cin', $cin);
            $company = $this->upsert(Company::class, $row->id, [
                'entity_type' => $type,
                'cin' => $cin,
                'name' => $name,
                'formerly_known_as' => $this->formerName($row->text('formerly_known'), $name),
                'pan' => $this->pan($row->text('pan_number'), $row->id),
                'company_class' => $type === EntityType::Company ? IndianIdentifiers::cinClass((string) $cin) : $this->companyClass($row->text('company_class')),
                'category' => Str::lower((string) $row->text('category')) === 'company limited by shares' ? CompanyCategory::LimitedByShares : null,
                'incorporated_on' => $this->incorporatedOn($row, $type, $cin),
                'is_listed' => $type === EntityType::Company ? IndianIdentifiers::isListedCin((string) $cin) : in_array(Str::lower((string) $row->text('isListed')), ['listed', 'yes'], true),
                'is_active' => $row->isLegacyActive(),
            ], 'companies');

            if ($cin !== null) {
                $cins[$cin] = $company->id;
            }
            if ($company->pan !== null) {
                $this->pans[$company->pan] = $company->id;
            }
        }
    }

    private function pan(?string $value, int $legacyId): ?string
    {
        $pan = IndianIdentifiers::normalise($value);
        if ($pan === null) {
            return null;
        }
        if (! IndianIdentifiers::isPan($pan)) {
            $this->report->warn('companies', $legacyId, "PAN \"{$pan}\" is invalid; left empty.");

            return null;
        }
        $owner = $this->pans[$pan] ?? Company::query()->withTrashed()->where('pan', $pan)->where(fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', '!=', $legacyId))->value('id');
        if ($owner !== null) {
            $this->report->warn('companies', $legacyId, "PAN {$pan} is already another company's; left empty.");

            return null;
        }

        return $pan;
    }

    private function formerName(?string $former, string $name): ?string
    {
        return $former === null || Str::lower($former) === Str::lower($name) ? null : Str::limit($former, 255, '');
    }

    private function companyClass(?string $value): ?CompanyClass
    {
        return match (Str::lower((string) $value)) {
            'public' => CompanyClass::Public,
            'private' => CompanyClass::Private,
            default => null,
        };
    }

    /** A company's incorporation year must match its CIN (the same rule as the company form). */
    private function incorporatedOn(LegacyRecord $row, EntityType $type, ?string $cin): ?string
    {
        $date = $row->date('incorp_date');
        if ($date !== null && $type === EntityType::Company && (int) substr($date, 0, 4) !== IndianIdentifiers::cinYear((string) $cin)) {
            $this->report->warn('companies', $row->id, "Incorporation date {$date} doesn't match the year in the CIN; left empty.");

            return null;
        }

        return $date;
    }

    private function gstins(): void
    {
        $states = State::query()->pluck('id', 'gst_code');
        $seen = []; // GSTIN => Nexora id

        foreach (LegacyRecord::from('master_gst_no')->orderBy('is_deleted')->orderBy('id')->get() as $row) {
            $this->report->read('gstins');
            $companyId = $this->idFor(Company::class, $row->ref('company_id'));
            $gstin = IndianIdentifiers::normalise($row->text('gstin'));

            if ($companyId === null) {
                $this->report->reject('gstins', $row->id, "Its company (legacy {$row->ref('company_id')}) wasn't imported.");

                continue;
            }
            if ($gstin === null || in_array($gstin, ['NA', 'N/A', 'NULL', '-'], true)) {
                $this->report->reject('gstins', $row->id, 'No GSTIN ("'.($gstin ?? '').'").');

                continue;
            }
            if (! IndianIdentifiers::isGstin($gstin)) {
                $this->report->reject('gstins', $row->id, "\"{$gstin}\" isn't a valid GSTIN (format or check digit).");

                continue;
            }
            if (isset($seen[$gstin])) {
                // Entered more than once: addresses pointing at any copy link to the first.
                $this->alias(CompanyGstin::class, $row->id, $seen[$gstin], "Same GSTIN {$gstin} as another legacy row", 'merged duplicate gstins');

                continue;
            }
            $stateId = $states[IndianIdentifiers::gstinStateCode($gstin)] ?? null;
            if ($stateId === null) {
                $this->report->reject('gstins', $row->id, "Unknown GST state code in {$gstin}.");

                continue;
            }
            $this->claim(CompanyGstin::class, $row->id, 'gstin', $gstin);
            $seen[$gstin] = $this->upsert(CompanyGstin::class, $row->id, [
                'company_id' => $companyId,
                'gstin' => $gstin,
                'state_id' => $stateId,
                'legal_name' => Str::limit((string) $row->text('name'), 255, '') ?: null,
                'trade_name' => Str::limit((string) $row->text('tradename'), 255, '') ?: null,
                'registered_on' => $row->date('registrationDate'),
                'is_active' => $row->isLegacyActive() && ! Str::startsWith(Str::lower((string) $row->text('status')), 'cancelled'),
            ], 'gstins')->id;

            $this->panFromGstin($companyId, $gstin, $row->id);
        }
    }

    /** Fills a missing company PAN from its GSTIN, and flags a GSTIN issued under another PAN. */
    private function panFromGstin(int $companyId, string $gstin, int $legacyId): void
    {
        $company = Company::query()->findOrFail($companyId);
        $gstinPan = IndianIdentifiers::gstinPan($gstin);

        if ($company->pan === null) {
            if (! isset($this->pans[$gstinPan]) && Company::query()->where('pan', $gstinPan)->doesntExist()) {
                $company->forceFill(['pan' => $gstinPan])->save();
                $this->pans[$gstinPan] = $company->id;
                $this->report->warn('companies', $company->legacy_id, "PAN {$gstinPan} taken from GSTIN {$gstin}.");
            }
        } elseif ($company->pan !== $gstinPan) {
            $this->report->warn('gstins', $legacyId, "GSTIN {$gstin} was issued under PAN {$gstinPan}, not the company's PAN {$company->pan}.");
        }
    }

    private function billingAddresses(): void
    {
        // Some addresses carry no company id; a transaction billed to them tells whose they are.
        $viaTransaction = LegacyRecord::from('transaction')->where('company_address_id', '>', 0)->where('company_id', '>', 0)
            ->get(['company_address_id', 'company_id'])->groupBy('company_address_id')
            ->filter(fn ($rows) => $rows->pluck('company_id')->unique()->count() === 1)
            ->map(fn ($rows) => (int) $rows->first()->getAttribute('company_id'));

        foreach (LegacyRecord::from('company_address_master')->orderBy('id')->get() as $row) {
            $this->report->read('billing addresses');
            $companyId = $this->idFor(Company::class, $row->ref('company_id') ?? $viaTransaction->get($row->id));
            if ($companyId === null) {
                $this->report->reject('billing addresses', $row->id, "Its company (legacy {$row->ref('company_id')}) wasn't imported.");

                continue;
            }

            $text = $row->text('billing_address') ?? $row->text('regis_address');
            $gstin = ($id = $this->idFor(CompanyGstin::class, $row->ref('master_gst_id'))) ? CompanyGstin::query()->find($id) : null;
            if ($gstin !== null && $gstin->company_id !== $companyId) {
                $this->report->warn('billing addresses', $row->id, "Its GSTIN {$gstin->gstin} belongs to another company; not linked.");
                $gstin = null;
            }
            $place = $this->place($text, $row->text('pincode'), $gstin?->state_id);
            if ($text === null || $place === null) {
                $this->report->reject('billing addresses', $row->id, $text === null ? 'No address text.' : 'No pincode to place it (city and state are unknown).');

                continue;
            }

            if ($place['city'] === 'Unknown') {
                $this->report->warn('billing addresses', $row->id, "Pincode {$place['pincode']} isn't in the pincode master; city set to \"Unknown\".");
            }
            [$line1, $line2] = $this->lines($text, $row->id, 'billing addresses');
            $companyName = (string) Company::query()->whereKey($companyId)->value('name');
            $billingName = Str::limit((string) $row->text('billing_name'), 255, '') ?: null;

            $this->upsert(CompanyAddress::class, $row->id, [
                'company_id' => $companyId,
                'type' => AddressType::Billing,
                'billing_name' => $billingName !== null && Str::lower($billingName) !== Str::lower($companyName) ? $billingName : null,
                'line1' => $line1,
                'line2' => $line2,
                'city' => $place['city'],
                'pincode' => $place['pincode'],
                'state_id' => $place['state_id'],
                'company_gstin_id' => $gstin?->id,
                'is_active' => $row->isLegacyActive(),
            ], 'billing addresses');
        }
    }

    /**
     * The address on the company master is its registered office. These have no legacy id of their
     * own, so a company's imported registered office is found by company and type.
     */
    private function registeredOffices(): void
    {
        foreach (LegacyRecord::from('master_cin')->whereNotNull('address')->orderBy('id')->get() as $row) {
            $companyId = $this->idFor(Company::class, $row->id);
            $text = $row->text('address');
            if ($companyId === null || $text === null || Company::query()->whereKey($companyId)->value('legacy_id') !== $row->id) {
                continue; // not imported, empty, or merged into another company
            }
            $this->report->read('registered offices');

            $place = $this->place($text, null, null);
            if ($place === null) {
                $this->report->reject('registered offices', $row->id, 'No pincode in the address, so city and state are unknown.');

                continue;
            }

            $existing = CompanyAddress::query()->withTrashed()->where('company_id', $companyId)->where('type', AddressType::Registered)->first();
            if ($existing !== null && $existing->legacy_id !== null) {
                $this->report->saved('registered offices', 'unchanged'); // a billing record already serves as one

                continue;
            }

            [$line1, $line2] = $this->lines($text, $row->id, 'registered offices');
            $address = $existing ?? new CompanyAddress;
            $address->forceFill([
                'company_id' => $companyId, 'type' => AddressType::Registered, 'line1' => $line1, 'line2' => $line2,
                'city' => $place['city'], 'pincode' => $place['pincode'], 'state_id' => $place['state_id'], 'is_active' => true,
            ]);
            $outcome = ! $address->exists ? 'created' : ($address->isDirty() ? 'updated' : 'unchanged');
            if ($outcome !== 'unchanged') {
                $address->save();
            }
            $this->report->saved('registered offices', $outcome);
        }
    }

    /**
     * Where an address is: its pincode (given, or the last 6-digit number in the text) gives the
     * city and state; a linked GSTIN's state wins over the pincode's.
     *
     * @return array{pincode: string, city: string, state_id: int}|null
     */
    private function place(?string $text, ?string $pincode, ?int $gstinStateId): ?array
    {
        $pincode = preg_replace('/\D/', '', (string) $pincode) ?? '';
        if (! preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
            $pincode = preg_match_all('/(?<!\d)([1-9]\d{2}\s?\d{3})(?!\d)/', (string) $text, $m) ? str_replace(' ', '', end($m[1])) : '';
        }
        $known = $this->places[$pincode] ?? null;
        if ($pincode === '' || ($known === null && $gstinStateId === null)) {
            return null;
        }

        return [
            'pincode' => $pincode,
            'city' => $known['city'] ?? 'Unknown',
            'state_id' => $gstinStateId ?? $known['state_id'],
        ];
    }

    /**
     * Splits free address text into two 255-character lines at a comma or space.
     *
     * @return array{0: string, 1: string|null}
     */
    private function lines(string $text, int $legacyId, string $entity): array
    {
        if (mb_strlen($text) <= 255) {
            return [$text, null];
        }
        $cut = max((int) mb_strrpos(mb_substr($text, 0, 255), ','), (int) mb_strrpos(mb_substr($text, 0, 255), ' ')) ?: 255;
        $rest = trim(mb_substr($text, $cut), ' ,');
        if (mb_strlen($rest) > 255) {
            $this->report->warn($entity, $legacyId, 'Address longer than two lines; the end was cut off.');
        }

        return [trim(mb_substr($text, 0, $cut), ' ,'), Str::limit($rest, 255, '') ?: null];
    }

    private function contacts(): void
    {
        $types = ContactType::query()->get()->mapWithKeys(fn (ContactType $t) => [Str::lower($t->name) => $t->id]);
        $emails = [];

        foreach (LegacyRecord::from('company_contact_master')->orderBy('is_deleted')->orderBy('id')->get() as $row) {
            $this->report->read('contacts');
            $companyId = $this->idFor(Company::class, $row->ref('company_id'));
            $name = Str::limit((string) $row->text('contact_name'), 150, '');
            if ($companyId === null) {
                $this->report->reject('contacts', $row->id, "Its company (legacy {$row->ref('company_id')}) wasn't imported.");

                continue;
            }

            $email = Str::lower((string) $row->text('email')) ?: null;
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->report->warn('contacts', $row->id, "Email \"{$email}\" is invalid; left empty.");
                $email = null;
            }
            if ($email !== null && (isset($emails[$companyId.'|'.$email]) || CompanyContact::query()->withTrashed()->where('company_id', $companyId)->where('email', $email)
                ->where(fn ($q) => $q->whereNull('legacy_id')->orWhere('legacy_id', '!=', $row->id))->exists())) {
                $this->report->warn('contacts', $row->id, "Email {$email} is already another contact's at this company; left empty.");
                $email = null;
            }

            $mobile = preg_replace('/[\s\-()]+/', '', (string) $row->text('mobile')) ?: null;
            if ($mobile !== null && ! preg_match('/^\+?[0-9]{10,15}$/', $mobile)) {
                $this->report->warn('contacts', $row->id, "Mobile \"{$mobile}\" is invalid; left empty.");
                $mobile = null;
            }
            if ($email === null && $mobile === null) {
                $this->report->reject('contacts', $row->id, 'Neither an email nor a mobile number.');

                continue;
            }
            if ($email !== null) {
                $emails[$companyId.'|'.$email] = true;
            }
            if ($name === '') {
                // Stack has many unnamed contacts that are just a mailbox (accounts@…); keep them, named after it.
                $name = Str::limit((string) ($email ?? $mobile), 150, '');
                $this->report->warn('contacts', $row->id, "No name; named \"{$name}\".");
            }

            $title = Str::ucfirst(Str::lower(rtrim((string) $row->text('title'), '.')));
            $landline = $row->text('landline');

            $this->upsert(CompanyContact::class, $row->id, [
                'company_id' => $companyId,
                'contact_type_id' => $types[Str::lower((string) $row->text('contact_type'))] ?? null,
                'salutation' => in_array($title, CompanyContact::SALUTATIONS, true) ? $title : (in_array(Str::upper($title), CompanyContact::SALUTATIONS, true) ? Str::upper($title) : null),
                'name' => $name,
                'designation' => Str::limit((string) $row->text('designation'), 150, '') ?: null,
                'department' => Str::limit((string) $row->text('department'), 150, '') ?: null,
                'email' => $email,
                'mobile' => $mobile,
                'landline' => $landline !== null && preg_match('/^[0-9+\-() ]{1,30}$/', $landline) ? $landline : null,
                'is_active' => $row->isLegacyActive(),
            ], 'contacts');
        }
    }
}
