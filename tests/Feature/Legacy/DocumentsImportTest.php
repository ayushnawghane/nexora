<?php

use App\Enums\ConditionStage;
use App\Enums\ConditionStatus;
use App\Enums\DealDocumentKind;
use App\Enums\DiligenceKind;
use App\Enums\ExecutionStatus;
use App\Enums\LegalDocumentCategory;
use App\Enums\OwnerIdType;
use App\Enums\RegistrationKind;
use App\Enums\SecurityNature;
use App\Enums\SignatoryType;
use App\Models\ConditionDocument;
use App\Models\DealCondition;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DealSecurity;
use App\Models\DocumentFile;
use App\Models\IssuingAuthority;
use App\Models\LegalDocumentType;
use App\Models\PoaHolder;
use App\Models\Product;
use App\Models\SecurityRegistration;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([PermissionSeeder::class, StateSeeder::class]);
    legacySchema();

    legacyRows('master_product', [['id' => 4, 'name' => 'Debenture Trustee', 'code' => 'DEB']]);
    legacyRows('users', [
        ['id' => 111, 'name' => 'Ops Officer', 'emp_code' => '111', 'email' => 'ops@b.test', 'password' => bcrypt('x')],
        ['id' => 112, 'name' => 'Legal Checker', 'emp_code' => '112', 'email' => 'legal@b.test', 'password' => bcrypt('x')],
    ]);
    legacyRows('master_cin', [['id' => 10, 'cin' => 'U65999MH2010PTC123456', 'company_name' => 'Aadhar Housing Finance Limited']]);
    legacyRows('transaction_status_master', [['id' => 10, 'status' => 'Live']]);
    legacyRows('transaction', [
        ['id' => 5001, 'product_id' => 4, 'cl_no' => 'BTL/DEB/EL/25-26/40', 'company_id' => 10, 'listed_unlisted' => 'Listed', 'secured' => 'Secured',
            'total_issue_size' => 2000000000, 'status' => 'Live', 'status_id' => 10, 'cl_date' => '2025-05-10', 'created_by' => 111],
    ]);

    legacyRows('issuing_authority', [
        ['id' => 1, 'Issuer_name' => 'Issuer/Borrower'],
        ['id' => 2, 'Issuer_name' => 'Statutory Auditor'],
        ['id' => 3, 'Issuer_name' => 'statutory auditor '], // a repeat
    ]);
    legacyRows('master_legal_documents', [
        ['id' => 9, 'legal_document_name' => 'Debenture / Bond Trust Deed', 'doc_category' => 1, 'product_id' => '2,4,17'],
        ['id' => 14, 'legal_document_name' => 'Deed Of Hypothecation', 'doc_category' => 2, 'product_id' => '2,4'],
        ['id' => 40, 'legal_document_name' => 'Escrow Agreement', 'doc_category' => 0, 'product_id' => '6'],
        // Added to deal 5001 in Stack: a supplement, two further copies (one numbered twice), and a one-off.
        ['id' => 501, 'con_id' => 5001, 'parent_id' => 14, 'supplementary_to' => 14, 'legal_document_name' => 'Supplement Deed Of Hypothecation-1', 'created_by' => 111],
        ['id' => 502, 'con_id' => 5001, 'parent_id' => 14, 'supplementary_to' => 14, 'legal_document_name' => 'Deed Of Hypothecation-1'],
        ['id' => 503, 'con_id' => 5001, 'parent_id' => 14, 'supplementary_to' => 14, 'legal_document_name' => 'Deed Of Hypothecation-1'],
        ['id' => 504, 'con_id' => 5001, 'legal_document_name' => 'Trustee Replacement Agreement'],
    ]);
    legacyRows('upload_file', [
        ['id' => 7001, 'name' => 'DTD draft.pdf', 'path' => 'documents/dtd-draft.pdf', 'created_by' => 111],
        ['id' => 7002, 'name' => 'DTD executed.pdf', 'path' => 'documents/dtd-executed.pdf', 'created_by' => 111],
        ['id' => 7003, 'name' => 'Supplement.pdf', 'path' => 'documents/supplement.pdf'],
        ['id' => 7101, 'name' => 'MOA.pdf', 'path' => 'erp_documents/compliance/moa.pdf'],
        ['id' => 7102, 'name' => 'Audit cert.pdf', 'path' => 'legal/cp_upload/audit.pdf'],
        ['id' => 7103, 'name' => 'Old cert.pdf', 'path' => 'legal/cp_upload/old.pdf'],
        ['id' => 7201, 'name' => 'Listing.pdf', 'path' => 'legal/cs_upload/listing.pdf'],
    ]);
    legacyRows('upload_document_mapping', [
        ['con_id' => 5001, 'legal_id' => 9, 'upload_id' => 7001, 'is_active' => 0, 'is_deleted' => 1, 'updated_at' => '2025-06-01 10:00:00'],
        ['con_id' => 5001, 'legal_id' => 9, 'upload_id' => 7002, 'created_by' => 111],
        ['con_id' => 5001, 'legal_id' => 501, 'upload_id' => 7003],
    ]);

    legacyRows('cp_documents', [
        ['id' => 1, 'issuing_authority_id' => 1, 'cp_doc_name' => 'MOA, AOA and Certificate of Incorporation'],
        ['id' => 2, 'issuing_authority_id' => 2, 'cp_doc_name' => 'Security cover certificate'],
        ['id' => 3, 'issuing_authority_id' => 2, 'cp_doc_name' => 'Security cover certificate'], // repeated name in Stack's master
        ['id' => 9, 'issuing_authority_id' => 1, 'cp_doc_name' => 'Deal-only CP', 'con_id' => 5001],
    ]);
    legacyRows('cp_mapping', [['cp_doc_id' => 1, 'listed_secured' => 1, 'unlisted_secured' => 1]]);
    legacyRows('cs_document', [['id' => 1, 'issuing_authority_id' => 1, 'cs_doc_name' => 'Listing approval']]);

    legacyRows('pre_documents_data', [
        // Verified, matched by master id.
        ['id' => 1, 'con_id' => 5001, 'cp_document_id' => 1, 'document_name' => 'MOA, AOA and Certificate of Incorporation', 'status' => 'verified',
            'created_by' => 111, 'created_date' => '2025-05-12 10:00:00', 'updated_by' => 111, 'updated_at' => '2025-05-13 10:00:00', 'verified_by' => 112, 'verified_date' => '2025-05-14 10:00:00'],
        // From the older ERP: no master id, matched by name; listed twice (via the repeated master), files on both rows.
        ['id' => 2, 'con_id' => 5001, 'document_name' => 'Security cover certificate', 'issuer_name' => 'Statutory Auditor', 'status' => 'WIP', 'upload_id' => '7103', 'created_by' => 111],
        ['id' => 3, 'con_id' => 5001, 'cp_document_id' => 3, 'document_name' => 'Security cover certificate', 'status' => 'verified', 'verified_by' => 112, 'verified_date' => '2025-06-01 10:00:00'],
        // Pending with no master match: an item for this deal only.
        ['id' => 4, 'con_id' => 5001, 'document_name' => 'Escrow bank letter', 'status' => 'pending'],
        // WIP without files: arrives pending.
        ['id' => 5, 'con_id' => 5001, 'cp_document_id' => 9, 'document_name' => 'Deal-only CP', 'status' => 'WIP'],
        // Removed in Stack.
        ['id' => 6, 'con_id' => 5001, 'document_name' => 'Old item', 'status' => 'pending', 'is_active' => 0],
    ]);
    legacyRows('pre_post_upload_map', [
        ['id' => 1, 'section' => 'pre', 'upload_id' => 7101],
        ['id' => 3, 'section' => 'pre', 'upload_id' => 7102],
    ]);
    legacyRows('post_documents_data', [
        ['id' => 1, 'con_id' => 5001, 'cs_document_id' => 1, 'document_name' => 'Listing approval', 'status' => 'WIP', 'created_by' => 111],
    ]);
    legacyRows('pre_post_upload_map', [['id' => 1, 'section' => 'post', 'upload_id' => 7201]]);
});

