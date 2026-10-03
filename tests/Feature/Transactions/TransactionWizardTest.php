<?php

use App\Enums\TransactionStatus;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\FeeLine;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerticalTeam;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;

test('the wizard needs permission', function () {
    signIn(permissions: ['transactions.view']);
    $transaction = Transaction::factory()->create();

    $this->get('/transactions/create')->assertForbidden();
    $this->post('/transactions', basicsPayload())->assertForbidden();
    $this->get("/transactions/{$transaction->ulid}/edit")->assertRedirect("/transactions/{$transaction->ulid}");
    $this->put("/transactions/{$transaction->ulid}/issue", issuePayload())->assertForbidden();
});

test('creating a draft saves the basics as a DT transaction and moves to contacts', function () {
    $user = wizardUser();
    $payload = basicsPayload();

    $response = $this->post('/transactions', $payload);

    $transaction = Transaction::query()->sole();
    $response->assertRedirect("/transactions/{$transaction->ulid}/edit?step=contacts");
    expect($transaction->status)->toBe(TransactionStatus::Draft)
        ->and($transaction->product->code)->toBe('DEB')
        ->and($transaction->brief)->toBe('NCD issue for working capital')
        ->and($transaction->created_by)->toBe($user->id);
});

test('basics reject inactive or missing masters', function (string $field, Closure $value) {
    wizardUser();

    $this->post('/transactions', basicsPayload([$field => $value()]))->assertSessionHasErrors($field);
})->with([
    'inactive company' => ['company_id', fn () => Company::factory()->create(['is_active' => false])->id],
    'no company' => ['company_id', fn () => null],
    'inactive team' => ['vertical_team_id', fn () => VerticalTeam::query()->create(['vertical_id' => Transaction::factory()->make()->verticalTeam->vertical_id, 'name' => 'Old', 'is_active' => false])->id],
    'signatory who is not authorised' => ['signatory_id', fn () => User::factory()->create(['is_authorised_signatory' => false])->id],
    'unknown origin' => ['origin', fn () => 'partner'],
]);

test('changing the company drops the contacts chosen for the old one', function () {
    wizardUser();
    $transaction = Transaction::factory()->create();
    $contact = CompanyContact::factory()->for($transaction->company)->create();
    $this->put("/transactions/{$transaction->ulid}/contacts", ['contacts' => [['company_contact_id' => $contact->id, 'recipient' => 'to']]]);
    expect($transaction->contacts()->count())->toBe(1);

    $this->put("/transactions/{$transaction->ulid}/basics", basicsPayload())->assertSessionHasNoErrors();

    expect($transaction->contacts()->count())->toBe(0);
});

test('contacts must belong to the company and include a "To" with an email', function () {
    wizardUser();
    $transaction = Transaction::factory()->create();
    $own = CompanyContact::factory()->for($transaction->company)->create();
    $noEmail = CompanyContact::factory()->for($transaction->company)->create(['email' => null]);
    $foreign = CompanyContact::factory()->create();
    $url = "/transactions/{$transaction->ulid}/contacts";

    $this->put($url, ['contacts' => [['company_contact_id' => $foreign->id, 'recipient' => 'to']]])->assertSessionHasErrors('contacts.0.company_contact_id');
    $this->put($url, ['contacts' => [['company_contact_id' => $own->id, 'recipient' => 'cc']]])->assertSessionHasErrors('contacts');
    $this->put($url, ['contacts' => [['company_contact_id' => $noEmail->id, 'recipient' => 'to']]])->assertSessionHasErrors('contacts');
    $this->put($url, ['contacts' => [
        ['company_contact_id' => $own->id, 'recipient' => 'to'], ['company_contact_id' => $own->id, 'recipient' => 'cc'],
    ]])->assertSessionHasErrors('contacts.0.company_contact_id');

    $this->put($url, ['contacts' => [
        ['company_contact_id' => $own->id, 'recipient' => 'to'], ['company_contact_id' => $noEmail->id, 'recipient' => 'cc'],
    ]])->assertRedirect("/transactions/{$transaction->ulid}/edit?step=issue");
    expect($transaction->contacts()->count())->toBe(2);
});

