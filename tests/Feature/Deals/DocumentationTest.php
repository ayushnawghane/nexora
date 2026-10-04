<?php

use App\Enums\ConditionStage;
use App\Enums\ConditionStatus;
use App\Enums\DealDocumentKind;
use App\Enums\DealStatus;
use App\Enums\LegalDocumentCategory;
use App\Enums\Listing;
use App\Models\ConditionDocument;
use App\Models\DealCondition;
use App\Models\DealDocument;
use App\Models\DocumentFile;
use App\Models\IssuingAuthority;
use App\Models\LegalDocumentType;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2025-10-01 10:00');
    $this->deal = Transaction::factory()->deal(DealStatus::Documentation, Listing::Listed)->create(); // listed, secured
    $this->deed = LegalDocumentType::query()->create(['name' => 'Debenture Trust Deed', 'category' => LegalDocumentCategory::Transaction]);
    $this->deed->products()->attach($this->deal->product_id);
    $this->issuer = IssuingAuthority::query()->create(['name' => 'Issuer']);
});

function pdf(string $name = 'deed.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 120, 'application/pdf');
}

function addDocument(Transaction $deal, LegalDocumentType $type, string $kind = 'standard')
{
    return test()->post("/deals/{$deal->ulid}/documents", ['legal_document_type_id' => $type->id, 'kind' => $kind]);
}

function addConditions(Transaction $deal, array $data)
{
    return test()->post("/deals/{$deal->ulid}/conditions", $data);
}

function uploadToCondition(Transaction $deal, DealCondition $condition, array $files)
{
    return test()->post("/deals/{$deal->ulid}/conditions/{$condition->id}/files", ['files' => $files]);
}

function checkCondition(Transaction $deal, DealCondition $condition, string $decision, ?string $comment = null)
{
    return test()->post("/deals/{$deal->ulid}/conditions/{$condition->id}/check", ['decision' => $decision, 'comment' => $comment]);
}

// ------------------------------------------------------------------ legal documents

test('a document is on a deal once; copies, supplements and amendments are numbered per kind', function () {
    dealUser(['deals.documents.manage']);

    addDocument($this->deal, $this->deed)->assertSessionHasNoErrors();
    addDocument($this->deal, $this->deed)->assertSessionHasErrors('legal_document_type_id');
    addDocument($this->deal, $this->deed, 'supplement')->assertSessionHasNoErrors();
    addDocument($this->deal, $this->deed, 'supplement')->assertSessionHasNoErrors();
    addDocument($this->deal, $this->deed, 'amendment')->assertSessionHasNoErrors();

    expect($this->deal->dealDocuments()->orderBy('id')->pluck('name')->all())->toBe([
        'Debenture Trust Deed',
        'Supplement Debenture Trust Deed-1',
        'Supplement Debenture Trust Deed-2',
        'Amendment Debenture Trust Deed-1',
    ]);

    // A removed supplement's number isn't reused.
    $second = DealDocument::query()->where('name', 'Supplement Debenture Trust Deed-2')->sole();
    $this->delete("/deals/{$this->deal->ulid}/documents/{$second->id}")->assertSessionHasNoErrors();
    addDocument($this->deal, $this->deed, 'supplement')->assertSessionHasNoErrors();
    expect(DealDocument::query()->latest('id')->value('name'))->toBe('Supplement Debenture Trust Deed-3')
        ->and(DealDocument::withTrashed()->find($second->id)->trashed())->toBeTrue();
});

test('only documents of the deal\'s product can be added, and only by makers on an open deal', function () {
    $other = LegalDocumentType::query()->create(['name' => 'Fund Trust Deed', 'category' => LegalDocumentCategory::Transaction]);
    $other->products()->attach(Product::query()->create(['code' => 'AIF', 'name' => 'AIF Trustee'])->id);

    dealUser();
    addDocument($this->deal, $this->deed)->assertForbidden();

    dealUser(['deals.documents.manage']);
    addDocument($this->deal, $other)->assertSessionHasErrors(['legal_document_type_id' => 'This document type isn\'t available for this deal\'s product.']);

    $closed = Transaction::factory()->deal(DealStatus::Redeemed)->create();
    $this->deed->products()->syncWithoutDetaching([$closed->product_id]);
    addDocument($closed, $this->deed)->assertForbidden();
});

