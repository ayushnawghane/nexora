import { ConfirmAction } from '@/Components/confirm-action';
import {
    FileLink,
    History,
    MAX_BYTES,
    ReasonDialog,
    fileProblem,
} from '@/Components/deals/document-files';
import { ComboField, SelectField, TextField } from '@/Components/form-fields';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { formatDate, formatDateTime } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { CalendarClockIcon, CheckCircle2Icon, SendIcon, UploadIcon, XIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function formatSchedule(value) {
    if (!value) return null;
    const [date, time] = value.split('T');
    return `${formatDate(date)}, ${time}`;
}

/** A checkbox list of { value, label, description? } that fills form.data[name]. */
function CheckList({ form, name, items, emptyText }) {
    const toggle = (id, on) =>
        form.setData(name, on ? [...form.data[name], id] : form.data[name].filter((v) => v !== id));
    const error =
        form.errors[name] ??
        Object.entries(form.errors).find(([k]) => k.startsWith(`${name}.`))?.[1];

    if (items.length === 0) {
        return <p className="text-[13px] text-muted-foreground">{emptyText}</p>;
    }
    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex max-h-60 flex-col gap-1 overflow-y-auto rounded-md border p-2">
                {items.map((item) => {
                    const id = `${name}-${item.value}`;
                    return (
                        <Field
                            key={item.value}
                            orientation="horizontal"
                            className="items-start rounded px-1 py-1.5 hover:bg-surface-3"
                        >
                            <Checkbox
                                id={id}
                                checked={form.data[name].includes(item.value)}
                                onCheckedChange={(on) => toggle(item.value, on === true)}
                            />
                            <FieldLabel
                                htmlFor={id}
                                className="flex min-w-0 flex-col items-start gap-0.5 font-normal"
                            >
                                <span className="[overflow-wrap:anywhere]">{item.label}</span>
                                {item.description && (
                                    <span className="text-xs text-muted-foreground">
                                        {item.description}
                                    </span>
                                )}
                            </FieldLabel>
                        </Field>
                    );
                })}
            </div>
            {error && <p className="text-[13px] text-destructive">{error}</p>}
        </div>
    );
}

function DialogButtons({ form, onCancel, label, busyLabel, disabled }) {
    return (
        <DialogFooter>
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.processing}>
                Cancel
            </Button>
            <Button type="submit" disabled={form.processing || disabled}>
                {form.processing ? busyLabel : label}
            </Button>
        </DialogFooter>
    );
}

