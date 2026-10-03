import { PageHeader } from '@/Components/page-header';
import { ApprovalPanel } from '@/Components/transactions/approval-panel';
import { LetterPanel } from '@/Components/transactions/letter-panel';
import { ScheduleTable } from '@/Components/transactions/schedule-table';
import { TransactionSummary } from '@/Components/transactions/transaction-summary';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { BriefcaseIcon, PencilIcon } from 'lucide-react';

const TONE = {
    draft: 'neutral',
    pending_approval: 'warning',
    rejected: 'danger',
    approved: 'success',
    active: 'success',
    closed: 'neutral',
    cancelled: 'danger',
};

export default function TransactionShow({
    transaction,
    options,
    approvals,
    openRequestId,
    letters,
    letterIssue,
    can,
}) {
    const title = transaction.company.name;

    return (
        <AppLayout title={title} breadcrumbs={[{ title: 'Transactions' }, { title }]}>
            <PageHeader
                title={title}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        {transaction.el_number ?? 'Debenture trustee'}
                        <Badge variant={TONE[transaction.status] ?? 'neutral'}>
                            {transaction.status_label}
                        </Badge>
                    </span>
                }
                actions={
                    <>
                        {can.update && (
                            <Button asChild>
                                <Link href={route('transactions.edit', transaction.id)}>
                                    <PencilIcon /> Continue editing
                                </Link>
                            </Button>
                        )}
                        {can.viewDeal && (
                            <Button asChild>
                                <Link href={route('deals.show', transaction.id)}>
                                    <BriefcaseIcon /> Open deal
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />
            <LetterPanel transactionId={transaction.id} letters={letters} issue={letterIssue} />
            <ApprovalPanel
                transactionId={transaction.id}
                approvals={approvals}
                openRequestId={openRequestId}
                can={can}
            />
            <TransactionSummary transaction={transaction} options={options} />
            {transaction.schedule.lines.map((line) => (
                <ScheduleTable key={line.kind} line={line} />
            ))}
        </AppLayout>
    );
}
