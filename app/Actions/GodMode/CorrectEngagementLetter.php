<?php

namespace App\Actions\GodMode;

use App\Enums\FeeStartReference;
use App\Models\EngagementLetter;
use App\Models\FeeLine;
use App\Models\GodModeChange;
use App\Models\NumberSequence;
use App\Models\RetiredElNumber;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Letters\EngagementLetterRenderer;
use App\Support\FinancialYear;
use Carbon\CarbonImmutable;
use Closure;
use DOMDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Engagement letter corrections. Each one keeps every earlier version and adds a new one:
 *  - regenerate: re-render from the current data (same number and date)
 *  - rewrite: new wording for this deal only (the body is sanitised; the letter's styles are kept)
 *  - renumber: new EL number and/or date; the old number is retired and can never be issued again
 *  - replacePdf: a signed or corrected PDF uploaded in place of the generated one
 */
class CorrectEngagementLetter
{
    public function __construct(private readonly EngagementLetterRenderer $renderer) {}

    public function regenerate(Transaction $deal, string $reason, User $actor): EngagementLetter
    {
        return $this->newVersion($deal, $actor, 'letter-regenerate', $reason, function (Transaction $locked) {
            $html = $this->renderer->html($locked, (string) $locked->el_number, CarbonImmutable::parse($locked->el_date));

            return [$html, null, null];
        });
    }

    public function rewrite(Transaction $deal, string $bodyHtml, string $reason, User $actor): EngagementLetter
    {
        return $this->newVersion($deal, $actor, 'letter-wording', $reason, function (Transaction $locked, EngagementLetter $latest) use ($bodyHtml) {
            $body = trim($this->sanitizer()->sanitize($bodyHtml));
            if (strip_tags($body) === '') {
                throw ValidationException::withMessages(['body' => 'The letter can\'t be empty.']);
            }

            return [$this->replaceBody($this->documentOf($locked, $latest), $body), null, null];
        });
    }

    public function renumber(Transaction $deal, ?string $newNumber, CarbonImmutable $newDate, string $reason, User $actor): EngagementLetter
    {
        return $this->newVersion($deal, $actor, 'letter-number', $reason, function (Transaction $locked, EngagementLetter $latest) use ($newNumber, $newDate, $actor) {
            $this->guardDate($locked, $newDate);
            $oldNumber = (string) $locked->el_number;
            $oldDate = $locked->el_date;

            $number = $newNumber === null
                ? "BTL/{$locked->product->code}/EL/".FinancialYear::short($newDate).'/'.NumberSequence::next('el:'.FinancialYear::short($newDate))
                : $this->checkedNumber($locked, $newNumber, $newDate);

            if ($number === $oldNumber && $oldDate?->isSameDay($newDate)) {
                throw ValidationException::withMessages(['el_number' => 'Give a new number or a new date.']);
            }

            $locked->el_number = $number;
            $locked->el_date = Carbon::parse($newDate->toDateString());
            $locked->updated_by = $actor->id;
            $locked->save();

            // Keep any wording edits: swap the old number and date for the new ones in the latest letter.
            $html = str_replace(
                [$oldNumber, (string) $oldDate?->format('F j, Y')],
                [$number, $newDate->format('F j, Y')],
                $this->documentOf($locked, $latest),
            );

            return [$html, null, $number !== $oldNumber ? $oldNumber : null];
        });
    }

    public function replacePdf(Transaction $deal, UploadedFile $pdf, string $reason, User $actor): EngagementLetter
    {
        return $this->newVersion($deal, $actor, 'letter-pdf', $reason, function (Transaction $locked, EngagementLetter $latest) use ($pdf) {
            if (! str_starts_with((string) file_get_contents($pdf->getRealPath(), length: 5), '%PDF')) {
                throw ValidationException::withMessages(['pdf' => 'That file isn\'t a PDF.']);
            }

            // The stored HTML stays that of the previous version: it's the closest text record we have.
            return [$this->documentOf($locked, $latest), $pdf, null];
        });
    }

    /**
     * Locks the deal, runs $make to get the new letter's HTML (plus an uploaded PDF and a number to
     * retire, if any), stores the new version and logs the change. Any failure undoes all of it.
     *
     * @param  Closure(Transaction, EngagementLetter): array{0: string, 1: UploadedFile|null, 2: string|null}  $make
     */
    private function newVersion(Transaction $deal, User $actor, string $editor, string $reason, Closure $make): EngagementLetter
    {
        $stored = null;

        try {
            return DB::transaction(function () use ($deal, $actor, $editor, $reason, $make, &$stored) {
                /** @var Transaction $locked */
                $locked = Transaction::query()->whereKey($deal->id)->with(['product', 'feeLines'])->lockForUpdate()->firstOrFail();
                /** @var EngagementLetter|null $latest */
                $latest = $locked->engagementLetters()->first();
                if ($latest === null) {
                    throw ValidationException::withMessages(['letter' => 'This transaction has no engagement letter yet.']);
                }

                $before = ['version' => $latest->version, 'el_number' => $locked->el_number, 'el_date' => $locked->el_date?->toDateString()];

                [$html, $upload, $retired] = $make($locked, $latest);
                $version = $latest->version + 1;
                $path = "letters/{$locked->ulid}/v{$version}.pdf";
                Storage::disk('local')->put($path, $upload ? (string) file_get_contents($upload->getRealPath()) : $this->renderer->pdf($html));
                $stored = $path;

                $letter = $locked->engagementLetters()->create([
                    'version' => $version,
                    'el_number' => $locked->el_number,
                    'el_date' => $locked->el_date,
                    'body_html' => $html,
                    'pdf_path' => $path,
                    'reason' => mb_substr('God Mode: '.trim($reason), 0, 500),
                    'generated_by' => $actor->id,
                ]);

                $change = GodModeChange::query()->create([
                    'user_id' => $actor->id, 'editor' => $editor, 'subject_type' => Transaction::class, 'subject_id' => $locked->id,
                    'transaction_id' => $locked->id, 'company_id' => $locked->company_id, 'reason' => trim($reason),
                    'before' => $before,
                    'after' => ['version' => $version, 'el_number' => $locked->el_number, 'el_date' => $locked->el_date?->toDateString()],
                    'can_roll_back' => false,
                ]);

                if ($retired !== null) {
                    RetiredElNumber::query()->create(['el_number' => $retired, 'transaction_id' => $locked->id, 'god_mode_change_id' => $change->id]);
                }

                return $letter;
            });
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            }
            throw $e;
        }
    }

    /**
     * The latest version's HTML; a version imported from Stack has none, so the letter is rendered
     * from the current data with that version's number and date.
     */
    public function documentOf(Transaction $deal, EngagementLetter $latest): string
    {
        return $latest->body_html ?? $this->renderer->html($deal, $latest->el_number, CarbonImmutable::parse($latest->el_date));
    }

    private function guardDate(Transaction $deal, CarbonImmutable $date): void
    {
        if ($date->isAfter(today())) {
            throw ValidationException::withMessages(['el_date' => 'The EL date can\'t be in the future.']);
        }
        if ($deal->approved_at && $date->lt($deal->approved_at->copy()->startOfDay())) {
            throw ValidationException::withMessages(['el_date' => 'The EL date can\'t be before the approval date ('.$deal->approved_at->format('d M Y').').']);
        }
        $tied = $deal->feeLines->first(fn (FeeLine $f) => $f->start_reference === FeeStartReference::ElDate && ! $f->start_date->isSameDay($date));
        if ($tied) {
            throw ValidationException::withMessages(['el_date' => "The {$tied->kind->label()} runs from the EL date and starts on {$tied->start_date->format('d M Y')}. Correct the fee start date first (Fees), then change the EL date."]);
        }
    }

    /** A hand-entered number must follow the EL format for the date's financial year and never have been used. */
    private function checkedNumber(Transaction $deal, string $number, CarbonImmutable $date): string
    {
        $number = strtoupper(trim($number));
        $prefix = "BTL/{$deal->product->code}/EL/".FinancialYear::short($date).'/';

        if (! str_starts_with($number, $prefix) || ! ctype_digit(substr($number, strlen($prefix))) || (int) substr($number, strlen($prefix)) < 1) {
            throw ValidationException::withMessages(['el_number' => "The number must look like {$prefix}123 for an EL dated {$date->format('d M Y')}."]);
        }
        if ($number === $deal->el_number) {
            return $number;
        }
        if (Transaction::query()->withTrashed()->where('el_number', $number)->exists()
            || RetiredElNumber::query()->where('el_number', $number)->exists()) {
            throw ValidationException::withMessages(['el_number' => 'That EL number has already been used.']);
        }

        // Keep the sequence ahead of any number entered by hand, so it is never issued again.
        $n = (int) substr($number, strlen($prefix));
        $key = 'el:'.FinancialYear::short($date);
        NumberSequence::query()->firstOrCreate(['key' => $key], ['last_value' => 0]);
        NumberSequence::query()->where('key', $key)->where('last_value', '<', $n)->lockForUpdate()->update(['last_value' => $n]);

        return $number;
    }

    /** Puts a new body into the letter document, keeping its <head> (and so its styles). */
    private function replaceBody(string $document, string $body): string
    {
        $start = stripos($document, '<body');
        $open = $start === false ? false : strpos($document, '>', $start);
        $close = strripos($document, '</body>');
        if ($open === false || $close === false) {
            return $body;
        }

        return substr($document, 0, $open + 1)."\n".$body."\n".substr($document, $close);
    }

    /** What letter wording may contain: text structure and the template's classes; no scripts, links or inline styles. */
    private function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p', ['class'])->allowElement('div', ['class'])->allowElement('span', ['class'])
            ->allowElement('br')->allowElement('hr')
            ->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')->allowElement('u')
            ->allowElement('h1', ['class'])->allowElement('h2', ['class'])->allowElement('h3', ['class'])->allowElement('h4', ['class'])
            ->allowElement('ul', ['class'])->allowElement('ol', ['class'])->allowElement('li', ['class'])
            ->allowElement('table', ['class'])->allowElement('thead')->allowElement('tbody')->allowElement('tr', ['class'])
            ->allowElement('td', ['class', 'colspan', 'rowspan'])->allowElement('th', ['class', 'colspan', 'rowspan'])
            // Links and the font/span styling a browser editor adds are unwrapped: their text stays.
            ->blockElement('a')->blockElement('font')->blockElement('section')->blockElement('article')
            ->withMaxInputLength(500_000);

        return new HtmlSanitizer($config);
    }

    /** The letter body (inside <body>) of a stored document, for the wording editor. */
    public static function bodyOf(string $document): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$document);
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return $document;
        }

        return implode('', array_map(fn ($n) => (string) $dom->saveHTML($n), iterator_to_array($body->childNodes)));
    }
}
