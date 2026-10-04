<?php

use App\Enums\DealDocumentKind;
use App\Enums\DealStatus;
use App\Enums\ExecutionStatus;
use App\Enums\LegalDocumentCategory;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\LegalDocumentType;
use App\Models\PoaHolder;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ExecutionScheduled;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2025-10-01 10:00');
    $this->deal = Transaction::factory()->deal(DealStatus::Documentation)->create();
    $type = LegalDocumentType::query()->create(['name' => 'Debenture Trust Deed', 'category' => LegalDocumentCategory::Transaction]);
    $this->document = DealDocument::query()->forceCreate([
        'transaction_id' => $this->deal->id, 'legal_document_type_id' => $type->id, 'kind' => DealDocumentKind::Standard,
        'sequence' => 0, 'name' => 'Debenture Trust Deed', 'created_by' => $this->deal->created_by,
    ]);
    $this->document->files()->create(['path' => 'x.pdf', 'original_name' => 'DTD final.pdf', 'uploaded_by' => $this->deal->created_by]);
    $this->signatory = User::factory()->create(['is_authorised_signatory' => true, 'name' => 'Asha Signatory']);
});

function executedPdf(string $name = 'DTD executed.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 200, 'application/pdf');
}

function sendDocuments(Transaction $deal, array $ids)
{
    return test()->post("/deals/{$deal->ulid}/executions", ['document_ids' => $ids]);
}

function scheduleExecutions(Transaction $deal, array $data)
{
    return test()->post("/deals/{$deal->ulid}/executions/schedule", $data);
}

function recordExecution(Transaction $deal, DealExecution $execution, array $data = [])
{
    return test()->post("/deals/{$deal->ulid}/executions/{$execution->id}/record", array_merge(
        ['file' => executedPdf(), 'executed_on' => '2025-09-30', 'document_date' => '2025-09-29', 'comments' => 'Stamped'], $data,
    ));
}

test('a document goes to execution once, and only with its execution version uploaded', function () {
    $draft = DealDocument::query()->forceCreate([
        'transaction_id' => $this->deal->id, 'legal_document_type_id' => $this->document->legal_document_type_id, 'kind' => DealDocumentKind::Supplement,
        'sequence' => 1, 'name' => 'Supplement Debenture Trust Deed-1', 'created_by' => $this->deal->created_by,
    ]);

    dealUser(['deals.documents.manage']);
    sendDocuments($this->deal, [$this->document->id])->assertForbidden();

    dealUser(['deals.execution.manage', 'deals.documents.manage']);
    sendDocuments($this->deal, [$draft->id])->assertSessionHasErrors(['document_ids' => 'Supplement Debenture Trust Deed-1 has no execution version uploaded.']);
    sendDocuments($this->deal, [$this->document->id])->assertSessionHasNoErrors();
    sendDocuments($this->deal, [$this->document->id])->assertSessionHasErrors(['document_ids' => 'Debenture Trust Deed is already in execution.']);

    expect(DealExecution::query()->sole()->status)->toBe(ExecutionStatus::ToSchedule);

    // While in execution, the document's execution version stays put.
    $this->post("/deals/{$this->deal->ulid}/documents/{$this->document->id}/file", ['file' => executedPdf('new.pdf')])->assertSessionHasErrors('file');
    $this->delete("/deals/{$this->deal->ulid}/files/{$this->document->currentFile->ulid}")->assertSessionHasErrors('file');
});