test('masters, deal documents and CP/CS items come over with their files', function () {
    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    $deal = Transaction::query()->where('legacy_id', 5001)->sole();
    $checker = User::query()->where('legacy_id', 112)->value('id');

    // Masters: repeats merged, categories mapped, products linked.
    expect(IssuingAuthority::query()->pluck('name')->all())->toBe(['Issuer/Borrower', 'Statutory Auditor']);
    $deed = LegalDocumentType::query()->where('legacy_id', 9)->sole();
    expect($deed->category)->toBe(LegalDocumentCategory::Transaction)
        ->and($deed->products()->pluck('code')->all())->toBe([Product::DEBENTURE_TRUSTEE])
        ->and(LegalDocumentType::query()->where('legacy_id', 40)->value('category'))->toBe(LegalDocumentCategory::Transaction); // no category in Stack
    expect(ConditionDocument::query()->where('stage', ConditionStage::Precedent)->pluck('name')->all())
        ->toBe(['MOA, AOA and Certificate of Incorporation', 'Security cover certificate']);
    $moa = ConditionDocument::query()->where('legacy_id', 1)->where('stage', ConditionStage::Precedent)->sole();
    expect($moa->listed_secured)->toBeTrue()->and($moa->listed_unsecured)->toBeFalse()->and($moa->unlisted_secured)->toBeTrue();

    // Deal documents: the standard one with its upload history, the supplement, numbered copies, the one-off.
    expect($deal->dealDocuments()->orderBy('name')->pluck('name')->all())->toBe([
        'Debenture / Bond Trust Deed', 'Deed Of Hypothecation-1', 'Deed Of Hypothecation-1', 'Supplement Deed Of Hypothecation-1', 'Trustee Replacement Agreement',
    ]);
    $dtd = DealDocument::query()->where('transaction_id', $deal->id)->where('kind', DealDocumentKind::Standard)->sole();
    expect($dtd->currentFile->original_name)->toBe('DTD executed.pdf')
        ->and($dtd->currentFile->isAvailable())->toBeFalse() // no uploads copy configured
        ->and($dtd->files()->whereNotNull('removed_at')->value('original_name'))->toBe('DTD draft.pdf');
    $copies = DealDocument::query()->where('transaction_id', $deal->id)->where('kind', DealDocumentKind::Additional)
        ->where('legal_document_type_id', LegalDocumentType::query()->where('legacy_id', 14)->value('id'))->orderBy('sequence')->pluck('sequence')->all();
    expect($copies)->toBe([1, 2]) // the second "-1" is renumbered
        ->and(DealDocument::query()->where('legacy_id', 501)->sole()->currentFile->original_name)->toBe('Supplement.pdf')
        ->and(DealDocument::query()->where('legacy_id', 504)->sole()->type->name)->toBe('Other document');

    // CP items.
    $cp = $deal->conditions()->where('stage', ConditionStage::Precedent)->get()->keyBy('name');
    expect($cp->keys()->sort()->values()->all())->toBe(['Deal-only CP', 'Escrow bank letter', 'MOA, AOA and Certificate of Incorporation', 'Security cover certificate']);

    expect($cp['MOA, AOA and Certificate of Incorporation']->status)->toBe(ConditionStatus::Verified)
        ->and($cp['MOA, AOA and Certificate of Incorporation']->checker_id)->toBe($checker)
        ->and($cp['MOA, AOA and Certificate of Incorporation']->condition_document_id)->toBe($moa->id)
        ->and($cp['MOA, AOA and Certificate of Incorporation']->currentFiles()->value('original_name'))->toBe('MOA.pdf');

    // The repeat merged in: both files, and the verified status wins.
    $cover = $cp['Security cover certificate'];
    expect($cover->status)->toBe(ConditionStatus::Verified)
        ->and($cover->condition_document_id)->not->toBeNull()
        ->and($cover->issuing_authority_id)->toBe(IssuingAuthority::query()->where('name', 'Statutory Auditor')->value('id'))
        ->and($cover->currentFiles()->pluck('original_name')->sort()->values()->all())->toBe(['Audit cert.pdf', 'Old cert.pdf']);

    expect($cp['Escrow bank letter']->condition_document_id)->toBeNull()
        ->and($cp['Escrow bank letter']->status)->toBe(ConditionStatus::Pending)
        ->and($cp['Deal-only CP']->status)->toBe(ConditionStatus::Pending); // WIP without files

    // CS item waiting for a check.
    $cs = $deal->conditions()->where('stage', ConditionStage::Subsequent)->sole();
    expect($cs->status)->toBe(ConditionStatus::Submitted)
        ->and($cs->submitted_by)->toBe(User::query()->where('legacy_id', 111)->value('id'));

    // Running it again changes nothing.
    $counts = fn () => [DealDocument::withTrashed()->count(), DealCondition::query()->count(), DocumentFile::query()->count()];
    $before = $counts();
    $this->artisan('legacy:import', ['area' => 'documents'])->assertSuccessful();
    expect($counts())->toBe($before);
});

