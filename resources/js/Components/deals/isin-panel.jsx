import { ConfirmAction } from '@/Components/confirm-action';
import { ACCEPT, FileLink, MAX_FILES, fileProblem } from '@/Components/deals/document-files';
import { SelectField, TextField } from '@/Components/form-fields';
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
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { ScrollArea } from '@/Components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { isinError } from '@/lib/identifiers';
import { formatDate, formatMoney } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { BellIcon, CalendarPlusIcon, PencilIcon, PlusIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

const EXCHANGES = ['BSE', 'NSE', 'Both'].map((v) => ({ value: v, label: v }));
const DEPOSITORIES = ['NSDL', 'CDSL', 'Both'].map((v) => ({ value: v, label: v }));
const CREDIT_DEPOSITORIES = ['NSDL', 'CDSL'].map((v) => ({ value: v, label: v }));

function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function Buttons({ form, onCancel, label, busyLabel, disabled, sheet = false }) {
    const Wrapper = sheet ? SheetFooter : DialogFooter;
    return (
        <Wrapper className={sheet ? 'flex-row justify-end border-t' : undefined}>
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.processing}>
                Cancel
            </Button>
            <Button type="submit" disabled={form.processing || disabled}>
                {form.processing ? busyLabel : label}
            </Button>
        </Wrapper>
    );
}