test('the instrument split must add up to the base issue and the green shoe', function () {
    wizardUser();
    $transaction = Transaction::factory()->create();
    $url = "/transactions/{$transaction->ulid}/issue";

    $this->put($url, issuePayload(['instruments' => [['instrument' => 'ncd', 'base_amount' => '1999999999.99', 'green_shoe_amount' => '0']]]))
        ->assertSessionHasErrors('instruments');
    $this->put($url, issuePayload(['green_shoe_size' => '500000000']))->assertSessionHasErrors('instruments');
    $this->put($url, issuePayload(['tenure_months' => 0, 'tenure_days' => 0]))->assertSessionHasErrors('tenure_months');
    $this->put($url, issuePayload(['base_issue_size' => '0']))->assertSessionHasErrors('base_issue_size');

    $this->put($url, issuePayload([
        'base_issue_size' => '1500000000', 'green_shoe_size' => '500000000',
        'instruments' => [
            ['instrument' => 'ncd', 'base_amount' => '1000000000', 'green_shoe_amount' => '500000000'],
            ['instrument' => 'mld', 'base_amount' => '500000000', 'green_shoe_amount' => '0'],
            ['instrument' => 'ocd', 'base_amount' => '0', 'green_shoe_amount' => '0'],
        ],
    ]))->assertSessionHasNoErrors();

    $issue = $transaction->issueDetail()->sole();
    expect($issue->total_issue_size)->toBe('2000000000.00')
        ->and($transaction->instruments()->pluck('instrument')->map->value->sort()->values()->all())->toBe(['mld', 'ncd']); // empty rows dropped
});

test('saving fees builds the schedule; a percentage is of the issue size', function () {
    wizardUser();
    $transaction = draftReadyForFees();

    $this->put("/transactions/{$transaction->ulid}/fees", feesPayload(
        service: ['amount_type' => 'percent', 'amount' => null, 'percent' => '0.00325'],
    ))->assertRedirect("/transactions/{$transaction->ulid}/edit?step=schedule");

    $service = FeeLine::query()->where('kind', 'service')->sole();
    // 0.00325% of ₹200 crore = ₹65,000 a year (legacy computed total + total × %, which was wrong).
    expect($service->annual_amount)->toBe('65000.00')
        ->and($service->periods()->first()->amount)->toBe('35082.00')
        ->and($service->periods()->count())->toBe(4)
        ->and(FeeLine::query()->where('kind', 'acceptance')->sole()->periods()->sole()->amount)->toBe('65000.00');
});

test('fee validation', function (array $acceptance, array $service, string $error) {
    wizardUser();
    $transaction = draftReadyForFees();

    $this->put("/transactions/{$transaction->ulid}/fees", feesPayload($acceptance, $service))->assertSessionHasErrors($error);
})->with([
    'no fees at all' => [['enabled' => false], ['enabled' => false], 'fees'],
    'missing amount' => [['amount' => ''], [], 'fees.acceptance.amount'],
    'percent of subscribed amount' => [[], ['amount_type' => 'percent', 'percent' => '0.5', 'basis' => 'subscribed_amount'], 'fees.service.basis'],
    'recurring acceptance fee' => [['frequency' => 'annual'], [], 'fees.acceptance.frequency'],
    'one-time service fee' => [[], ['frequency' => 'one_time'], 'fees.service.frequency'],
    'escalating acceptance fee' => [['escalation_type' => 'percent', 'escalation_value' => '5', 'escalation_every_years' => 1], [], 'fees.acceptance.escalation_type'],
    'escalation without a period' => [[], ['escalation_type' => 'percent', 'escalation_value' => '5'], 'fees.service.escalation_every_years'],
    'escalation over 100%' => [[], ['escalation_type' => 'percent', 'escalation_value' => '150', 'escalation_every_years' => 1], 'fees.service.escalation_value'],
    'percent over 100' => [[], ['amount_type' => 'percent', 'percent' => '101'], 'fees.service.percent'],
]);

test('fees need the issue details first', function () {
    wizardUser();
    $transaction = Transaction::factory()->create();

    $this->put("/transactions/{$transaction->ulid}/fees", feesPayload())->assertSessionHasErrors('fees');
});

test('disabling a fee removes it and its periods', function () {
    wizardUser();
    $transaction = draftReadyForFees();
    $this->put("/transactions/{$transaction->ulid}/fees", feesPayload());
    expect($transaction->feeLines()->count())->toBe(2);

    $this->put("/transactions/{$transaction->ulid}/fees", feesPayload(acceptance: ['enabled' => false]))->assertSessionHasNoErrors();

    expect($transaction->feeLines()->pluck('kind')->map->value->all())->toBe(['service'])
        ->and($transaction->schedulePeriods()->count())->toBe(4);
});

