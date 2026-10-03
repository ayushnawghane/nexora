import { ConfirmAction } from '@/Components/confirm-action';
import { ComboField, SelectField, SwitchField, TextField } from '@/Components/form-fields';
import { PageHeader } from '@/Components/page-header';
import { ScheduleTable } from '@/Components/transactions/schedule-table';
import { TransactionSummary } from '@/Components/transactions/transaction-summary';
import { WizardSteps } from '@/Components/transactions/wizard-steps';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { FieldError, FieldGroup } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/Components/ui/toggle-group';
import AppLayout from '@/Layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { ArrowRightIcon, ExternalLinkIcon, ShieldCheckIcon } from 'lucide-react';

/* Money arrives and leaves as decimal strings; this only reads them for live totals. */
const toPaise = (v) => {
    const n = Number(String(v ?? '').replace(/[,\s]/g, ''));
    return Number.isFinite(n) ? Math.round(n * 100) : NaN;
};
const fromPaise = (p) => (Number.isFinite(p) ? (p / 100).toFixed(2) : '');

function StepCard({ title, description, children, footer }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>{children}</CardContent>
            {footer && <CardFooter className="justify-end gap-2 border-t">{footer}</CardFooter>}
        </Card>
    );
}

function SaveButton({ processing, label = 'Save and continue' }) {
    return (
        <Button type="submit" disabled={processing}>
            {processing ? 'Saving…' : label}
            {!processing && <ArrowRightIcon />}
        </Button>
    );
}

/* ---------------------------------------------------------------- 1. Basics */

function BasicsStep({ transaction, options }) {
    const form = useForm({
        company_id: transaction?.basics.company_id ?? null,
        vertical_team_id: transaction?.basics.vertical_team_id ?? null,
        relationship_manager_id: transaction?.basics.relationship_manager_id ?? null,
        signatory_id: transaction?.basics.signatory_id ?? null,
        transaction_type_id: transaction?.basics.transaction_type_id ?? null,
        lead_source_id: transaction?.basics.lead_source_id ?? null,
        arranger_id: transaction?.basics.arranger_id ?? null,
        origin: transaction?.basics.origin ?? 'bd',
        brief: transaction?.basics.brief ?? '',
    });
    const idOptions = (items) => items.map((i) => ({ value: i.id, label: i.name }));
    const companyChanged =
        transaction &&
        form.data.company_id !== transaction.basics.company_id &&
        transaction.contacts.length > 0;

    const submit = (e) => {
        e.preventDefault();
        if (transaction) form.put(route('transactions.basics', transaction.id));
        else form.post(route('transactions.store'));
    };

    return (
        <form onSubmit={submit} noValidate>
            <StepCard
                title="Basics"
                description="The client company and who at Beacon owns this deal."
                footer={
                    <SaveButton
                        processing={form.processing}
                        label={transaction ? 'Save and continue' : 'Create draft'}
                    />
                }
            >
                <FieldGroup className="grid gap-4 md:grid-cols-2">
                    <ComboField
                        form={form}
                        name="company_id"
                        label="Company"
                        required
                        items={options.companies}
                        placeholder="Search companies"
                        className="md:col-span-2"
                        hint={
                            companyChanged
                                ? 'Changing the company removes the contacts already chosen.'
                                : 'Not listed? Add it under Companies first.'
                        }
                    />
                    <SelectField
                        form={form}
                        name="vertical_team_id"
                        label="Vertical team"
                        required
                        numeric
                        items={idOptions(options.verticalTeams)}
                    />
                    <ComboField
                        form={form}
                        name="relationship_manager_id"
                        label="Relationship manager"
                        required
                        items={options.users}
                    />
                    <ComboField
                        form={form}
                        name="signatory_id"
                        label="Signatory"
                        items={options.signatories}
                        hint="Signs the engagement letter."
                    />
                    <SelectField
                        form={form}
                        name="origin"
                        label="Originated by"
                        required
                        items={options.origins}
                    />
                    <SelectField
                        form={form}
                        name="transaction_type_id"
                        label="Transaction type"
                        numeric
                        items={idOptions(options.transactionTypes)}
                    />
                    <SelectField
                        form={form}
                        name="lead_source_id"
                        label="Lead source"
                        numeric
                        items={idOptions(options.leadSources)}
                    />
                    <ComboField
                        form={form}
                        name="arranger_id"
                        label="Arranger"
                        items={options.arrangers}
                        className="md:col-span-2"
                    />
                    <TextField
                        form={form}
                        name="brief"
                        label="Brief"
                        multiline
                        rows={3}
                        maxLength={2000}
                        className="md:col-span-2"
                    />
                </FieldGroup>
            </StepCard>
        </form>
    );
}

