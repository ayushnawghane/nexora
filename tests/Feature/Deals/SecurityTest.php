<?php

use App\Enums\ConditionStatus;
use App\Enums\DealDocumentKind;
use App\Enums\DealStatus;
use App\Enums\LegalDocumentCategory;
use App\Enums\RegistrationAction;
use App\Enums\RegistrationStatus;
use App\Enums\SecurityNature;
use App\Models\AssetType;
use App\Models\ChargeType;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealSecurity;
use App\Models\EmpanelledAgency;
use App\Models\LegalDocumentType;
use App\Models\SecurityRegistration;
use App\Models\SecurityType;
use App\Models\Transaction;
use Database\Seeders\StateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(StateSeeder::class);
    $this->travelTo('2025-10-01 10:00');
    $this->deal = Transaction::factory()->deal(DealStatus::Live)->create();
    $type = LegalDocumentType::query()->create(['name' => 'Deed of Hypothecation', 'category' => LegalDocumentCategory::Security, 'security_nature' => SecurityNature::Hypothecation]);
    $this->document = DealDocument::query()->forceCreate([
        'transaction_id' => $this->deal->id, 'legal_document_type_id' => $type->id, 'kind' => DealDocumentKind::Standard,
        'sequence' => 0, 'name' => 'Deed of Hypothecation', 'created_by' => $this->deal->created_by,
    ]);
    $movable = AssetType::query()->create(['name' => 'Movable Assets']);
    $this->receivables = SecurityType::query()->create(['name' => 'Receivables', 'asset_type_id' => $movable->id]);
    $this->charge = ChargeType::query()->create(['name' => 'First Pari Passu']);
    $this->movable = $movable;
});

function securityPayload(array $overrides = []): array
{
    return array_merge([
        'deal_document_id' => test()->document->id,
        'nature' => 'hypothecation',
        'asset_owner' => 'Aadhar Housing Finance Limited',
        'owner_id_type' => 'cin',
        'owner_id_number' => 'U65999MH2010PTC123456',
        'asset_type_id' => test()->movable->id,
        'charge_type_id' => test()->charge->id,
        'security_type_ids' => [test()->receivables->id],
        'is_encumbered' => false,
        'description' => 'All receivables of the issuer, present and future.',
    ], $overrides);
}

function addSecurity(Transaction $deal, array $overrides = [])
{
    return test()->post("/deals/{$deal->ulid}/securities", securityPayload($overrides));
}

function register(Transaction $deal, array $data)
{
    return test()->post("/deals/{$deal->ulid}/registrations", $data);
}

test('a security is recorded under one of the deal\'s documents, with a valid owner id', function () {
    dealUser();
    addSecurity($this->deal)->assertForbidden();

    dealUser(['deals.security.manage']);
    addSecurity($this->deal, ['owner_id_number' => 'NOTACIN'])->assertSessionHasErrors(['owner_id_number' => 'Enter a valid CIN or LLPIN.']);
    $other = Transaction::factory()->deal()->create();
    $foreign = DealDocument::query()->forceCreate([
        'transaction_id' => $other->id, 'legal_document_type_id' => $this->document->legal_document_type_id, 'kind' => DealDocumentKind::Standard,
        'sequence' => 0, 'name' => 'Other deal deed', 'created_by' => $other->created_by,
    ]);
    addSecurity($this->deal, ['deal_document_id' => $foreign->id])->assertSessionHasErrors('deal_document_id');
    addSecurity($this->deal)->assertSessionHasNoErrors();

    $security = DealSecurity::query()->sole();
    expect($security->nature)->toBe(SecurityNature::Hypothecation)
        ->and($security->is_encumbered)->toBeFalse()
        ->and($security->securityTypes()->pluck('name')->all())->toBe(['Receivables'])
        ->and($security->summary())->toBe('Receivables · Aadhar Housing Finance Limited');
});

test('a ROC charge covers securities that allow it, once per kind, and is modified then satisfied with its filings', function () {
    dealUser(['deals.security.manage']);
    addSecurity($this->deal);
    addSecurity($this->deal, ['nature' => 'guarantee', 'asset_owner' => 'Promoter', 'owner_id_type' => null, 'owner_id_number' => null]);
    [$hypothecation, $guarantee] = DealSecurity::query()->orderBy('id')->get()->all();

    $base = ['kind' => 'roc', 'happened_on' => '2025-09-20', 'filing_reference' => 'AB1234567', 'reference' => '100123456', 'amount' => '2000000000'];
    register($this->deal, [...$base, 'security_ids' => [$guarantee->id]])->assertSessionHasErrors('security_ids');
    register($this->deal, [...$base, 'happened_on' => '2025-10-05', 'security_ids' => [$hypothecation->id]])->assertSessionHasErrors('happened_on');
    register($this->deal, [...$base, 'security_ids' => [$hypothecation->id], 'files' => [UploadedFile::fake()->create('challan.pdf', 50, 'application/pdf')]])->assertSessionHasNoErrors();
    register($this->deal, [...$base, 'security_ids' => [$hypothecation->id]])->assertSessionHasErrors('security_ids'); // already charged

    $roc = SecurityRegistration::query()->sole();
    expect($roc->status)->toBe(RegistrationStatus::Active)
        ->and($roc->reference)->toBe('100123456')
        ->and($roc->events()->sole()->files()->sole()->original_name)->toBe('challan.pdf');

    $event = fn (array $data) => test()->post("/deals/{$this->deal->ulid}/registrations/{$roc->id}/events", $data);
    $event(['action' => 'modify', 'happened_on' => '2025-09-25'])->assertSessionHasErrors('reason');
    $event(['action' => 'modify', 'happened_on' => '2025-09-10', 'reason' => 'Amount raised'])->assertSessionHasErrors('happened_on'); // before the last filing
    $event(['action' => 'modify', 'happened_on' => '2025-09-25', 'reason' => 'Amount raised', 'amount' => '2500000000'])->assertSessionHasNoErrors();
    expect($roc->fresh()->amount)->toBe('2500000000.00');

    $event(['action' => 'satisfy', 'happened_on' => '2025-09-30', 'filing_reference' => 'CD7654321'])->assertSessionHasNoErrors();
    $roc->refresh();
    expect($roc->status)->toBe(RegistrationStatus::Satisfied)
        ->and($roc->events()->pluck('action')->all())->toBe([RegistrationAction::Create, RegistrationAction::Modify, RegistrationAction::Satisfy]);
    $event(['action' => 'modify', 'happened_on' => '2025-09-30', 'reason' => 'Again'])->assertSessionHasErrors('action');

    // A registered security can't be removed or change kind.
    $this->delete("/deals/{$this->deal->ulid}/securities/{$hypothecation->id}")->assertSessionHasErrors('security');
    $this->put("/deals/{$this->deal->ulid}/securities/{$hypothecation->id}", securityPayload(['nature' => 'mortgage']))->assertSessionHasErrors('nature');
});

