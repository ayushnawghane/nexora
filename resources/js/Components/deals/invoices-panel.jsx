import { DraftInvoiceSheet, MONEY } from '@/Components/billing/draft-invoice-sheet';
import { ConfirmAction } from '@/Components/confirm-action';
import { ACCEPT, FileLink, MAX_FILES, fileProblem } from '@/Components/deals/document-files';
import { TextField } from '@/Components/form-fields';
import { Alert, AlertDescription } from '@/Components/ui/alert';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDate, formatMoney } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { FilePlusIcon, PencilIcon, PlusIcon, ReceiptIcon, TrashIcon } from 'lucide-react';
import { useState } from 'react';

const dash = <span className="text-subtle-foreground">—</span>;

function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function BilledOn({ invoice }) {
    return (
        <Link
            href={route('invoices.show', invoice.id)}
            className="text-[13px] text-brand-text hover:underline"
        >
            {invoice.title}
        </Link>
    );
}

function ExpenseDialog({ dealId, expense, open, onOpenChange }) {
    const editing = Boolean(expense);
    const form = useForm({
        incurred_on: expense?.incurred_on ?? '',
        description: expense?.description ?? '',
        amount: expense?.amount ?? '',
        files: [],
    });
    const [problem, setProblem] = useState({});

    const submit = (e) => {
        e.preventDefault();
        const issues = {};
        if (!form.data.description.trim()) issues.description = 'Describe the expense.';
        if (!MONEY.test(String(form.data.amount).trim()) || Number(form.data.amount) <= 0)
            issues.amount = 'Enter an amount above zero, with up to 2 decimals.';
        if (form.data.incurred_on && form.data.incurred_on > today())
            issues.incurred_on = "The expense date can't be in the future.";
        setProblem(issues);
        if (Object.keys(issues).length) return;

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        };
        if (editing) form.post(route('deals.expenses.update', [dealId, expense.id]), options);
        else form.post(route('deals.expenses.store', dealId), options);
    };

    const fileError =
        problem.files ??
        form.errors.files ??
        Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1];

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit the expense' : 'Record an expense'}
                        </DialogTitle>
                        <DialogDescription>
                            Out-of-pocket costs Beacon paid for the deal (courier, stamp duty,
                            travel…), billed back without GST.
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        form={form}
                        name="description"
                        label="Description"
                        required
                        maxLength={255}
                        error={problem.description}
                        autoFocus
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="amount"
                            label="Amount (₹)"
                            required
                            inputMode="decimal"
                            error={problem.amount}
                        />
                        <TextField
                            form={form}
                            name="incurred_on"
                            label="Date"
                            type="date"
                            min="2000-01-01"
                            max={today()}
                            error={problem.incurred_on}
                        />
                    </div>
                    <Field data-invalid={!!fileError || undefined}>
                        <FieldLabel htmlFor="expense-files">
                            {editing ? 'Add proof' : 'Proof (bills, receipts)'}
                        </FieldLabel>
                        <Input
                            id="expense-files"
                            type="file"
                            multiple
                            accept={ACCEPT}
                            onChange={(e) => {
                                const chosen = Array.from(e.target.files ?? []);
                                const issue = chosen.length ? fileProblem(chosen) : null;
                                setProblem((p) => ({ ...p, files: issue }));
                                form.setData('files', issue ? [] : chosen);
                            }}
                            aria-invalid={!!fileError || undefined}
                        />
                        <FieldDescription>
                            PDF, Word, Excel or image, 20 MB each, up to {MAX_FILES}.
                        </FieldDescription>
                        <FieldError>{fileError}</FieldError>
                    </Field>
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
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** The deal's billing: its invoices, fee schedule (billed or not) and out-of-pocket expenses. */
export function InvoicesPanel({ dealId, invoices: data, can }) {
    const [draft, setDraft] = useState(null); // 'proforma' | 'reimbursement'
    const [expenseDialog, setExpenseDialog] = useState(null); // { expense } | {}

    if (!data) {
        return (
            <Empty className="border">
                <EmptyHeader>
                    <EmptyTitle>Invoices</EmptyTitle>
                    <EmptyDescription>You don&apos;t have access to billing.</EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    const billablePeriods = data.periods.filter((p) => p.billable);
    const unbilledExpenses = data.expenses.filter((x) => !x.invoice);
    const canDraft = can.raiseBilling && data.has_billing;

    return (
        <div className="flex flex-col gap-4">
            {!data.has_billing && (
                <Alert>
                    <AlertDescription>
                        Set who the deal is billed to (Contacts &amp; billing tab) before raising
                        invoices.
                    </AlertDescription>
                </Alert>
            )}

            <div className="flex flex-wrap items-end justify-between gap-3">
                <dl className="flex gap-6">
                    <div>
                        <dt className="text-xs text-muted-foreground">Outstanding</dt>
                        <dd className="text-lg font-semibold tabular-nums">
                            {formatMoney(data.balance_due)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Overdue invoices</dt>
                        <dd className="text-lg font-semibold tabular-nums">
                            {data.overdue > 0 ? (
                                <span className="text-destructive">{data.overdue}</span>
                            ) : (
                                0
                            )}
                        </dd>
                    </div>
                </dl>
                {can.raiseBilling && (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => setExpenseDialog({})}>
                            <PlusIcon /> Expense
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!canDraft || unbilledExpenses.length === 0}
                            onClick={() => setDraft('reimbursement')}
                        >
                            <ReceiptIcon /> Reimbursement bill
                        </Button>
                        <Button disabled={!canDraft} onClick={() => setDraft('proforma')}>
                            <FilePlusIcon /> Draft proforma
                        </Button>
                    </div>
                )}
            </div>

            <Card className="gap-0 py-0">
                <CardHeader className="border-b py-4">
                    <CardTitle>Invoices</CardTitle>
                    <CardDescription>
                        Proformas, tax invoices, credit notes and reimbursement bills.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    {data.invoices.length === 0 ? (
                        <p className="px-6 py-6 text-[13px] text-muted-foreground">
                            Nothing billed yet.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead className="hidden md:table-cell">Date</TableHead>
                                    <TableHead className="hidden md:table-cell">Status</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead className="hidden text-right md:table-cell">
                                        Outstanding
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.invoices.map((i) => (
                                    <TableRow key={i.id}>
                                        <TableCell>
                                            <Link
                                                href={route('invoices.show', i.id)}
                                                className="group block"
                                            >
                                                <span className="font-mono text-[13px] font-medium group-hover:text-brand-text">
                                                    {i.number ?? 'Draft'}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {i.kind_label}
                                                    {i.invoice_date && (
                                                        <span className="md:hidden">
                                                            {' '}
                                                            · {formatDate(i.invoice_date)}
                                                        </span>
                                                    )}
                                                </span>
                                            </Link>
                                            <span className="mt-1 flex flex-wrap gap-1 md:hidden">
                                                <Badge variant={i.status_tone}>
                                                    {i.status_label}
                                                </Badge>
                                                {i.returned && (
                                                    <Badge variant="warning">Sent back</Badge>
                                                )}
                                                {i.overdue && (
                                                    <Badge variant="danger">Overdue</Badge>
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground md:table-cell">
                                            {i.invoice_date ? formatDate(i.invoice_date) : dash}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            <span className="flex flex-wrap gap-1">
                                                <Badge variant={i.status_tone}>
                                                    {i.status_label}
                                                </Badge>
                                                {i.returned && (
                                                    <Badge variant="warning">Sent back</Badge>
                                                )}
                                                {i.overdue && (
                                                    <Badge variant="danger">Overdue</Badge>
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right align-top tabular-nums">
                                            {formatMoney(i.total)}
                                            {i.balance_due !== null && (
                                                <span className="block text-xs text-muted-foreground md:hidden">
                                                    {formatMoney(i.balance_due)} due
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden text-right tabular-nums md:table-cell">
                                            {i.balance_due === null
                                                ? dash
                                                : formatMoney(i.balance_due)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card className="gap-0 py-0">
                <CardHeader className="border-b py-4">
                    <CardTitle>Fee schedule</CardTitle>
                    <CardDescription>
                        Each period is billed once. Periods due within the billing window show as
                        ready to bill.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    {data.periods.length === 0 ? (
                        <p className="px-6 py-6 text-[13px] text-muted-foreground">
                            The deal has no fee schedule.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fee</TableHead>
                                    <TableHead className="hidden md:table-cell">Period</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Bill date
                                    </TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead className="hidden md:table-cell">Billed</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.periods.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="whitespace-normal">
                                            {p.fee}
                                            <span className="block text-xs text-muted-foreground md:hidden">
                                                {formatDate(p.from)} – {formatDate(p.to)} · bill{' '}
                                                {formatDate(p.bill_date)}
                                            </span>
                                            <span className="mt-1 block md:hidden">
                                                {p.invoice ? (
                                                    <BilledOn invoice={p.invoice} />
                                                ) : p.due ? (
                                                    <Badge variant="warning">Ready to bill</Badge>
                                                ) : (
                                                    <span className="text-[13px] text-muted-foreground">
                                                        Later
                                                    </span>
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden whitespace-nowrap md:table-cell">
                                            {formatDate(p.from)} – {formatDate(p.to)}
                                        </TableCell>
                                        <TableCell className="hidden whitespace-nowrap text-muted-foreground md:table-cell">
                                            {formatDate(p.bill_date)}
                                        </TableCell>
                                        <TableCell className="text-right align-top tabular-nums">
                                            {formatMoney(p.amount)}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {p.invoice ? (
                                                <BilledOn invoice={p.invoice} />
                                            ) : p.due ? (
                                                <Badge variant="warning">Ready to bill</Badge>
                                            ) : (
                                                <span className="text-[13px] text-muted-foreground">
                                                    Later
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            <Card className="gap-0 py-0">
                <CardHeader className="border-b py-4">
                    <CardTitle>Out-of-pocket expenses</CardTitle>
                    <CardDescription>
                        Billed back without GST, on a reimbursement bill or with a proforma.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    {data.expenses.length === 0 ? (
                        <p className="px-6 py-6 text-[13px] text-muted-foreground">
                            No expenses recorded.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Expense</TableHead>
                                    <TableHead className="text-right">Amount</TableHead>
                                    <TableHead className="hidden md:table-cell">Proof</TableHead>
                                    <TableHead className="hidden md:table-cell">Billed</TableHead>
                                    <TableHead className="w-0" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {data.expenses.map((x) => (
                                    <TableRow key={x.id}>
                                        <TableCell className="max-w-72 whitespace-normal md:max-w-72">
                                            <span className="block text-[13px]">
                                                {x.description}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {x.incurred_on
                                                    ? formatDate(x.incurred_on)
                                                    : 'No date'}{' '}
                                                · {x.recorded_by}
                                            </span>
                                            <span className="mt-1 flex flex-col gap-1 md:hidden">
                                                {x.files.map((f) => (
                                                    <FileLink key={f.id} dealId={dealId} file={f} />
                                                ))}
                                                {x.invoice && <BilledOn invoice={x.invoice} />}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right align-top tabular-nums">
                                            {formatMoney(x.amount)}
                                        </TableCell>
                                        <TableCell className="hidden max-w-56 md:table-cell">
                                            {x.files.length === 0
                                                ? dash
                                                : x.files.map((f) => (
                                                      <FileLink
                                                          key={f.id}
                                                          dealId={dealId}
                                                          file={f}
                                                      />
                                                  ))}
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {x.invoice ? <BilledOn invoice={x.invoice} /> : dash}
                                        </TableCell>
                                        <TableCell>
                                            {can.raiseBilling && !x.invoice && (
                                                <span className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Edit the expense"
                                                        onClick={() =>
                                                            setExpenseDialog({ expense: x })
                                                        }
                                                    >
                                                        <PencilIcon />
                                                    </Button>
                                                    <ConfirmAction
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Remove the expense"
                                                            >
                                                                <TrashIcon />
                                                            </Button>
                                                        }
                                                        title="Remove this expense?"
                                                        description={`${x.description}, ${formatMoney(x.amount)}. It won't be billed.`}
                                                        confirmLabel="Remove"
                                                        destructive
                                                        method="delete"
                                                        href={route('deals.expenses.destroy', [
                                                            dealId,
                                                            x.id,
                                                        ])}
                                                    />
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>

            {draft && (
                <DraftInvoiceSheet
                    open
                    onOpenChange={(o) => !o && setDraft(null)}
                    kind={draft}
                    dealId={dealId}
                    periods={billablePeriods}
                    expenses={unbilledExpenses}
                    initial={{
                        periods:
                            draft === 'proforma'
                                ? billablePeriods.filter((p) => p.due).map((p) => p.id)
                                : [],
                    }}
                />
            )}
            {expenseDialog && (
                <ExpenseDialog
                    key={expenseDialog.expense?.id ?? 'new'}
                    dealId={dealId}
                    expense={expenseDialog.expense}
                    open
                    onOpenChange={(o) => !o && setExpenseDialog(null)}
                />
            )}
        </div>
    );
}
