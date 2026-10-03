import { ConfirmAction } from '@/Components/confirm-action';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Field, FieldError, FieldLabel } from '@/Components/ui/field';
import { Textarea } from '@/Components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { CheckIcon, MailIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

const REQUEST_TONE = { open: 'warning', approved: 'success', rejected: 'danger' };

/**
 * Approve / reject controls. Reject always asks for a reason. Used in the app and on the
 * page an emailed link opens (`action` is then the signed URL).
 */
export function VoteButtons({ action }) {
    const [rejecting, setRejecting] = useState(false);
    const reject = useForm({ decision: 'reject', comment: '' });

    return (
        <>
            <div className="flex flex-wrap gap-2">
                <ConfirmAction
                    href={action}
                    data={{ decision: 'approve' }}
                    preserveState
                    title="Approve this transaction?"
                    description="Your approval is recorded against your name and can't be changed."
                    confirmLabel="Approve"
                    trigger={
                        <Button>
                            <CheckIcon /> Approve
                        </Button>
                    }
                />
                <Button variant="destructive" onClick={() => setRejecting(true)}>
                    <XIcon /> Reject
                </Button>
            </div>
            <Dialog
                open={rejecting}
                onOpenChange={(open) => !reject.processing && setRejecting(open)}
            >
                <DialogContent>
                    <form
                        noValidate
                        className="flex flex-col gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            reject.post(action, {
                                preserveScroll: true,
                                onSuccess: () => setRejecting(false),
                            });
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Reject this transaction?</DialogTitle>
                            <DialogDescription>
                                It goes back to the team to correct. Tell them what needs to change.
                            </DialogDescription>
                        </DialogHeader>
                        <Field data-invalid={!!reject.errors.comment || undefined}>
                            <FieldLabel htmlFor="reject-comment">Reason</FieldLabel>
                            <Textarea
                                id="reject-comment"
                                rows={4}
                                maxLength={2000}
                                value={reject.data.comment}
                                onChange={(e) => reject.setData('comment', e.target.value)}
                                aria-invalid={!!reject.errors.comment || undefined}
                                autoFocus
                            />
                            <FieldError>{reject.errors.comment ?? reject.errors.vote}</FieldError>
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRejecting(false)}
                                disabled={reject.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={reject.processing}
                            >
                                {reject.processing ? 'Rejecting…' : 'Reject'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Approval history (newest round first) with voting and revise actions. */
export function ApprovalPanel({ transactionId, approvals, openRequestId, can }) {
    if (approvals.length === 0 && !can.revise) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Approval</CardTitle>
                <CardDescription>
                    Needs a head approver and at least one more approval. Any rejection sends it
                    back.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                {can.vote && openRequestId && (
                    <VoteButtons action={route('approvals.vote', openRequestId)} />
                )}
                {can.revise && (
                    <ConfirmAction
                        href={route('transactions.revise', transactionId)}
                        title="Revise this transaction?"
                        description="It goes back to draft so you can make the changes the approvers asked for, then send it again."
                        confirmLabel="Revise"
                        trigger={<Button className="self-start">Revise and resubmit</Button>}
                    />
                )}
                {approvals.map((request, index) => (
                    <section key={request.id} className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center gap-2 text-[13px]">
                            <span className="font-medium">
                                {approvals.length > 1
                                    ? `Round ${approvals.length - index}`
                                    : 'Request'}
                            </span>
                            <Badge variant={REQUEST_TONE[request.status]}>
                                {request.status_label}
                            </Badge>
                            <span className="text-muted-foreground">
                                sent by {request.requested_by} on{' '}
                                {formatDateTime(request.requested_at)}
                            </span>
                        </div>
                        {request.votes.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">No votes yet.</p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {request.votes.map((vote) => (
                                    <li
                                        key={`${vote.user}-${vote.at}`}
                                        className="rounded-md bg-surface-3 px-3 py-2"
                                    >
                                        <div className="flex flex-wrap items-center gap-2 text-[13px]">
                                            <Badge
                                                variant={
                                                    vote.decision === 'approve'
                                                        ? 'success'
                                                        : 'danger'
                                                }
                                            >
                                                {vote.decision_label}
                                            </Badge>
                                            <span className="font-medium">{vote.user}</span>
                                            {vote.is_head && (
                                                <Badge variant="brand">Head approver</Badge>
                                            )}
                                            <span className="text-xs text-muted-foreground">
                                                {formatDateTime(vote.at)}
                                                {vote.via === 'email' && (
                                                    <MailIcon
                                                        className="ml-1 inline size-3"
                                                        aria-label="by email"
                                                    />
                                                )}
                                            </span>
                                        </div>
                                        {vote.comment && (
                                            <p className="mt-1 text-[13px] text-muted-foreground">
                                                “{vote.comment}”
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ))}
            </CardContent>
        </Card>
    );
}
