import { DataTable } from '@/Components/data-table';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { DownloadIcon, SearchIcon } from 'lucide-react';
import { useMemo } from 'react';

const ALL = '__all__';
const dash = <span className="text-subtle-foreground">—</span>;

export default function IsinsIndex({ isins, filters: initialFilters, sort, exportUrl }) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });

    const columns = useMemo(
        () => [
            {
                id: 'isin',
                header: 'ISIN',
                meta: { sortKey: 'isin' },
                cell: ({ row }) => (
                    <Link
                        href={route('deals.show', {
                            transaction: row.original.deal_id,
                            tab: 'isin',
                        })}
                        className="group block min-w-0"
                    >
                        <div className="font-mono font-medium text-foreground group-hover:text-brand-text">
                            {row.original.isin}
                        </div>
                        <div className="max-w-72 truncate text-xs text-muted-foreground">
                            {row.original.series_name}
                        </div>
                    </Link>
                ),
            },
            {
                id: 'company',
                header: 'Company',
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <div className="truncate">{row.original.company}</div>
                        <div className="truncate font-mono text-xs text-muted-foreground">
                            {row.original.el_number}
                        </div>
                    </div>
                ),
            },
            {
                id: 'coupon',
                header: 'Coupon',
                cell: ({ row }) => row.original.coupon ?? dash,
            },
            {
                id: 'maturity_date',
                header: 'Maturity',
                meta: { sortKey: 'maturity_date' },
                cell: ({ row }) =>
                    row.original.maturity_date ? (
                        <span className="text-muted-foreground">
                            {formatDate(row.original.maturity_date)}
                        </span>
                    ) : (
                        dash
                    ),
            },
            {
                id: 'next_due_on',
                header: 'Next due',
                meta: { sortKey: 'next_due_on' },
                cell: ({ row }) => (
                    <div className="flex flex-col items-start gap-0.5">
                        {row.original.next_due_on ? formatDate(row.original.next_due_on) : dash}
                        {row.original.overdue > 0 && (
                            <Badge variant="danger">{row.original.overdue} overdue</Badge>
                        )}
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout title="ISINs" breadcrumbs={[{ title: 'ISINs' }]}>
            <PageHeader
                title="ISINs"
                description="Every ISIN across deals, with the next interest or principal payment due."
            />
            <DataTable
                columns={columns}
                paginator={isins}
                sort={sort}
                query={query}
                emptyTitle="No ISINs found"
                toolbar={
                    <div className="flex w-full flex-wrap gap-2">
                        <div className="relative w-full sm:w-80">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filters.search}
                                onChange={(e) => setFilter('search', e.target.value)}
                                placeholder="Search ISIN, series, company or EL number"
                                className="pl-8"
                                aria-label="Search ISINs"
                            />
                        </div>
                        <Select
                            value={filters.due || ALL}
                            onValueChange={(v) => setFilter('due', v === ALL ? '' : v)}
                        >
                            <SelectTrigger className="w-48" aria-label="Payments">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All ISINs</SelectItem>
                                <SelectItem value="next30">Due in the next 30 days</SelectItem>
                                <SelectItem value="overdue">With overdue payments</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="outline" asChild className="sm:ml-auto">
                            <a href={exportUrl}>
                                <DownloadIcon /> Excel
                            </a>
                        </Button>
                    </div>
                }
            />
        </AppLayout>
    );
}
