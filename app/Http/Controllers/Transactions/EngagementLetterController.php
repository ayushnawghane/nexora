<?php

namespace App\Http\Controllers\Transactions;

use App\Actions\Letters\IssueEngagementLetter;
use App\Http\Controllers\Controller;
use App\Models\EngagementLetter;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngagementLetterController extends Controller
{
    public function issue(Request $request, Transaction $transaction, IssueEngagementLetter $issue): RedirectResponse
    {
        $this->authorize('transactions.issue_el');
        $data = $request->validate(['el_date' => ['required', 'date_format:Y-m-d']]);

        $letter = $issue->handle($transaction, $request->user(), CarbonImmutable::parse($data['el_date']));

        return redirect()->route('transactions.show', $transaction)
            ->with('success', "Engagement letter {$letter->el_number} issued. The deal is now active.");
    }

    /** Streams one version's PDF to anyone who can view the transaction. */
    public function download(Transaction $transaction, int $version): StreamedResponse
    {
        $this->authorize('view', $transaction);

        /** @var EngagementLetter $letter */
        $letter = $transaction->engagementLetters()->where('version', $version)->firstOrFail();
        $name = str_replace('/', '-', $letter->el_number)."-v{$letter->version}.pdf";

        return Storage::disk('local')->response($letter->pdf_path, $name, ['Content-Type' => 'application/pdf'], 'inline');
    }
}