test('a new execution version replaces the current file; the old one stays in the history', function () {
    $maker = dealUser(['deals.documents.manage']);
    addDocument($this->deal, $this->deed);
    $document = DealDocument::query()->sole();

    $this->post("/deals/{$this->deal->ulid}/documents/{$document->id}/file", ['file' => pdf('draft.pdf')])->assertSessionHasNoErrors();
    $this->post("/deals/{$this->deal->ulid}/documents/{$document->id}/file", ['file' => pdf('executed.pdf')])->assertSessionHasNoErrors();

    $files = $document->files()->get();
    expect($files)->toHaveCount(2)
        ->and($document->currentFile->original_name)->toBe('executed.pdf')
        ->and($files->firstWhere('original_name', 'draft.pdf')->removed_by)->toBe($maker->id);
    Storage::disk('local')->assertExists($document->currentFile->path);

    // The document can't go while it has a current file.
    $this->delete("/deals/{$this->deal->ulid}/documents/{$document->id}")->assertSessionHasErrors(['document' => 'Remove the uploaded file before removing the document.']);
    $this->delete("/deals/{$this->deal->ulid}/files/{$document->currentFile->ulid}")->assertSessionHasNoErrors();
    $this->delete("/deals/{$this->deal->ulid}/documents/{$document->id}")->assertSessionHasNoErrors();
});