test('files are copied from the uploads copy when one is configured', function () {
    $root = storage_path('framework/testing/stack-uploads');
    @mkdir("{$root}/documents", 0777, true);
    file_put_contents("{$root}/documents/dtd-executed.pdf", "%PDF-1.4\n%test\n");
    config(['legacy.uploads_path' => $root]);

    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    $dtd = DealDocument::query()->where('kind', DealDocumentKind::Standard)->sole();
    expect($dtd->currentFile->isAvailable())->toBeTrue();
    Storage::disk('local')->assertExists($dtd->currentFile->path);

    @unlink("{$root}/documents/dtd-executed.pdf");
});

test('POA holders and executions come over, one per document, with their executed copies', function () {
    legacyRows('poa_master', [
        ['id' => 1, 'poa_name' => 'Ravi POA', 'email' => 'RAVI@poa.test', 'mobile' => '98200 12345', 'valid_from' => '2024-01-01', 'valid_till' => '2026-12-31'],
        ['id' => 2, 'poa_name' => 'Bad Email POA', 'email' => 'not-an-email'],
    ]);
    legacyRows('upload_file', [['id' => 7301, 'name' => 'DTD signed.pdf', 'path' => 'execution/dtd-signed.pdf', 'created_by' => 111]]);
    legacyRows('execution_details', [
        // Two rows for the trust deed: the verified one wins.
        ['id' => 1, 'con_id' => 5001, 'doc_id' => 9, 'exe_place' => 'Mumbai', 'exe_date' => '2025-05-20', 'exe_time' => '11:00:00', 'sign_type' => 'Internal', 'sign_name' => '112'],
        ['id' => 2, 'con_id' => 5001, 'doc_id' => 9, 'exe_place' => 'Mumbai', 'exe_date' => '2025-05-21', 'exe_time' => '12:00:00', 'sign_type' => 'External', 'sign_name' => '1',
            'upload_id' => 7301, 'uploaded_by' => '111', 'uploaded_date' => '2025-05-22 10:00:00', 'execution_date' => '2025-05-21', 'is_verified' => 1, 'verified_by' => 112, 'verified_datetime' => '2025-05-23 10:00:00'],
        // The supplement (a per-deal document), scheduled only.
        ['id' => 3, 'con_id' => 5001, 'doc_id' => 501, 'exe_place' => 'Pune', 'exe_date' => '2025-06-01', 'sign_type' => 'Internal', 'sign_name' => '111'],
        // Removed in Stack.
        ['id' => 4, 'con_id' => 5001, 'doc_id' => 14, 'is_active' => 0],
    ]);

    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    expect(PoaHolder::query()->orderBy('name')->get(['name', 'email', 'mobile'])->toArray())->toBe([
        ['name' => 'Bad Email POA', 'email' => null, 'mobile' => null],
        ['name' => 'Ravi POA', 'email' => 'ravi@poa.test', 'mobile' => '9820012345'],
    ]);

    $deal = Transaction::query()->where('legacy_id', 5001)->sole();
    expect($deal->executions()->count())->toBe(2);

    $dtd = DealExecution::query()->where('legacy_id', 2)->sole();
    expect($dtd->status)->toBe(ExecutionStatus::Verified)
        ->and($dtd->document->kind)->toBe(DealDocumentKind::Standard)
        ->and($dtd->signatory_type)->toBe(SignatoryType::External)
        ->and($dtd->poaHolder->name)->toBe('Ravi POA')
        ->and($dtd->scheduled_at->format('Y-m-d H:i'))->toBe('2025-05-21 12:00')
        ->and($dtd->executed_on->toDateString())->toBe('2025-05-21')
        ->and($dtd->checker_id)->toBe(User::query()->where('legacy_id', 112)->value('id'))
        ->and($dtd->currentFile->original_name)->toBe('DTD signed.pdf');

    $supplement = DealExecution::query()->where('legacy_id', 3)->sole();
    expect($supplement->status)->toBe(ExecutionStatus::Scheduled)
        ->and($supplement->document->legacy_id)->toBe(501)
        ->and($supplement->signatoryUser->legacy_id)->toBe(111);

    // Running it again changes nothing.
    $before = [DealExecution::query()->count(), DocumentFile::query()->count()];
    $this->artisan('legacy:import', ['area' => 'execution'])->assertSuccessful();
    expect([DealExecution::query()->count(), DocumentFile::query()->count()])->toBe($before);
});

