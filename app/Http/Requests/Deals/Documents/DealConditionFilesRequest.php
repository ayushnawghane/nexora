<?php

namespace App\Http\Requests\Deals\Documents;

use App\Models\DocumentFile;

class DealConditionFilesRequest extends DocumentsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.DocumentFile::MAX_FILES],
            'files.*' => DocumentFile::RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Choose at least one file.',
            'files.max' => 'Upload at most '.DocumentFile::MAX_FILES.' files at a time.',
            'files.*.mimes' => 'Upload PDF, Word, Excel or image files only.',
            'files.*.max' => 'Each file must be 20 MB or smaller.',
        ];
    }
}
