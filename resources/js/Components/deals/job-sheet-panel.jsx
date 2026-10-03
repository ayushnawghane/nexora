import { ConfirmAction } from '@/Components/confirm-action';
import { TextField } from '@/Components/form-fields';
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
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { FieldError } from '@/Components/ui/field';
import { formatDate, formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function SubmitDialog({ dealId, row, open, onOpenChange }) {
    const form = useForm({ received_on: row.entry?.received_on ?? today(), comment: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.job-sheet.submit', [dealId, row.activity_id]), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{row.activity}</DialogTitle>
                        <DialogDescription>
                            Record it as done and send it for checking. Someone else must check it.
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        form={form}
                        name="received_on"
                        label="Received on"
                        required
                        type="date"
                        max={today()}
                        className="w-48"
                    />
                    <TextField
                        form={form}
                        name="comment"
                        label="Comment"
                        multiline
                        rows={3}
                        maxLength={2000}
                    />
                    <FieldError>{form.errors.activity}</FieldError>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Sending…' : 'Send for checking'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ReturnDialog({ dealId, row, open, onOpenChange }) {
    const form = useForm({ decision: 'returned', comment: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.job-sheet.check', [dealId, row.entry.id]), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Send back: {row.activity}</DialogTitle>
                        <DialogDescription>Tell the maker what needs fixing.</DialogDescription>
                    </DialogHeader>
                    <TextField
                        form={form}
                        name="comment"
                        label="What needs fixing"
                        required
                        multiline
                        rows={3}
                        maxLength={2000}
                        autoFocus
                    />
                    <FieldError>{form.errors.decision}</FieldError>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={form.processing}>
                            {form.processing ? 'Sending…' : 'Send back'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Row({ dealId, row, can }) {
    const [dialog, setDialog] = useState(null);
    const { entry } = row;

    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-medium">{row.activity}</span>
                    {entry ? (
                        <Badge variant={entry.status_tone}>{entry.status_label}</Badge>
                    ) : (
                        <Badge variant="neutral">Not started</Badge>
                    )}
                </div>
                {entry && (
                    <div className="mt-1 flex flex-col gap-0.5 text-xs text-muted-foreground">
                        <span>
                            Received {formatDate(entry.received_on)} · made by {entry.maker},{' '}
                            {formatDateTime(entry.made_at)}
                            {entry.maker_comment && ` · “${entry.maker_comment}”`}
                        </span>
                        {entry.checker && (
                            <span>
                                {entry.status === 'verified' ? 'Verified' : 'Sent back'} by{' '}
                                {entry.checker}, {formatDateTime(entry.checked_at)}
                                {entry.checker_comment && ` · “${entry.checker_comment}”`}
                            </span>
                        )}
                    </div>
                )}
            </div>
            <div className="flex flex-wrap gap-2">
                {can.makeJobSheet && row.can_submit && (
                    <Button size="sm" variant="outline" onClick={() => setDialog('submit')}>
                        {entry ? 'Resubmit' : 'Record'}
                    </Button>
                )}
                {can.checkJobSheet && row.can_check && (
                    <>
                        <ConfirmAction
                            href={route('deals.job-sheet.check', [dealId, entry.id])}
                            data={{ decision: 'verified' }}
                            title={`Verify “${row.activity}”?`}
                            description="A verified entry can't be changed afterwards."
                            confirmLabel="Verify"
                            trigger={<Button size="sm">Verify</Button>}
                        />
                        <Button size="sm" variant="destructive" onClick={() => setDialog('return')}>
                            Send back
                        </Button>
                    </>
                )}
                {can.checkJobSheet && entry?.status === 'submitted' && entry.is_mine && (
                    <span className="text-xs text-muted-foreground">
                        You made this entry; someone else checks it.
                    </span>
                )}
            </div>
            {dialog === 'submit' && (
                <SubmitDialog dealId={dealId} row={row} open onOpenChange={() => setDialog(null)} />
            )}
            {dialog === 'return' && (
                <ReturnDialog dealId={dealId} row={row} open onOpenChange={() => setDialog(null)} />
            )}
        </li>
    );
}

/** The deal's job sheet: each activity is made by one person and checked by another. */
export function JobSheetPanel({ dealId, rows, can }) {
    const done = rows.filter((r) => r.entry?.status === 'verified').length;

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="pb-4">
                <CardTitle>Job sheet</CardTitle>
                <CardDescription>
                    {rows.length > 0
                        ? `${done} of ${rows.length} verified. The checker can never be the maker.`
                        : 'The checker can never be the maker.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="px-0">
                {rows.length === 0 ? (
                    <Empty className="mx-4 mb-4 border">
                        <EmptyHeader>
                            <EmptyTitle>No job sheet activities</EmptyTitle>
                            <EmptyDescription>
                                Add them under Masters → Job sheet activities.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="divide-y border-t">
                        {rows.map((row) => (
                            <Row key={row.activity_id} dealId={dealId} row={row} can={can} />
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
