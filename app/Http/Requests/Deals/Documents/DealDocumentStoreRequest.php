<?php

namespace App\Http\Requests\Deals\Documents;

use App\Enums\DealDocumentKind;
use Illuminate\Validation\Rule;

class DealDocumentStoreRequest extends DocumentsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_document_type_id' => ['required', 'integer', Rule::exists('legal_document_types', 'id')->whereNull('deleted_at')],
            'kind' => ['required', Rule::enum(DealDocumentKind::class)],
        ];
    }

    public function attributes(): array
    {
        return ['legal_document_type_id' => 'document'];
    }
}
