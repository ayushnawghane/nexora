<?php

use App\Enums\DealStatus;
use App\Enums\JobSheetStatus;
use App\Enums\Listing;
use App\Models\DealJobSheetEntry;
use App\Models\JobSheetActivity;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->travelTo('2025-10-01 10:00');
    $this->deal = Transaction::factory()->deal()->create(); // unlisted issue
    $this->activity = JobSheetActivity::query()->create(['name' => 'Debenture trust deed received']);
});

function submitEntry(Transaction $deal, JobSheetActivity $activity, array $data = [])
{
    return test()->post("/deals/{$deal->ulid}/job-sheet/{$activity->id}", array_merge(['received_on' => '2025-09-29', 'comment' => 'Original received'], $data));
}

function checkEntry(Transaction $deal, DealJobSheetEntry $entry, string $decision, ?string $comment = null)
{
    return test()->post("/deals/{$deal->ulid}/job-sheet/entries/{$entry->id}/check", ['decision' => $decision, 'comment' => $comment]);
}

test('the maker submits, a different checker verifies', function () {
    $maker = dealUser(['deals.jobsheet.make', 'deals.jobsheet.check']);
    submitEntry($this->deal, $this->activity)->assertSessionHasNoErrors();

    $entry = DealJobSheetEntry::query()->sole();
    expect($entry->status)->toBe(JobSheetStatus::Submitted)
        ->and($entry->maker_id)->toBe($maker->id)
        ->and($entry->received_on->toDateString())->toBe('2025-09-29');

    checkEntry($this->deal, $entry, 'verified')->assertSessionHasErrors(['decision' => 'You made this entry, so someone else has to check it.']);

    $checker = dealUser(['deals.jobsheet.check']);
    checkEntry($this->deal, $entry, 'verified')->assertSessionHasNoErrors();
    $entry->refresh();
    expect($entry->status)->toBe(JobSheetStatus::Verified)
        ->and($entry->checker_id)->toBe($checker->id);

    // Verified is final.
    actAs($maker);
    submitEntry($this->deal, $this->activity)->assertSessionHasErrors(['activity' => 'This activity has already been verified.']);
    checkEntry($this->deal, $entry, 'returned', 'Again')->assertSessionHasErrors('decision');
});

test('a returned entry needs a reason and can be submitted again', function () {
    $maker = dealUser(['deals.jobsheet.make']);
    submitEntry($this->deal, $this->activity);
    submitEntry($this->deal, $this->activity)->assertSessionHasErrors(['activity' => 'This activity is already waiting for a check.']);
    $entry = DealJobSheetEntry::query()->sole();

    dealUser(['deals.jobsheet.check']);
    checkEntry($this->deal, $entry, 'returned')->assertSessionHasErrors('comment');
    checkEntry($this->deal, $entry, 'returned', 'Stamp duty receipt missing')->assertSessionHasNoErrors();
    expect($entry->fresh()->status)->toBe(JobSheetStatus::Returned);

    actAs($maker);
    submitEntry($this->deal, $this->activity, ['comment' => 'Receipt attached'])->assertSessionHasNoErrors();
    $entry->refresh();
    expect($entry->status)->toBe(JobSheetStatus::Submitted)
        ->and($entry->checker_id)->toBeNull()
        ->and($entry->maker_comment)->toBe('Receipt attached');
});

test('activities follow the deal\'s listing, and inactive ones are not on the sheet', function () {
    $listedOnly = JobSheetActivity::query()->create(['name' => 'Listing approval', 'listing' => Listing::Listed]);
    $unlistedOnly = JobSheetActivity::query()->create(['name' => 'Private placement memorandum', 'listing' => Listing::Unlisted]);
    JobSheetActivity::query()->create(['name' => 'Old checklist item', 'is_active' => false]);

    dealUser(['deals.jobsheet.make']);
    submitEntry($this->deal, $listedOnly)->assertSessionHasErrors(['activity' => 'This activity isn\'t on this deal\'s job sheet.']);
    submitEntry($this->deal, $unlistedOnly)->assertSessionHasNoErrors();

    $this->get("/deals/{$this->deal->ulid}?tab=job-sheet")->assertInertia(fn (AssertableInertia $page) => $page
        ->has('jobSheet', 2)
        ->where('jobSheet.0.activity', 'Debenture trust deed received')
        ->where('jobSheet.1.activity', 'Private placement memorandum')
        ->where('jobSheet.1.entry.status', 'submitted')
        ->where('jobSheet.1.can_check', false));
});

test('dates in the future, missing permissions and closed deals are refused', function () {
    dealUser(['deals.jobsheet.make']);
    submitEntry($this->deal, $this->activity, ['received_on' => '2025-10-02'])->assertSessionHasErrors('received_on');

    dealUser(['deals.jobsheet.check']);
    submitEntry($this->deal, $this->activity)->assertForbidden();

    dealUser(['deals.jobsheet.make']);
    $closed = Transaction::factory()->deal(DealStatus::Closed)->create();
    submitEntry($closed, $this->activity)->assertForbidden();

    // An entry can only be checked through its own deal.
    submitEntry($this->deal, $this->activity);
    $entry = DealJobSheetEntry::query()->sole();
    $other = Transaction::factory()->deal()->create();
    actAs(tap(User::factory()->create())->givePermissionTo(['deals.view', 'deals.jobsheet.check']));
    checkEntry($other, $entry, 'verified')->assertNotFound();
});

test('job sheet activities are managed as a master, with listing as a choice', function () {
    signIn(permissions: ['masters.view', 'masters.manage']);

    $this->post('/masters/job-sheet-activities', ['name' => 'Security cover certificate', 'listing' => 'listed'])->assertSessionHasNoErrors();
    $this->post('/masters/job-sheet-activities', ['name' => 'Bad', 'listing' => 'sometimes'])->assertSessionHasErrors('listing');

    expect(JobSheetActivity::query()->where('name', 'Security cover certificate')->sole()->listing)->toBe(Listing::Listed);
    $this->get('/masters/job-sheet-activities')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('records.data.0.listing__label', 'All deals')
        ->where('records.data.1.listing__label', 'Listed'));
});