test('verifying the schedule is recorded, and any later change to the issue or fees clears it', function () {
    $user = wizardUser();
    $transaction = draftReadyForFees();
    $url = "/transactions/{$transaction->ulid}";

    $this->post("{$url}/schedule/verify")->assertSessionHasErrors('schedule'); // nothing to verify yet

    $this->put("{$url}/fees", feesPayload());
    $this->post("{$url}/schedule/verify")->assertRedirect("{$url}/edit?step=review");
    expect($transaction->fresh())->schedule_verified_by->toBe($user->id);

    $this->put("{$url}/issue", issuePayload(['tenure_months' => 60]))->assertSessionHasNoErrors();
    expect($transaction->fresh()->isScheduleVerified())->toBeFalse()
        ->and($transaction->schedulePeriods()->count())->toBe(1 + 6); // one-time + 6 periods over 5 years

    $this->post("{$url}/schedule/verify");
    $this->put("{$url}/fees", feesPayload(service: ['amount' => '70000']));
    expect($transaction->fresh()->isScheduleVerified())->toBeFalse();
});

test('the wizard opens the first incomplete step and refuses to skip ahead', function () {
    wizardUser();
    $transaction = Transaction::factory()->create();

    $this->get("/transactions/{$transaction->ulid}/edit?step=fees")->assertInertia(fn ($page) => $page
        ->component('Transactions/Wizard')
        ->where('step', 'contacts')
        ->where('progress.basics', true)
        ->where('progress.contacts', false));

    $ready = draftReadyForFees();
    $this->get("/transactions/{$ready->ulid}/edit")->assertInertia(fn ($page) => $page->where('step', 'fees'));
    $this->get("/transactions/{$ready->ulid}/edit?step=basics")->assertInertia(fn ($page) => $page->where('step', 'basics'));
});

test('a submitted transaction can no longer be edited through the wizard', function () {
    wizardUser();
    $transaction = Transaction::factory()->create();
    $transaction->forceFill(['status' => TransactionStatus::PendingApproval])->save();

    $this->get("/transactions/{$transaction->ulid}/edit")->assertRedirect("/transactions/{$transaction->ulid}");
    $this->put("/transactions/{$transaction->ulid}/basics", basicsPayload())->assertForbidden();
    $this->get("/transactions/{$transaction->ulid}")->assertInertia(fn ($page) => $page
        ->component('Transactions/Show')
        ->where('transaction.status', 'pending_approval')
        ->where('can.update', false));
});

test('the state machine refuses transitions it does not allow', function () {
    $transaction = Transaction::factory()->create();

    expect(fn () => $transaction->transitionTo(TransactionStatus::Active))->toThrow(LogicException::class);
    $transaction->transitionTo(TransactionStatus::PendingApproval);
    expect($transaction->status)->toBe(TransactionStatus::PendingApproval);
});

test('the lists show transactions by stage', function () {
    wizardUser();
    $draft = Transaction::factory()->create();
    $pending = Transaction::factory()->create();
    $pending->forceFill(['status' => TransactionStatus::PendingApproval])->save();

    $this->get('/transactions/drafts')->assertInertia(fn ($page) => $page
        ->component('Transactions/Index')
        ->where('transactions.total', 1)
        ->where('transactions.data.0.id', $draft->ulid));
    $this->get('/transactions/pending')->assertInertia(fn ($page) => $page->where('transactions.data.0.id', $pending->ulid));
    $this->get('/transactions/drafts?filter[search]='.urlencode($draft->company->name))->assertInertia(fn ($page) => $page->where('transactions.total', 1));
});

test('super-admins also cannot edit a transaction once it has left draft', function () {
    $admin = User::factory()->create();
    $this->seed(PermissionSeeder::class);
    $admin->assignRole(Permissions::superAdminRole());
    actAs($admin);
    $transaction = Transaction::factory()->create();
    $transaction->forceFill(['status' => TransactionStatus::Active])->save();

    expect($admin->can('update', $transaction))->toBeFalse()
        ->and($admin->can('transactions.create'))->toBeTrue();
    $this->get("/transactions/{$transaction->ulid}/edit")->assertRedirect("/transactions/{$transaction->ulid}");
    $this->put("/transactions/{$transaction->ulid}/basics", basicsPayload())->assertForbidden();
});

test('lists can be exported to Excel with the same search', function () {
    wizardUser();
    Transaction::factory()->create();

    $response = $this->get('/transactions/export/drafts');

    $response->assertOk()->assertDownload('transactions-drafts-'.now()->format('Y-m-d').'.xlsx');
    $this->get('/transactions/export/everything')->assertNotFound();
});
