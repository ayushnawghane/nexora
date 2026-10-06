<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * One row per invoice, with its GST split and what's outstanding (same tab and search as the hub).
 *
 * @implements WithMapping<Invoice>
 */
class InvoicesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Invoice>  $query
     */
    public function __construct(private readonly Builder $query) {}

    /**
     * @return Builder<Invoice>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Number', 'Kind', 'Status', 'Date', 'Company', 'EL number', 'Billed to', 'GSTIN', 'Period from', 'Period to',
            'Taxable', 'Not taxable', 'CGST', 'SGST', 'IGST', 'Total', 'Outstanding', 'IRN', 'Cancelled on', 'Cancel reason',
        ];
    }

    /**
     * @param  Invoice  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->number ?? 'Draft',
            $row->kind->label(),
            $row->status->label(),
            $row->invoice_date?->format('Y-m-d'),
            $row->transaction->company->name,
            $row->transaction->el_number,
            $row->billed_name,
            $row->billed_gstin,
            $row->period_from?->format('Y-m-d'),
            $row->period_to?->format('Y-m-d'),
            (float) $row->taxable_amount,
            (float) $row->non_taxable_amount,
            (float) $row->cgst,
            (float) $row->sgst,
            (float) $row->igst,
            (float) $row->total,
            $row->isCollectable() ? (float) $row->balance_due : null,
            $row->irn,
            $row->cancelled_at?->format('Y-m-d'),
            $row->cancel_reason,
        ];
    }
}