test('securities, registrations and due diligence come over; empty Stack rows are skipped', function () {
    legacyRows('master_asset_type', [['id' => 2, 'type_asset' => 'Movable Assets']]);
    legacyRows('master_type_charge', [['id' => 2, 'type_charge' => 'First Exclusive']]);
    legacyRows('master_security', [['id' => 13, 'security_name' => 'Receivables', 'asset_type_id' => 2]]);
    legacyRows('ea_master', [['id' => 57, 'ea_code' => 'EA-57', 'ea_name' => 'G V Jain & Co']]);
    legacyRows('legal_compliance_documents_data', [
        ['id' => 1, 'con_id' => 5001, 'legal_id' => 14, 'asset_owner' => 'Aadhar Housing Finance Limited', 'charge_type' => 'first_exclusive',
            'asset_type' => 'Movable Assets', 'encumbered' => 'non-encumbered', 'asset_office' => 'All receivables', 'cin_pan_num' => 'U65999MH2010PTC123456'],
        ['id' => 2, 'con_id' => 5001, 'legal_id' => 9], // an empty placeholder
    ]);
    legacyRows('security_mapping', [['id' => 501, 'legal_id' => 1, 'security_id' => 13]]);
    legacyRows('upload_file', [['id' => 7401, 'name' => 'ROC challan.pdf', 'path' => 'roc/challan.pdf'], ['id' => 7402, 'name' => 'Cover cert.pdf', 'path' => 'dd/cover.pdf']]);
    legacyRows('sec_roc_mapping', [['id' => 1, 'con_id' => 5001, 'security_map_id' => '501', 'section' => 1, 'created_by' => 111]]);
    legacyRows('sec_roc_asset_type', [['id' => 1, 'con_id' => 5001, 'map_id' => 1, 'amount' => '2000000000', 'challan_date' => '2025-06-01', 'charge_id' => '100123456', 'srn_no' => 'AB12345', 'challan_id' => 7401]]);
    legacyRows('due_dilligience_security_certificate', [['id' => 1, 'con_id' => 5001, 'document' => 'Security Cover Certificate', 'issuing_authority_id' => 57, 'udin_unique_number' => '26064817EKEPNV6029']]);
    legacyRows('due_dilligience_upload_files', [['id' => 1, 'con_id' => 5001, 'type' => 4, 'upload_id' => 7402, 'is_verified' => 1, 'verified_by' => 112, 'verified_at' => '2025-06-05 10:00:00', 'created_by' => 111]]);

    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    $security = DealSecurity::query()->sole(); // the placeholder is skipped
    expect($security->nature)->toBe(SecurityNature::Hypothecation)
        ->and($security->chargeType->name)->toBe('First Exclusive')
        ->and($security->is_encumbered)->toBeFalse()
        ->and($security->owner_id_type)->toBe(OwnerIdType::Cin)
        ->and($security->securityTypes()->pluck('name')->all())->toBe(['Receivables'])
        ->and(LegalDocumentType::query()->where('legacy_id', 14)->value('security_nature'))->toBeNull(); // Stack type not set in this fixture

    $roc = SecurityRegistration::query()->sole();
    expect($roc->kind)->toBe(RegistrationKind::Roc)
        ->and($roc->reference)->toBe('100123456')
        ->and($roc->securities()->sole()->id)->toBe($security->id)
        ->and($roc->events()->sole()->filing_reference)->toBe('AB12345')
        ->and($roc->events()->sole()->files()->sole()->original_name)->toBe('ROC challan.pdf');

    $cover = DealDiligenceItem::query()->sole();
    expect($cover->kind)->toBe(DiligenceKind::SecurityCover)
        ->and($cover->status)->toBe(ConditionStatus::Verified)
        ->and($cover->agency->name)->toBe('G V Jain & Co')
        ->and($cover->currentFiles()->sole()->original_name)->toBe('Cover cert.pdf');

    $before = [DealSecurity::query()->count(), SecurityRegistration::query()->count(), DealDiligenceItem::query()->count(), DocumentFile::query()->count()];
    $this->artisan('legacy:import', ['area' => 'security'])->assertSuccessful();
    expect([DealSecurity::query()->count(), SecurityRegistration::query()->count(), DealDiligenceItem::query()->count(), DocumentFile::query()->count()])->toBe($before);
});
