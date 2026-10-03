<?php

use App\Enums\DealStatus;
use App\Enums\StatusRequestState;
use App\Enums\TransactionStatus;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\GodModeChange;
use App\Models\Transaction;
use App\Services\Auth\TwoFactor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Notification::fake();
    $this->seed([PermissionSeeder::class, StateSeeder::class]);
    $this->travelTo('2025-10-01 10:00');
});

function correct(string $editor, string $id, array $changes, string $reason = 'Corrected after checking the source document')
{
    $form = godForm($editor, $id);

    return test()->post("/god-mode/correct/{$editor}/{$id}", [
        'values' => array_replace_recursive($form['values'], $changes),
        'fingerprint' => $form['fingerprint'],
        'reason' => $reason,
    ]);
}

test('only super-admins with a recent 2FA code get in', function () {
    signIn(permissions: ['god_mode.access', 'dashboard.view']);
    $this->get('/god-mode')->assertForbidden();
    // …and the menu doesn't offer it to them.
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('auth.permissions', fn ($permissions) => ! collect($permissions)->contains('god_mode.access')));

    $admin = godUser();
    $this->get('/god-mode')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('GodMode/Index'));

    $this->actingAs($admin)->withSession([TwoFactor::SESSION_PASSED_AT => now()->subMinutes(20)->getTimestamp()])
        ->get('/god-mode')->assertRedirect(route('two-factor.challenge', ['reconfirm' => 1]));
});

test('a correction must pass the normal form\'s rules and give a reason, and is logged', function () {
    $admin = godUser();
    $company = Company::factory()->create();

    correct('company', (string) $company->id, ['pan' => 'BADPAN'])->assertSessionHasErrors(['pan' => 'A PAN is 10 characters, like AAACB1234C.']);
    correct('company', (string) $company->id, ['name' => 'Acme Housing Finance Limited'], 'typo')->assertSessionHasErrors('reason');
    expect(GodModeChange::query()->count())->toBe(0);

    correct('company', (string) $company->id, ['name' => '  Acme Housing Finance Limited '])->assertSessionHasNoErrors();

    $change = GodModeChange::query()->sole();
    expect($company->fresh()->name)->toBe('Acme Housing Finance Limited')
        ->and($change->user_id)->toBe($admin->id)
        ->and($change->company_id)->toBe($company->id)
        ->and($change->before['name'])->toBe($company->name)
        ->and($change->after['name'])->toBe('Acme Housing Finance Limited')
        ->and($change->can_roll_back)->toBeTrue();
});

test('a correction is refused if the record changed after the screen was loaded, or if nothing changed', function () {
    godUser();
    $company = Company::factory()->create();
    $stale = godForm('company', (string) $company->id);
    $company->update(['formerly_known_as' => 'Old Name Limited']);

    $this->post("/god-mode/correct/company/{$company->id}", [
        'values' => ['name' => 'New Name Limited'] + $stale['values'], 'fingerprint' => $stale['fingerprint'], 'reason' => 'Name changed per new certificate',
    ])->assertSessionHasErrors(['fingerprint' => 'This record changed after you opened it. Reload the page and make the correction again.']);

    correct('company', (string) $company->id, [])->assertSessionHasErrors(['fingerprint' => 'Nothing changed. Edit a value before saving.']);
});

test('a correction can be undone once, only while nobody has changed the record since', function () {
    godUser();
    $contact = CompanyContact::factory()->create(['name' => 'Ravi Kumar', 'email' => 'ravi@issuer.test']);

    correct('company-contact', (string) $contact->id, ['name' => 'Ravi Kumar Shah'])->assertSessionHasNoErrors();
    $change = GodModeChange::query()->sole();

    $this->post("/god-mode/changes/{$change->ulid}/rollback", ['reason' => 'Wrong person was renamed'])->assertSessionHasNoErrors();
    expect($contact->fresh()->name)->toBe('Ravi Kumar');

    $undo = GodModeChange::query()->latest('id')->first();
    expect($undo->reverts_change_id)->toBe($change->id)->and($undo->can_roll_back)->toBeFalse();

    $this->post("/god-mode/changes/{$change->ulid}/rollback", ['reason' => 'Trying again for no reason'])->assertSessionHasErrors(['rollback' => 'This change has already been undone.']);
    $this->post("/god-mode/changes/{$undo->ulid}/rollback", ['reason' => 'Undo of the undo please'])->assertSessionHasErrors('rollback');

    // A later edit blocks the undo of an earlier correction.
    correct('company-contact', (string) $contact->id, ['designation' => 'CFO'])->assertSessionHasNoErrors();
    $second = GodModeChange::query()->latest('id')->first();
    $contact->update(['department' => 'Finance']);
    $this->post("/god-mode/changes/{$second->ulid}/rollback", ['reason' => 'Undo the designation change'])
        ->assertSessionHasErrors(['rollback' => 'The record has changed since this correction, so undoing it would overwrite later edits. Correct it by hand instead.']);
});