test('uploads must be documents of an accepted type and size', function () {
    dealUser(['deals.documents.manage']);
    addDocument($this->deal, $this->deed);
    $document = DealDocument::query()->sole();
    $url = "/deals/{$this->deal->ulid}/documents/{$document->id}/file";

    $this->post($url, ['file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors(['file' => 'Upload a PDF, Word, Excel or image file.']);
    $this->post($url, ['file' => UploadedFile::fake()->create('huge.pdf', 21 * 1024, 'application/pdf')])->assertSessionHasErrors(['file' => 'The file must be 20 MB or smaller.']);
    expect(DocumentFile::query()->count())->toBe(0);
});

test('files download for deal viewers only, through their own deal', function () {
    dealUser(['deals.documents.manage']);
    addDocument($this->deal, $this->deed);
    $document = DealDocument::query()->sole();
    $this->post("/deals/{$this->deal->ulid}/documents/{$document->id}/file", ['file' => pdf('executed.pdf')]);
    $file = $document->currentFile;

    dealUser();
    $this->get("/deals/{$this->deal->ulid}/files/{$file->ulid}")->assertDownload('executed.pdf');

    $other = Transaction::factory()->deal()->create();
    $this->get("/deals/{$other->ulid}/files/{$file->ulid}")->assertNotFound();

    // A Stack file that hasn't been copied over yet.
    $file->forceFill(['path' => null])->save();
    $this->get("/deals/{$this->deal->ulid}/files/{$file->ulid}")->assertRedirect()->assertSessionHas('error');

    signIn();
    $this->get("/deals/{$this->deal->ulid}/files/{$file->ulid}")->assertForbidden();
});

// ------------------------------------------------------------------ CP / CS

test('CP items come from the master once each, or are written for the deal', function () {
    $kyc = ConditionDocument::query()->create(['stage' => ConditionStage::Precedent, 'name' => 'KYC of authorised signatories', 'issuing_authority_id' => $this->issuer->id, 'listed_secured' => true]);
    $rating = ConditionDocument::query()->create(['stage' => ConditionStage::Precedent, 'name' => 'Rating letter']);
    $cs = ConditionDocument::query()->create(['stage' => ConditionStage::Subsequent, 'name' => 'Listing approval']);

    dealUser(['deals.documents.manage']);
    addConditions($this->deal, ['stage' => 'precedent', 'source' => 'master', 'document_ids' => [$kyc->id, $cs->id]])
        ->assertSessionHasErrors('document_ids'); // a CS document isn't a CP
    addConditions($this->deal, ['stage' => 'precedent', 'source' => 'master', 'document_ids' => [$kyc->id, $rating->id], 'due_on' => '2025-10-15'])
        ->assertSessionHasNoErrors();
    addConditions($this->deal, ['stage' => 'precedent', 'source' => 'master', 'document_ids' => [$kyc->id]])
        ->assertSessionHasErrors(['document_ids' => 'Already on this deal: KYC of authorised signatories.']);

    addConditions($this->deal, ['stage' => 'precedent', 'source' => 'custom', 'name' => '  Escrow   account letter ', 'issuing_authority_id' => $this->issuer->id])->assertSessionHasNoErrors();
    addConditions($this->deal, ['stage' => 'precedent', 'source' => 'custom', 'name' => 'Escrow account letter'])->assertSessionHasErrors('name');

    $items = $this->deal->conditions()->orderBy('id')->get();
    expect($items->pluck('name')->all())->toBe(['KYC of authorised signatories', 'Rating letter', 'Escrow account letter'])
        ->and($items->every(fn (DealCondition $c) => $c->status === ConditionStatus::Pending))->toBeTrue()
        ->and($items[0]->issuing_authority_id)->toBe($this->issuer->id)
        ->and($items[0]->due_on->toDateString())->toBe('2025-10-15')
        ->and($items[2]->condition_document_id)->toBeNull();

    // The add form suggests the master documents set for this kind of issue, and hides those already added.
    $this->get("/deals/{$this->deal->ulid}?tab=documentation")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('documentation.issue', 'Listed, secured')
        ->has('documentation.options.conditions', 1)
        ->where('documentation.options.conditions.0.label', 'Listing approval')
        ->has('documentation.conditions', 3));
});

test('the uploader sends an item for checking; a different checker verifies it, and then it is final', function () {
    $item = DealCondition::query()->forceCreate(['transaction_id' => $this->deal->id, 'stage' => ConditionStage::Precedent, 'name' => 'Board resolution', 'status' => ConditionStatus::Pending, 'created_by' => $this->deal->created_by]);

    $maker = dealUser(['deals.documents.manage', 'deals.documents.verify']);
    uploadToCondition($this->deal, $item, [pdf('resolution.pdf'), UploadedFile::fake()->image('stamp.png')])->assertSessionHasNoErrors();
    $item->refresh();
    expect($item->status)->toBe(ConditionStatus::Submitted)
        ->and($item->submitted_by)->toBe($maker->id)
        ->and($item->currentFiles()->count())->toBe(2);

    checkCondition($this->deal, $item, 'verified')->assertSessionHasErrors(['decision' => 'You uploaded these files, so someone else has to check them.']);

    $checker = dealUser(['deals.documents.verify']);
    checkCondition($this->deal, $item, 'verified')->assertSessionHasNoErrors();
    $item->refresh();
    expect($item->status)->toBe(ConditionStatus::Verified)->and($item->checker_id)->toBe($checker->id);

    actAs($maker);
    uploadToCondition($this->deal, $item, [pdf()])->assertSessionHasErrors('files');
    $this->delete("/deals/{$this->deal->ulid}/files/{$item->currentFiles()->first()->ulid}")->assertSessionHasErrors('file');
    $this->post("/deals/{$this->deal->ulid}/conditions/{$item->id}/waive", ['reason' => 'Not needed after all'])->assertSessionHasErrors('reason');
});

test('a sent-back item needs a reason, stays sent back while files are fixed, and goes for checking again', function () {
    $item = DealCondition::query()->forceCreate(['transaction_id' => $this->deal->id, 'stage' => ConditionStage::Subsequent, 'name' => 'Listing approval', 'status' => ConditionStatus::Pending, 'created_by' => $this->deal->created_by]);

    $maker = dealUser(['deals.documents.manage']);
    uploadToCondition($this->deal, $item, [pdf('wrong.pdf')]);

    dealUser(['deals.documents.verify']);
    checkCondition($this->deal, $item, 'returned')->assertSessionHasErrors('comment');
    checkCondition($this->deal, $item, 'returned', 'This is the in-principle approval')->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe(ConditionStatus::Returned);

    actAs($maker);
    $wrong = $item->currentFiles()->sole();
    $this->delete("/deals/{$this->deal->ulid}/files/{$wrong->ulid}")->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe(ConditionStatus::Returned) // still sent back, comment kept
        ->and($wrong->fresh()->removed_by)->toBe($maker->id);

    uploadToCondition($this->deal, $item, [pdf('final-approval.pdf')])->assertSessionHasNoErrors();
    $item->refresh();
    expect($item->status)->toBe(ConditionStatus::Submitted)
        ->and($item->checker_id)->toBeNull()
        ->and($item->files()->count())->toBe(2);

    // Removing the only file of an item waiting for a check puts it back to pending.
    $this->delete("/deals/{$this->deal->ulid}/files/{$item->currentFiles()->sole()->ulid}")->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe(ConditionStatus::Pending);
});

test('an item can be marked not applicable with a reason; only an untouched item can be removed', function () {
    $make = fn (string $name) => DealCondition::query()->forceCreate(['transaction_id' => $this->deal->id, 'stage' => ConditionStage::Precedent, 'name' => $name, 'status' => ConditionStatus::Pending, 'created_by' => $this->deal->created_by]);
    $untouched = $make('Added by mistake');
    $used = $make('Security cover certificate');

    dealUser(['deals.documents.manage']);
    uploadToCondition($this->deal, $used, [pdf()]);

    $this->delete("/deals/{$this->deal->ulid}/conditions/{$used->id}")->assertSessionHasErrors('condition');
    $this->delete("/deals/{$this->deal->ulid}/conditions/{$untouched->id}")->assertSessionHasNoErrors();
    expect(DealCondition::query()->find($untouched->id))->toBeNull();

    $this->post("/deals/{$this->deal->ulid}/conditions/{$used->id}/waive", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("/deals/{$this->deal->ulid}/conditions/{$used->id}/waive", ['reason' => 'Unsecured tranche, no security cover'])->assertSessionHasNoErrors();
    $used->refresh();
    expect($used->status)->toBe(ConditionStatus::Waived)
        ->and($used->waived_reason)->toBe('Unsecured tranche, no security cover')
        ->and($used->currentFiles()->count())->toBe(1); // files stay on record
});

test('items past their due date are overdue until they are done', function () {
    $item = DealCondition::query()->forceCreate(['transaction_id' => $this->deal->id, 'stage' => ConditionStage::Subsequent, 'name' => 'Credit of debentures', 'status' => ConditionStatus::Pending, 'created_by' => $this->deal->created_by]);

    dealUser(['deals.documents.manage']);
    $this->put("/deals/{$this->deal->ulid}/conditions/{$item->id}/due-date", ['due_on' => '2025-09-15'])->assertSessionHasNoErrors();
    expect($item->fresh()->isOverdue())->toBeTrue();

    $this->put("/deals/{$this->deal->ulid}/conditions/{$item->id}/due-date", ['due_on' => ''])->assertSessionHasNoErrors();
    expect($item->fresh()->due_on)->toBeNull()->and($item->fresh()->isOverdue())->toBeFalse();
});

test('checkers see items waiting for them on the dashboard; makers see what was sent back', function () {
    $item = DealCondition::query()->forceCreate(['transaction_id' => $this->deal->id, 'stage' => ConditionStage::Precedent, 'name' => 'Board resolution', 'status' => ConditionStatus::Pending, 'created_by' => $this->deal->created_by]);
    $maker = dealUser(['deals.documents.manage']);
    uploadToCondition($this->deal, $item, [pdf()]);

    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->has('queue', 0));

    dealUser(['deals.documents.verify']);
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('queue', 1)
        ->where('queue.0.kind', 'CP check')
        ->where('queue.0.detail', 'Board resolution'));
    checkCondition($this->deal, $item, 'returned', 'Unsigned copy');

    actAs($maker);
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('queue', 1)
        ->where('queue.0.kind', 'Sent back to you'));
});

