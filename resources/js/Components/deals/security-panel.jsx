import { ConfirmAction } from '@/Components/confirm-action';
import {
    ACCEPT,
    FileLink,
    History,
    MAX_FILES,
    ReasonDialog,
    UploadDialog,
    fileProblem,
} from '@/Components/deals/document-files';
import { CheckList } from '@/Components/deals/execution-panel';
import { ComboField, SelectField, TextField } from '@/Components/form-fields';
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
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { PencilIcon, PlusIcon, UploadIcon, XIcon } from 'lucide-react';
import { useMemo, useState } from 'react';

function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const ENCUMBRANCE = [
    { value: 'true', label: 'Encumbered' },
    { value: 'false', label: 'Not encumbered' },
];

function Footer({ form, onCancel, label, busyLabel, disabled, Wrapper = DialogFooter }) {
    return (
        <Wrapper className={Wrapper === SheetFooter ? 'flex-row justify-end border-t' : undefined}>
            <Button type="button" variant="outline" onClick={onCancel} disabled={form.processing}>
                Cancel
            </Button>
            <Button type="submit" disabled={form.processing || disabled}>
                {form.processing ? busyLabel : label}
            </Button>
        </Wrapper>
    );
}

/** Files picked for a filing, checked in the browser before upload like everywhere else. */
function FilesField({ form, label = 'Documents', hint }) {
    const [problem, setProblem] = useState(null);
    const error =
        problem ??
        form.errors.files ??
        Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1];

    return (
        <Field data-invalid={!!error || undefined}>
            <FieldLabel htmlFor="filing-files">{label}</FieldLabel>
            <Input
                id="filing-files"
                type="file"
                multiple
                accept={ACCEPT}
                onChange={(e) => {
                    const chosen = Array.from(e.target.files ?? []);
                    const issue = chosen.length ? fileProblem(chosen) : null;
                    setProblem(issue);
                    form.setData('files', issue ? [] : chosen);
                }}
                aria-invalid={!!error || undefined}
            />
            <FieldDescription>
                {hint ??
                    `Challan, signed form, certificate … up to ${MAX_FILES} files, 20 MB each.`}
            </FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

// ---------------------------------------------------------------- securities

function SecurityForm({ dealId, security, options, onDone }) {
    const editing = Boolean(security);
    const form = useForm({
        deal_document_id: security?.deal_document_id ?? null,
        nature: security?.nature ?? 'hypothecation',
        asset_owner: security?.asset_owner ?? '',
        owner_id_type: security?.owner_id_type ?? null,
        owner_id_number: security?.owner_id_number ?? '',
        asset_type_id: security?.asset_type_id ?? null,
        charge_type_id: security?.charge_type_id ?? null,
        security_type_ids: security?.security_type_ids ?? [],
        pertaining_to: security?.pertaining_to ?? '',
        is_encumbered:
            security?.is_encumbered === null || security?.is_encumbered === undefined
                ? null
                : String(security.is_encumbered),
        description: security?.description ?? '',
        address: security?.address ?? '',
        pincode: security?.pincode ?? '',
        city: security?.city ?? '',
        state_id: security?.state_id ?? null,
        form_of_securities: security?.form_of_securities ?? '',
        confirming_party: security?.confirming_party ?? '',
    });

    // Security types follow the chosen asset type (all when none is chosen).
    const securityTypes = useMemo(
        () =>
            options.security_types
                .filter(
                    (t) => !form.data.asset_type_id || t.asset_type_id === form.data.asset_type_id,
                )
                .map((t) => ({ value: t.value, label: t.label })),
        [options.security_types, form.data.asset_type_id],
    );

    const chooseDocument = (id) => {
        const nature = options.documents.find((d) => d.value === id)?.nature;
        form.setData((data) => ({ ...data, deal_document_id: id, nature: nature ?? data.nature }));
    };

    const submit = (e) => {
        e.preventDefault();
        const payload = {
            preserveScroll: true,
            onSuccess: onDone,
        };
        form.transform((data) => ({
            ...data,
            is_encumbered: data.is_encumbered === null ? null : data.is_encumbered === 'true',
        }));
        if (editing) form.put(route('deals.securities.update', [dealId, security.id]), payload);
        else form.post(route('deals.securities.store', dealId), payload);
    };

    const isGuarantee = form.data.nature === 'guarantee';

    return (
        <form onSubmit={submit} noValidate className="flex h-full min-h-0 flex-col">
            <SheetHeader>
                <SheetTitle>{editing ? 'Edit security' : 'Add a security'}</SheetTitle>
                <SheetDescription>
                    A security created under one of the deal&apos;s legal documents.
                </SheetDescription>
            </SheetHeader>
            <ScrollArea className="min-h-0 flex-1 px-4">
                <div className="flex flex-col gap-4 pb-4">
                    <ComboField
                        form={form}
                        name="deal_document_id"
                        label="Legal document"
                        required
                        items={options.documents}
                        set={chooseDocument}
                        placeholder="Choose a document…"
                    />
                    <SelectField
                        form={form}
                        name="nature"
                        label="Kind"
                        required
                        items={options.natures}
                    />
                    <TextField
                        form={form}
                        name="asset_owner"
                        label={isGuarantee ? 'Guarantor' : 'Asset owner'}
                        required
                        maxLength={255}
                    />
                    <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                        <SelectField
                            form={form}
                            name="owner_id_type"
                            label="ID type"
                            items={options.owner_id_types}
                        />
                        <TextField
                            form={form}
                            name="owner_id_number"
                            label="CIN / PAN"
                            maxLength={25}
                            className="uppercase"
                        />
                    </div>
                    {!isGuarantee && (
                        <>
                            <ComboField
                                form={form}
                                name="charge_type_id"
                                label="Charge"
                                items={options.charge_types}
                                placeholder="Choose…"
                            />
                            <ComboField
                                form={form}
                                name="asset_type_id"
                                label="Asset type"
                                items={options.asset_types}
                                placeholder="Choose…"
                            />
                            <Field>
                                <FieldLabel>Securities over</FieldLabel>
                                <CheckList
                                    form={form}
                                    name="security_type_ids"
                                    items={securityTypes}
                                    emptyText="No security types for this asset type."
                                />
                            </Field>
                            <SelectField
                                form={form}
                                name="is_encumbered"
                                label="Encumbrance"
                                items={ENCUMBRANCE}
                            />
                        </>
                    )}
                    <TextField
                        form={form}
                        name="pertaining_to"
                        label="Pertaining to"
                        maxLength={255}
                    />
                    <TextField
                        form={form}
                        name="description"
                        label="Description"
                        multiline
                        rows={3}
                        maxLength={5000}
                        hint="What the security covers, as worded in the document."
                    />
                    <TextField form={form} name="address" label="Asset address" maxLength={500} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="pincode"
                            label="Pincode"
                            inputMode="numeric"
                            maxLength={6}
                        />
                        <TextField form={form} name="city" label="City" maxLength={120} />
                    </div>
                    <ComboField
                        form={form}
                        name="state_id"
                        label="State"
                        items={options.states}
                        placeholder="Choose…"
                    />
                    <TextField
                        form={form}
                        name="form_of_securities"
                        label="Form of securities"
                        maxLength={255}
                    />
                    <TextField
                        form={form}
                        name="confirming_party"
                        label="Confirming party"
                        maxLength={255}
                    />
                </div>
            </ScrollArea>
            <Footer
                form={form}
                onCancel={onDone}
                label="Save"
                busyLabel="Saving…"
                Wrapper={SheetFooter}
            />
        </form>
    );
}

function SecurityRow({ dealId, security: s, can, onEdit }) {
    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-medium [overflow-wrap:anywhere]">
                        {s.security_types.length ? s.security_types.join(', ') : s.nature_label}
                    </span>
                    <Badge variant="neutral">{s.nature_label}</Badge>
                    {s.registered.map((r) => (
                        <Badge key={r} variant="success">
                            {r}
                        </Badge>
                    ))}
                </div>
                <span className="text-xs text-muted-foreground">
                    {s.nature === 'guarantee' ? 'Guarantor' : 'Owner'}: {s.asset_owner}
                    {s.owner_id_number && ` (${s.owner_id_number})`}
                    {s.charge_type && ` · ${s.charge_type} charge`}
                    {s.asset_type && ` · ${s.asset_type}`}
                    {s.is_encumbered !== null &&
                        ` · ${s.is_encumbered ? 'Encumbered' : 'Not encumbered'}`}
                </span>
                {s.description && (
                    <p className="line-clamp-2 text-xs [overflow-wrap:anywhere] text-muted-foreground">
                        {s.description}
                    </p>
                )}
                {(s.address || s.city) && (
                    <span className="text-xs text-muted-foreground">
                        {[s.address, s.city, s.state, s.pincode].filter(Boolean).join(', ')}
                    </span>
                )}
            </div>
            {can.manageSecurity && (
                <div className="flex flex-wrap items-center gap-2">
                    <Button size="sm" variant="outline" onClick={() => onEdit(s)}>
                        <PencilIcon /> Edit
                    </Button>
                    {s.can_remove && (
                        <ConfirmAction
                            href={route('deals.securities.destroy', [dealId, s.id])}
                            method="delete"
                            title="Remove this security?"
                            description="It stays in the deal's activity history."
                            confirmLabel="Remove"
                            destructive
                            trigger={
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="size-8"
                                    aria-label="Remove security"
                                >
                                    <XIcon />
                                </Button>
                            }
                        />
                    )}
                </div>
            )}
        </li>
    );
}

