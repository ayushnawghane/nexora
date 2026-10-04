<?php

use App\Enums\AllotmentKind;
use App\Enums\DealStatus;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Models\DealIsin;
use App\Models\IsinPayment;
use App\Models\IsinReminder;
use App\Models\Transaction;
use App\Notifications\IsinPaymentsDue;
use Database\Seeders\StateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(StateSeeder::class);
    $this->travelTo('2025-10-01 10:00'); // a Wednesday
    $this->deal = Transaction::factory()->deal(DealStatus::Live)->create();
    $this->deal->verticalTeam->update(['email' => 'dt-team@beacon.test']);
});

function isinPayload(array $overrides = []): array
{
    return array_merge([
        'isin' => 'ine471x07014 ',
        'series_name' => 'Series A 9.50% secured NCDs',
        'listing' => 'listed',
        'exchange' => 'BSE',
        'depository' => 'NSDL',
        'placement' => 'private',
        'allotment_date' => '2025-06-15',
        'maturity_date' => '2028-06-15',
        'coupon_type' => 'fixed',
        'coupon_rate' => '9.5',
        'interest_frequency' => 'quarterly',
        'principal_frequency' => 'bullet',
        'day_count' => 'act_365',
        'holiday_convention' => 'none',
    ], $overrides);
}

function addIsin(Transaction $deal, array $overrides = [])
{
    return test()->post("/deals/{$deal->ulid}/isins", isinPayload($overrides));
}

function addDueDates(Transaction $deal, DealIsin $isin, array $data)
{
    return test()->post("/deals/{$deal->ulid}/isins/{$isin->ulid}/schedule", $data);
}

function scheduleFile(string $csv): UploadedFile
{
    return UploadedFile::fake()->createWithContent('schedule.csv', $csv);
}

test('an ISIN is added with a valid check digit, once per deal', function () {
    dealUser();
    addIsin($this->deal)->assertForbidden();

    dealUser(['deals.isin.manage']);
    addIsin($this->deal, ['isin' => 'INE471X07015'])->assertSessionHasErrors(['isin' => 'Enter a valid ISIN (IN, 9 letters or digits, and a check digit).']);
    addIsin($this->deal, ['maturity_date' => '2025-01-01'])->assertSessionHasErrors('maturity_date');
    addIsin($this->deal, ['coupon_type' => 'zero'])->assertSessionHasErrors('coupon_rate');
    addIsin($this->deal, ['put_date' => '2029-01-01'])->assertSessionHasErrors(['put_date' => 'The put date must be on or before maturity.']);
    addIsin($this->deal)->assertSessionHasNoErrors();
    addIsin($this->deal)->assertSessionHasErrors(['isin' => 'This ISIN is already on the deal.']);

    $isin = DealIsin::query()->sole();
    expect($isin->isin)->toBe('INE471X07014')
        ->and($isin->coupon_rate)->toBe('9.5000')
        ->and($isin->maturity_date->toDateString())->toBe('2028-06-15');

    // The same ISIN may sit on another deal (Stack has this for re-issued series).
    $other = Transaction::factory()->deal(DealStatus::Live)->create();
    addIsin($other)->assertSessionHasNoErrors();

    // A closed deal's ISINs can't change.
    $closed = Transaction::factory()->deal(DealStatus::Closed)->create();
    addIsin($closed)->assertForbidden();
});

test('allotments work out their amount; one initial, further tranches after it', function () {
    dealUser(['deals.isin.manage']);
    addIsin($this->deal, ['allotment_date' => null]);
    $isin = DealIsin::query()->sole();
    $allot = fn (array $data) => test()->post("/deals/{$this->deal->ulid}/isins/{$isin->ulid}/allotments", $data);
    $initial = ['kind' => 'initial', 'allotment_date' => '2025-06-15', 'face_value' => '100000', 'quantity_offered' => '20000', 'quantity_allotted' => '15000'];

    $allot([...$initial, 'kind' => 'additional'])->assertSessionHasErrors(['kind' => 'Record the initial allotment first.']);
    $allot([...$initial, 'allotment_date' => '2025-10-02'])->assertSessionHasErrors('allotment_date'); // future
    $allot([...$initial, 'credit_depository' => 'NSDL', 'credited_on' => '2025-06-17', 'file' => UploadedFile::fake()->create('credit.pdf', 40, 'application/pdf')])
        ->assertSessionHasNoErrors();
    $allot($initial)->assertSessionHasErrors('kind');
    $allot([...$initial, 'kind' => 'additional', 'allotment_date' => '2025-06-01'])->assertSessionHasErrors('allotment_date');
    $allot([...$initial, 'kind' => 'additional', 'allotment_date' => '2025-08-01', 'face_value' => '100000.50', 'quantity_allotted' => '3'])->assertSessionHasNoErrors();

    [$first, $tranche] = $isin->allotments()->get()->all();
    expect($first->kind)->toBe(AllotmentKind::Initial)
        ->and($first->amount)->toBe('1500000000.00')
        ->and($first->files()->sole()->original_name)->toBe('credit.pdf')
        ->and($tranche->amount)->toBe('300001.50')
        ->and($isin->fresh()->allotment_date->toDateString())->toBe('2025-06-15'); // filled from the initial allotment
});

