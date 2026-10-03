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
import { Link } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { useMemo } from 'react';

const ALL = '__all__';

const muted = <span className="text-subtle-foreground">—</span>;

export default function CompaniesIndex({
    companies,
    filters: initialFilters,
    sort,
    entityTypes,
    can,
}) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: 'Company',
                meta: { sortKey: 'name' },
                cell: ({ row }) => (
                    <Link
                        href={route('companies.show', row.original.id)}
                        className="group block min-w-0"
                    >
                        <div className="truncate font-medium text-foreground group-hover:text-brand-text">
                            {row.original.name}
                        </div>
                        {row.original.formerly_known_as && (
                            <div className="truncate text-xs text-muted-foreground">
                                Formerly {row.original.formerly_known_as}
                            </div>
                        )}
                    </Link>
                ),
            },
            {
                id: 'cin',
                header: 'CIN / LLPIN',
                meta: { sortKey: 'cin' },
                cell: ({ row }) =>
                    row.original.cin ? (
                        <span className="font-mono text-xs">{row.original.cin}</span>
                    ) : (
                        muted
                    ),
            },
            {
                id: 'pan',
                header: 'PAN',
                meta: { sortKey: 'pan' },
                cell: ({ row }) =>
                    row.original.pan ? (
                        <span className="font-mono text-xs">{row.original.pan}</span>
                    ) : (
                        muted
                    ),
            },
            {
                id: 'gstins_count',
                header: 'GSTINs',
                meta: { align: 'right' },
                cell: ({ row }) => (
                    <span className="tabular-nums">{row.original.gstins_count}</span>
                ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <div className="flex flex-wrap gap-1">
                        {row.original.is_active ? (
                            <Badge variant="success">Active</Badge>
                        ) : (
                            <Badge variant="danger">Inactive</Badge>
                        )}
                        {row.original.is_listed && <Badge variant="brand">Listed</Badge>}
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout title="Companies" breadcrumbs={[{ title: 'Companies' }]}>
            <PageHeader
                title="Companies"
                description="Clients and counterparties, with their GSTINs, addresses and contacts."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={route('companies.create')}>
                                <PlusIcon /> New company
                            </Link>
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                paginator={companies}
                sort={sort}
                query={query}
                emptyTitle="No companies match these filters"
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-80">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filters.search}
                                onChange={(e) => setFilter('search', e.target.value)}
                                placeholder="Search name, CIN, PAN or GSTIN"
                                className="pl-8"
                                aria-label="Search companies"
                            />
                        </div>
                        <Select
                            value={filters.entity_type || ALL}
                            onValueChange={(v) => setFilter('entity_type', v === ALL ? '' : v)}
                        >
                            <SelectTrigger className="w-44" aria-label="Entity type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All entity types</SelectItem>
                                {entityTypes.map((type) => (
                                    <SelectItem key={type.value} value={type.value}>
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.is_active === '' ? ALL : String(filters.is_active)}
                            onValueChange={(v) => setFilter('is_active', v === ALL ? '' : v)}
                        >
                            <SelectTrigger className="w-36" aria-label="Status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All statuses</SelectItem>
                                <SelectItem value="1">Active</SelectItem>
                                <SelectItem value="0">Inactive</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                }
            />
        </AppLayout>
    );
}
