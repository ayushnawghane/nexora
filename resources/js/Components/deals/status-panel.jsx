import { SelectField, TextField } from '@/Components/form-fields';
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
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { ArrowRightIcon, CheckIcon, FileDownIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

function teamsText(teams) {
    return teams.length === 0
        ? 'Applies at once, no approval needed.'
        : `Needs approval from ${teams.join(' and ')}.`;
}

function ChangeStatusDialog({ dealId, status, open, onOpenChange }) {
    const form = useForm({
        to_status: null,
        effective_on: status.max_date,
        reason: '',
        noc: null,
    });
    const target = status.next.find((s) => s.value === form.data.to_status);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.status.store', dealId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Change deal status</DialogTitle>
                        <DialogDescription>
                            Only the moves allowed from the current status are listed.
                        </DialogDescription>
                    </DialogHeader>
                    <SelectField
                        form={form}
                        name="to_status"
                        label="New status"
                        required
                        items={status.next}
                        hint={target ? teamsText(target.teams) : undefined}
                    />
                    <TextField
                        form={form}
                        name="effective_on"
                        label="Effective date"
                        required
                        type="date"
                        min={status.min_date ?? undefined}
                        max={status.max_date}
                        className="w-48"
                    />
                    <Field data-invalid={!!form.errors.reason || undefined}>
                        <FieldLabel htmlFor="status-reason">
                            Reason<span className="text-destructive">*</span>
                        </FieldLabel>
                        <Textarea
                            id="status-reason"
                            rows={3}
                            maxLength={2000}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            aria-invalid={!!form.errors.reason || undefined}
                        />
                        <FieldError>{form.errors.reason}</FieldError>
                    </Field>
                    {status.needs_noc && (
                        <Field data-invalid={!!form.errors.noc || undefined}>
                            <FieldLabel htmlFor="status-noc">
                                NOC<span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="status-noc"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => form.setData('noc', e.target.files?.[0] ?? null)}
                                aria-invalid={!!form.errors.noc || undefined}
                            />
                            <FieldDescription>
                                Required to move a deal out of Live. PDF or image, up to 10 MB.
                            </FieldDescription>
                            <FieldError>{form.errors.noc}</FieldError>
                        </Field>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.to_status}>
                            {form.processing
                                ? 'Sending…'
                                : target && target.teams.length === 0
                                  ? 'Change status'
                                  : 'Request approval'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** Approve / reject for one team. Reject always asks for a reason. */
function TeamVote({ requestId, team }) {
    const [rejecting, setRejecting] = useState(false);
    const reject = useForm({ team: team.value, decision: 'reject', comment: '' });
    const action = route('deals.status.vote', requestId);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <ConfirmAction
                href={action}
                data={{ team: team.value, decision: 'approve' }}
                title={`Approve for ${team.label}?`}
                description="Your approval is recorded against your name and can't be changed."
                confirmLabel="Approve"
                trigger={
                    <Button>
                        <CheckIcon /> Approve for {team.label}
                    </Button>
                }
            />
            <Button variant="destructive" onClick={() => setRejecting(true)}>
                <XIcon /> Reject
            </Button>
            <Dialog open={rejecting} onOpenChange={(o) => !reject.processing && setRejecting(o)}>
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
                            <DialogTitle>Reject this status change?</DialogTitle>
                            <DialogDescription>
                                The deal stays at its current status. Tell the requester why.
                            </DialogDescription>
                        </DialogHeader>
                        <Field data-invalid={!!reject.errors.comment || undefined}>
                            <FieldLabel htmlFor={`reject-${team.value}`}>Reason</FieldLabel>
                            <Textarea
                                id={`reject-${team.value}`}
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
        </div>
    );
}

function RequestItem({ request, status }) {
    const isOpen = request.status === 'open';

    return (
        <li className="flex flex-col gap-3 rounded-lg border p-3">
            <div className="flex flex-wrap items-center gap-2 text-[13px]">
                <span className="font-medium">{request.from}</span>
                <ArrowRightIcon className="size-3.5 text-muted-foreground" />
                <span className="font-medium">{request.to}</span>
                <Badge variant={request.status_tone}>{request.status_label}</Badge>
                <span className="text-muted-foreground">
                    effective {formatDate(request.effective_on)}
                </span>
            </div>
            <p className="text-[13px] text-muted-foreground">
                “{request.reason}” · {request.requested_by}, {formatDateTime(request.requested_at)}
            </p>
            {request.has_noc && (
                <a
                    href={route('deals.status.noc', request.id)}
                    className="inline-flex items-center gap-1.5 self-start text-[13px] text-brand-text hover:underline"
                >
                    <FileDownIcon className="size-4" /> {request.noc_name ?? 'NOC'}
                </a>
            )}
            {isOpen && request.pending_teams.length > 0 && (
                <p className="text-[13px]">
                    Waiting for{' '}
                    <span className="font-medium">{request.pending_teams.join(' and ')}</span>.
                </p>
            )}
            {request.votes.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {request.votes.map((vote) => (
                        <li
                            key={`${vote.team}-${vote.at}`}
                            className="rounded-md bg-surface-3 px-3 py-2"
                        >
                            <div className="flex flex-wrap items-center gap-2 text-[13px]">
                                <Badge variant={vote.decision === 'approve' ? 'success' : 'danger'}>
                                    {vote.decision_label}
                                </Badge>
                                <span className="font-medium">{vote.user}</span>
                                <span className="text-muted-foreground">for {vote.team}</span>
                                <span className="text-xs text-muted-foreground">
                                    {formatDateTime(vote.at)}
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
            {isOpen && (status.can_vote_as.length > 0 || status.can_withdraw) && (
                <div className="flex flex-col gap-2">
                    {status.can_vote_as.map((team) => (
                        <TeamVote key={team.value} requestId={request.id} team={team} />
                    ))}
                    {status.can_withdraw && (
                        <ConfirmAction
                            href={route('deals.status.withdraw', request.id)}
                            title="Withdraw this request?"
                            description="The deal stays at its current status. You can raise a new request afterwards."
                            confirmLabel="Withdraw"
                            destructive
                            trigger={
                                <Button variant="outline" className="self-start">
                                    Withdraw request
                                </Button>
                            }
                        />
                    )}
                </div>
            )}
        </li>
    );
}

/** Current status, the change request flow (Management + Accounts approval) and the history. */
export function StatusPanel({ deal, status, canRequest }) {
    const [changing, setChanging] = useState(false);
    const canChange =
        canRequest && deal.is_open && !status.open_request_id && status.next.length > 0;

    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Status</CardTitle>
                    <CardDescription>
                        Putting a deal on hold applies at once. Cancelling a preliminary deal needs
                        Management; every other change needs Management and Accounts.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap items-center gap-3">
                    <Badge variant={deal.deal_status_tone}>{deal.deal_status_label}</Badge>
                    <span className="text-[13px] text-muted-foreground">
                        since {formatDate(deal.deal_status_since)}
                    </span>
                    {canChange && (
                        <Button className="ml-auto" onClick={() => setChanging(true)}>
                            Change status
                        </Button>
                    )}
                    {status.open_request_id && canRequest && (
                        <span className="ml-auto text-[13px] text-muted-foreground">
                            A change is waiting for approval.
                        </span>
                    )}
                </CardContent>
            </Card>

            {status.requests.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Change requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col gap-3">
                            {status.requests.map((request) => (
                                <RequestItem key={request.id} request={request} status={status} />
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Status history</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol className="flex flex-col gap-3">
                        {status.history.map((change) => (
                            <li
                                key={`${change.at}-${change.to}`}
                                className="flex flex-wrap items-center gap-2 text-[13px]"
                            >
                                <Badge variant={change.tone}>{change.to}</Badge>
                                <span className="text-muted-foreground">
                                    {change.from ? `from ${change.from}, ` : ''}effective{' '}
                                    {formatDate(change.effective_on)} · {change.changed_by}
                                </span>
                            </li>
                        ))}
                    </ol>
                </CardContent>
            </Card>

            {canChange && (
                <ChangeStatusDialog
                    dealId={deal.id}
                    status={status}
                    open={changing}
                    onOpenChange={setChanging}
                />
            )}
        </div>
    );
}