test('a schedule is generated from a frequency up to maturity, without duplicates', function () {
    dealUser(['deals.isin.manage']);
    addIsin($this->deal);
    $isin = DealIsin::query()->sole();

    addDueDates($this->deal, $isin, ['source' => 'generate', 'kind' => 'interest', 'first_due_on' => '2028-07-01', 'frequency' => 'quarterly'])
        ->assertSessionHasErrors(['first_due_on' => 'The first due date is after maturity.']);
    addDueDates($this->deal, $isin, ['source' => 'generate', 'kind' => 'interest', 'first_due_on' => '2025-05-15', 'frequency' => 'quarterly'])
        ->assertSessionHasErrors('first_due_on'); // before allotment
    addDueDates($this->deal, $isin, ['source' => 'generate', 'kind' => 'interest', 'first_due_on' => '2025-09-15', 'frequency' => 'quarterly'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', '12 due dates added.');
    addDueDates($this->deal, $isin, ['source' => 'generate', 'kind' => 'interest', 'first_due_on' => '2025-09-15', 'frequency' => 'quarterly'])
        ->assertSessionHas('success', 'Those dates are already in the schedule.');
    addDueDates($this->deal, $isin, ['source' => 'generate', 'kind' => 'principal', 'first_due_on' => '2028-06-15', 'frequency' => 'bullet'])
        ->assertSessionHas('success', '1 due date added.');
    addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'principal', 'due_on' => '2029-01-01'])
        ->assertSessionHasErrors(['due_on' => '01 Jan 2029 is after maturity (15 Jun 2028).']);

    $interest = $isin->payments()->where('kind', 'interest')->pluck('due_on')->map->toDateString();
    expect($interest->first())->toBe('2025-09-15')
        ->and($interest->last())->toBe('2028-06-15')
        ->and($isin->payments()->where('kind', 'principal')->sole()->due_on->toDateString())->toBe('2028-06-15')
        ->and(IsinPayment::query()->where('status', '!=', 'due')->count())->toBe(0);
});

test('a schedule file in Stack\'s format is checked in full before anything is added', function () {
    dealUser(['deals.isin.manage']);
    addIsin($this->deal);
    $isin = DealIsin::query()->sole();

    addDueDates($this->deal, $isin, ['source' => 'file', 'file' => scheduleFile("Dates\n15-09-2025\n")])
        ->assertSessionHasErrors('file');
    addDueDates($this->deal, $isin, ['source' => 'file', 'file' => scheduleFile("Principle Schedule,Interest Schedule\n,15-09-2025\n,31-02-2026\n")])
        ->assertSessionHasErrors(['file' => 'Line 3: "31-02-2026" isn\'t a date.']);
    addDueDates($this->deal, $isin, ['source' => 'file', 'file' => scheduleFile("Principal Schedule,Interest Schedule\n,15-09-2025\n15-06-2030,\n")])
        ->assertSessionHasErrors(['file' => '15 Jun 2030 is after maturity (15 Jun 2028).']);
    expect(IsinPayment::query()->count())->toBe(0);

    addDueDates($this->deal, $isin, ['source' => 'file', 'file' => scheduleFile("\xEF\xBB\xBFPrincipal Schedule,Interest Schedule\n,15-09-2025\n,15/12/2025\n15-06-2028,2028-06-15\n")])
        ->assertSessionHasNoErrors()->assertSessionHas('success', '4 due dates added.');
    expect($isin->payments()->where('kind', 'interest')->count())->toBe(3)
        ->and($isin->payments()->where('kind', 'principal')->count())->toBe(1);
});