test('scheduling needs an active authorised signatory or a POA valid on the date, and emails them', function () {
    dealUser(['deals.execution.manage']);
    sendDocuments($this->deal, [$this->document->id]);
    $execution = DealExecution::query()->sole();
    $base = ['execution_ids' => [$execution->id], 'place' => 'Mumbai', 'scheduled_at' => '2025-10-03T15:30'];

    $notSignatory = User::factory()->create();
    scheduleExecutions($this->deal, [...$base, 'signatory_type' => 'internal', 'signatory_user_id' => $notSignatory->id])
        ->assertSessionHasErrors(['signatory_user_id' => 'Choose an active authorised signatory.']);

    $expired = PoaHolder::query()->create(['name' => 'Old POA', 'email' => 'old@poa.test', 'valid_till' => '2025-09-30']);
    scheduleExecutions($this->deal, [...$base, 'signatory_type' => 'external', 'poa_holder_id' => $expired->id])
        ->assertSessionHasErrors('poa_holder_id');

    scheduleExecutions($this->deal, [...$base, 'signatory_type' => 'internal', 'signatory_user_id' => $this->signatory->id])->assertSessionHasNoErrors();
    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Scheduled)
        ->and($execution->place)->toBe('Mumbai')
        ->and($execution->scheduled_at->format('Y-m-d H:i'))->toBe('2025-10-03 15:30')
        ->and($execution->signatoryName())->toBe('Asha Signatory');
    Notification::assertSentTo($this->signatory, ExecutionScheduled::class);

    // Rescheduled to a POA holder valid on the date; they're emailed too.
    $poa = PoaHolder::query()->create(['name' => 'Ravi POA', 'email' => 'ravi@poa.test', 'valid_from' => '2025-01-01', 'valid_till' => '2026-12-31']);
    scheduleExecutions($this->deal, [...$base, 'signatory_type' => 'external', 'poa_holder_id' => $poa->id])->assertSessionHasNoErrors();
    expect($execution->fresh()->signatory_user_id)->toBeNull()->and($execution->fresh()->poa_holder_id)->toBe($poa->id);
    Notification::assertSentTo($poa, ExecutionScheduled::class);
});

test('the uploader records the executed copy; a different checker verifies it, and then it is final', function () {
    $maker = dealUser(['deals.execution.manage', 'deals.execution.verify']);
    sendDocuments($this->deal, [$this->document->id]);
    $execution = DealExecution::query()->sole();

    recordExecution($this->deal, $execution)->assertSessionHasErrors(['file' => 'Schedule the execution first.']);
    scheduleExecutions($this->deal, ['execution_ids' => [$execution->id], 'place' => 'Mumbai', 'scheduled_at' => '2025-09-30T11:00', 'signatory_type' => 'internal', 'signatory_user_id' => $this->signatory->id]);

    recordExecution($this->deal, $execution, ['executed_on' => '2025-10-02'])->assertSessionHasErrors('executed_on');
    recordExecution($this->deal, $execution, ['document_date' => '2025-10-01'])->assertSessionHasErrors('document_date');
    recordExecution($this->deal, $execution, ['file' => UploadedFile::fake()->create('scan.docx', 10)])->assertSessionHasErrors(['file' => 'Upload the executed copy as a PDF.']);
    recordExecution($this->deal, $execution)->assertSessionHasNoErrors();

    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Executed)
        ->and($execution->uploaded_by)->toBe($maker->id)
        ->and($execution->executed_on->toDateString())->toBe('2025-09-30')
        ->and($execution->currentFile->original_name)->toBe('DTD executed.pdf');

    $this->post("/deals/{$this->deal->ulid}/executions/{$execution->id}/check", ['decision' => 'verified'])
        ->assertSessionHasErrors(['decision' => 'You uploaded the executed copy, so someone else has to check it.']);

    $checker = dealUser(['deals.execution.verify']);
    $this->post("/deals/{$this->deal->ulid}/executions/{$execution->id}/check", ['decision' => 'verified'])->assertSessionHasNoErrors();
    expect($execution->fresh()->status)->toBe(ExecutionStatus::Verified)->and($execution->fresh()->checker_id)->toBe($checker->id);

    actAs($maker);
    recordExecution($this->deal, $execution)->assertSessionHasErrors('file');
    $this->delete("/deals/{$this->deal->ulid}/files/{$execution->currentFile->ulid}")->assertSessionHasErrors('file');
});

