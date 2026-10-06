import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldError, FieldLabel } from '@/Components/ui/field';
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
import { Textarea } from '@/Components/ui/textarea';
import { formatDate, formatMoney } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { PlusIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

/** Rupees with up to 2 decimals, as the server accepts. */
export const MONEY = /^\d{1,16}(\.\d{1,2})?$/;

/** Sums decimal strings in paise, so the preview never shows float noise. */
export function sumMoney(values) {
    const paise = values.reduce((total, v) => {
        if (!MONEY.test(String(v ?? ''))) return total;
        const [whole, frac = ''] = String(v).split('.');
        return total + Number(whole) * 100 + Number(frac.padEnd(2, '0'));
    }, 0);
    return (paise / 100).toFixed(2);
}

function toggle(list, id) {
    return list.includes(id) ? list.filter((v) => v !== id) : [...list, id];
}

function Pick({ checked, onChange, title, detail, amount, id }) {
    return (
        <label
            htmlFor={id}
            className="flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 has-data-checked:border-primary has-data-checked:bg-accent/40"
        >
            <Checkbox id={id} checked={checked} onCheckedChange={onChange} className="mt-0.5" />
            <span className="min-w-0 flex-1">
                <span className="block text-[13px] font-medium">{title}</span>
                {detail && <span className="block text-xs text-muted-foreground">{detail}</span>}
            </span>
            <span className="text-[13px] whitespace-nowrap tabular-nums">
                {formatMoney(amount)}
            </span>
        </label>
    );
}

/**
 * Drafts (or revises) a proforma or reimbursement bill: the fee periods and expenses to bill, plus
 * other fees typed in. Only unbilled periods and expenses are offered. GST is worked out by the
 * server; the preview shows the amount before tax.
 */
export function DraftInvoiceSheet({
    open,
    onOpenChange,
    kind = 'proforma',
    dealId,
    invoiceId,
    periods = [],
    expenses = [],
    initial = {},
}) {
    const reimbursement = kind === 'reimbursement';
    const revising = Boolean(invoiceId);
    const form = useForm({
        ...(revising ? {} : { kind }),
        periods: initial.periods ?? [],
        expenses: initial.expenses ?? [],
        others: initial.others ?? [],
        notes: initial.notes ?? '',
    });
    const [problem, setProblem] = useState(null);

    const subtotal = sumMoney([
        ...periods.filter((p) => form.data.periods.includes(p.id)).map((p) => p.amount),
        ...expenses.filter((e) => form.data.expenses.includes(e.id)).map((e) => e.amount),
        ...form.data.others.map((o) => o.amount),
    ]);

    const setOther = (index, key, value) =>
        form.setData(
            'others',
            form.data.others.map((o, i) => (i === index ? { ...o, [key]: value } : o)),
        );

    const submit = (e) => {
        e.preventDefault();
        const empty =
            !form.data.periods.length && !form.data.expenses.length && !form.data.others.length;
        const badOther = form.data.others.find(
            (o) =>
                !o.description?.trim() ||
                !MONEY.test(String(o.amount ?? '').trim()) ||
                Number(o.amount) <= 0,
        );
        const issue = empty
            ? reimbursement
                ? 'Pick at least one expense.'
                : 'Pick at least one fee period, expense or other fee.'
            : badOther
              ? 'Each other fee needs a description and an amount above zero (up to 2 decimals).'
              : null;
        setProblem(issue);
        if (issue) return;

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };
        if (revising) form.put(route('invoices.update', invoiceId), options);
        else form.post(route('deals.invoices.store', dealId), options);
    };

    const errors = Object.entries(form.errors);
    const serverError = errors.length ? errors[0][1] : null;

    return (
        <Sheet open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <SheetContent className="w-full sm:max-w-xl">
                <form onSubmit={submit} noValidate className="flex h-full min-h-0 flex-col">
                    <SheetHeader>
                        <SheetTitle>
                            {revising
                                ? 'Change the draft'
                                : reimbursement
                                  ? 'Draft a reimbursement bill'
                                  : 'Draft a proforma invoice'}
                        </SheetTitle>
                        <SheetDescription>
                            {reimbursement
                                ? 'Out-of-pocket expenses, billed back without GST.'
                                : 'GST is added when the proforma is issued. Someone other than you issues it.'}
                        </SheetDescription>
                    </SheetHeader>
                    <ScrollArea className="min-h-0 flex-1 px-4">
                        <div className="flex flex-col gap-5 pb-4">
                            {!reimbursement && (
                                <section className="flex flex-col gap-2">
                                    <h3 className="text-[13px] font-medium">Fee periods</h3>
                                    {periods.length === 0 && (
                                        <p className="text-[13px] text-muted-foreground">
                                            No unbilled fee periods.
                                        </p>
                                    )}
                                    {periods.map((p) => (
                                        <Pick
                                            key={p.id}
                                            id={`period-${p.id}`}
                                            checked={form.data.periods.includes(p.id)}
                                            onChange={() =>
                                                form.setData(
                                                    'periods',
                                                    toggle(form.data.periods, p.id),
                                                )
                                            }
                                            title={p.fee}
                                            detail={`${formatDate(p.from)} – ${formatDate(p.to)} · bill date ${formatDate(p.bill_date)}`}
                                            amount={p.amount}
                                        />
                                    ))}
                                </section>
                            )}

                            <section className="flex flex-col gap-2">
                                <h3 className="text-[13px] font-medium">
                                    Out-of-pocket expenses{' '}
                                    {!reimbursement && (
                                        <span className="font-normal text-muted-foreground">
                                            (no GST)
                                        </span>
                                    )}
                                </h3>
                                {expenses.length === 0 && (
                                    <p className="text-[13px] text-muted-foreground">
                                        No unbilled expenses.
                                    </p>
                                )}
                                {expenses.map((x) => (
                                    <Pick
                                        key={x.id}
                                        id={`expense-${x.id}`}
                                        checked={form.data.expenses.includes(x.id)}
                                        onChange={() =>
                                            form.setData(
                                                'expenses',
                                                toggle(form.data.expenses, x.id),
                                            )
                                        }
                                        title={x.description}
                                        detail={x.incurred_on ? formatDate(x.incurred_on) : null}
                                        amount={x.amount}
                                    />
                                ))}
                            </section>

                            {!reimbursement && (
                                <section className="flex flex-col gap-2">
                                    <h3 className="text-[13px] font-medium">Other fees</h3>
                                    {form.data.others.map((o, i) => (
                                        <div key={i} className="flex items-start gap-2">
                                            <Input
                                                value={o.description}
                                                onChange={(e) =>
                                                    setOther(i, 'description', e.target.value)
                                                }
                                                placeholder="Description"
                                                maxLength={255}
                                                aria-label={`Other fee ${i + 1} description`}
                                                className="min-w-0 flex-1"
                                            />
                                            <Input
                                                value={o.amount}
                                                onChange={(e) =>
                                                    setOther(i, 'amount', e.target.value)
                                                }
                                                placeholder="Amount"
                                                inputMode="decimal"
                                                aria-label={`Other fee ${i + 1} amount`}
                                                className="w-32"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Remove this fee"
                                                onClick={() =>
                                                    form.setData(
                                                        'others',
                                                        form.data.others.filter((_, j) => j !== i),
                                                    )
                                                }
                                            >
                                                <XIcon />
                                            </Button>
                                        </div>
                                    ))}
                                    {form.data.others.length < 10 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="self-start"
                                            onClick={() =>
                                                form.setData('others', [
                                                    ...form.data.others,
                                                    { description: '', amount: '' },
                                                ])
                                            }
                                        >
                                            <PlusIcon /> Add a fee
                                        </Button>
                                    )}
                                </section>
                            )}

                            <Field>
                                <FieldLabel htmlFor="draft-notes">Notes on the invoice</FieldLabel>
                                <Textarea
                                    id="draft-notes"
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                    rows={2}
                                    maxLength={2000}
                                />
                            </Field>
                            <FieldError>{problem ?? serverError}</FieldError>
                        </div>
                    </ScrollArea>
                    <SheetFooter className="flex-row items-center justify-between border-t">
                        <span className="text-[13px] text-muted-foreground">
                            Before GST{' '}
                            <span className="font-medium text-foreground tabular-nums">
                                {formatMoney(subtotal)}
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