function SendDialog({ dealId, ready, onClose }) {
    const form = useForm({ document_ids: [] });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.executions.store', dealId), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Send documents for execution</DialogTitle>
                        <DialogDescription>
                            Documents with an execution version uploaded on the Documentation tab.
                        </DialogDescription>
                    </DialogHeader>
                    <CheckList
                        form={form}
                        name="document_ids"
                        items={ready}
                        emptyText="Every document with an execution version is already in execution."
                    />
                    <DialogButtons
                        form={form}
                        onCancel={onClose}
                        label="Send"
                        busyLabel="Sending…"
                        disabled={form.data.document_ids.length === 0}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleDialog({ dealId, executions, options, preselected, onClose }) {
    const schedulable = executions.filter((e) => e.can_schedule);
    const first = schedulable.find((e) => e.id === preselected[0]);
    const form = useForm({
        execution_ids: preselected,
        place: first?.place ?? '',
        scheduled_at: first?.scheduled_at ?? '',
        signatory_type: first?.signatory_type ?? 'internal',
        signatory_user_id: null,
        poa_holder_id: null,
    });

    // Only POA holders whose power of attorney covers the chosen date (the server checks it too).
    const day = form.data.scheduled_at.slice(0, 10);
    const poaHolders = useMemo(
        () =>
            options.poa_holders.filter(
                (p) =>
                    !day ||
                    ((!p.valid_from || p.valid_from <= day) &&
                        (!p.valid_till || p.valid_till >= day)),
            ),
        [options.poa_holders, day],
    );

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.executions.schedule', dealId), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Schedule execution</DialogTitle>
                        <DialogDescription>
                            The signatory is emailed the documents, place and time.
                        </DialogDescription>
                    </DialogHeader>
                    <CheckList
                        form={form}
                        name="execution_ids"
                        items={schedulable.map((e) => ({
                            value: e.id,
                            label: e.document,
                            description: e.scheduled_at
                                ? `Now ${formatSchedule(e.scheduled_at)}, ${e.place}`
                                : null,
                        }))}
                        emptyText="Nothing is waiting to be scheduled."
                    />
                    <TextField form={form} name="place" label="Place" required maxLength={100} />
                    <TextField
                        form={form}
                        name="scheduled_at"
                        label="Date and time"
                        required
                        type="datetime-local"
                        min="2000-01-01T00:00"
                        max="2100-12-31T23:59"
                        className="sm:w-64"
                    />
                    <SelectField
                        form={form}
                        name="signatory_type"
                        label="Signed by"
                        required
                        items={options.types}
                    />
                    {form.data.signatory_type === 'internal' ? (
                        <ComboField
                            form={form}
                            name="signatory_user_id"
                            label="Authorised signatory"
                            required
                            items={options.signatories}
                            placeholder="Choose a signatory…"
                        />
                    ) : (
                        <ComboField
                            form={form}
                            name="poa_holder_id"
                            label="POA holder"
                            required
                            items={poaHolders}
                            placeholder="Choose a POA holder…"
                            hint={day ? 'Only POAs valid on the execution date are listed.' : null}
                        />
                    )}
                    <DialogButtons
                        form={form}
                        onCancel={onClose}
                        label="Schedule"
                        busyLabel="Scheduling…"
                        disabled={form.data.execution_ids.length === 0}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RecordDialog({ dealId, execution, onClose }) {
    const form = useForm({
        file: null,
        executed_on: execution.executed_on ?? execution.scheduled_at?.slice(0, 10) ?? '',
        document_date: execution.document_date ?? '',
        comments: execution.comments ?? '',
    });
    const [problem, setProblem] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        const issue = fileProblem(form.data.file ? [form.data.file] : [], {
            accept: '.pdf',
            label: 'a PDF',
        });
        setProblem(issue);
        if (issue) return;
        form.post(route('deals.executions.record', [dealId, execution.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const fileError = problem ?? form.errors.file;

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Record execution</DialogTitle>
                        <DialogDescription>
                            {execution.document}. Uploading sends it for checking by someone else.
                        </DialogDescription>
                    </DialogHeader>
                    <Field data-invalid={!!fileError || undefined}>
                        <FieldLabel htmlFor="executed-copy">
                            Executed copy<span className="text-destructive">*</span>
                        </FieldLabel>
                        <Input
                            id="executed-copy"
                            type="file"
                            accept=".pdf"
                            onChange={(e) => {
                                setProblem(null);
                                form.setData('file', e.target.files?.[0] ?? null);
                            }}
                            aria-invalid={!!fileError || undefined}
                        />
                        <FieldDescription>
                            PDF, up to {Math.round(MAX_BYTES / 1024 / 1024)} MB.
                        </FieldDescription>
                        <FieldError>{fileError}</FieldError>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="executed_on"
                            label="Executed on"
                            required
                            type="date"
                            min="2000-01-01"
                            max={today()}
                        />
                        <TextField
                            form={form}
                            name="document_date"
                            label="Document date"
                            type="date"
                            min="2000-01-01"
                            max={form.data.executed_on || today()}
                        />
                    </div>
                    <TextField
                        form={form}
                        name="comments"
                        label="Comments"
                        multiline
                        rows={2}
                        maxLength={1000}
                    />
                    <DialogButtons
                        form={form}
                        onCancel={onClose}
                        label="Upload"
                        busyLabel="Uploading…"
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ExecutionRow({ dealId, execution, can, onSchedule }) {
    const [dialog, setDialog] = useState(null);
    const close = () => setDialog(null);
    const e = execution;

    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-medium">{e.document}</span>
                    <Badge variant={e.status_tone}>{e.status_label}</Badge>
                    {e.picked_up_at && <Badge variant="success">Picked up</Badge>}
                </div>
                {e.scheduled_at && (
                    <span className="text-xs text-muted-foreground">
                        {formatSchedule(e.scheduled_at)}, {e.place} · {e.signatory}
                        {e.signatory_type === 'external' && ' (POA holder)'}
                    </span>
                )}
                {e.current && (
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <FileLink dealId={dealId} file={e.current} />
                        <span className="text-xs text-muted-foreground">
                            Executed {formatDate(e.executed_on)}
                            {e.document_date && ` · dated ${formatDate(e.document_date)}`} ·
                            uploaded by {e.uploaded_by}, {formatDateTime(e.uploaded_at)}
                            {e.comments && ` · “${e.comments}”`}
                        </span>
                    </div>
                )}
                {e.checker && (
                    <span className="text-xs text-muted-foreground">
                        {e.status === 'verified' ? 'Verified' : 'Sent back'} by {e.checker},{' '}
                        {formatDateTime(e.checked_at)}
                        {e.checker_comment && ` · “${e.checker_comment}”`}
                    </span>
                )}
                {e.picked_up_at && (
                    <span className="text-xs text-muted-foreground">
                        Picked up by {e.picked_up_by}, {formatDateTime(e.picked_up_at)}
                    </span>
                )}
                <History
                    dealId={dealId}
                    label={`${e.history.length} earlier ${e.history.length === 1 ? 'copy' : 'copies'}`}
                    files={e.history}
                />
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {can.manageExecution && e.can_schedule && (
                    <Button size="sm" variant="outline" onClick={() => onSchedule(e.id)}>
                        <CalendarClockIcon /> {e.scheduled_at ? 'Reschedule' : 'Schedule'}
                    </Button>
                )}
                {can.manageExecution && e.can_record && (
                    <Button size="sm" variant="outline" onClick={() => setDialog('record')}>
                        <UploadIcon /> {e.current ? 'Upload again' : 'Record execution'}
                    </Button>
                )}
                {can.verifyExecution && e.can_check && (
                    <>
                        <ConfirmAction
                            href={route('deals.executions.check', [dealId, e.id])}
                            data={{ decision: 'verified' }}
                            title={`Verify the executed “${e.document}”?`}
                            description="A verified execution can't be changed afterwards."
                            confirmLabel="Verify"
                            trigger={<Button size="sm">Verify</Button>}
                        />
                        <Button size="sm" variant="destructive" onClick={() => setDialog('return')}>
                            Send back
                        </Button>
                    </>
                )}
                {can.verifyExecution && e.status === 'executed' && e.is_mine && (
                    <span className="text-xs text-muted-foreground">
                        You uploaded the copy; someone else checks it.
                    </span>
                )}
                {can.manageExecution &&
                    e.current &&
                    ['executed', 'returned'].includes(e.status) && (
                        <ConfirmAction
                            href={route('deals.files.destroy', [dealId, e.current.id])}
                            method="delete"
                            title="Remove the executed copy?"
                            description="It stays in the history; the document goes back to scheduled."
                            confirmLabel="Remove copy"
                            destructive
                            trigger={
                                <Button size="sm" variant="ghost">
                                    Remove copy
                                </Button>
                            }
                        />
                    )}
                {can.manageExecution && e.can_withdraw && (
                    <ConfirmAction
                        href={route('deals.executions.destroy', [dealId, e.id])}
                        method="delete"
                        title={`Take “${e.document}” out of execution?`}
                        description="It goes back to the Documentation tab, where its execution version can be changed."
                        confirmLabel="Take out"
                        destructive
                        trigger={
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-8"
                                aria-label="Take out of execution"
                            >
                                <XIcon />
                            </Button>
                        }
                    />
                )}
            </div>
            {dialog === 'record' && <RecordDialog dealId={dealId} execution={e} onClose={close} />}
            {dialog === 'return' && (
                <ReasonDialog
                    title={`Send back: ${e.document}`}
                    description="Tell the maker what needs fixing."
                    label="What needs fixing"
                    field="comment"
                    href={route('deals.executions.check', [dealId, e.id])}
                    submitLabel="Send back"
                    destructive
                    open
                    onOpenChange={close}
                />
            )}
        </li>
    );
}

/** Executing the deal's documents, from sending them to custody picking them up. */
export function ExecutionPanel({ dealId, execution, can }) {
    const [dialog, setDialog] = useState(null);
    const { executions, ready, pickup } = execution;
    const verified = executions.filter((e) => e.status === 'verified').length;
    const waiting = executions.filter((e) => e.status === 'executed').length;
    const toSchedule = executions.filter((e) => e.status === 'to_schedule').length;

    const summary = [
        `${verified} of ${executions.length} verified`,
        toSchedule > 0 && `${toSchedule} to schedule`,
        waiting > 0 && `${waiting} waiting for a check`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <div className="flex flex-col gap-4">
            {execution.suggest_live && (
                <Alert>
                    <CheckCircle2Icon />
                    <AlertTitle>Every document is executed and verified</AlertTitle>
                    <AlertDescription>
                        <span>
                            The deal can move to Live. Request it on the{' '}
                            <Link
                                href={route('deals.show', { transaction: dealId, tab: 'status' })}
                                className="text-brand-text underline-offset-2 hover:underline"
                            >
                                Status tab
                            </Link>
                            .
                        </span>
                    </AlertDescription>
                </Alert>
            )}
            <Card className="gap-0 pb-0">
                <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                    <div className="flex flex-col gap-1.5">
                        <CardTitle>Execution</CardTitle>
                        <CardDescription>
                            {executions.length > 0
                                ? `${summary}. The checker can never be the uploader.`
                                : 'Documents being signed and their executed copies.'}
                        </CardDescription>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.manageExecution && ready.length > 0 && (
                            <Button size="sm" onClick={() => setDialog({ type: 'send' })}>
                                <SendIcon /> Send for execution
                            </Button>
                        )}
                        {can.manageExecution && executions.some((e) => e.can_schedule) && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    setDialog({
                                        type: 'schedule',
                                        ids: executions
                                            .filter((e) => e.status === 'to_schedule')
                                            .map((e) => e.id),
                                    })
                                }
                            >
                                <CalendarClockIcon /> Schedule
                            </Button>
                        )}
                        {can.custody && pickup.ready && (
                            <ConfirmAction
                                href={route('deals.executions.pick-up', dealId)}
                                title="Mark the executed documents as picked up?"
                                description="Records that custody has collected every verified document not picked up yet."
                                confirmLabel="Mark picked up"
                                trigger={
                                    <Button size="sm" variant="outline">
                                        Mark picked up
                                    </Button>
                                }
                            />
                        )}
                    </div>
                </CardHeader>
                <CardContent className="px-0">
                    {executions.length === 0 ? (
                        <Empty className="mx-4 mb-4 border">
                            <EmptyHeader>
                                <EmptyTitle>Nothing in execution yet</EmptyTitle>
                                <EmptyDescription>
                                    {ready.length > 0
                                        ? 'Send the documents whose execution version is final.'
                                        : 'Upload execution versions on the Documentation tab first.'}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <ul className="divide-y border-t">
                            {executions.map((e) => (
                                <ExecutionRow
                                    key={e.id}
                                    dealId={dealId}
                                    execution={e}
                                    can={can}
                                    onSchedule={(id) => setDialog({ type: 'schedule', ids: [id] })}
                                />
                            ))}
                        </ul>
                    )}
                    {pickup.all_picked_up && (
                        <p className="border-t px-4 py-3 text-xs text-muted-foreground">
                            Every executed document has been picked up for custody.
                        </p>
                    )}
                </CardContent>
            </Card>
            {dialog?.type === 'send' && (
                <SendDialog dealId={dealId} ready={ready} onClose={() => setDialog(null)} />
            )}
            {dialog?.type === 'schedule' && (
                <ScheduleDialog
                    dealId={dealId}
                    executions={executions}
                    options={execution.options}
                    preselected={dialog.ids}
                    onClose={() => setDialog(null)}
                />
            )}
        </div>
    );
}
