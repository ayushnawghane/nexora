<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Documents\AddDealDocument;
use App\Actions\Documents\RemoveDealDocument;
use App\Actions\Documents\RemoveDocumentFile;
use App\Actions\Documents\UploadDealDocumentFile;
use App\Enums\DealDocumentKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\Documents\DealDocumentFileRequest;
use App\Http\Requests\Deals\Documents\DealDocumentStoreRequest;
use App\Models\DealCondition;
use App\Models\DealDiligenceItem;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DocumentFile;
use App\Models\LegalDocumentType;
use App\Models\SecurityRegistration;
use App\Models\SecurityRegistrationEvent;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A deal's legal documents and the files of its documents and CP/CS items. */
class DealDocumentController extends Controller
{
    public function store(DealDocumentStoreRequest $request, Transaction $transaction, AddDealDocument $add): RedirectResponse
    {
        $document = $add->handle(
            $transaction,
            LegalDocumentType::query()->findOrFail($request->integer('legal_document_type_id')),
            DealDocumentKind::from($request->validated('kind')),
            $request->user(),
        );

        return back()->with('success', "{$document->name} added.");
    }

    public function destroy(Request $request, Transaction $transaction, DealDocument $document, RemoveDealDocument $remove): RedirectResponse
    {
        $this->authorize('manageDocuments', $transaction);
        abort_unless($document->transaction_id === $transaction->id, 404);

        $remove->handle($document, $request->user());

        return back()->with('success', "{$document->name} removed.");
    }

    public function upload(DealDocumentFileRequest $request, Transaction $transaction, DealDocument $document, UploadDealDocumentFile $upload): RedirectResponse
    {
        abort_unless($document->transaction_id === $transaction->id, 404);

        $upload->handle($document, $request->file('file'), $request->user());

        return back()->with('success', "Execution version of {$document->name} uploaded.");
    }

    public function removeFile(Request $request, Transaction $transaction, DocumentFile $file, RemoveDocumentFile $remove): RedirectResponse
    {
        // Executed copies belong to the execution team; everything else to the document makers.
        $this->authorize(match ($file->attachable_type) {
            DealExecution::class => 'manageExecution',
            DealDiligenceItem::class => 'manageSecurity',
            default => 'manageDocuments',
        }, $transaction);
        abort_unless($this->dealOf($file) === $transaction->id, 404);

        $remove->handle($file, $request->user());

        return back()->with('success', "{$file->original_name} removed.");
    }

    public function download(Transaction $transaction, DocumentFile $file): StreamedResponse|RedirectResponse
    {
        $this->authorize('viewDeal', $transaction);
        abort_unless($this->dealOf($file) === $transaction->id, 404);

        if (! $file->isAvailable() || ! Storage::disk('local')->exists((string) $file->path)) {
            return back()->with('error', "{$file->original_name} hasn't been copied over from Stack yet.");
        }

        return Storage::disk('local')->download((string) $file->path, $file->original_name);
    }

    /** The deal a file belongs to, through its document, CP/CS item or execution (removed documents included). */
    private function dealOf(DocumentFile $file): ?int
    {
        return match ($file->attachable_type) {
            DealDocument::class => DealDocument::withTrashed()->whereKey($file->attachable_id)->value('transaction_id'),
            DealCondition::class => DealCondition::query()->whereKey($file->attachable_id)->value('transaction_id'),
            DealExecution::class => DealExecution::query()->whereKey($file->attachable_id)->value('transaction_id'),
            DealDiligenceItem::class => DealDiligenceItem::query()->whereKey($file->attachable_id)->value('transaction_id'),
            SecurityRegistrationEvent::class => SecurityRegistration::query()
                ->whereKey(SecurityRegistrationEvent::query()->whereKey($file->attachable_id)->value('security_registration_id'))
                ->value('transaction_id'),
            default => null,
        };
    }
}