test('a due date moves with a reason and keeps the original; settled dates are fixed', function () {
    $user = dealUser(['deals.isin.manage']);
    addIsin($this->deal);
    $isin = DealIsin::query()->sole();
    addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'interest', 'due_on' => '2025-12-15']);
    addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'interest', 'due_on' => '2026-03-15']);
    addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'principal', 'due_on' => '2028-06-15']);
    [$december, $march] = $isin->payments()->where('kind', 'interest')->get()->all();
    $principal = $isin->payments()->where('kind', 'principal')->sole();
    $base = "/deals/{$this->deal->ulid}/isin-payments";

    $this->put("{$base}/{$december->id}/due-date", ['due_on' => '2025-12-16'])->assertSessionHasErrors(['reason' => 'Say why the due date moved.']);
    $this->put("{$base}/{$december->id}/due-date", ['due_on' => '2026-03-15', 'reason' => 'Bank holiday'])->assertSessionHasErrors('due_on');
    $this->put("{$base}/{$december->id}/due-date", ['due_on' => '2025-12-16', 'reason' => 'Bank holiday on the 15th'])->assertSessionHasNoErrors();
    $this->put("{$base}/{$december->id}/due-date", ['due_on' => '2025-12-17', 'reason' => 'Moved again by the issuer'])->assertSessionHasNoErrors();
    $december->refresh();
    expect($december->due_on->toDateString())->toBe('2025-12-17')
        ->and($december->original_due_on->toDateString())->toBe('2025-12-15')
        ->and($december->due_date_reason)->toBe('Moved again by the issuer');

    // Recording: paid needs the date and amount; principal also says how it redeems.
    $this->post("{$base}/{$march->id}/record", ['status' => 'due'])->assertSessionHasErrors('status');
    $this->post("{$base}/{$march->id}/record", ['status' => 'paid', 'paid_on' => '2025-09-30'])->assertSessionHasErrors(['amount' => 'Enter the amount paid.']);
    $this->post("{$base}/{$march->id}/record", ['status' => 'paid', 'paid_on' => '2025-10-02', 'amount' => '35000000'])->assertSessionHasErrors('paid_on');
    $this->post("{$base}/{$march->id}/record", ['status' => 'paid', 'paid_on' => '2025-09-30', 'amount' => '35000000', 'files' => [UploadedFile::fake()->create('utr.pdf', 20, 'application/pdf')]])
        ->assertSessionHasNoErrors();
    $this->post("{$base}/{$principal->id}/record", ['status' => 'paid', 'paid_on' => '2025-09-30', 'amount' => '1500000000'])->assertSessionHasErrors('redemption_basis');
    $this->post("{$base}/{$principal->id}/record", ['status' => 'redeemed_early', 'remark' => 'Call exercised'])->assertSessionHasNoErrors();

    $march->refresh();
    expect($march->status)->toBe(IsinPaymentStatus::Paid)
        ->and($march->amount)->toBe('35000000.00')
        ->and($march->recorded_by)->toBe($user->id)
        ->and($march->redemption_basis)->toBeNull()
        ->and($march->files()->sole()->original_name)->toBe('utr.pdf')
        ->and($principal->fresh()->status)->toBe(IsinPaymentStatus::RedeemedEarly)
        ->and($isin->fresh()->load('payments')->isRedeemed())->toBeTrue();

    // A settled date can't be moved, removed or recorded again.
    $this->put("{$base}/{$march->id}/due-date", ['due_on' => '2026-03-20', 'reason' => 'Too late now'])->assertSessionHasErrors(['due_on' => 'This payment is already Paid.']);
    $this->delete("{$base}/{$march->id}")->assertSessionHasErrors('payment');
    $this->post("{$base}/{$march->id}/record", ['status' => 'defaulted'])->assertSessionHasErrors('status');
    $this->delete("{$base}/{$december->id}")->assertSessionHasNoErrors();
    expect(IsinPayment::query()->whereKey($december->id)->exists())->toBeFalse();

    // Maturity can't move before a settled payment.
    $this->put("/deals/{$this->deal->ulid}/isins/{$isin->ulid}", isinPayload(['maturity_date' => '2026-01-31']))->assertSessionHasErrors('maturity_date');

    // Another deal's payment isn't reachable through this deal.
    $other = Transaction::factory()->deal(DealStatus::Live)->create();
    $this->delete("/deals/{$other->ulid}/isin-payments/{$principal->id}")->assertNotFound();
});