test('a sent-back execution is recorded again with a new copy; the old one stays in the history', function () {
    $maker = dealUser(['deals.execution.manage']);
    sendDocuments($this->deal, [$this->document->id]);
    $execution = DealExecution::query()->sole();
    scheduleExecutions($this->deal, ['execution_ids' => [$execution->id], 'place' => 'Pune', 'scheduled_at' => '2025-09-30T11:00', 'signatory_type' => 'internal', 'signatory_user_id' => $this->signatory->id]);
    recordExecution($this->deal, $execution, ['file' => executedPdf('unsigned.pdf')]);

    dealUser(['deals.execution.verify']);
    $this->post("/deals/{$this->deal->ulid}/executions/{$execution->id}/check", ['decision' => 'returned'])->assertSessionHasErrors('comment');
    $this->post("/deals/{$this->deal->ulid}/executions/{$execution->id}/check", ['decision' => 'returned', 'comment' => 'Page 4 not signed'])->assertSessionHasNoErrors();

    actAs($maker);
    recordExecution($this->deal, $execution, ['file' => executedPdf('signed.pdf')])->assertSessionHasNoErrors();
    $execution->refresh();
    expect($execution->status)->toBe(ExecutionStatus::Executed)
        ->and($execution->currentFile->original_name)->toBe('signed.pdf')
        ->and($execution->files()->whereNotNull('removed_at')->value('original_name'))->toBe('unsigned.pdf');
});

test('a document can be taken out of execution only before a copy was uploaded', function () {
    dealUser(['deals.execution.manage']);
    sendDocuments($this->deal, [$this->document->id]);
    $execution = DealExecution::query()->sole();

    $this->delete("/deals/{$this->deal->ulid}/executions/{$execution->id}")->assertSessionHasNoErrors();
    expect(DealExecution::query()->count())->toBe(0);

    sendDocuments($this->deal, [$this->document->id]);
    $execution = DealExecution::query()->sole();
    scheduleExecutions($this->deal, ['execution_ids' => [$execution->id], 'place' => 'Pune', 'scheduled_at' => '2025-09-30T11:00', 'signatory_type' => 'internal', 'signatory_user_id' => $this->signatory->id]);
    recordExecution($this->deal, $execution);
    $this->delete("/deals/{$this->deal->ulid}/executions/{$execution->id}")->assertSessionHasErrors('execution');
});

test('custody picks up a deal once every document in execution is verified; it is on their dashboard until then', function () {
    $execution = DealExecution::query()->forceCreate([
        'transaction_id' => $this->deal->id, 'deal_document_id' => $this->document->id, 'status' => ExecutionStatus::Executed,
        'uploaded_by' => $this->deal->created_by, 'uploaded_at' => now(), 'created_by' => $this->deal->created_by,
    ]);

    $custody = dealUser(['deals.execution.custody']);
    $this->post("/deals/{$this->deal->ulid}/executions/pick-up")->assertSessionHasErrors('pickup');
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->has('queue', 0));

    $execution->forceFill(['status' => ExecutionStatus::Verified, 'checked_at' => now()])->save();
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('queue', 1)
        ->where('queue.0.kind', 'Ready for pickup'));

    $this->post("/deals/{$this->deal->ulid}/executions/pick-up")->assertSessionHasNoErrors();
    expect($execution->fresh()->picked_up_by)->toBe($custody->id);
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->has('queue', 0));
    $this->post("/deals/{$this->deal->ulid}/executions/pick-up")->assertSessionHasErrors(['pickup' => 'There\'s nothing waiting to be picked up.']);
});

test('the signatory sees documents to sign; the tab suggests going live once everything is verified', function () {
    DealExecution::query()->forceCreate([
        'transaction_id' => $this->deal->id, 'deal_document_id' => $this->document->id, 'status' => ExecutionStatus::Scheduled,
        'place' => 'Mumbai', 'scheduled_at' => '2025-10-03 15:30:00', 'signatory_type' => 'internal',
        'signatory_user_id' => $this->signatory->id, 'created_by' => $this->deal->created_by,
    ]);

    actAs($this->signatory);
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('queue.0.kind', 'To sign')
        ->where('queue.0.detail', 'Debenture Trust Deed · 03 Oct 2025, 15:30, Mumbai'));

    DealExecution::query()->update(['status' => ExecutionStatus::Verified]);
    dealUser();
    $this->get("/deals/{$this->deal->ulid}?tab=execution")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('execution.suggest_live', true)
        ->where('execution.executions.0.document', 'Debenture Trust Deed')
        ->where('deal.deal_status', 'documentation')); // never moved to Live by itself
});
