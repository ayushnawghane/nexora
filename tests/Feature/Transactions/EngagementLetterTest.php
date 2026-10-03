<?php

use App\Actions\Approvals\CastVote;
use App\Enums\TransactionStatus;
use App\Enums\VoteDecision;
use App\Models\ApprovalRequest;
use App\Models\NumberSequence;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->travelTo('2025-09-12 10:00'); // approve a few days before the planned EL date (16 Sep)
});

/** A transaction approved on 12 Sep 2025 whose acceptance fee runs from the EL date, 16 Sep 2025. */
function approvedTransaction(array $extraPermissions = ['transactions.issue_el']): Transaction
{
    $head = approver(head: true);
    $other = approver();
    wizardUser(['transactions.submit', ...$extraPermissions]);
    $transaction = draftReadyForApproval();
    test()->post("/transactions/{$transaction->ulid}/submit");
    $request = ApprovalRequest::query()->where('transaction_id', $transaction->id)->sole();
    foreach ([$head, $other] as $voter) {
        app(CastVote::class)->handle($request, $voter, VoteDecision::Approve, null, 'app');
    }

    return $transaction->fresh();
}

test('issuing the letter numbers it, sets the deal code, stores the PDF and activates the deal', function () {
    $transaction = approvedTransaction();
    $this->travelTo('2025-09-16 11:00');

    $this->post("/transactions/{$transaction->ulid}/letter", ['el_date' => '2025-09-16'])
        ->assertRedirect("/transactions/{$transaction->ulid}")
        ->assertSessionHas('success');

    $transaction->refresh();
    $letter = $transaction->engagementLetters()->sole();
    expect($transaction->status)->toBe(TransactionStatus::Active)
        ->and($transaction->el_number)->toBe('BTL/DEB/EL/25-26/1')
        ->and($transaction->deal_code)->toBe("DEB/25-26/{$transaction->id}")
        ->and($transaction->el_date->toDateString())->toBe('2025-09-16')
        ->and($letter->version)->toBe(1)
        ->and($letter->body_html)->toContain('BTL/DEB/EL/25-26/1')
        ->and($letter->body_html)->toContain(e($transaction->company->name))
        ->and($letter->body_html)->toContain(Money::inWords('2000000000'));
    Storage::disk('local')->assertExists($letter->pdf_path);
    expect(Storage::disk('local')->get($letter->pdf_path))->toStartWith('%PDF');

    $this->get("/transactions/{$transaction->ulid}/letters/1")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get("/transactions/{$transaction->ulid}/letters/2")->assertNotFound();

    // Can't be issued twice.
    $this->post("/transactions/{$transaction->ulid}/letter", ['el_date' => '2025-09-16'])->assertSessionHasErrors('el_date');
});

test('EL numbers run in one sequence per financial year, shared across transactions', function () {
    $first = approvedTransaction();
    $second = approvedTransaction();
    $this->travelTo('2025-09-16 11:00');

    $this->post("/transactions/{$first->ulid}/letter", ['el_date' => '2025-09-16']);
    $this->post("/transactions/{$second->ulid}/letter", ['el_date' => '2025-09-16']);

    expect($first->fresh()->el_number)->toBe('BTL/DEB/EL/25-26/1')
        ->and($second->fresh()->el_number)->toBe('BTL/DEB/EL/25-26/2')
        ->and(NumberSequence::query()->where('key', 'el:25-26')->value('last_value'))->toBe(2);
});

test('a refused issue uses up no number', function () {
    $transaction = approvedTransaction();
    $this->travelTo('2025-09-16 11:00');

    // The acceptance fee runs from the EL date, approved as 16 Sep: any other date is refused.
    $this->post("/transactions/{$transaction->ulid}/letter", ['el_date' => '2025-09-15'])->assertSessionHasErrors('el_date');
    // Future dates and dates before approval are refused too.
    $this->post("/transactions/{$transaction->ulid}/letter", ['el_date' => '2025-09-17'])->assertSessionHasErrors('el_date');
    $this->post("/transactions/{$transaction->ulid}/letter", ['el_date' => '2025-09-11'])->assertSessionHasErrors('el_date');

    expect(NumberSequence::query()->where('key', 'el:25-26')->value('last_value') ?? 0)->toBe(0)
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Approved);
});

test('only approved transactions get a letter, and only with the issue permission', function () {
    $transaction = approvedTransaction(extraPermissions: []);
    $this->travelTo('2025-09-16 11:00');
    $this->post("/transactions/{$transaction->ulid}/letter", ['el_date' => '2025-09-16'])->assertForbidden();

    wizardUser(['transactions.issue_el']);
    $draft = draftReadyForApproval();
    $this->post("/transactions/{$draft->ulid}/letter", ['el_date' => '2025-09-16'])->assertSessionHasErrors('el_date');
});

test('the transaction page offers the issue form with the date fixed by the fees', function () {
    $transaction = approvedTransaction();

    $this->get("/transactions/{$transaction->ulid}")->assertInertia(fn ($page) => $page
        ->where('letterIssue.fixed_date', '2025-09-16')
        ->where('letters', []));
});

test('amounts are written in words in the Indian system', function () {
    expect(Money::inWords('65000'))->toBe('Rupees Sixty Five Thousand Only')
        ->and(Money::inWords('2000000000'))->toBe('Rupees Two Hundred Crore Only')
        ->and(Money::inWords('1234567.89'))->toBe('Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven and Eighty Nine Paise Only')
        ->and(Money::format('123456789.5'))->toBe('₹12,34,56,789.50');
});
