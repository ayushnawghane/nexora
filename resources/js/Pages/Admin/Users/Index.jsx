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
import { formatDateTime } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { PlusIcon, SearchIcon, ShieldCheckIcon, ShieldOffIcon } from 'lucide-react';
import { useMemo } from 'react';

const ALL = '__all__';

export default function UsersIndex({ users, filters: initialFilters, sort, roles, can }) {
    const { filters, setFilter, query } = useTableFilters(initialFilters, { sort });

    const columns = useMemo(
        () => [
            {
                id: 'name',
                header: 'User',
                meta: { sortKey: 'name' },
                cell: ({ row }) => (
                    <Link
                        href={route('users.edit', row.original.id)}
                        className="group block min-w-0"
                    >
                        <div className="truncate font-medium text-foreground group-hover:text-primary">
                            {row.original.name}
                        </div>
                        <div className="truncate text-xs text-muted-foreground">
                            {row.original.email}
                        </div>
                    </Link>
                ),
            },
            {
                id: 'emp_code',
                header: 'Emp. code',
                meta: { sortKey: 'emp_code' },
                cell: ({ row }) => (
                    <span className="font-mono text-xs">{row.original.emp_code}</span>
                ),
            },
            {
                id: 'department',
                header: 'Department',
                cell: ({ row }) =>
                    row.original.department ?? <span className="text-subtle-foreground">—</span>,
            },
            {
                id: 'roles',
                header: 'Roles',
                cell: ({ row }) => (
                    <div className="flex flex-wrap gap-1">
                        {row.original.roles.map((role) => (
                            <Badge key={role} variant="neutral">
                                {role}
                            </Badge>
                        ))}
                    </div>
                ),
            },
            {
                id: 'two_factor',
                header: '2FA',
                cell: ({ row }) =>
                    row.original.two_factor ? (
                        <ShieldCheckIcon className="size-4 text-success" aria-label="2FA on" />
                    ) : (
                        <ShieldOffIcon
                            className="size-4 text-warning"
                            aria-label="2FA not set up"
                        />
                    ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) =>
                    row.original.is_active ? (
                        <Badge variant="success">Active</Badge>
                    ) : (
                        <Badge variant="danger">Inactive</Badge>
                    ),
            },
            {
                id: 'last_login_at',
                header: 'Last sign-in',
                meta: { sortKey: 'last_login_at' },
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.last_login_at
                            ? formatDateTime(row.original.last_login_at)
                            : 'Never'}
                    </span>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout title="Users" breadcrumbs={[{ title: 'Administration' }, { title: 'Users' }]}>
            <PageHeader
                title="Users"
                description="Staff accounts, their roles and access."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={route('users.create')}>
                                <PlusIcon /> New user
                            </Link>
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                paginator={users}
                sort={sort}
                query={query}
                emptyTitle="No users match these filters"
                toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full sm:w-72">
                            <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={filters.search}
                                onChange={(e) => setFilter('search', e.target.value)}
                                placeholder="Search name, code or email"
                                className="pl-8"
                                aria-label="Search users"
                            />
                        </div>
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
                        <Select
                            value={filters.role || ALL}
                            onValueChange={(v) => setFilter('role', v === ALL ? '' : v)}
                        >
                            <SelectTrigger className="w-44" aria-label="Role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>All roles</SelectItem>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {role}
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
