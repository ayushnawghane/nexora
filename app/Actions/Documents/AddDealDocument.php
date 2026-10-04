<?php

namespace App\Actions\Documents;

use App\Enums\DealDocumentKind;
use App\Models\DealDocument;
use App\Models\LegalDocumentType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddDealDocument
{
    /**
     * Adds a legal document to a deal's documentation list. A type can be on a deal once as the
     * standard document; further copies, supplements and amendments are numbered 1, 2, … per kind.
     */
    public function handle(Transaction $deal, LegalDocumentType $type, DealDocumentKind $kind, User $actor): DealDocument
    {
        return DB::transaction(function () use ($deal, $type, $kind, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['legal_document_type_id' => 'The documents of a closed deal can\'t be changed.']);
            }
            if (LegalDocumentType::query()->forProduct($locked->product_id)->whereKey($type->id)->doesntExist()) {
                throw ValidationException::withMessages(['legal_document_type_id' => 'This document type isn\'t available for this deal\'s product.']);
            }

            $existing = $locked->dealDocuments()->where('legal_document_type_id', $type->id)->where('kind', $kind);
            if ($kind === DealDocumentKind::Standard) {
                if ($existing->exists()) {
                    throw ValidationException::withMessages(['legal_document_type_id' => "{$type->name} is already on this deal. Add another copy, a supplement or an amendment instead."]);
                }
                $sequence = 0;
            } else {
                // Numbers are never reused, even after a document is removed (it stays in the history).
                $sequence = (int) $locked->dealDocuments()->withTrashed()
                    ->where('legal_document_type_id', $type->id)->where('kind', $kind)->max('sequence') + 1;
            }

            return $locked->dealDocuments()->create([
                'legal_document_type_id' => $type->id,
                'kind' => $kind,
                'sequence' => $sequence,
                'name' => $kind->documentName($type->name, $sequence),
                'created_by' => $actor->id,
            ]);
        });
    }
}
