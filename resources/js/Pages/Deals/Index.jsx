import { DataTable } from '@/Components/data-table';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
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
import { formatDate, formatMoney } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { SearchIcon } from 'lucide-react';
import { useMemo } from 'react';

const ALL = '__all__';

const dash = <span className="text-subtle-foreground">—</span>;

export default function DealsIndex({ deals, statuses, filters: initialFilters, sort }) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });

    const columns = useMemo(
        () => [
            {
                id: 'company',
                header: 'Company',
                cell: ({ row }) => (
                    <Link
                        href={route('deals.show', row.original.id)}
                        className="group block min-w-0"
                    >
                        <div className="truncate font-medium text-foreground group-hover:text-brand-text">
                            {row.original.company}
                        </div>
                        <div className="truncate font-mono text-xs text-muted-foreground">
                            {row.original.el_number}
                        </div>
                    </Link>
                ),
            },
            {
                id: 'deal_code',
                header: 'Deal code',
                cell: ({ row }) => (
                    <span className="font-mono text-[13px]">{row.original.deal_code ?? dash}</span>
                ),
            },
            {
                id: 'issue_size',
                header: 'Issue size',
                meta: { align: 'right' },
                cell: ({ row }) =>
                    row.original.issue_size ? formatMoney(row.original.issue_size) : dash,
            },
            {
                id: 'relationship_manager',
                header: 'Relationship manager',
                cell: ({ row }) => row.original.relationship_manager ?? dash,
            },
            {
                id: 'el_date',
                header: 'EL date',
                meta: { sortKey: 'el_date' },
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {formatDate(row.original.el_date)}
                    </span>
                ),
            },
            {
                id: 'deal_status',
                header: 'Status',
                meta: { sortKey: 'deal_status_since' },
                cell: ({ row }) => (
                    <div className="flex flex-col items-start gap-0.5">
                        <Badge variant={row.original.deal_status_tone}>
                            {row.original.deal_status_label}
                        </Badge>
                        <span className="text-xs text-muted-foreground">
                            since {formatDate(row.original.deal_status_since)}
                        </span>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout title="Deals" breadcrumbs={[{ title: 'Deals' }]}>
            <PageHeader
                title="Deals"
                description="Transactions whose engagement letter has been issued."
            />
            <DataTable
                columns={columns}
                paginator={deals}
                sort={sort}
                query={query}
                emptyTitle="No deals found"
                toolbar={
                    <div className="flex w-full flex-wrap gap-2">
                        <div className="relative w-full sm:w-80">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filters.search}
                                onChange={(e) => setFilter('search', e.target.value)}
                                placeholder="Search company, CIN, EL number or deal code"
                                className="pl-8"
                                aria-label="Search deals"
                            />
                        </div>
                        <Select
                            value={filters.status || ALL}
                            onValueChange={(v) => setFilter('status', v === ALL ? '' : v)}
                        >
                            <SelectTrigger className="w-44" aria-label="Status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All statuses</SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                }
            />
        </AppLayout>
    );
}