function FilesInput({ form, field = 'files', multiple = true, label, hint }) {
    const [problem, setProblem] = useState(null);
    const error =
        problem ??
        form.errors[field] ??
        Object.entries(form.errors).find(([k]) => k.startsWith(`${field}.`))?.[1];
    return (
        <Field data-invalid={!!error || undefined}>
            <FieldLabel htmlFor={`isin-${field}`}>{label}</FieldLabel>
            <Input
                id={`isin-${field}`}
                type="file"
                multiple={multiple}
                accept={ACCEPT}
                onChange={(e) => {
                    const chosen = Array.from(e.target.files ?? []);
                    const issue = chosen.length ? fileProblem(chosen) : null;
                    setProblem(issue);
                    form.setData(
                        field,
                        issue ? (multiple ? [] : null) : multiple ? chosen : (chosen[0] ?? null),
                    );
                }}
                aria-invalid={!!error || undefined}
            />
            <FieldDescription>
                {hint ??
                    `PDF, Word, Excel or image, 20 MB each${multiple ? `, up to ${MAX_FILES}` : ''}.`}
            </FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

// ---------------------------------------------------------------- ISIN form

function IsinForm({ dealId, isin, options, onDone }) {
    const editing = Boolean(isin);
    const form = useForm(
        isin?.values ?? {
            isin: '',
            series_name: '',
            listing: null,
            exchange: null,
            depository: null,
            placement: 'private',
            allotment_date: '',
            maturity_date: '',
            coupon_type: 'fixed',
            coupon_rate: '',
            coupon_description: '',
            interest_frequency: 'annual',
            principal_frequency: 'bullet',
            day_count: 'act_act',
            holiday_convention: 'following',
            put_date: '',
            call_date: '',
            comments: '',
        },
    );
    const [isinProblem, setIsinProblem] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        const issue = isinError(form.data.isin.trim().toUpperCase());
        setIsinProblem(issue);
        if (issue) return;
        const options = { preserveScroll: true, onSuccess: onDone };
        if (editing) form.put(route('deals.isins.update', [dealId, isin.id]), options);
        else form.post(route('deals.isins.store', dealId), options);
    };

    return (
        <form onSubmit={submit} noValidate className="flex h-full min-h-0 flex-col">
            <SheetHeader>
                <SheetTitle>{editing ? `Edit ${isin.isin}` : 'Add an ISIN'}</SheetTitle>
                <SheetDescription>
                    The series and how it pays interest and principal.
                </SheetDescription>
            </SheetHeader>
            <ScrollArea className="min-h-0 flex-1 px-4">
                <div className="flex flex-col gap-4 pb-4">
                    <TextField
                        form={form}
                        name="isin"
                        label="ISIN"
                        required
                        maxLength={12}
                        className="font-mono uppercase"
                        error={isinProblem}
                        autoCapitalize="characters"
                    />
                    <TextField
                        form={form}
                        name="series_name"
                        label="Series"
                        multiline
                        rows={2}
                        maxLength={2000}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="allotment_date"
                            label="Allotment date"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                        <TextField
                            form={form}
                            name="maturity_date"
                            label="Maturity date"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                        <SelectField
                            form={form}
                            name="listing"
                            label="Listing"
                            items={options.listings}
                        />
                        <SelectField
                            form={form}
                            name="exchange"
                            label="Exchange"
                            items={EXCHANGES}
                        />
                        <SelectField
                            form={form}
                            name="depository"
                            label="Depository"
                            items={DEPOSITORIES}
                        />
                        <SelectField
                            form={form}
                            name="placement"
                            label="Placement"
                            items={options.placements}
                        />
                        <SelectField
                            form={form}
                            name="coupon_type"
                            label="Coupon"
                            items={options.coupon_types}
                        />
                        <TextField
                            form={form}
                            name="coupon_rate"
                            label="Coupon rate (% p.a.)"
                            inputMode="decimal"
                        />
                    </div>
                    <TextField
                        form={form}
                        name="coupon_description"
                        label="Coupon notes"
                        maxLength={255}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            form={form}
                            name="interest_frequency"
                            label="Interest paid"
                            items={options.frequencies}
                        />
                        <SelectField
                            form={form}
                            name="principal_frequency"
                            label="Principal repaid"
                            items={options.frequencies}
                        />
                        <SelectField
                            form={form}
                            name="day_count"
                            label="Day count"
                            items={options.day_counts}
                        />
                        <SelectField
                            form={form}
                            name="holiday_convention"
                            label="Due date on a weekend"
                            items={options.holiday_conventions}
                        />
                        <TextField
                            form={form}
                            name="put_date"
                            label="Put date"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                        <TextField
                            form={form}
                            name="call_date"
                            label="Call date"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                    </div>
                    <TextField
                        form={form}
                        name="comments"
                        label="Comments"
                        multiline
                        rows={2}
                        maxLength={2000}
                    />
                </div>
            </ScrollArea>
            <Buttons form={form} onCancel={onDone} label="Save" busyLabel="Saving…" sheet />
        </form>
    );
}

// ---------------------------------------------------------------- dialogs

function AllotmentDialog({ dealId, isin, options, onClose }) {
    const hasInitial = isin.allotments.some((a) => a.kind_label === 'Initial allotment');
    const form = useForm({
        kind: hasInitial ? 'additional' : 'initial',
        allotment_date: '',
        issue_opened_on: '',
        issue_closed_on: '',
        face_value: '',
        quantity_offered: '',
        quantity_allotted: '',
        credit_depository: null,
        credited_on: '',
        file: null,
    });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.isins.allot', [dealId, isin.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Record an allotment</DialogTitle>
                        <DialogDescription>
                            {isin.isin}. The amount is face value × quantity allotted.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            form={form}
                            name="kind"
                            label="Allotment"
                            required
                            items={options.allotment_kinds}
                        />
                        <TextField
                            form={form}
                            name="allotment_date"
                            label="Allotment date"
                            required
                            type="date"
                            min="2000-01-01"
                            max={today()}
                        />
                        <TextField
                            form={form}
                            name="issue_opened_on"
                            label="Issue opened"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                        <TextField
                            form={form}
                            name="issue_closed_on"
                            label="Issue closed"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                        <TextField
                            form={form}
                            name="face_value"
                            label="Face value (₹)"
                            required
                            inputMode="decimal"
                        />
                        <TextField
                            form={form}
                            name="quantity_allotted"
                            label="Quantity allotted"
                            required
                            inputMode="numeric"
                        />
                        <TextField
                            form={form}
                            name="quantity_offered"
                            label="Quantity offered"
                            inputMode="numeric"
                        />
                        <span />
                        <SelectField
                            form={form}
                            name="credit_depository"
                            label="Credited at"
                            items={CREDIT_DEPOSITORIES}
                        />
                        <TextField
                            form={form}
                            name="credited_on"
                            label="Credited on"
                            type="date"
                            min="2000-01-01"
                            max="2100-12-31"
                        />
                    </div>
                    <FilesInput
                        form={form}
                        field="file"
                        multiple={false}
                        label="Credit confirmation"
                    />
                    <Buttons form={form} onCancel={onClose} label="Record" busyLabel="Saving…" />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ScheduleDialog({ dealId, isin, options, onClose }) {
    const form = useForm({
        source: 'generate',
        kind: 'interest',
        first_due_on: '',
        frequency: isin.values.interest_frequency ?? 'annual',
        due_on: '',
        file: null,
    });

    const setKind = (kind) =>
        form.setData((d) => ({
            ...d,
            kind,
            frequency:
                (kind === 'principal'
                    ? isin.values.principal_frequency
                    : isin.values.interest_frequency) ?? d.frequency,
        }));

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.isins.schedule', [dealId, isin.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Add due dates</DialogTitle>
                        <DialogDescription>
                            {isin.isin}
                            {isin.maturity_date && `, maturing ${formatDate(isin.maturity_date)}`}.
                            Dates already in the schedule are skipped.
                        </DialogDescription>
                    </DialogHeader>
                    <Tabs value={form.data.source} onValueChange={(v) => form.setData('source', v)}>
                        <TabsList className="w-full">
                            <TabsTrigger value="generate">From a frequency</TabsTrigger>
                            <TabsTrigger value="file">From a file</TabsTrigger>
                            <TabsTrigger value="single">One date</TabsTrigger>
                        </TabsList>
                        <TabsContent value="generate" className="flex flex-col gap-4 pt-2">
                            <SelectField
                                form={form}
                                name="kind"
                                label="Schedule"
                                required
                                items={options.kinds}
                                set={setKind}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    form={form}
                                    name="first_due_on"
                                    label="First due date"
                                    required
                                    type="date"
                                    min="2000-01-01"
                                    max="2100-12-31"
                                />
                                <SelectField
                                    form={form}
                                    name="frequency"
                                    label="Frequency"
                                    required
                                    items={options.frequencies}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Every period from the first date up to maturity, with maturity as
                                the last date. Weekend dates follow the ISIN&apos;s weekend rule.
                            </p>
                        </TabsContent>
                        <TabsContent value="file" className="flex flex-col gap-2 pt-2">
                            <Field data-invalid={!!form.errors.file || undefined}>
                                <FieldLabel htmlFor="schedule-file">Schedule file (CSV)</FieldLabel>
                                <Input
                                    id="schedule-file"
                                    type="file"
                                    accept=".csv,text/csv"
                                    onChange={(e) =>
                                        form.setData('file', e.target.files?.[0] ?? null)
                                    }
                                    aria-invalid={!!form.errors.file || undefined}
                                />
                                <FieldDescription>
                                    Columns &quot;Principal Schedule&quot; and &quot;Interest
                                    Schedule&quot; (Stack&apos;s format), one date per row, e.g.
                                    31-03-2026.
                                </FieldDescription>
                                <FieldError>{form.errors.file}</FieldError>
                            </Field>
                        </TabsContent>
                        <TabsContent value="single" className="flex flex-col gap-4 pt-2">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <SelectField
                                    form={form}
                                    name="kind"
                                    label="Schedule"
                                    required
                                    items={options.kinds}
                                />
                                <TextField
                                    form={form}
                                    name="due_on"
                                    label="Due date"
                                    required
                                    type="date"
                                    min="2000-01-01"
                                    max="2100-12-31"
                                />
                            </div>
                        </TabsContent>
                    </Tabs>
                    <Buttons form={form} onCancel={onClose} label="Add" busyLabel="Adding…" />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RecordDialog({ dealId, payment, options, onClose }) {
    const principal = payment.kind === 'principal';
    const form = useForm({
        status: 'paid',
        paid_on: payment.due_on <= today() ? payment.due_on : today(),
        amount: '',
        redemption_basis: principal ? 'full' : null,
        face_value: '',
        quantity: '',
        remark: '',
        files: [],
    });
    const paid = form.data.status === 'paid';

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.isin-payments.record', [dealId, payment.id]), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {payment.kind_label} due {formatDate(payment.due_on)}
                        </DialogTitle>
                        <DialogDescription>
                            Record what happened. This can&apos;t be changed afterwards.
                        </DialogDescription>
                    </DialogHeader>
                    <SelectField
                        form={form}
                        name="status"
                        label="Outcome"
                        required
                        items={options.outcomes}
                    />
                    {paid && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                form={form}
                                name="paid_on"
                                label="Paid on"
                                required
                                type="date"
                                min="2000-01-01"
                                max={today()}
                            />
                            <TextField
                                form={form}
                                name="amount"
                                label="Amount paid (₹)"
                                required
                                inputMode="decimal"
                            />
                            {principal && (
                                <>
                                    <SelectField
                                        form={form}
                                        name="redemption_basis"
                                        label="Redemption"
                                        required
                                        items={options.redemption_bases}
                                    />
                                    <span />
                                    <TextField
                                        form={form}
                                        name="face_value"
                                        label="Face value redeemed (₹)"
                                        inputMode="decimal"
                                    />
                                    <TextField
                                        form={form}
                                        name="quantity"
                                        label="Quantity redeemed"
                                        inputMode="numeric"
                                    />
                                </>
                            )}
                        </div>
                    )}
                    <TextField
                        form={form}
                        name="remark"
                        label="Remark"
                        multiline
                        rows={2}
                        maxLength={2000}
                    />
                    <FilesInput
                        form={form}
                        label="Proof"
                        hint="Issuer confirmation, bank statement … up to 10 files, 20 MB each."
                    />
                    <Buttons form={form} onCancel={onClose} label="Record" busyLabel="Saving…" />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function MoveDialog({ dealId, payment, onClose }) {
    const form = useForm({ due_on: payment.due_on, reason: '' });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('deals.isin-payments.move', [dealId, payment.id]), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Move due date</DialogTitle>
                        <DialogDescription>
                            {payment.kind_label} due {formatDate(payment.due_on)}. The original date
                            is kept.
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        form={form}
                        name="due_on"
                        label="New due date"
                        required
                        type="date"
                        min="2000-01-01"
                        max="2100-12-31"
                        className="w-48"
                    />
                    <TextField
                        form={form}
                        name="reason"
                        label="Why it moved"
                        required
                        multiline
                        rows={2}
                        maxLength={500}
                    />
                    <Buttons form={form} onCancel={onClose} label="Move" busyLabel="Saving…" />
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ---------------------------------------------------------------- schedule

function PaymentRow({ dealId, payment: p, options, canManage }) {
    const [dialog, setDialog] = useState(null);
    const close = () => setDialog(null);

    return (
        <li className="flex flex-col gap-1.5 px-4 py-2.5 sm:flex-row sm:items-center sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="w-24 text-[13px] font-medium tabular-nums">
                        {formatDate(p.due_on)}
                    </span>
                    <Badge variant="neutral">{p.kind_label}</Badge>
                    <Badge variant={p.status_tone}>{p.overdue ? 'Overdue' : p.status_label}</Badge>
                    {p.last_reminded && (
                        <span className="text-xs text-muted-foreground">
                            reminded {formatDate(p.last_reminded)}
                        </span>
                    )}
                </div>
                {p.original_due_on && (
                    <span className="text-xs text-muted-foreground">
                        Moved from {formatDate(p.original_due_on)}: “{p.due_date_reason}”
                    </span>
                )}
                {p.status !== 'due' && (
                    <span className="text-xs text-muted-foreground">
                        {p.paid_on && `Paid ${formatDate(p.paid_on)}`}
                        {p.amount && ` · ${formatMoney(p.amount)}`}
                        {p.redemption_basis && ` · ${p.redemption_basis}`}
                        {p.quantity && ` · ${Number(p.quantity).toLocaleString('en-IN')} units`}
                        {p.recorded_by && ` · recorded by ${p.recorded_by}`}
                        {p.remark && ` · “${p.remark}”`}
                    </span>
                )}
                {p.files.map((f) => (
                    <FileLink key={f.id} dealId={dealId} file={f} />
                ))}
            </div>
            {canManage && p.status === 'due' && (
                <div className="flex flex-wrap items-center gap-2">
                    <Button size="sm" variant="outline" onClick={() => setDialog('record')}>
                        Record
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setDialog('move')}>
                        Move
                    </Button>
                    <ConfirmAction
                        href={route('deals.isin-payments.destroy', [dealId, p.id])}
                        method="delete"
                        title={`Remove the ${p.kind_label.toLowerCase()} due ${formatDate(p.due_on)}?`}
                        confirmLabel="Remove"
                        destructive
                        trigger={
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-8"
                                aria-label="Remove due date"
                            >
                                <XIcon />
                            </Button>
                        }
                    />
                </div>
            )}
            {dialog === 'record' && (
                <RecordDialog dealId={dealId} payment={p} options={options} onClose={close} />
            )}
            {dialog === 'move' && <MoveDialog dealId={dealId} payment={p} onClose={close} />}
        </li>
    );
}

/** By default: overdue dates and the next few; the full schedule on request. */
function Schedule({ dealId, isin, options, canManage }) {
    const [all, setAll] = useState(false);
    const upcoming = isin.payments.filter((p) => p.status === 'due');
    const shown = all
        ? isin.payments
        : [...upcoming.filter((p) => p.overdue), ...upcoming.filter((p) => !p.overdue).slice(0, 6)];

    if (isin.payments.length === 0) {
        return <p className="px-4 pb-4 text-[13px] text-muted-foreground">No due dates yet.</p>;
    }
    return (
        <>
            {shown.length === 0 ? (
                <p className="border-t px-4 py-3 text-[13px] text-muted-foreground">
                    Nothing due: every payment is settled.
                </p>
            ) : (
                <ul className="divide-y border-t">
                    {shown.map((p) => (
                        <PaymentRow
                            key={p.id}
                            dealId={dealId}
                            payment={p}
                            options={options}
                            canManage={canManage}
                        />
                    ))}
                </ul>
            )}
            {isin.payments.length > shown.length || all ? (
                <div className="border-t px-4 py-2">
                    <Button size="sm" variant="ghost" onClick={() => setAll(!all)}>
                        {all
                            ? 'Show only what is due'
                            : `Show the full schedule (${isin.payments.length})`}
                    </Button>
                </div>
            ) : null}
        </>
    );
}

function IsinCard({ dealId, isin, options, can, onEdit }) {
    const [dialog, setDialog] = useState(null);
    const close = () => setDialog(null);

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                <div className="flex min-w-0 flex-col gap-1.5">
                    <CardTitle className="flex flex-wrap items-center gap-2">
                        <span className="font-mono">{isin.isin}</span>
                        {isin.redeemed && <Badge variant="neutral">Redeemed</Badge>}
                        {isin.overdue > 0 && <Badge variant="danger">{isin.overdue} overdue</Badge>}
                    </CardTitle>
                    {isin.series_name && (
                        <CardDescription className="line-clamp-2 [overflow-wrap:anywhere]">
                            {isin.series_name}
                        </CardDescription>
                    )}
                    <span className="text-xs text-muted-foreground">
                        {isin.allotment_date && `Allotted ${formatDate(isin.allotment_date)}`}
                        {isin.maturity_date && ` · matures ${formatDate(isin.maturity_date)}`}
                        {isin.allotted_quantity > 0 &&
                            ` · ${isin.allotted_quantity.toLocaleString('en-IN')} debentures, ${formatMoney(isin.allotted_amount)}`}
                        {isin.next_due &&
                            ` · next ${isin.next_due.kind.toLowerCase()} ${formatDate(isin.next_due.due_on)}`}
                    </span>
                </div>
                {can.manageIsin && (
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={() => setDialog('schedule')}>
                            <CalendarPlusIcon /> Add due dates
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setDialog('allot')}>
                            <PlusIcon /> Allotment
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => onEdit(isin)}>
                            <PencilIcon /> Edit
                        </Button>
                    </div>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-0 px-0">
                {Object.keys(isin.facts).length > 0 && (
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-2 px-4 pb-4 sm:grid-cols-3 lg:grid-cols-5">
                        {Object.entries(isin.facts).map(([label, value]) => (
                            <div key={label} className="flex min-w-0 flex-col">
                                <dt className="text-xs text-muted-foreground">{label}</dt>
                                <dd className="text-[13px] [overflow-wrap:anywhere]">{value}</dd>
                            </div>
                        ))}
                    </dl>
                )}
                {isin.allotments.length > 0 && (
                    <ul className="flex flex-col gap-1 border-t px-4 py-3">
                        {isin.allotments.map((a) => (
                            <li
                                key={a.id}
                                className="flex flex-col gap-0.5 text-xs text-muted-foreground"
                            >
                                <span>
                                    <span className="font-medium text-foreground">
                                        {a.kind_label}
                                    </span>{' '}
                                    {formatDate(a.allotment_date)} ·{' '}
                                    {Number(a.quantity_allotted).toLocaleString('en-IN')} ×{' '}
                                    {formatMoney(a.face_value)} = {formatMoney(a.amount)}
                                    {a.credit && ` · credited ${a.credit}`}
                                </span>
                                {a.files.map((f) => (
                                    <FileLink key={f.id} dealId={dealId} file={f} />
                                ))}
                            </li>
                        ))}
                    </ul>
                )}
                <Schedule
                    dealId={dealId}
                    isin={isin}
                    options={options}
                    canManage={can.manageIsin}
                />
            </CardContent>
            {dialog === 'allot' && (
                <AllotmentDialog dealId={dealId} isin={isin} options={options} onClose={close} />
            )}
            {dialog === 'schedule' && (
                <ScheduleDialog dealId={dealId} isin={isin} options={options} onClose={close} />
            )}
        </Card>
    );
}

/** The deal's ISINs, their allotments and their interest and principal schedules. */
export function IsinPanel({ dealId, isin, can }) {
    const [sheet, setSheet] = useState({ open: false, isin: null, key: 0 });
    const open = (i = null) => setSheet((v) => ({ open: true, isin: i, key: v.key + 1 }));
    const close = () => setSheet((v) => ({ ...v, open: false }));
    const overdue = isin.isins.reduce((n, i) => n + i.overdue, 0);

    return (
        <div className="flex flex-col gap-4">
            <Card className="gap-0">
                <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1.5">
                        <CardTitle>ISINs</CardTitle>
                        <CardDescription>
                            {isin.isins.length > 0
                                ? `${isin.isins.length} ${isin.isins.length === 1 ? 'ISIN' : 'ISINs'}${overdue ? ` · ${overdue} payments overdue` : ''}. The team is emailed about payments due within ${isin.reminder_days} days or overdue up to ${isin.reminder_overdue_days} days.`
                                : 'Debentures issued under this deal and their payment schedules.'}
                        </CardDescription>
                    </div>
                    {can.manageIsin && (
                        <div className="flex flex-wrap gap-2">
                            <Button size="sm" onClick={() => open()}>
                                <PlusIcon /> Add ISIN
                            </Button>
                            {isin.isins.length > 0 && (
                                <ConfirmAction
                                    href={route('deals.isins.remind', dealId)}
                                    title="Email the deal team now?"
                                    description={`About payments due in the next ${isin.reminder_days} days or overdue up to ${isin.reminder_overdue_days} days, unless they were already reminded today.`}
                                    confirmLabel="Send reminder"
                                    trigger={
                                        <Button size="sm" variant="outline">
                                            <BellIcon /> Send reminder
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    )}
                </CardHeader>
                {isin.isins.length === 0 && (
                    <CardContent>
                        <Empty className="border">
                            <EmptyHeader>
                                <EmptyTitle>No ISINs yet</EmptyTitle>
                                <EmptyDescription>
                                    Add each series once it&apos;s allotted.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </CardContent>
                )}
            </Card>
            {isin.isins.map((i) => (
                <IsinCard
                    key={i.id}
                    dealId={dealId}
                    isin={i}
                    options={isin.options}
                    can={can}
                    onEdit={open}
                />
            ))}
            <Sheet open={sheet.open} onOpenChange={(o) => !o && close()}>
                <SheetContent className="flex w-full flex-col gap-0 sm:max-w-lg">
                    {sheet.open && (
                        <IsinForm
                            key={sheet.key}
                            dealId={dealId}
                            isin={sheet.isin}
                            options={isin.options}
                            onDone={close}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}
