<?php

namespace App\Http\Controllers\GodMode;

use App\Actions\GodMode\CorrectEngagementLetter;
use App\Http\Controllers\Controller;
use App\Http\Requests\GodMode\GodModeRequest;
use App\Http\Requests\GodMode\LetterNumberRequest;
use App\Http\Requests\GodMode\LetterPdfRequest;
use App\Http\Requests\GodMode\LetterWordingRequest;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

/** Engagement letter corrections. Each one adds a new version; earlier versions are kept. */
class GodModeLetterController extends Controller
{
    public function regenerate(GodModeRequest $request, Transaction $transaction, CorrectEngagementLetter $letters): RedirectResponse
    {
        $letter = $letters->regenerate($transaction, $request->validated('reason'), $request->user());

        return back()->with('success', "Letter regenerated as version {$letter->version}.");
    }

    public function wording(LetterWordingRequest $request, Transaction $transaction, CorrectEngagementLetter $letters): RedirectResponse
    {
        $letter = $letters->rewrite($transaction, $request->validated('body'), $request->validated('reason'), $request->user());

        return back()->with('success', "New wording saved as version {$letter->version}.");
    }

    public function number(LetterNumberRequest $request, Transaction $transaction, CorrectEngagementLetter $letters): RedirectResponse
    {
        $mode = $request->validated('number_mode');
        $letter = $letters->renumber(
            $transaction,
            match ($mode) {
                'keep' => (string) $transaction->el_number,
                'manual' => $request->validated('el_number'),
                default => null,
            },
            CarbonImmutable::parse($request->validated('el_date')),
            $request->validated('reason'),
            $request->user(),
        );

        return back()->with('success', "Letter is now {$letter->el_number}, version {$letter->version}.");
    }

    public function pdf(LetterPdfRequest $request, Transaction $transaction, CorrectEngagementLetter $letters): RedirectResponse
    {
        $letter = $letters->replacePdf($transaction, $request->file('pdf'), $request->validated('reason'), $request->user());

        return back()->with('success', "PDF replaced as version {$letter->version}.");
    }
}