test('a pledge needs its depository details; a registration can be satisfied after the deal closes', function () {
    dealUser(['deals.security.manage']);
    addSecurity($this->deal, ['nature' => 'pledge', 'charge_type_id' => null]);
    $security = DealSecurity::query()->sole();

    register($this->deal, ['kind' => 'roc', 'happened_on' => '2025-09-20', 'security_ids' => [$security->id]])->assertSessionHasErrors('security_ids');
    register($this->deal, ['kind' => 'pledge', 'happened_on' => '2025-09-20', 'security_ids' => [$security->id]])
        ->assertSessionHasErrors(['security_name', 'quantity', 'depository']);
    register($this->deal, [
        'kind' => 'pledge', 'happened_on' => '2025-09-20', 'security_ids' => [$security->id], 'reference' => 'INE123A01012',
        'security_name' => 'Equity shares of the issuer', 'quantity' => 100000, 'depository' => 'NSDL',
    ])->assertSessionHasNoErrors();
    $pledge = SecurityRegistration::query()->sole();

    $this->deal->forceFill(['deal_status' => DealStatus::Redeemed, 'status' => 'closed', 'closed_at' => now()])->save();
    $this->post("/deals/{$this->deal->ulid}/registrations/{$pledge->id}/events", ['action' => 'modify', 'happened_on' => '2025-09-30', 'reason' => 'Partial'])->assertForbidden();
    $this->post("/deals/{$this->deal->ulid}/registrations/{$pledge->id}/events", ['action' => 'satisfy', 'happened_on' => '2025-09-30'])->assertSessionHasNoErrors();
    expect($pledge->fresh()->status)->toBe(RegistrationStatus::Satisfied);
});

test('due diligence items are uploaded by one person and verified by another', function () {
    $agency = EmpanelledAgency::query()->create(['code' => 'EA-57', 'name' => 'G V Jain & Co']);
    $maker = dealUser(['deals.security.manage', 'deals.security.verify']);
    addSecurity($this->deal);
    $security = DealSecurity::query()->sole();

    $add = fn (array $data) => test()->post("/deals/{$this->deal->ulid}/diligence", $data);
    $add(['kind' => 'roc_search', 'title' => 'ROC search report'])->assertSessionHasErrors('asset_owner');
    $add(['kind' => 'noc', 'title' => 'NOC'])->assertSessionHasErrors('deal_security_id');
    $add(['kind' => 'security_certificate', 'title' => 'Security certificate', 'deal_security_id' => $security->id, 'empanelled_agency_id' => $agency->id, 'reference' => '26064817EKEPNV6029'])->assertSessionHasNoErrors();
    $item = DealDiligenceItem::query()->sole();

    $this->post("/deals/{$this->deal->ulid}/diligence/{$item->id}/files", ['files' => [UploadedFile::fake()->create('cert.pdf', 40, 'application/pdf')]])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe(ConditionStatus::Submitted);
    $this->post("/deals/{$this->deal->ulid}/diligence/{$item->id}/check", ['decision' => 'verified'])
        ->assertSessionHasErrors(['decision' => 'You uploaded these files, so someone else has to check them.']);

    dealUser(['deals.security.verify']);
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->where('queue.0.kind', 'Due diligence check'));
    $this->post("/deals/{$this->deal->ulid}/diligence/{$item->id}/check", ['decision' => 'verified'])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe(ConditionStatus::Verified);

    // A security with due diligence can't be removed.
    actAs($maker);
    $this->delete("/deals/{$this->deal->ulid}/securities/{$security->id}")->assertSessionHasErrors('security');
});

test('the security tab lists securities, registrations and diligence; closing warns about charges in force', function () {
    dealUser(['deals.security.manage']);
    addSecurity($this->deal);
    $security = DealSecurity::query()->sole();
    register($this->deal, ['kind' => 'cersai', 'happened_on' => '2025-09-20', 'security_ids' => [$security->id], 'reference' => '400012345678']);

    $this->get("/deals/{$this->deal->ulid}?tab=security")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('security.securities.0.summary', 'Receivables · Aadhar Housing Finance Limited')
        ->where('security.securities.0.registered.0', 'CERSAI · Registered')
        ->where('security.registrations.0.reference_label', 'Security interest ID')
        ->where('security.active_registrations', 1)
        ->where('status.active_registrations', 1));
});
