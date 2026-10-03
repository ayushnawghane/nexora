import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Card } from '@/Components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { Link } from '@inertiajs/react';

export default function ApprovalsIndex({ requests }) {
    return (
        <AppLayout title="Approvals" breadcrumbs={[{ title: 'Approvals' }]}>
            <PageHeader
                title="Approvals"
                description="Transactions waiting for your vote, oldest first."
            />
            {requests.length === 0 ? (
                <Empty className="border border-dashed">
                    <EmptyHeader>
                        <EmptyTitle>Nothing waiting for you</EmptyTitle>
                        <EmptyDescription>New requests also arrive by email.</EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Company</TableHead>
                                <TableHead className="text-right">Issue size</TableHead>
                                <TableHead>Sent by</TableHead>
                                <TableHead>Approvals so far</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {requests.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell>
                                        <Link
                                            href={route('transactions.show', r.transaction_id)}
                                            className="font-medium hover:text-brand-text"
                                        >
                                            {r.company}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(r.issue_size)}
                                    </TableCell>
                                    <TableCell>
                                        <div>{r.requested_by}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDateTime(r.requested_at)}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="neutral">{r.approvals_count} of 2+</Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            )}
        </AppLayout>
    );
}