function Securities({ dealId, security, can }) {
    const [sheet, setSheet] = useState({ open: false, security: null, key: 0 });
    const open = (s = null) => setSheet((v) => ({ open: true, security: s, key: v.key + 1 }));
    const close = () => setSheet((v) => ({ ...v, open: false }));

    const groups = useMemo(() => {
        const byDocument = new Map();
        for (const s of security.securities) {
            if (!byDocument.has(s.document)) byDocument.set(s.document, []);
            byDocument.get(s.document).push(s);
        }
        return [...byDocument.entries()];
    }, [security.securities]);

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                <div className="flex flex-col gap-1.5">
                    <CardTitle>Securities</CardTitle>
                    <CardDescription>
                        {security.securities.length > 0
                            ? `${security.securities.length} ${security.securities.length === 1 ? 'security' : 'securities'} under ${groups.length} ${groups.length === 1 ? 'document' : 'documents'}.`
                            : 'What the deal is secured by, document by document.'}
                    </CardDescription>
                </div>
                {can.manageSecurity && security.options.documents.length > 0 && (
                    <Button size="sm" onClick={() => open()}>
                        <PlusIcon /> Add security
                    </Button>
                )}
            </CardHeader>
            <CardContent className="px-0">
                {groups.length === 0 ? (
                    <Empty className="mx-4 mb-4 border">
                        <EmptyHeader>
                            <EmptyTitle>No securities recorded</EmptyTitle>
                            <EmptyDescription>
                                {security.options.documents.length > 0
                                    ? 'Add the securities each legal document creates.'
                                    : 'Add the legal documents on the Documentation tab first.'}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    groups.map(([document, items]) => (
                        <div key={document}>
                            <div className="border-t bg-surface-2 px-4 py-1.5 text-xs font-medium text-muted-foreground">
                                {document}
                            </div>
                            <ul className="divide-y border-t">
                                {items.map((s) => (
                                    <SecurityRow
                                        key={s.id}
                                        dealId={dealId}
                                        security={s}
                                        can={can}
                                        onEdit={open}
                                    />
                                ))}
                            </ul>
                        </div>
                    ))
                )}
            </CardContent>
            <Sheet open={sheet.open} onOpenChange={(o) => !o && close()}>
                <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                    {sheet.open && (
                        <SecurityForm
                            key={sheet.key}
                            dealId={dealId}
                            security={sheet.security}
                            options={security.options}
                            onDone={close}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </Card>
    );
}

// ---------------------------------------------------------------- registrations

function RegisterDialog({ dealId, security, onClose }) {
    const form = useForm({
        kind: 'roc',
        security_ids: [],
        happened_on: today(),
        filing_reference: '',
        reference: '',
        amount: '',
        security_name: '',
        quantity: '',
        face_value: '',
        depository: 'NSDL',
        pledgor_dp_id: '',
        pledgor_client_id: '',
        pledgee_dp_id: '',
        pledgee_client_id: '',
        files: [],
    });
    const kind = security.options.kinds.find((k) => k.value === form.data.kind);
    const eligible = security.securities
        .filter((s) => s.kinds.includes(form.data.kind))
        .map((s) => ({ value: s.id, label: s.summary, description: s.document }));
    const pledge = form.data.kind === 'pledge';
    const labels = {
        roc: { reference: 'Charge ID', filing: 'SRN' },
        cersai: { reference: 'Security interest ID', filing: 'Transaction ID' },
        pledge: { reference: 'ISIN', filing: 'PSN' },
    }[form.data.kind];

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.registrations.store', dealId), {
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
                        <DialogTitle>Record a registration</DialogTitle>
                        <DialogDescription>
                            A ROC charge, CERSAI registration or pledge.
                        </DialogDescription>
                    </DialogHeader>
                    <SelectField
                        form={form}
                        name="kind"
                        label="Registration"
                        required
                        items={security.options.kinds}
                        set={(v) => form.setData((d) => ({ ...d, kind: v, security_ids: [] }))}
                    />
                    <Field>
                        <FieldLabel>
                            Securities covered<span className="text-destructive">*</span>
                        </FieldLabel>
                        <CheckList
                            form={form}
                            name="security_ids"
                            items={eligible}
                            emptyText={`No security can have a ${kind?.label ?? 'registration'}.`}
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="happened_on"
                            label="Date"
                            required
                            type="date"
                            min="2000-01-01"
                            max={today()}
                        />
                        <TextField
                            form={form}
                            name="filing_reference"
                            label={labels.filing}
                            maxLength={60}
                        />
                        <TextField
                            form={form}
                            name="reference"
                            label={labels.reference}
                            maxLength={60}
                        />
                        {!pledge && (
                            <TextField
                                form={form}
                                name="amount"
                                label="Amount (₹)"
                                inputMode="decimal"
                            />
                        )}
                    </div>
                    {pledge && (
                        <>
                            <TextField
                                form={form}
                                name="security_name"
                                label="Security pledged"
                                required
                                maxLength={255}
                            />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <TextField
                                    form={form}
                                    name="quantity"
                                    label="Number of securities"
                                    required
                                    inputMode="numeric"
                                />
                                <TextField
                                    form={form}
                                    name="face_value"
                                    label="Face value per security (₹)"
                                    inputMode="decimal"
                                />
                                <SelectField
                                    form={form}
                                    name="depository"
                                    label="Depository"
                                    required
                                    items={[
                                        { value: 'NSDL', label: 'NSDL' },
                                        { value: 'CDSL', label: 'CDSL' },
                                    ]}
                                />
                                <span />
                                <TextField
                                    form={form}
                                    name="pledgor_dp_id"
                                    label="Pledgor DP ID"
                                    maxLength={20}
                                />
                                <TextField
                                    form={form}
                                    name="pledgor_client_id"
                                    label="Pledgor client ID"
                                    maxLength={20}
                                />
                                <TextField
                                    form={form}
                                    name="pledgee_dp_id"
                                    label="Pledgee DP ID"
                                    maxLength={20}
                                />
                                <TextField
                                    form={form}
                                    name="pledgee_client_id"
                                    label="Pledgee client ID"
                                    maxLength={20}
                                />
                            </div>
                        </>
                    )}
                    <FilesField form={form} />
                    <Footer
                        form={form}
                        onCancel={onClose}
                        label="Record"
                        busyLabel="Saving…"
                        disabled={form.data.security_ids.length === 0}
                    />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EventDialog({ dealId, registration: r, action, onClose }) {
    const form = useForm({
        action,
        happened_on: today(),
        filing_reference: '',
        amount: action === 'modify' ? (r.amount ?? '') : '',
        reason: '',
        files: [],
    });
    const verb = action === 'modify' ? 'Modify' : r.kind === 'pledge' ? 'Release' : 'Satisfy';

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.registrations.event', [dealId, r.id]), {
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
                            {verb} {r.kind_label}
                            {r.reference && ` ${r.reference}`}
                        </DialogTitle>
                        <DialogDescription>
                            {action === 'modify'
                                ? 'Record the modification filed.'
                                : 'Record the satisfaction filed. This is final.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            form={form}
                            name="happened_on"
                            label="Date"
                            required
                            type="date"
                            min="2000-01-01"
                            max={today()}
                        />
                        <TextField
                            form={form}
                            name="filing_reference"
                            label={r.filing_label}
                            maxLength={60}
                        />
                        {action === 'modify' && r.kind !== 'pledge' && (
                            <TextField
                                form={form}
                                name="amount"
                                label="Amount (₹)"
                                inputMode="decimal"
                            />
                        )}
                    </div>
                    <TextField
                        form={form}
                        name="reason"
                        label={action === 'modify' ? 'What was modified and why' : 'Note'}
                        required={action === 'modify'}
                        multiline
                        rows={2}
                        maxLength={2000}
                    />
                    <FilesField form={form} />
                    <FieldError>{form.errors.action}</FieldError>
                    <Footer form={form} onCancel={onClose} label={verb} busyLabel="Saving…" />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RegistrationRow({ dealId, registration: r, can }) {
    const [dialog, setDialog] = useState(null);

    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-medium">
                        {r.kind_label}
                        {r.reference && ` · ${r.reference_label} ${r.reference}`}
                    </span>
                    <Badge variant={r.status_tone}>{r.status_label}</Badge>
                    {r.amount && (
                        <span className="text-xs text-muted-foreground">
                            {formatMoney(r.amount)}
                        </span>
                    )}
                </div>
                {r.pledge && (
                    <span className="text-xs text-muted-foreground">
                        {r.pledge.quantity?.toLocaleString('en-IN')} × {r.pledge.security_name}
                        {r.pledge.face_value && ` @ ${formatMoney(r.pledge.face_value)}`} ·{' '}
                        {r.pledge.depository}
                        {r.pledge.pledgor && ` · pledgor ${r.pledge.pledgor}`}
                        {r.pledge.pledgee && ` · pledgee ${r.pledge.pledgee}`}
                    </span>
                )}
                <span className="text-xs [overflow-wrap:anywhere] text-muted-foreground">
                    Covers: {r.securities.join('; ')}
                </span>
                <ol className="mt-1 flex flex-col gap-1.5 border-l pl-3">
                    {r.events.map((e) => (
                        <li key={e.id} className="flex flex-col gap-0.5">
                            <span className="text-xs">
                                <span className="font-medium">{e.action_label}</span>{' '}
                                {formatDate(e.happened_on)}
                                {e.filing_reference && ` · ${r.filing_label} ${e.filing_reference}`}
                                {e.amount && ` · ${formatMoney(e.amount)}`}
                                <span className="text-muted-foreground"> · {e.by}</span>
                            </span>
                            {e.reason && (
                                <span className="text-xs text-muted-foreground">“{e.reason}”</span>
                            )}
                            {e.files.map((f) => (
                                <FileLink key={f.id} dealId={dealId} file={f} />
                            ))}
                        </li>
                    ))}
                </ol>
            </div>
            {r.is_active && (
                <div className="flex flex-wrap gap-2">
                    {can.manageSecurity && (
                        <Button size="sm" variant="outline" onClick={() => setDialog('modify')}>
                            Modify
                        </Button>
                    )}
                    {can.satisfyRegistration && (
                        <Button size="sm" variant="outline" onClick={() => setDialog('satisfy')}>
                            {r.kind === 'pledge' ? 'Release' : 'Satisfy'}
                        </Button>
                    )}
                </div>
            )}
            {dialog && (
                <EventDialog
                    dealId={dealId}
                    registration={r}
                    action={dialog}
                    onClose={() => setDialog(null)}
                />
            )}
        </li>
    );
}

function Registrations({ dealId, security, can }) {
    const [adding, setAdding] = useState(false);
    const active = security.registrations.filter((r) => r.is_active).length;

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                <div className="flex flex-col gap-1.5">
                    <CardTitle>Registrations</CardTitle>
                    <CardDescription>
                        {security.registrations.length > 0
                            ? `${active} in force, ${security.registrations.length - active} satisfied or released.`
                            : 'ROC charges, CERSAI registrations and pledges, with their filings.'}
                    </CardDescription>
                </div>
                {can.manageSecurity && security.securities.length > 0 && (
                    <Button size="sm" onClick={() => setAdding(true)}>
                        <PlusIcon /> Record registration
                    </Button>
                )}
            </CardHeader>
            <CardContent className="px-0">
                {security.registrations.length === 0 ? (
                    <Empty className="mx-4 mb-4 border">
                        <EmptyHeader>
                            <EmptyTitle>Nothing registered yet</EmptyTitle>
                            <EmptyDescription>
                                Record the ROC charge, CERSAI registration or pledge once it&apos;s
                                filed.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="divide-y border-t">
                        {security.registrations.map((r) => (
                            <RegistrationRow
                                key={r.id}
                                dealId={dealId}
                                registration={r}
                                can={can}
                            />
                        ))}
                    </ul>
                )}
            </CardContent>
            {adding && (
                <RegisterDialog
                    dealId={dealId}
                    security={security}
                    onClose={() => setAdding(false)}
                />
            )}
        </Card>
    );
}

// ---------------------------------------------------------------- due diligence

function AddDiligenceDialog({ dealId, security, onClose }) {
    const form = useForm({
        kind: 'roc_search',
        title: '',
        deal_security_id: null,
        asset_owner: '',
        empanelled_agency_id: null,
        reference: '',
    });
    const needsSecurity = ['security_certificate', 'noc'].includes(form.data.kind);
    const needsOwner = form.data.kind === 'roc_search';

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.diligence.store', dealId), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto">
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Add a due diligence item</DialogTitle>
                        <DialogDescription>
                            Upload its files afterwards; someone else checks them.
                        </DialogDescription>
                    </DialogHeader>
                    <SelectField
                        form={form}
                        name="kind"
                        label="Kind"
                        required
                        items={security.options.diligence_kinds}
                        set={(v) => {
                            const label = security.options.diligence_kinds.find(
                                (k) => k.value === v,
                            )?.label;
                            form.setData((d) => ({ ...d, kind: v, title: d.title || label || '' }));
                        }}
                    />
                    <TextField form={form} name="title" label="Document" required maxLength={500} />
                    {needsSecurity && (
                        <ComboField
                            form={form}
                            name="deal_security_id"
                            label="Security"
                            required
                            items={security.securities.map((s) => ({
                                value: s.id,
                                label: s.summary,
                                description: s.document,
                            }))}
                            placeholder="Choose a security…"
                        />
                    )}
                    {needsOwner && (
                        <TextField
                            form={form}
                            name="asset_owner"
                            label="Asset owner searched"
                            required
                            maxLength={255}
                            list="diligence-owners"
                        />
                    )}
                    <datalist id="diligence-owners">
                        {security.options.asset_owners.map((o) => (
                            <option key={o} value={o} />
                        ))}
                    </datalist>
                    <ComboField
                        form={form}
                        name="empanelled_agency_id"
                        label="Issued by (empanelled agency)"
                        items={security.options.agencies}
                        placeholder="Choose…"
                    />
                    <TextField
                        form={form}
                        name="reference"
                        label="UDIN / reference"
                        maxLength={60}
                    />
                    <Footer form={form} onCancel={onClose} label="Add" busyLabel="Adding…" />
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DiligenceRow({ dealId, item: d, can }) {
    const [dialog, setDialog] = useState(null);
    const close = () => setDialog(null);

    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-medium [overflow-wrap:anywhere]">
                        {d.title}
                    </span>
                    <Badge variant={d.status_tone}>{d.status_label}</Badge>
                </div>
                <span className="text-xs [overflow-wrap:anywhere] text-muted-foreground">
                    {[
                        d.kind_label,
                        d.security,
                        d.asset_owner && `Owner: ${d.asset_owner}`,
                        d.issued_by,
                        d.reference && `UDIN / ref ${d.reference}`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
                {d.files.length > 0 && (
                    <ul className="flex flex-col gap-1 pt-0.5">
                        {d.files.map((file) => (
                            <li key={file.id} className="flex min-w-0 items-center gap-1">
                                <FileLink dealId={dealId} file={file} />
                                {can.manageSecurity && d.is_open && (
                                    <ConfirmAction
                                        href={route('deals.files.destroy', [dealId, file.id])}
                                        method="delete"
                                        title={`Remove ${file.name}?`}
                                        description="The file stays on record as removed."
                                        confirmLabel="Remove file"
                                        destructive
                                        trigger={
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7 shrink-0"
                                                aria-label={`Remove ${file.name}`}
                                            >
                                                <XIcon />
                                            </Button>
                                        }
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex flex-col gap-0.5 text-xs text-muted-foreground">
                    {d.submitted_by && (
                        <span>
                            Sent for checking by {d.submitted_by}, {formatDateTime(d.submitted_at)}
                        </span>
                    )}
                    {d.checker && (
                        <span>
                            {d.status === 'verified' ? 'Verified' : 'Sent back'} by {d.checker},{' '}
                            {formatDateTime(d.checked_at)}
                            {d.checker_comment && ` · “${d.checker_comment}”`}
                        </span>
                    )}
                </div>
                <History
                    dealId={dealId}
                    label={`${d.removed_files.length} removed ${d.removed_files.length === 1 ? 'file' : 'files'}`}
                    files={d.removed_files}
                />
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {can.manageSecurity && d.is_open && (
                    <Button size="sm" variant="outline" onClick={() => setDialog('upload')}>
                        <UploadIcon /> Upload
                    </Button>
                )}
                {can.verifySecurity && d.can_check && (
                    <>
                        <ConfirmAction
                            href={route('deals.diligence.check', [dealId, d.id])}
                            data={{ decision: 'verified' }}
                            title={`Verify “${d.title}”?`}
                            description="A verified item can't be changed afterwards."
                            confirmLabel="Verify"
                            trigger={<Button size="sm">Verify</Button>}
                        />
                        <Button size="sm" variant="destructive" onClick={() => setDialog('return')}>
                            Send back
                        </Button>
                    </>
                )}
                {can.verifySecurity && d.status === 'submitted' && d.is_mine && (
                    <span className="text-xs text-muted-foreground">
                        You uploaded these files; someone else checks them.
                    </span>
                )}
                {can.manageSecurity && d.can_remove && (
                    <ConfirmAction
                        href={route('deals.diligence.destroy', [dealId, d.id])}
                        method="delete"
                        title={`Remove “${d.title}”?`}
                        description="Use this for an item added by mistake."
                        confirmLabel="Remove"
                        destructive
                        trigger={
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-8"
                                aria-label="Remove item"
                            >
                                <XIcon />
                            </Button>
                        }
                    />
                )}
            </div>
            {dialog === 'upload' && (
                <UploadDialog
                    title="Upload files"
                    description={`${d.title}. Uploading sends it for checking by someone else.`}
                    href={route('deals.diligence.upload', [dealId, d.id])}
                    multiple
                    open
                    onOpenChange={close}
                />
            )}
            {dialog === 'return' && (
                <ReasonDialog
                    title={`Send back: ${d.title}`}
                    description="Tell the maker what needs fixing."
                    label="What needs fixing"
                    field="comment"
                    href={route('deals.diligence.check', [dealId, d.id])}
                    submitLabel="Send back"
                    destructive
                    open
                    onOpenChange={close}
                />
            )}
        </li>
    );
}

function Diligence({ dealId, security, can }) {
    const [adding, setAdding] = useState(false);
    const items = security.diligence;
    const verified = items.filter((d) => d.status === 'verified').length;
    const waiting = items.filter((d) => d.status === 'submitted').length;

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                <div className="flex flex-col gap-1.5">
                    <CardTitle>Due diligence</CardTitle>
                    <CardDescription>
                        {items.length > 0
                            ? `${verified} of ${items.length} verified${waiting ? ` · ${waiting} waiting for a check` : ''}. The checker can never be the uploader.`
                            : 'ROC searches, security certificates, NOCs, the security cover certificate and annexures.'}
                    </CardDescription>
                </div>
                {can.manageSecurity && (
                    <Button size="sm" onClick={() => setAdding(true)}>
                        <PlusIcon /> Add item
                    </Button>
                )}
            </CardHeader>
            <CardContent className="px-0">
                {items.length === 0 ? (
                    <Empty className="mx-4 mb-4 border">
                        <EmptyHeader>
                            <EmptyTitle>No due diligence items</EmptyTitle>
                            <EmptyDescription>
                                Add the reports and certificates this deal needs.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="divide-y border-t">
                        {items.map((d) => (
                            <DiligenceRow key={d.id} dealId={dealId} item={d} can={can} />
                        ))}
                    </ul>
                )}
            </CardContent>
            {adding && (
                <AddDiligenceDialog
                    dealId={dealId}
                    security={security}
                    onClose={() => setAdding(false)}
                />
            )}
        </Card>
    );
}

/** The deal's securities, their registrations and its due diligence. */
export function SecurityPanel({ dealId, security, can }) {
    return (
        <div className="flex flex-col gap-4">
            <Securities dealId={dealId} security={security} can={can} />
            <Registrations dealId={dealId} security={security} can={can} />
            <Diligence dealId={dealId} security={security} can={can} />
        </div>
    );
}