test('reminders go once a day per payment, one email per deal, to the team and RM', function () {
    Notification::fake();
    dealUser(['deals.isin.manage']);
    addIsin($this->deal);
    $isin = DealIsin::query()->sole();
    // 1 Aug is overdue by more than 30 days: shown on the dashboard, no longer emailed.
    foreach (['2025-08-01', '2025-09-15', '2025-10-06', '2025-12-15'] as $date) {
        addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'interest', 'due_on' => $date]);
    }
    // Paid payments and closed deals are left out.
    $isin->payments()->whereDate('due_on', '2025-12-15')->update(['due_on' => '2025-10-03', 'status' => IsinPaymentStatus::Paid]);
    $closed = Transaction::factory()->deal(DealStatus::Closed)->create();
    $closed->isins()->create([...isinPayload(['isin' => 'INE002A01018']), 'created_by' => $closed->created_by])
        ->payments()->create(['kind' => IsinPaymentKind::Interest, 'due_on' => '2025-10-02', 'status' => IsinPaymentStatus::Due, 'created_by' => $closed->created_by]);

    $this->artisan('isin:send-reminders')->expectsOutput('1 reminder emails sent.')->assertSuccessful();
    $rm = $this->deal->relationshipManager->email;
    Notification::assertSentOnDemandTimes(IsinPaymentsDue::class, 1);
    Notification::assertSentOnDemand(IsinPaymentsDue::class, function (IsinPaymentsDue $n, array $channels, AnonymousNotifiable $to) use ($rm) {
        $mail = $n->toMail($to);

        return $to->routes['mail'] === ['dt-team@beacon.test', $rm]
            && count($n->paymentIds) === 2
            && $mail->subject === "Overdue and upcoming debenture payments: {$this->deal->company->name}"
            && str_contains(implode("\n", $mail->introLines), 'INE471X07014 · Interest due 15 Sep 2025 (overdue)');
    });
    expect(IsinReminder::query()->count())->toBe(2);

    // Not again today, by the job or by hand.
    $this->artisan('isin:send-reminders')->expectsOutput('0 reminder emails sent.');
    $this->post("/deals/{$this->deal->ulid}/isin-reminders")->assertSessionHasErrors('reminder');

    $this->travelTo('2025-10-02 09:00');
    $this->post("/deals/{$this->deal->ulid}/isin-reminders")->assertSessionHasNoErrors()->assertSessionHas('success', 'Reminder sent about 2 payments.');
    Notification::assertSentOnDemandTimes(IsinPaymentsDue::class, 2);
});

test('the deal tab, the ISIN list with its overdue filter, the export and the dashboard show the schedule', function () {
    dealUser(['deals.isin.manage']);
    addIsin($this->deal);
    $isin = DealIsin::query()->sole();
    addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'interest', 'due_on' => '2025-09-15']);
    addDueDates($this->deal, $isin, ['source' => 'single', 'kind' => 'interest', 'due_on' => '2025-12-15']);
    // A file on an allotment: the tab presents it (with its uploader and remover) without lazy loading.
    $this->post("/deals/{$this->deal->ulid}/isins/{$isin->ulid}/allotments", [
        'kind' => 'initial', 'allotment_date' => '2025-06-15', 'face_value' => '100000', 'quantity_allotted' => '10',
        'file' => UploadedFile::fake()->create('credit.pdf', 20, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $quiet = Transaction::factory()->deal(DealStatus::Live)->create();
    $quiet->isins()->create([...isinPayload(['isin' => 'INE002A01018']), 'created_by' => $quiet->created_by]);

    $this->get("/deals/{$this->deal->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can.manageIsin', true)
        ->where('isin.isins.0.isin', 'INE471X07014')
        ->where('isin.isins.0.overdue', 1)
        ->where('isin.isins.0.next_due.due_on', '2025-12-15')
        ->where('isin.isins.0.payments.0.status_tone', 'danger')
        ->where('isin.isins.0.allotments.0.files.0.name', 'credit.pdf'));

    // ISINs with something due come first; one with nothing due goes last.
    $this->get('/isins')->assertInertia(fn (AssertableInertia $page) => $page->component('Isins/Index')->has('isins.data', 2)
        ->where('isins.data.0.isin', 'INE471X07014')->where('isins.data.1.next_due_on', null));
    $this->get('/isins?sort=-next_due_on')->assertInertia(fn (AssertableInertia $page) => $page->where('isins.data.0.isin', 'INE471X07014'));
    $this->get('/isins?filter[due]=overdue')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('isins.data', 1)
        ->where('isins.data.0.isin', 'INE471X07014')
        ->where('isins.data.0.next_due_on', '2025-09-15')
        ->where('isins.data.0.overdue', 1));
    $this->get('/isins?filter[search]=INE002')->assertInertia(fn (AssertableInertia $page) => $page->has('isins.data', 1));

    $response = $this->get('/isins/export?filter[due]=overdue')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('isins-2025-10-01.xlsx');

    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('queue', fn ($queue) => collect($queue)->contains(fn ($item) => $item['kind'] === 'Payment overdue' && str_contains($item['detail'], 'Interest due 15 Sep 2025'))));
});
