import { VoteButtons } from '@/Components/transactions/approval-panel';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Toaster } from '@/Components/ui/sonner';
import AuthLayout from '@/Layouts/AuthLayout';
import { formatDateTime } from '@/lib/format';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

function Row({ label, children }) {
    return (
        <div className="flex justify-between gap-4 text-[13px]">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{children}</dd>
        </div>
    );
}

/** Opened from the approval email (signed link, no sign-in). */
export default function EmailVote({ approver, request, transaction, myVote, canVote, voteUrl }) {
    const { flash, errors } = usePage().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash]);

    return (
        <AuthLayout
            title="Approval request"
            description={`For ${approver}`}
            footer="This page was opened from a personal link. Don't forward the email."
        >
            <div className="flex flex-col gap-5">
                <dl className="flex flex-col gap-2">
                    <Row label="Company">{transaction.company}</Row>
                    {transaction.cin && <Row label="CIN">{transaction.cin}</Row>}
                    <Row label="Issue size">{transaction.issue_size}</Row>
                    {transaction.tenure_months && (
                        <Row label="Tenure">{transaction.tenure_months} months</Row>
                    )}
                    {transaction.fees.map((f) => (
                        <Row key={f.label} label={f.label}>
                            {f.amount} · {f.frequency}
                        </Row>
                    ))}
                    <Row label="Sent by">
                        {request.requested_by}, {formatDateTime(request.requested_at)}
                    </Row>
                    <Row label="Status">
                        <Badge
                            variant={
                                request.status === 'open'
                                    ? 'warning'
                                    : request.status === 'approved'
                                      ? 'success'
                                      : 'danger'
                            }
                        >
                            {request.status_label}
                        </Badge>
                    </Row>
                </dl>
                {transaction.brief && (
                    <p className="rounded-md bg-surface-3 p-3 text-[13px]">{transaction.brief}</p>
                )}

                {errors?.vote && (
                    <Alert variant="destructive">
                        <AlertTitle>Your vote wasn&apos;t recorded</AlertTitle>
                        <AlertDescription>{errors.vote}</AlertDescription>
                    </Alert>
                )}

                {myVote ? (
                    <Alert>
                        <AlertTitle>You voted: {myVote.decision}</AlertTitle>
                        {myVote.comment && <AlertDescription>“{myVote.comment}”</AlertDescription>}
                    </Alert>
                ) : canVote ? (
                    <VoteButtons action={voteUrl} />
                ) : (
                    <p className="text-[13px] text-muted-foreground">
                        Voting on this request has closed, or you can&apos;t vote on it.
                    </p>
                )}
            </div>
            <Toaster position="top-center" />
        </AuthLayout>
    );
}
