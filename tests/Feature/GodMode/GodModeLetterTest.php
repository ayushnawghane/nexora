<?php

use App\Enums\DealStatus;
use App\Models\GodModeChange;
use App\Models\NumberSequence;
use App\Models\RetiredElNumber;
use App\Models\Transaction;
use App\Services\Auth\TwoFactor;
use App\Services\Letters\EngagementLetterRenderer;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([PermissionSeeder::class, StateSeeder::class]);
    $this->travelTo('2025-10-01 10:00');
    godUser();

    // A live deal with version 1 of its letter, numbered BTL/DEB/EL/25-26/7 and dated 1 Apr 2025.
    NumberSequence::query()->create(['key' => 'el:25-26', 'last_value' => 7]);
    $this->deal = Transaction::factory()->deal(DealStatus::Live)->create(['el_number' => 'BTL/DEB/EL/25-26/7']);
    $html = app(EngagementLetterRenderer::class)->html($this->deal, 'BTL/DEB/EL/25-26/7', CarbonImmutable::parse('2025-04-01'));
    Storage::disk('local')->put("letters/{$this->deal->ulid}/v1.pdf", '%PDF-1.4 test');
    $this->deal->engagementLetters()->create([
        'version' => 1, 'el_number' => 'BTL/DEB/EL/25-26/7', 'el_date' => '2025-04-01', 'body_html' => $html,
        'pdf_path' => "letters/{$this->deal->ulid}/v1.pdf", 'generated_by' => $this->deal->created_by,
    ]);
});

function letterUrl(Transaction $deal, string $action): string
{
    return "/god-mode/transactions/{$deal->ulid}/letter/{$action}";
}

test('regenerating makes version 2 from the current data and keeps version 1', function () {
    $this->post(letterUrl($this->deal, 'regenerate'), ['reason' => 'Registered address was corrected'])->assertSessionHasNoErrors();

    $letters = $this->deal->engagementLetters()->get();
    expect($letters)->toHaveCount(2)
        ->and($letters->first()->version)->toBe(2)
        ->and($letters->first()->reason)->toBe('God Mode: Registered address was corrected')
        ->and($letters->first()->el_number)->toBe('BTL/DEB/EL/25-26/7');
    Storage::disk('local')->assertExists($letters->first()->pdf_path);
    expect(GodModeChange::query()->sole()->after['version'])->toBe(2);
});

test('new wording is sanitised and keeps the letter\'s styles', function () {
    $this->post(letterUrl($this->deal, 'wording'), [
        'reason' => 'Client asked for clause 4 to be reworded',
        'body' => '<p class="lead">Dear Sir,</p><script>alert(1)</script><p onclick="x()">Revised <a href="http://evil.test">clause</a> 4.</p><table class="grid"><tr><td>Fee</td></tr></table>',
    ])->assertSessionHasNoErrors();

    $html = $this->deal->engagementLetters()->first()->body_html;
    expect($html)->toContain('<p class="lead">Dear Sir,</p>')
        ->and($html)->toContain('Revised clause 4.')
        ->and($html)->toContain('<table class="grid">')
        ->and($html)->toContain('<style>')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('onclick')
        ->and($html)->not->toContain('evil.test');

    $this->post(letterUrl($this->deal, 'wording'), ['reason' => 'Trying an empty letter', 'body' => '<script>x</script>'])->assertSessionHasErrors('body');
});

