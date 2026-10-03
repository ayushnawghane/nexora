<?php

namespace App\Services\Letters;

use App\Enums\AddressType;
use App\Enums\FeeAmountType;
use App\Enums\FeeStartReference;
use App\Enums\Recipient;
use App\Models\CompanyAddress;
use App\Models\FeeLine;
use App\Models\Transaction;
use App\Models\TransactionContact;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * Turns a transaction into its engagement letter: HTML from the DT template (resources/views/letters),
 * then a PDF. The HTML is stored with each version, so a letter can be re-rendered exactly as issued.
 */
class EngagementLetterRenderer
{
    public function html(Transaction $transaction, string $elNumber, CarbonInterface $elDate): string
    {
        $transaction->loadMissing([
            'company.addresses.state', 'issueDetail', 'instruments', 'feeLines',
            'contacts.companyContact', 'signatory.designation', 'relationshipManager',
        ]);

        $company = $transaction->company;
        $registered = $company->addresses->first(fn (CompanyAddress $a) => $a->is_active && $a->type === AddressType::Registered);
        $issue = $transaction->issueDetail;
        $to = $transaction->contacts->filter(fn (TransactionContact $c) => $c->recipient === Recipient::To);

        return view('letters.dt-engagement', [
            'elNumber' => $elNumber,
            'elDate' => $elDate->format('F j, Y'),
            'dealCode' => $transaction->deal_code,
            'company' => $company,
            'registeredAddress' => $registered ? $this->address($registered) : null,
            'attention' => $to->map(fn (TransactionContact $c) => trim("{$c->companyContact->salutation} {$c->companyContact->name}")
                .($c->companyContact->designation ? ", {$c->companyContact->designation}" : ''))->values(),
            'instruments' => $transaction->instruments->map(fn ($i) => strtoupper($i->instrument->value))->implode(' / ') ?: 'debentures',
            'issueSize' => Money::format($issue?->total_issue_size, 'INR '),
            'issueSizeWords' => $issue ? Money::inWords($issue->total_issue_size) : null,
            'greenShoe' => $issue && BigDecimal::of($issue->green_shoe_size)->isPositive() ? Money::format($issue->green_shoe_size, 'INR ') : null,
            'baseIssue' => Money::format($issue?->base_issue_size, 'INR '),
            'listing' => $issue?->listing->label(),
            'issueType' => $issue?->issue_type->label(),
            'secured' => $issue?->is_secured ? 'Secured' : 'Unsecured',
            'tenure' => $issue ? trim($issue->tenure_months.' months'.($issue->tenure_days ? " {$issue->tenure_days} days" : '')) : null,
            'fees' => $transaction->feeLines->sortBy(fn (FeeLine $f) => $f->kind->value)->map(fn (FeeLine $f) => [
                'label' => $f->kind->label(),
                'amount' => Money::format($f->annual_amount, 'INR ').($f->kind->value === 'service' ? ' p.a.' : ''),
                'words' => Money::inWords($f->annual_amount),
                'basis' => $f->amount_type === FeeAmountType::Percent
                    ? rtrim(rtrim((string) $f->percent, '0'), '.').'% '.strtolower($f->basis->label())
                    : null,
                'frequency' => $f->frequency->label(),
                'from' => $f->start_reference === FeeStartReference::CustomDate
                    ? $f->start_date->format('F j, Y')
                    : $f->start_reference->label(),
                'timing' => $f->timing->label(),
                'escalation' => $f->escalation_value
                    ? ($f->escalation_type->value === 'percent'
                        ? "{$f->escalation_value}% every {$f->escalation_every_years} year(s)"
                        : Money::format($f->escalation_value, 'INR ')." every {$f->escalation_every_years} year(s)")
                    : null,
            ])->values(),
            'signatory' => $transaction->signatory,
            'relationshipManager' => $transaction->relationshipManager,
        ])->render();
    }

    /** PDF bytes for the given letter HTML. */
    public function pdf(string $html): string
    {
        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    private function address(CompanyAddress $address): string
    {
        return collect([$address->line1, $address->line2, $address->city, $address->state->name.' '.$address->pincode])
            ->filter()->implode(', ');
    }
}
