import { DraftInvoiceSheet, MONEY, sumMoney } from '@/Components/billing/draft-invoice-sheet';
import { ConfirmAction } from '@/Components/confirm-action';
import { ReasonDialog } from '@/Components/deals/document-files';
import { TextField } from '@/Components/form-fields';
import { PageHeader } from '@/Components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
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
import { FieldError } from '@/Components/ui/field';
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
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import {
    ArrowRightLeftIcon,
    BanIcon,
    CheckIcon,
    DownloadIcon,
    MailIcon,
    MinusCircleIcon,
    PencilIcon,
    PlusIcon,
    Trash2Icon,
    Undo2Icon,
} from 'lucide-react';
import { useState } from 'react';

const dash = <span className="text-subtle-foreground">—</span>;
const pct = (v) => `${String(v).replace(/\.?0+$/, '')}%`;

function Detail({ label, children, mono = false }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={`mt-0.5 text-[13px] [overflow-wrap:anywhere] ${mono ? 'font-mono' : ''}`}
            >
                {children || dash}
            </dd>
        </div>
    );
}

function ReceiptDialog({ invoice, today, open, onOpenChange }) {
    const form = useForm({ received_on: today, amount: '', tds_amount: '', utr: '', remark: '' });
    const [problem, setProblem] = useState({});

    const submit = (e) => {
        e.preventDefault();
        const issues = {};
        const amount = String(form.data.amount).trim();
        const tds = String(form.data.tds_amount).trim();
        if (!form.data.received_on) issues.received_on = 'Enter the date received.';
        else if (form.data.received_on > today)
            issues.received_on = "The date received can't be in the future.";
        else if (form.data.received_on < invoice.invoice_date)
            issues.received_on = "The date received can't be before the invoice date.";
        if (!MONEY.test(amount)) issues.amount = 'Enter the amount received (0 if only TDS).';
        if (tds && !MONEY.test(tds)) issues.tds_amount = 'Enter the TDS with up to 2 decimals.';
        if (!issues.amount && !issues.tds_amount) {
            const settled = Number(sumMoney([amount, tds || '0']));
            if (settled <= 0) issues.amount = 'Enter the amount received or the TDS deducted.';
            else if (settled > Number(invoice.balance_due))
                issues.amount = `Only ${formatMoney(invoice.balance_due)} is outstanding.`;
        }
        if (form.data.utr && !/^[A-Za-z0-9\-/]+$/.test(form.data.utr.trim()))
            issues.utr = 'Letters, digits, - and / only.';
        setProblem(issues);
        if (Object.keys(issues).length) return;
        form.post(route('invoices.receipts.store', invoice.id), {
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
                        <DialogTitle>Record a receipt</DialogTitle>
                        <DialogDescription>
                            {formatMoney(invoice.balance_due)} is outstanding. TDS the client
                            deducted counts towards settling the invoice.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="received_on"
                            label="Date received"
                            type="date"
                            required
                            min={invoice.invoice_date}
                            max={today}
                            error={problem.received_on}
                        />
                        <TextField
                            form={form}
                            name="utr"
                            label="UTR / reference"
                            maxLength={40}
                            className="font-mono"
                            error={problem.utr}
                        />
                        <TextField
                            form={form}
                            name="amount"
                            label="Amount received (₹)"
                            required
                            inputMode="decimal"
                            error={problem.amount}
                        />
                        <TextField
                            form={form}
                            name="tds_amount"
                            label="TDS deducted (₹)"
                            inputMode="decimal"
                            error={problem.tds_amount}
                        />
                    </div>
                    <TextField
                        form={form}
                        name="remark"
                        label="Remark"
                        multiline
                        rows={2}
                        maxLength={2000}
                    />
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
                            {form.processing ? 'Saving…' : 'Record'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CreditNoteSheet({ invoice, open, onOpenChange }) {
    const form = useForm({ amounts: {}, reason: '' });
    const [problem, setProblem] = useState(null);
    const creditable = invoice.lines.filter((l) => Number(l.creditable) > 0);
    const total = sumMoney(Object.values(form.data.amounts));

    const submit = (e) => {
        e.preventDefault();
        const entered = Object.entries(form.data.amounts).filter(
            ([, v]) => String(v ?? '').trim() !== '',
        );
        const bad = entered.find(([id, v]) => {
            const line = invoice.lines.find((l) => String(l.id) === id);
            return !MONEY.test(String(v).trim()) || Number(v) > Number(line?.creditable ?? 0);
        });
        const issue = !entered.some(([, v]) => Number(v) > 0)
            ? 'Enter the amount to credit on at least one line.'
            : bad
              ? "Each amount needs up to 2 decimals and can't be more than is left on its line."
              : form.data.reason.trim().length < 5
                ? 'Give the reason (at least 5 characters).'
                : null;
        setProblem(issue);
        if (issue) return;
        form.post(route('invoices.credit-notes.store', invoice.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const serverError = Object.values(form.errors)[0];

    return (
        <Sheet open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <SheetContent className="w-full sm:max-w-xl">
                <form onSubmit={submit} noValidate className="flex h-full min-h-0 flex-col">
                    <SheetHeader>
                        <SheetTitle>Draft a credit note</SheetTitle>
                        <SheetDescription>
                            Reduces {invoice.number}. GST is reversed at the invoice&apos;s own
                            rates. Someone other than you issues it.
                        </SheetDescription>
                    </SheetHeader>
                    <ScrollArea className="min-h-0 flex-1 px-4">
                        <div className="flex flex-col gap-3 pb-4">
                            {creditable.map((l) => (
                                <div
                                    key={l.id}
                                    className="flex items-center gap-3 rounded-md border px-3 py-2"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="text-[13px] font-medium">
                                            {l.description}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Up to {formatMoney(l.creditable)} of{' '}
                                            {formatMoney(l.amount)}
                                            {l.taxable ? '' : ' · no GST'}
                                        </div>
                                    </div>
                                    <Input
                                        value={form.data.amounts[l.id] ?? ''}
                                        onChange={(e) =>
                                            form.setData('amounts', {
                                                ...form.data.amounts,
                                                [l.id]: e.target.value,
                                            })
                                        }
                                        inputMode="decimal"
                                        placeholder="0.00"
                                        aria-label={`Credit on ${l.description}`}
                                        className="w-32"
                                    />
                                </div>
                            ))}
                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="credit-reason" className="text-[13px] font-medium">
                                    Reason <span className="text-destructive">*</span>
                                </label>
                                <Textarea
                                    id="credit-reason"
                                    value={form.data.reason}
                                    onChange={(e) => form.setData('reason', e.target.value)}
                                    rows={3}
                                    maxLength={2000}
                                />
                            </div>
                            <FieldError>{problem ?? serverError}</FieldError>
                        </div>
                    </ScrollArea>
                    <SheetFooter className="flex-row items-center justify-between border-t">
                        <span className="text-[13px] text-muted-foreground">
                            Before GST{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {formatMoney(total)}
                            </span>
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save draft'}
                            </Button>
                        </div>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

export default function InvoiceShow({
    invoice,
    history,
    can,
    revise,
    cancelBlockedBecause,
    today,
}) {
    const [dialog, setDialog] = useState(null);
    const close = (o) => !o && setDialog(null);
    const isDraft = invoice.status === 'draft';
    const title = invoice.title;

    return (
        <AppLayout
            title={title}
            breadcrumbs={[
                { title: 'Invoices', href: route('invoices.index') },
                { title: invoice.number ?? 'Draft' },
            ]}
        >
            <PageHeader
                title={title}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('deals.show', {
                                transaction: invoice.deal.id,
                                tab: 'invoices',
                            })}
                            className="hover:text-brand-text"
                        >
                            {invoice.deal.company} ·{' '}
                            <span className="font-mono">{invoice.deal.el_number}</span>
                        </Link>
                        <Badge variant={invoice.status_tone}>{invoice.status_label}</Badge>
                        {invoice.overdue && <Badge variant="danger">Overdue</Badge>}
                    </span>
                }
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a
                                href={route('invoices.pdf', invoice.id)}
                                target={isDraft ? '_blank' : undefined}
                                rel="noreferrer"
                            >
                                <DownloadIcon /> {isDraft ? 'Preview PDF' : 'PDF'}
                            </a>
                        </Button>
                        {can.resend && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        <MailIcon /> Email
                                    </Button>
                                }
                                title="Email it to the client again?"
                                description="It goes to the deal's billing contacts, with the PDF."
                                confirmLabel="Send"
                                href={route('invoices.resend', invoice.id)}
                            />
                        )}
                        {can.revise && (
                            <Button variant="outline" onClick={() => setDialog('revise')}>
                                <PencilIcon /> Change
                            </Button>
                        )}
                        {can.discard && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        <Trash2Icon /> Discard
                                    </Button>
                                }
                                title="Discard this draft?"
                                description="Whatever it bills can be billed again."
                                confirmLabel="Discard"
                                destructive
                                method="delete"
                                href={route('invoices.destroy', invoice.id)}
                            />
                        )}
                        {can.sendBack && (
                            <Button variant="outline" onClick={() => setDialog('send-back')}>
                                <Undo2Icon /> Send back
                            </Button>
                        )}
                        {can.issue && (
                            <ConfirmAction
                                trigger={
                                    <Button>
                                        <CheckIcon /> Issue
                                    </Button>
                                }
                                title={`Issue this ${invoice.kind_label.toLowerCase()}?`}
                                description="It gets its number and today's date, GST is worked out at today's rate, and the client is emailed. Issued invoices can't be changed."
                                confirmLabel="Issue"
                                href={route('invoices.issue', invoice.id)}
                            />
                        )}
                        {can.convert && (
                            <ConfirmAction
                                trigger={
                                    <Button>
                                        <ArrowRightLeftIcon /> Tax invoice
                                    </Button>
                                }
                                title="Issue the tax invoice for this proforma?"
                                description="Same lines and client, GST at today's rate, registered for an IRN if the client has a GSTIN, and emailed. Payments stay recorded on this proforma."
                                confirmLabel="Issue tax invoice"
                                href={route('invoices.convert', invoice.id)}
                            />
                        )}
                        {can.creditNote && (
                            <Button variant="outline" onClick={() => setDialog('credit')}>
                                <MinusCircleIcon /> Credit note
                            </Button>
                        )}
                        {can.receipt && (
                            <Button onClick={() => setDialog('receipt')}>
                                <PlusIcon /> Receipt
                            </Button>
                        )}
                        {can.cancel && (
                            <Button variant="outline" onClick={() => setDialog('cancel')}>
                                <BanIcon /> Cancel
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="flex flex-col gap-4">
                {invoice.returned && (
                    <Alert>
                        <AlertTitle>Sent back by {invoice.returned.by}</AlertTitle>
                        <AlertDescription>{invoice.returned.reason}</AlertDescription>
                    </Alert>
                )}
                {invoice.cancelled && (
                    <Alert>
                        <AlertTitle>
                            Cancelled by {invoice.cancelled.by} on{' '}
                            {formatDateTime(invoice.cancelled.at)}
                        </AlertTitle>
                        <AlertDescription>{invoice.cancelled.reason}</AlertDescription>
                    </Alert>
                )}
                {cancelBlockedBecause && invoice.status === 'issued' && !can.cancel && (
                    <p className="text-xs text-muted-foreground">
                        Can&apos;t be cancelled: {cancelBlockedBecause}
                    </p>
                )}

                <Card>
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Detail label="Number" mono>
                                {invoice.number}
                            </Detail>
                            <Detail label="Date">
                                {invoice.invoice_date && formatDate(invoice.invoice_date)}
                            </Detail>
                            <Detail label="Billed to">{invoice.billed_name}</Detail>
                            <Detail label="GSTIN" mono>
                                {invoice.billed_gstin ??
                                    (invoice.gst_applies ? 'Unregistered' : null)}
                            </Detail>
                            <Detail label="Address">{invoice.billed_address}</Detail>
                            <Detail label="Place of supply">{invoice.place_of_supply}</Detail>
                            <Detail label="Period">
                                {invoice.period_from &&
                                    `${formatDate(invoice.period_from)} – ${formatDate(invoice.period_to)}`}
                            </Detail>
                            {invoice.parent && (
                                <Detail label={invoice.kind === 'credit_note' ? 'Against' : 'From'}>
                                    <Link
                                        href={route('invoices.show', invoice.parent.id)}
                                        className="text-brand-text hover:underline"
                                    >
                                        {invoice.parent.title}
                                    </Link>
                                </Detail>
                            )}
                            <Detail label="Drafted by">
                                {invoice.created_by} · {formatDateTime(invoice.created_at)}
                            </Detail>
                            <Detail label="Issued by">
                                {invoice.issued_by &&
                                    `${invoice.issued_by} · ${formatDateTime(invoice.issued_at)}`}
                            </Detail>
                            {invoice.kind === 'tax' && invoice.parent && (
                                <Detail label="Payments">Recorded on the proforma</Detail>
                            )}
                            {invoice.balance_due !== null && (
                                <Detail label="Outstanding">
                                    <span className="font-semibold tabular-nums">
                                        {formatMoney(invoice.balance_due)}
                                    </span>
                                </Detail>
                            )}
                            {invoice.notes && (
                                <Detail label={invoice.kind === 'credit_note' ? 'Reason' : 'Notes'}>
                                    {invoice.notes}
                                </Detail>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b py-4">
                        <CardTitle>Lines</CardTitle>
                        {invoice.gst_applies && invoice.sac && (
                            <CardDescription>SAC {invoice.sac}</CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Description</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoice.lines.map((l) => (
                                    <TableRow key={l.id}>
                                        <TableCell className="whitespace-normal">
                                            {l.description}
                                            {l.period_from && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatDate(l.period_from)}
                                                    {l.period_to !== l.period_from &&
                                                        ` – ${formatDate(l.period_to)}`}
                                                </span>
                                            )}
                                            {!l.taxable && invoice.gst_applies && (
                                                <span className="block text-xs text-muted-foreground">
                                                    No GST (reimbursement)
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(l.amount)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                {invoice.gst_applies && (
                                    <>
                                        <TableRow>
                                            <TableCell>Taxable value</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(invoice.taxable_amount)}
                                            </TableCell>
                                        </TableRow>
                                        {invoice.inter_state ? (
                                            <TableRow>
                                                <TableCell>
                                                    IGST @ {pct(invoice.igst_rate)}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(invoice.igst)}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            <>
                                                <TableRow>
                                                    <TableCell>
                                                        CGST @ {pct(invoice.cgst_rate)}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatMoney(invoice.cgst)}
                                                    </TableCell>
                                                </TableRow>
                                                <TableRow>
                                                    <TableCell>
                                                        SGST @ {pct(invoice.sgst_rate)}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatMoney(invoice.sgst)}
                                                    </TableCell>
                                                </TableRow>
                                            </>
                                        )}
                                        {Number(invoice.non_taxable_amount) > 0 && (
                                            <TableRow>
                                                <TableCell>Reimbursements (no GST)</TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(invoice.non_taxable_amount)}
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </>
                                )}
                                <TableRow>
                                    <TableCell className="font-medium">
                                        Total
                                        {isDraft && invoice.gst_applies
                                            ? ' (GST is worked out again when issued)'
                                            : ''}
                                    </TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">
                                        {formatMoney(invoice.total)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </CardContent>
                </Card>

                {invoice.irn && (
                    <Card>
                        <CardHeader>
                            <CardTitle>E-invoice</CardTitle>
                            {invoice.irn_cancelled_at && (
                                <CardDescription>
                                    IRN cancelled {formatDateTime(invoice.irn_cancelled_at)}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-[2fr_1fr_1fr]">
                                <Detail label="IRN" mono>
                                    {invoice.irn}
                                </Detail>
                                <Detail label="Ack no." mono>
                                    {invoice.ack_no}
                                </Detail>
                                <Detail label="Ack date">{formatDateTime(invoice.ack_at)}</Detail>
                            </dl>
                        </CardContent>
                    </Card>
                )}

                {invoice.children.length > 0 && (
                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b py-4">
                            <CardTitle>
                                {invoice.kind === 'tax' ? 'Credit notes' : 'Tax invoices'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableBody>
                                    {invoice.children.map((c) => (
                                        <TableRow key={c.id}>
                                            <TableCell className="whitespace-normal">
                                                <Link
                                                    href={route('invoices.show', c.id)}
                                                    className="text-brand-text hover:underline"
                                                >
                                                    {c.title}
                                                </Link>
                                                <span className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                    {c.invoice_date && formatDate(c.invoice_date)}
                                                    <Badge variant={c.status_tone}>
                                                        {c.status_label}
                                                    </Badge>
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right align-top tabular-nums">
                                                {formatMoney(c.total)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {(invoice.receipts.length > 0 || can.receipt) && invoice.balance_due !== null && (
                    <Card className="gap-0 py-0">
                        <CardHeader className="border-b py-4">
                            <CardTitle>Receipts</CardTitle>
                            <CardDescription>Money received and TDS deducted.</CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            {invoice.receipts.length === 0 ? (
                                <p className="px-6 py-6 text-[13px] text-muted-foreground">
                                    Nothing received yet.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Received</TableHead>
                                            <TableHead className="text-right">
                                                Amount · TDS
                                            </TableHead>
                                            <TableHead className="w-0" />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {invoice.receipts.map((r) => (
                                            <TableRow
                                                key={r.id}
                                                className={
                                                    r.reversed ? 'text-muted-foreground' : undefined
                                                }
                                            >
                                                <TableCell className="whitespace-normal">
                                                    {formatDate(r.received_on)}
                                                    {r.utr && (
                                                        <span className="block font-mono text-xs [overflow-wrap:anywhere]">
                                                            {r.utr}
                                                        </span>
                                                    )}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {r.recorded_by}
                                                        {r.remark ? ` · ${r.remark}` : ''}
                                                    </span>
                                                    {r.reversed && (
                                                        <span className="block text-xs">
                                                            <Badge variant="neutral">
                                                                Reversed
                                                            </Badge>{' '}
                                                            {r.reversed.reason} ({r.reversed.by})
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right align-top tabular-nums ${r.reversed ? 'line-through' : ''}`}
                                                >
                                                    {formatMoney(r.amount)}
                                                    {Number(r.tds_amount) > 0 && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            TDS {formatMoney(r.tds_amount)}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {can.reverseReceipt && !r.reversed && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setDialog({ reverse: r.id })
                                                            }
                                                        >
                                                            Reverse
                                                        </Button>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {(invoice.mails.length > 0 || history.length > 0) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ol className="flex flex-col gap-2 text-[13px]">
                                {[
                                    ...invoice.mails.map((m) => ({
                                        at: m.at,
                                        text: `Emailed to ${m.recipients}${m.sent_by ? ` by ${m.sent_by}` : ''}`,
                                    })),
                                    ...history.map((h) => ({
                                        at: h.at,
                                        text: `${h.text}${h.by ? ` by ${h.by}` : ''}`,
                                    })),
                                ]
                                    .sort((a, b) => (a.at < b.at ? 1 : a.at > b.at ? -1 : 0))
                                    .map((e, i) => (
                                        <li
                                            key={i}
                                            className="flex flex-col sm:flex-row sm:gap-x-3"
                                        >
                                            <span className="shrink-0 text-xs text-muted-foreground sm:w-36 sm:text-[13px]">
                                                {formatDateTime(e.at)}
                                            </span>
                                            <span className="[overflow-wrap:anywhere]">
                                                {e.text}
                                            </span>
                                        </li>
                                    ))}
                            </ol>
                        </CardContent>
                    </Card>
                )}
            </div>

            {dialog === 'revise' && revise && (
                <DraftInvoiceSheet
                    open
                    onOpenChange={close}
                    kind={invoice.kind}
                    invoiceId={invoice.id}
                    periods={revise.periods}
                    expenses={revise.expenses}
                    initial={revise.initial}
                />
            )}
            {dialog === 'credit' && <CreditNoteSheet invoice={invoice} open onOpenChange={close} />}
            {dialog === 'receipt' && (
                <ReceiptDialog invoice={invoice} today={today} open onOpenChange={close} />
            )}
            <ReasonDialog
                title="Send it back"
                description="It goes back to its maker as a draft, with your reason."
                label="Reason"
                field="reason"
                href={route('invoices.send-back', invoice.id)}
                submitLabel="Send back"
                open={dialog === 'send-back'}
                onOpenChange={close}
            />
            <ReasonDialog
                title={`Cancel ${invoice.number ?? 'this invoice'}?`}
                description={
                    invoice.kind === 'proforma' || invoice.kind === 'reimbursement'
                        ? 'What it billed can be billed again. The client is told it was cancelled.'
                        : 'Its IRN is cancelled too. The client is told it was cancelled.'
                }
                label="Reason"
                field="reason"
                href={route('invoices.cancel', invoice.id)}
                submitLabel="Cancel invoice"
                destructive
                open={dialog === 'cancel'}
                onOpenChange={close}
            />
            {dialog?.reverse && (
                <ReasonDialog
                    title="Reverse this receipt?"
                    description="The amount becomes outstanding again. The receipt stays on record, marked reversed."
                    label="Reason"
                    field="reason"
                    href={route('invoice-receipts.reverse', dialog.reverse)}
                    submitLabel="Reverse"
                    destructive
                    open
                    onOpenChange={close}
                />
            )}
        </AppLayout>
    );
}