test('documents and CP/CS items of a closed deal can\'t change', function () {
    $closed = Transaction::factory()->deal(DealStatus::Redeemed)->create();
    $item = DealCondition::query()->forceCreate(['transaction_id' => $closed->id, 'stage' => ConditionStage::Precedent, 'name' => 'Board resolution', 'status' => ConditionStatus::Pending, 'created_by' => $closed->created_by]);

    dealUser(['deals.documents.manage', 'deals.documents.verify']);
    uploadToCondition($closed, $item, [pdf()])->assertForbidden();
    $this->post("/deals/{$closed->ulid}/conditions/{$item->id}/waive", ['reason' => 'Closing the file'])->assertForbidden();

    // An item of another deal is not reachable through this one.
    uploadToCondition($this->deal, $item, [pdf()])->assertNotFound();
});

test('the documentation tab lists documents with their current file and the add options', function () {
    dealUser(['deals.documents.manage']);
    addDocument($this->deal, $this->deed);
    $document = DealDocument::query()->sole();
    $this->post("/deals/{$this->deal->ulid}/documents/{$document->id}/file", ['file' => pdf('executed.pdf')]);

    $this->get("/deals/{$this->deal->ulid}?tab=documentation")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tab', 'documentation')
        ->where('can.manageDocuments', true)
        ->where('can.verifyDocuments', false)
        ->where('documentation.documents.0.name', 'Debenture Trust Deed')
        ->where('documentation.documents.0.kind', DealDocumentKind::Standard->value)
        ->where('documentation.documents.0.current.name', 'executed.pdf')
        ->where('documentation.options.types.0.on_deal', true));
});
