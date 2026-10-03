import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { LockIcon, PlusIcon } from 'lucide-react';

export default function RolesIndex({ roles, can }) {
    return (
        <AppLayout
            title="Roles & permissions"
            breadcrumbs={[{ title: 'Administration' }, { title: 'Roles & permissions' }]}
        >
            <PageHeader
                title="Roles & permissions"
                description="Each role is a set of permissions. Users can hold more than one role."
                actions={
                    can.manage && (
                        <Button asChild>
                            <Link href={route('roles.create')}>
                                <PlusIcon /> New role
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="overflow-hidden rounded-lg border bg-card shadow-card">
                <Table>
                    <TableHeader className="bg-surface-3">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="text-xs">Role</TableHead>
                            <TableHead className="text-right text-xs">Permissions</TableHead>
                            <TableHead className="text-right text-xs">Users</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {roles.map((role) => (
                            <TableRow key={role.id} className="h-11">
                                <TableCell>
                                    {role.locked || !can.manage ? (
                                        <span className="inline-flex items-center gap-2 font-medium">
                                            {role.name}
                                            {role.locked && (
                                                <Badge variant="brand">
                                                    <LockIcon /> All access
                                                </Badge>
                                            )}
                                        </span>
                                    ) : (
                                        <Link
                                            href={route('roles.edit', role.id)}
                                            className="font-medium hover:text-brand-text"
                                        >
                                            {role.name}
                                        </Link>
                                    )}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {role.permissions_count}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {role.users_count}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AppLayout>
    );
}