test('the change log can\'t be edited or deleted', function () {
    godUser();
    $company = Company::factory()->create();
    correct('company', (string) $company->id, ['name' => 'Renamed Private Limited']);
    $change = GodModeChange::query()->sole();

    expect(fn () => $change->update(['reason' => 'rewritten']))->toThrow(LogicException::class)
        ->and(fn () => $change->delete())->toThrow(LogicException::class);
});

test('an active transaction the normal screens lock can be corrected; its company can\'t be swapped', function () {
    godUser();
    $deal = Transaction::factory()->deal()->create();
    $other = Company::factory()->create();
    $team = Transaction::factory()->make()->vertical_team_id;

    correct('transaction-basics', $deal->ulid, ['company_id' => $other->id, 'brief' => 'Refinancing of existing NCDs', 'vertical_team_id' => $team])
        ->assertSessionHasNoErrors();

    $deal->refresh();
    expect($deal->brief)->toBe('Refinancing of existing NCDs')
        ->and($deal->company_id)->not->toBe($other->id)
        ->and($deal->status)->toBe(TransactionStatus::Active);
});

test('correcting fees rebuilds the schedule and prompts to verify it, which is logged too', function () {
    godUser();
    $deal = Transaction::factory()->deal()->create();
    $fees = feesPayload()['fees'];

    correct('fees', $deal->ulid, ['fees' => $fees])->assertSessionHasNoErrors();
    $deal->refresh();
    expect($deal->feeLines()->count())->toBe(2)
        ->and($deal->isScheduleVerified())->toBeFalse()
        ->and(GodModeChange::query()->sole()->can_roll_back)->toBeFalse(); // no fees before: nothing to go back to

    $this->get("/god-mode/transactions/{$deal->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('GodMode/Record')
        ->where('prompts.verify_schedule', true));

    $this->post("/god-mode/transactions/{$deal->ulid}/schedule/verify", ['reason' => 'Checked against the term sheet'])->assertSessionHasNoErrors();
    expect($deal->fresh()->isScheduleVerified())->toBeTrue()
        ->and(GodModeChange::query()->latest('id')->first()->editor)->toBe('schedule-verify');
});

test('a forced status change skips approvals but keeps the state machine', function () {
    godUser();
    $deal = Transaction::factory()->deal(DealStatus::Live)->create();
    $pending = $deal->statusRequests()->create([
        'from_status' => DealStatus::Live, 'to_status' => DealStatus::Hold, 'effective_on' => '2025-09-01', 'reason' => 'Pending one',
        'needs_management' => true, 'needs_accounts' => true, 'status' => StatusRequestState::Open, 'requested_by' => $deal->created_by,
    ]);

    correct('deal-status', $deal->ulid, ['deal_status' => 'preliminary'])->assertSessionHasErrors('deal_status');
    correct('deal-status', $deal->ulid, ['deal_status' => 'redeemed', 'effective_on' => '2025-09-30'])->assertSessionHasNoErrors();

    $deal->refresh();
    expect($deal->deal_status)->toBe(DealStatus::Redeemed)
        ->and($deal->status)->toBe(TransactionStatus::Closed)
        ->and($pending->fresh()->status)->toBe(StatusRequestState::Withdrawn)
        ->and($deal->statusChanges()->latest('id')->first()->to_status)->toBe(DealStatus::Redeemed)
        ->and(GodModeChange::query()->sole()->can_roll_back)->toBeFalse();
});

test('billing of a closed deal can be corrected with the same checks', function () {
    godUser();
    $deal = Transaction::factory()->deal(DealStatus::Closed)->create();
    $address = CompanyAddress::factory()->for($deal->company)->create();
    $contact = CompanyContact::factory()->for($deal->company)->create(['email' => 'accounts@issuer.test']);
    $stranger = CompanyContact::factory()->create();

    correct('deal-billing', $deal->ulid, ['company_address_id' => $address->id, 'contact_ids' => [$stranger->id]])->assertSessionHasErrors('contact_ids');
    correct('deal-billing', $deal->ulid, ['company_address_id' => $address->id, 'contact_ids' => [$contact->id]])->assertSessionHasNoErrors();

    expect($deal->billing()->sole()->company_address_id)->toBe($address->id);
});

test('search finds companies and deals; record pages show every editable part', function () {
    godUser();
    $deal = Transaction::factory()->deal()->create();

    $this->get('/god-mode?q='.urlencode($deal->el_number))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('results.transactions', 1)
        ->where('results.transactions.0.href', route('god-mode.transactions', $deal->ulid)));
    $this->get('/god-mode?q='.urlencode($deal->company->name))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('results.companies', 1));

    $this->get("/god-mode/transactions/{$deal->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.items.0.editor', 'transaction-basics')
        ->where('sections.1.title', 'Deal')
        ->where('sections.1.items.1.editor', 'deal-status'));
    $this->get("/god-mode/companies/{$deal->company->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections.0.items.0.editor', 'company')
        ->has('links', 1));
});
