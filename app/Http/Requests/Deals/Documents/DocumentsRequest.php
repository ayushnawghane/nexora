<?php

namespace App\Http\Requests\Deals\Documents;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

/** Base for the documentation forms: only document makers may use them, on an open deal. */
abstract class DocumentsRequest extends FormRequest
{
    protected string $ability = 'manageDocuments';

    public function authorize(): bool
    {
        /** @var Transaction $deal */
        $deal = $this->route('transaction');

        return $this->user()->can($this->ability, $deal);
    }
}
