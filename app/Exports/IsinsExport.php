<?php

namespace App\Exports;

use App\Models\DealIsin;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * One row per ISIN, with what's due next: the ISIN MIS (same search and filter as the screen).
 *
 * @implements WithMapping<DealIsin>
 */
class IsinsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<DealIsin>  $query
     */
    public function __construct(private readonly Builder $query) {}

    /**
     * @return Builder<DealIsin>
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
            'ISIN', 'Series', 'Company', 'EL number', 'Listing', 'Exchange', 'Allotment date', 'Maturity date',
            'Coupon type', 'Coupon rate (%)', 'Interest', 'Principal', 'Day count', 'Next due', 'Overdue payments',
        ];
    }

    /**
     * @param  DealIsin  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return [
            $row->isin,
            $row->series_name,
            $row->transaction->company->name,
            $row->transaction->el_number,
            $row->listing?->label(),
            $row->exchange,
            $row->allotment_date?->format('Y-m-d'),
            $row->maturity_date?->format('Y-m-d'),
            $row->coupon_type?->label(),
            $row->coupon_rate !== null ? (float) $row->coupon_rate : null,
            $row->interest_frequency?->label(),
            $row->principal_frequency?->label(),
            $row->day_count?->label(),
            $row->getAttribute('next_due_on'),
            (int) $row->getAttribute('overdue_count'),
        ];
    }
}