/* -------------------------------------------------------------- 2. Contacts */

function ContactsStep({ transaction, options }) {
    const form = useForm({ contacts: transaction.contacts });
    const selected = new Map(form.data.contacts.map((c) => [c.company_contact_id, c.recipient]));

    const toggle = (id, on) =>
        form.setData(
            'contacts',
            on
                ? [
                      ...form.data.contacts,
                      { company_contact_id: id, recipient: selected.size === 0 ? 'to' : 'cc' },
                  ]
                : form.data.contacts.filter((c) => c.company_contact_id !== id),
        );
    const setRecipient = (id, recipient) =>
        recipient &&
        form.setData(
            'contacts',
            form.data.contacts.map((c) => (c.company_contact_id === id ? { ...c, recipient } : c)),
        );

    const submit = (e) => {
        e.preventDefault();
        form.put(route('transactions.contacts', transaction.id));
    };

    const manageLink = (
        <Button variant="outline" asChild>
            <a
                href={route('companies.show', transaction.company.id)}
                target="_blank"
                rel="noreferrer"
            >
                Manage contacts <ExternalLinkIcon />
            </a>
        </Button>
    );

    return (
        <form onSubmit={submit} noValidate>
            <StepCard
                title="Contacts"
                description={`People at ${transaction.company.name} who receive the engagement letter. At least one "To" needs an email.`}
                footer={
                    <>
                        {manageLink}
                        <SaveButton processing={form.processing} />
                    </>
                }
            >
                {options.contacts.length === 0 ? (
                    <Empty className="border border-dashed">
                        <EmptyHeader>
                            <EmptyTitle>This company has no contacts yet</EmptyTitle>
                            <EmptyDescription>
                                Add them on the company page, then come back and refresh.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="flex flex-col divide-y rounded-lg border">
                        {options.contacts.map((contact) => {
                            const recipient = selected.get(contact.id);
                            const id = `contact-${contact.id}`;
                            return (
                                <li
                                    key={contact.id}
                                    className="flex flex-wrap items-center gap-3 px-3 py-2.5"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={!!recipient}
                                        onCheckedChange={(on) => toggle(contact.id, on === true)}
                                    />
                                    <label htmlFor={id} className="min-w-0 flex-1 cursor-pointer">
                                        <div className="text-[13px] font-medium">
                                            {contact.name}
                                            {contact.type && (
                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                    {contact.type}
                                                </span>
                                            )}
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            {[
                                                contact.designation,
                                                contact.email ?? 'No email',
                                                contact.mobile,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </label>
                                    {recipient && (
                                        <ToggleGroup
                                            type="single"
                                            variant="outline"
                                            size="sm"
                                            value={recipient}
                                            onValueChange={(v) => setRecipient(contact.id, v)}
                                            aria-label={`Send to ${contact.name} as`}
                                        >
                                            {options.recipients.map((r) => (
                                                <ToggleGroupItem key={r.value} value={r.value}>
                                                    {r.label}
                                                </ToggleGroupItem>
                                            ))}
                                        </ToggleGroup>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
                <FieldError className="mt-2">
                    {form.errors.contacts ??
                        Object.entries(form.errors).find(([k]) => k.startsWith('contacts.'))?.[1]}
                </FieldError>
            </StepCard>
        </form>
    );
}

/* ----------------------------------------------------------------- 3. Issue */

function IssueStep({ transaction, options }) {
    const issue = transaction.issue;
    const form = useForm({
        listing: issue?.listing ?? 'unlisted',
        issue_type: issue?.issue_type ?? 'private_placement',
        is_secured: issue?.is_secured ?? true,
        is_rated: issue?.is_rated ?? false,
        base_issue_size: issue?.base_issue_size ?? '',
        green_shoe_size: issue?.green_shoe_size ?? '0',
        tenure_months: issue?.tenure_months ?? '',
        tenure_days: issue?.tenure_days ?? 0,
        instruments: options.instruments.map((i) => {
            const saved = issue?.instruments.find((s) => s.instrument === i.value);
            return {
                instrument: i.value,
                base_amount: saved?.base_amount ?? (!issue && i.value === 'ncd' ? '' : '0'),
                green_shoe_amount: saved?.green_shoe_amount ?? '0',
            };
        }),
    });

    const setRow = (index, key, value) =>
        form.setData(
            'instruments',
            form.data.instruments.map((row, i) => (i === index ? { ...row, [key]: value } : row)),
        );

    const sumBase = form.data.instruments.reduce((s, r) => s + toPaise(r.base_amount || 0), 0);
    const sumGreen = form.data.instruments.reduce(
        (s, r) => s + toPaise(r.green_shoe_amount || 0),
        0,
    );
    const base = toPaise(form.data.base_issue_size);
    const green = toPaise(form.data.green_shoe_size || 0);
    const baseOk = sumBase === base;
    const greenOk = sumGreen === green;

    const submit = (e) => {
        e.preventDefault();
        form.put(route('transactions.issue', transaction.id));
    };

    const amountProps = { inputMode: 'decimal', className: 'tabular-nums text-right' };

    return (
        <form onSubmit={submit} noValidate>
            <StepCard
                title="Issue details"
                description="The total issue size is base + green shoe. Split it across instruments; the split must add up exactly."
                footer={<SaveButton processing={form.processing} />}
            >
                <FieldGroup className="grid gap-4 md:grid-cols-2">
                    <SelectField
                        form={form}
                        name="listing"
                        label="Listing"
                        required
                        items={options.listings}
                    />
                    <SelectField
                        form={form}
                        name="issue_type"
                        label="Issue type"
                        required
                        items={options.issueTypes}
                    />
                    <TextField
                        form={form}
                        name="base_issue_size"
                        label="Base issue size (₹)"
                        required
                        {...amountProps}
                        hint={formatMoney(form.data.base_issue_size)}
                    />
                    <TextField
                        form={form}
                        name="green_shoe_size"
                        label="Green shoe (₹)"
                        {...amountProps}
                        hint={formatMoney(form.data.green_shoe_size)}
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <TextField
                            form={form}
                            name="tenure_months"
                            label="Tenure (months)"
                            required
                            inputMode="numeric"
                        />
                        <TextField
                            form={form}
                            name="tenure_days"
                            label="+ days"
                            inputMode="numeric"
                        />
                    </div>
                    <div className="flex flex-col justify-end gap-3 sm:flex-row sm:items-center sm:gap-6">
                        <SwitchField form={form} name="is_secured" label="Secured" />
                        <SwitchField form={form} name="is_rated" label="Rated" />
                    </div>
                    <div className="rounded-lg bg-surface-3 px-4 py-3 md:col-span-2">
                        <div className="text-xs text-muted-foreground">Total issue size</div>
                        <div className="text-stat font-semibold tracking-tight tabular-nums">
                            {Number.isFinite(base + green)
                                ? formatMoney(fromPaise(base + green))
                                : '—'}
                        </div>
                    </div>
                </FieldGroup>

                <div className="mt-6">
                    <div className="mb-2 text-[13px] font-medium">Split by instrument</div>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-[480px] text-sm">
                            <thead className="bg-surface-3 text-xs text-muted-foreground">
                                <tr>
                                    <th className="h-10 px-3 text-left font-medium">Instrument</th>
                                    <th className="h-10 px-3 text-right font-medium">Base (₹)</th>
                                    <th className="h-10 px-3 text-right font-medium">
                                        Green shoe (₹)
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {form.data.instruments.map((row, index) => (
                                    <tr key={row.instrument} className="border-t">
                                        <td className="px-3 py-2 text-[13px]">
                                            {
                                                options.instruments.find(
                                                    (i) => i.value === row.instrument,
                                                )?.label
                                            }
                                        </td>
                                        <td className="px-3 py-2">
                                            <Input
                                                aria-label={`${row.instrument} base amount`}
                                                value={row.base_amount}
                                                onChange={(e) =>
                                                    setRow(index, 'base_amount', e.target.value)
                                                }
                                                {...amountProps}
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <Input
                                                aria-label={`${row.instrument} green shoe amount`}
                                                value={row.green_shoe_amount}
                                                onChange={(e) =>
                                                    setRow(
                                                        index,
                                                        'green_shoe_amount',
                                                        e.target.value,
                                                    )
                                                }
                                                {...amountProps}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t bg-surface-3 text-[13px]">
                                <tr>
                                    <td className="px-3 py-2 font-medium">Total</td>
                                    <td
                                        className={`px-3 py-2 text-right tabular-nums ${baseOk ? '' : 'text-danger'}`}
                                    >
                                        {formatMoney(fromPaise(sumBase))}
                                    </td>
                                    <td
                                        className={`px-3 py-2 text-right tabular-nums ${greenOk ? '' : 'text-danger'}`}
                                    >
                                        {formatMoney(fromPaise(sumGreen))}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    {(!baseOk || !greenOk) && form.data.base_issue_size !== '' && (
                        <p className="mt-2 text-xs text-danger">
                            The split must equal the base issue size and the green shoe.
                        </p>
                    )}
                    <FieldError className="mt-2">{form.errors.instruments}</FieldError>
                </div>
            </StepCard>
        </form>
    );
}

/* ------------------------------------------------------------------ 4. Fees */

const emptyFee = (kind) => ({
    enabled: true,
    amount_type: 'fixed',
    amount: '',
    percent: '',
    basis: 'issue_size',
    frequency: kind === 'acceptance' ? 'one_time' : 'annual',
    start_reference: kind === 'acceptance' ? 'el_date' : 'dta_execution',
    start_date: '',
    timing: 'advance',
    escalation_type: 'none',
    escalation_value: '',
    escalation_every_years: '',
});

function FeeCard({ form, kind, options, totalIssueSize }) {
    const fee = form.data.fees[kind.value];
    const base = `fees.${kind.value}`;
    const bind = (key) => ({
        name: `${base}.${key}`,
        get: () => fee[key],
        set: (v) => form.setData('fees', { ...form.data.fees, [kind.value]: { ...fee, [key]: v } }),
    });
    const percentAmount =
        fee.amount_type === 'percent' && fee.percent !== ''
            ? fromPaise(Math.round((toPaise(totalIssueSize) * Number(fee.percent)) / 100))
            : null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>{kind.label}</CardTitle>
                <CardDescription>
                    {kind.value === 'acceptance'
                        ? 'Charged once.'
                        : 'Quoted per annum and billed in periods.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <SwitchField
                    form={form}
                    label={
                        kind.value === 'acceptance'
                            ? 'Charge an acceptance fee'
                            : 'Charge a service fee'
                    }
                    {...bind('enabled')}
                />
                {fee.enabled && (
                    <FieldGroup className="mt-4 grid gap-4 md:grid-cols-2">
                        <SelectField
                            form={form}
                            label="Amount type"
                            required
                            items={options.fee.amountTypes}
                            {...bind('amount_type')}
                        />
                        {fee.amount_type === 'percent' ? (
                            <TextField
                                form={form}
                                label="Percentage of issue size"
                                required
                                inputMode="decimal"
                                className="[&_input]:text-right"
                                hint={
                                    percentAmount
                                        ? `= ${formatMoney(percentAmount)}${kind.value === 'service' ? ' p.a.' : ''}`
                                        : undefined
                                }
                                {...bind('percent')}
                            />
                        ) : (
                            <TextField
                                form={form}
                                label={
                                    kind.value === 'service' ? 'Amount per annum (₹)' : 'Amount (₹)'
                                }
                                required
                                inputMode="decimal"
                                className="[&_input]:text-right"
                                hint={fee.amount ? formatMoney(fee.amount) : undefined}
                                {...bind('amount')}
                            />
                        )}
                        <SelectField
                            form={form}
                            label="Basis"
                            required
                            items={options.fee.bases}
                            {...bind('basis')}
                        />
                        <SelectField
                            form={form}
                            label="Frequency"
                            required
                            items={kind.frequencies}
                            {...bind('frequency')}
                        />
                        <SelectField
                            form={form}
                            label="Runs from"
                            required
                            items={options.fee.startReferences}
                            {...bind('start_reference')}
                        />
                        <TextField
                            form={form}
                            label="Start date"
                            required
                            type="date"
                            hint="The schedule starts on this date."
                            {...bind('start_date')}
                        />
                        <SelectField
                            form={form}
                            label="Payable"
                            required
                            items={options.fee.timings}
                            {...bind('timing')}
                        />
                        {kind.value === 'service' && (
                            <SelectField
                                form={form}
                                label="Escalation"
                                required
                                items={options.fee.escalationTypes}
                                {...bind('escalation_type')}
                            />
                        )}
                        {kind.value === 'service' && fee.escalation_type !== 'none' && (
                            <>
                                <TextField
                                    form={form}
                                    label={
                                        fee.escalation_type === 'percent'
                                            ? 'Increase (%)'
                                            : 'Increase (₹)'
                                    }
                                    required
                                    inputMode="decimal"
                                    {...bind('escalation_value')}
                                />
                                <TextField
                                    form={form}
                                    label="Every (years)"
                                    required
                                    inputMode="numeric"
                                    {...bind('escalation_every_years')}
                                />
                            </>
                        )}
                    </FieldGroup>
                )}
            </CardContent>
        </Card>
    );
}

function FeesStep({ transaction, options }) {
    const form = useForm({
        fees: Object.fromEntries(
            options.fee.kinds.map((k) => {
                const saved = transaction.fees[k.value];
                const blank = emptyFee(k.value);
                return [
                    k.value,
                    saved
                        ? {
                              ...blank,
                              ...saved,
                              percent: saved.percent ? String(Number(saved.percent)) : '',
                              escalation_value: saved.escalation_value ?? '',
                              escalation_every_years: saved.escalation_every_years ?? '',
                          }
                        : { ...blank, enabled: Object.keys(transaction.fees).length === 0 },
                ];
            }),
        ),
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('transactions.fees', transaction.id));
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-3">
            {transaction.schedule.verified_at && (
                <Alert>
                    <AlertTitle>Saving changes here clears the schedule verification</AlertTitle>
                    <AlertDescription>
                        The schedule is rebuilt and has to be checked again.
                    </AlertDescription>
                </Alert>
            )}
            {options.fee.kinds.map((kind) => (
                <FeeCard
                    key={kind.value}
                    form={form}
                    kind={kind}
                    options={options}
                    totalIssueSize={transaction.issue?.total_issue_size}
                />
            ))}
            <FieldError>{form.errors.fees}</FieldError>
            <div className="flex justify-end">
                <SaveButton processing={form.processing} label="Save and build schedule" />
            </div>
        </form>
    );
}

/* -------------------------------------------------------------- 5. Schedule */

function ScheduleStep({ transaction }) {
    const { schedule } = transaction;
    const verify = useForm({});

    return (
        <div className="flex flex-col gap-3">
            {schedule.verified_at ? (
                <Alert>
                    <ShieldCheckIcon />
                    <AlertTitle>Schedule verified</AlertTitle>
                    <AlertDescription>
                        Checked by {schedule.verified_by}. Changing the issue or fees will clear
                        this.
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert>
                    <AlertTitle>Check the schedule before verifying</AlertTitle>
                    <AlertDescription>
                        Periods follow the financial year; part-periods are charged by days. The
                        transaction can only be sent for approval once someone has verified it.
                    </AlertDescription>
                </Alert>
            )}
            {schedule.lines.map((line) => (
                <ScheduleTable key={line.kind} line={line} />
            ))}
            <FieldError>{verify.errors.schedule}</FieldError>
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="outline" asChild>
                    <Link href={route('transactions.edit', [transaction.id, { step: 'fees' }])}>
                        Change fees
                    </Link>
                </Button>
                {schedule.verified_at ? (
                    <Button asChild>
                        <Link
                            href={route('transactions.edit', [transaction.id, { step: 'review' }])}
                        >
                            Continue <ArrowRightIcon />
                        </Link>
                    </Button>
                ) : (
                    <Button
                        onClick={() =>
                            verify.post(route('transactions.schedule.verify', transaction.id))
                        }
                        disabled={verify.processing}
                    >
                        <ShieldCheckIcon />{' '}
                        {verify.processing ? 'Verifying…' : 'I have checked it — verify'}
                    </Button>
                )}
            </div>
        </div>
    );
}

/* ---------------------------------------------------------------- 6. Review */

function ReviewStep({ transaction, options, can }) {
    const canSubmit = can?.submit && route().has('transactions.submit');

    return (
        <div className="flex flex-col gap-3">
            <TransactionSummary transaction={transaction} options={options} />
            <div className="flex flex-wrap justify-end gap-2">
                {canSubmit ? (
                    <ConfirmAction
                        href={route('transactions.submit', transaction.id)}
                        title="Send for approval?"
                        description="Approvers are notified and the transaction can't be edited while they decide."
                        confirmLabel="Send for approval"
                        trigger={<Button>Send for approval</Button>}
                    />
                ) : (
                    <p className="text-[13px] text-muted-foreground">
                        {can?.submit === false
                            ? 'You need the "Send transactions for approval" permission to submit.'
                            : 'Everything is ready for approval.'}
                    </p>
                )}
            </div>
        </div>
    );
}

/* ------------------------------------------------------------------- Page */

export default function TransactionWizard({ transaction, step, progress, options, can }) {
    const title = transaction ? transaction.company.name : 'New transaction';

    const body = {
        basics: <BasicsStep transaction={transaction} options={options} />,
        contacts: transaction && <ContactsStep transaction={transaction} options={options} />,
        issue: transaction && <IssueStep transaction={transaction} options={options} />,
        fees: transaction && <FeesStep transaction={transaction} options={options} />,
        schedule: transaction && <ScheduleStep transaction={transaction} />,
        review: transaction && <ReviewStep transaction={transaction} options={options} can={can} />,
    }[step];

    return (
        <AppLayout
            title={title}
            breadcrumbs={[
                { title: 'Transactions' },
                { title: 'Drafts', href: route('transactions.drafts') },
                { title: transaction ? title : 'New' },
            ]}
        >
            <PageHeader
                title={title}
                description={
                    transaction ? (
                        <span className="flex items-center gap-2">
                            Debenture trustee
                            <Badge variant="neutral">{transaction.status_label}</Badge>
                        </span>
                    ) : (
                        'Debenture trustee · a draft is saved after the first step'
                    )
                }
            />
            <WizardSteps transactionId={transaction?.id} current={step} progress={progress} />
            <div className="max-w-5xl">{body}</div>
        </AppLayout>
    );
}
