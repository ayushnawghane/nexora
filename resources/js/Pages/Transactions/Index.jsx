import { DataTable } from '@/Components/data-table';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { DownloadIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { useMemo } from 'react';

const dash = <span className="text-subtle-foreground">—</span>;

export default function TransactionsIndex({
    title,
    description,
    transactions,
    filters: initialFilters,
    sort,
    exportUrl,
    can,
}) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });

    const columns = useMemo(
        () => [
            {
                id: 'company',
                header: 'Company',
                cell: ({ row }) => (
                    <Link
                        href={route(
                            row.original.editable ? 'transactions.edit' : 'transactions.show',
                            row.original.id,
                        )}
                        className="group block min-w-0"
                    >
                        <div className="truncate font-medium text-foreground group-hover:text-brand-text">
                            {row.original.company}
                        </div>
                        <div className="truncate font-mono text-xs text-muted-foreground">
                            {row.original.el_number ?? 'No EL number yet'}
                        </div>
                    </Link>
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
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge variant={row.original.status_tone}>{row.original.status_label}</Badge>
                ),
            },
            {
                id: 'updated_at',
                header: 'Last updated',
                meta: { sortKey: 'updated_at' },
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {formatDateTime(row.original.updated_at)}
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout title={title} breadcrumbs={[{ title: 'Transactions' }, { title }]}>
            <PageHeader
                title={title}
                description={description}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <DownloadIcon /> Export
                            </a>
                        </Button>
                        {can.create && (
                            <Button asChild>
                                <Link href={route('transactions.create')}>
                                    <PlusIcon /> New transaction
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />
            <DataTable
                columns={columns}
                paginator={transactions}
                sort={sort}
                query={query}
                emptyTitle="Nothing here yet"
                toolbar={
                    <div className="relative w-full sm:w-80">
                        <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={filters.search}
                            onChange={(e) => setFilter('search', e.target.value)}
                            placeholder="Search company, CIN or EL number"
                            className="pl-8"
                            aria-label="Search transactions"
                        />
                    </div>
                }
            />
        </AppLayout>
    );
}
