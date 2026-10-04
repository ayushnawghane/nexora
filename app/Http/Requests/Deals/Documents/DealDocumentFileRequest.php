<?php

namespace App\Http\Requests\Deals\Documents;

use App\Models\DocumentFile;

class DealDocumentFileRequest extends DocumentsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['file' => ['required', ...DocumentFile::RULES]];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Upload a PDF, Word, Excel or image file.',
            'file.max' => 'The file must be 20 MB or smaller.',
        ];
    }
}
