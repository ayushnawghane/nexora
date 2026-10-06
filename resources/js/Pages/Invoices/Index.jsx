import { DataTable } from '@/Components/data-table';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { DownloadIcon, SearchIcon } from 'lucide-react';
import { useMemo } from 'react';

const dash = <span className="text-subtle-foreground">—</span>;

const TABS = [
    ['due', 'Due'],
    ['drafts', 'Drafts'],
    ['proforma', 'Proforma'],
    ['tax', 'Tax invoices'],
    ['credit_note', 'Credit notes'],
    ['reimbursement', 'Reimbursement'],
    ['cancelled', 'Cancelled'],
];

export default function InvoicesIndex({
    invoices,
    counts,
    filters: initialFilters,
    sort,
    exportUrl,
}) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });
    const tab = filters.tab;

    const columns = useMemo(
        () => [
            {
                id: 'number',
                header: 'Invoice',
                cell: ({ row }) => (
                    <Link
                        href={route('invoices.show', row.original.id)}
                        className="group block min-w-0"
                    >
                        <div className="font-mono font-medium text-foreground group-hover:text-brand-text">
                            {row.original.number ?? 'Draft'}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.kind_label}
                            {row.original.number === null && row.original.maker
                                ? ` · by ${row.original.maker}`
                                : ''}
                        </div>
                    </Link>
                ),
            },
            {
                id: 'company',
                header: 'Deal',
                cell: ({ row }) => (
                    <div className="min-w-0">
                        <div className="max-w-64 truncate">{row.original.deal.company}</div>
                        <div className="truncate font-mono text-xs text-muted-foreground">
                            {row.original.deal.el_number}
                        </div>
                    </div>
                ),
            },
            {
                id: 'invoice_date',
                header: 'Date',
                meta: { sortKey: 'invoice_date' },
                cell: ({ row }) =>
                    row.original.invoice_date ? (
                        <span className="text-muted-foreground">
                            {formatDate(row.original.invoice_date)}
                        </span>
                    ) : (
                        dash
                    ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <span className="flex flex-wrap gap-1">
                        <Badge variant={row.original.status_tone}>
                            {row.original.status_label}
                        </Badge>
                        {row.original.returned && <Badge variant="warning">Sent back</Badge>}
                        {row.original.overdue && <Badge variant="danger">Overdue</Badge>}
                    </span>
                ),
            },
            {
                id: 'total',
                header: 'Total',
                meta: { sortKey: 'total', align: 'right' },
                cell: ({ row }) => (
                    <span className="tabular-nums">{formatMoney(row.original.total)}</span>
                ),
            },
            {
                id: 'balance_due',
                header: 'Outstanding',
                meta: { sortKey: 'balance_due', align: 'right' },
                cell: ({ row }) =>
                    row.original.balance_due === null ? (
                        dash
                    ) : (
                        <span className="tabular-nums">
                            {formatMoney(row.original.balance_due)}
                        </span>
                    ),
            },
        ],
        [],
    );

    return (
        <AppLayout title="Invoices" breadcrumbs={[{ title: 'Invoices' }]}>
            <PageHeader
                title="Invoices"
                description={`${counts.due} with money due${counts.overdue ? `, ${counts.overdue} overdue` : ''}. ${counts.drafts} draft${counts.drafts === 1 ? '' : 's'} waiting to be issued.`}
                actions={
                    <Button variant="outline" asChild>
                        <Link href={route('billing.queue')}>Billing queue</Link>
                    </Button>
                }
            />
            <div className="-mx-1 max-w-full overflow-x-auto px-1">
                <Tabs value={tab} onValueChange={(v) => setFilter('tab', v)}>
                    <TabsList>
                        {TABS.map(([value, label]) => (
                            <TabsTrigger key={value} value={value}>
                                {label}
                                {value === 'drafts' && counts.drafts > 0 && (
                                    <Badge variant="warning" className="ml-1">
                                        {counts.drafts}
                                    </Badge>
                                )}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>
            </div>
            <DataTable
                columns={columns}
                paginator={invoices}
                sort={sort}
                query={query}
                emptyTitle="No invoices here"
                toolbar={
                    <div className="flex w-full flex-wrap gap-2">
                        <div className="relative w-full sm:w-80">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filters.search}
                                onChange={(e) => setFilter('search', e.target.value)}
                                placeholder="Search number, client, GSTIN or EL number"
                                className="pl-8"
                                aria-label="Search invoices"
                            />
                        </div>
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
