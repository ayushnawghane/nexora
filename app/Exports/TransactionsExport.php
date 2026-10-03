<?php

namespace App\Exports;

use App\Enums\TransactionStatus;
use App\Models\FeeLine;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * One row per transaction in a list (same statuses and search as the screen).
 *
 * @implements WithMapping<Transaction>
 */
class TransactionsExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping
{
    /**
     * @param  list<TransactionStatus>  $statuses
     */
    public function __construct(private readonly array $statuses, private readonly ?string $search = null) {}

    /**
     * @return Builder<Transaction>
     */
    public function query(): Builder
    {
        return Transaction::query()
            ->inStatus(...$this->statuses)
            ->when($this->search, fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w
                ->where('el_number', 'like', "%{$term}%")
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")->orWhere('cin', 'like', "%{$term}%"))))
            ->with(['company:id,name,cin', 'issueDetail', 'feeLines', 'relationshipManager:id,name', 'verticalTeam:id,name'])
            ->orderByDesc('updated_at')
            ->orderBy('id'); // stable order for chunked export
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'EL number', 'Deal code', 'Status', 'Company', 'CIN', 'Total issue size (INR)', 'Tenure (months)',
            'Acceptance fee (INR)', 'Service fee p.a. (INR)', 'Relationship manager', 'Vertical team',
            'Submitted', 'Approved', 'EL date', 'Last updated',
        ];
    }

    /**
     * @param  Transaction  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        $fee = fn (string $kind) => ($line = $row->feeLines->first(fn (FeeLine $f) => $f->kind->value === $kind)) ? (float) $line->annual_amount : null;

        return [
            $row->el_number,
            $row->deal_code,
            $row->status->label(),
            $row->company->name,
            $row->company->cin,
            $row->issueDetail ? (float) $row->issueDetail->total_issue_size : null,
            $row->issueDetail?->tenure_months,
            $fee('acceptance'),
            $fee('service'),
            $row->relationshipManager?->name,
            $row->verticalTeam?->name,
            $row->submitted_at?->format('Y-m-d'),
            $row->approved_at?->format('Y-m-d'),
            $row->el_date?->format('Y-m-d'),
            $row->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return ['F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, 'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, 'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }
}