test('renumbering retires the old number for good and the search still finds the deal by it', function () {
    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Number was issued twice in legacy', 'number_mode' => 'next', 'el_date' => '2025-04-01'])
        ->assertSessionHasNoErrors();

    $deal = $this->deal->fresh();
    expect($deal->el_number)->toBe('BTL/DEB/EL/25-26/8')
        ->and(RetiredElNumber::query()->sole()->el_number)->toBe('BTL/DEB/EL/25-26/7')
        ->and($deal->engagementLetters()->first()->body_html)->toContain('BTL/DEB/EL/25-26/8')
        ->and($deal->engagementLetters()->first()->body_html)->not->toContain('BTL/DEB/EL/25-26/7');

    // The retired number can't be given back, by hand or otherwise.
    $this->post(letterUrl($deal, 'number'), ['reason' => 'Put the old number back', 'number_mode' => 'manual', 'el_number' => 'BTL/DEB/EL/25-26/7', 'el_date' => '2025-04-01'])
        ->assertSessionHasErrors(['el_number' => 'That EL number has already been used.']);

    $this->get('/god-mode?q='.urlencode('BTL/DEB/EL/25-26/7'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('results.transactions', 1));
});

test('a hand-entered number must match the date\'s financial year, and moves the sequence past it', function () {
    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Matching the signed copy', 'number_mode' => 'manual', 'el_number' => 'BTL/DEB/EL/24-25/90', 'el_date' => '2025-04-01'])
        ->assertSessionHasErrors(['el_number' => 'The number must look like BTL/DEB/EL/25-26/123 for an EL dated 01 Apr 2025.']);

    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Matching the signed copy', 'number_mode' => 'manual', 'el_number' => 'btl/deb/el/25-26/90', 'el_date' => '2025-04-02'])
        ->assertSessionHasNoErrors();

    expect($this->deal->fresh()->el_number)->toBe('BTL/DEB/EL/25-26/90')
        ->and($this->deal->fresh()->el_date->toDateString())->toBe('2025-04-02')
        ->and(NumberSequence::next('el:25-26'))->toBe(91);
});

test('only the date can change, but not into the future or before approval', function () {
    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Date typed wrongly', 'number_mode' => 'keep', 'el_date' => '2025-10-02'])->assertSessionHasErrors('el_date');
    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Date typed wrongly', 'number_mode' => 'keep', 'el_date' => '2025-03-01'])->assertSessionHasErrors('el_date');
    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Date typed wrongly', 'number_mode' => 'keep', 'el_date' => '2025-04-01'])
        ->assertSessionHasErrors(['el_number' => 'Give a new number or a new date.']);

    $this->post(letterUrl($this->deal, 'number'), ['reason' => 'Date typed wrongly', 'number_mode' => 'keep', 'el_date' => '2025-04-03'])->assertSessionHasNoErrors();
    expect($this->deal->fresh()->el_number)->toBe('BTL/DEB/EL/25-26/7')
        ->and(RetiredElNumber::query()->count())->toBe(0)
        ->and($this->deal->engagementLetters()->first()->body_html)->toContain('April 3, 2025');
});

test('a replacement PDF must be a real PDF and becomes the new version', function () {
    $this->post(letterUrl($this->deal, 'pdf'), ['reason' => 'Signed copy received', 'pdf' => UploadedFile::fake()->createWithContent('signed.pdf', 'not a pdf')])
        ->assertSessionHasErrors('pdf');
    expect($this->deal->engagementLetters()->count())->toBe(1);

    $this->post(letterUrl($this->deal, 'pdf'), ['reason' => 'Signed copy received', 'pdf' => UploadedFile::fake()->createWithContent('signed.pdf', '%PDF-1.7 signed')])
        ->assertSessionHasNoErrors();
    $letter = $this->deal->engagementLetters()->first();
    expect(Storage::disk('local')->get($letter->pdf_path))->toBe('%PDF-1.7 signed');
});

test('a correction to letter data after the latest letter prompts a regeneration', function () {
    $this->travel(1)->hours();
    $this->withSession([TwoFactor::SESSION_PASSED_AT => now()->getTimestamp()]); // God Mode wants a code from the last 15 minutes
    $form = godForm('company', (string) $this->deal->company_id);
    $this->post("/god-mode/correct/company/{$this->deal->company_id}", [
        'values' => ['name' => 'Renamed Finance Limited'] + $form['values'], 'fingerprint' => $form['fingerprint'], 'reason' => 'Name change approved by ROC',
    ])->assertSessionHasNoErrors();
    expect(GodModeChange::query()->where('editor', 'company')->exists())->toBeTrue();

    $this->get("/god-mode/transactions/{$this->deal->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('prompts.letter_outdated', true)
        ->where('letter.el_number', 'BTL/DEB/EL/25-26/7'));
});
