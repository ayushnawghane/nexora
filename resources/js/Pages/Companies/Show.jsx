import { Combobox } from '@/Components/combobox';
import { ConfirmAction } from '@/Components/confirm-action';
import { PageHeader } from '@/Components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { ScrollArea } from '@/Components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
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
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Spinner } from '@/Components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { useCompanyLookup } from '@/hooks/use-company-lookup';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { gstinError, normaliseIdentifier } from '@/lib/identifiers';
import { Link, useForm } from '@inertiajs/react';
import { MoreHorizontalIcon, PencilIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

const NONE = '__none__';

const dash = <span className="text-subtle-foreground">—</span>;

function StatusBadge({ active }) {
    return active ? (
        <Badge variant="success">Active</Badge>
    ) : (
        <Badge variant="neutral">Inactive</Badge>
    );
}

function Detail({ label, children, mono = false }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={mono ? 'mt-0.5 font-mono text-[13px]' : 'mt-0.5 text-[13px]'}>
                {children || dash}
            </dd>
        </div>
    );
}

/** Text input bound to a useForm instance; `error` overrides the server error (client-side checks). */
function TextField({ form, name, label, required, hint, error, action, ...props }) {
    const message = form.errors[name] ?? error;
    return (
        <Field data-invalid={!!message || undefined}>
            <FieldLabel htmlFor={`f-${name}`}>
                {label}
                {required && <span className="text-destructive">*</span>}
            </FieldLabel>
            <div className="flex gap-2">
                <Input
                    id={`f-${name}`}
                    value={form.data[name] ?? ''}
                    onChange={(e) => form.setData(name, e.target.value)}
                    aria-invalid={!!message || undefined}
                    autoComplete="off"
                    {...props}
                />
                {action}
            </div>
            {hint && !message && <FieldDescription>{hint}</FieldDescription>}
            <FieldError>{message}</FieldError>
        </Field>
    );
}

function SelectField({ form, name, label, required, items, allowNone = true, onValueChange }) {
    const message = form.errors[name];
    return (
        <Field data-invalid={!!message || undefined}>
            <FieldLabel htmlFor={`f-${name}`}>
                {label}
                {required && <span className="text-destructive">*</span>}
            </FieldLabel>
            <Select
                value={form.data[name] == null ? NONE : String(form.data[name])}
                onValueChange={(v) => {
                    const value = v === NONE ? null : v;
                    if (onValueChange) onValueChange(value);
                    else form.setData(name, value);
                }}
            >
                <SelectTrigger
                    id={`f-${name}`}
                    aria-invalid={!!message || undefined}
                    className="w-full"
                >
                    <SelectValue placeholder="Select…" />
                </SelectTrigger>
                <SelectContent>
                    {allowNone && <SelectItem value={NONE}>Not set</SelectItem>}
                    {items.map((item) => (
                        <SelectItem key={item.value} value={String(item.value)}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <FieldError>{message}</FieldError>
        </Field>
    );
}

/** Sheet body shared by the three record forms: header, scrolling fields, sticky footer. */
function SheetForm({ title, onSubmit, onCancel, processing, children }) {
    return (
        <form onSubmit={onSubmit} noValidate className="flex h-full flex-col">
            <SheetHeader>
                <SheetTitle>{title}</SheetTitle>
                <SheetDescription>Fields marked * are required.</SheetDescription>
            </SheetHeader>
            <ScrollArea className="min-h-0 flex-1 px-4">
                <FieldGroup className="pb-4">{children}</FieldGroup>
            </ScrollArea>
            <SheetFooter className="flex-row justify-end border-t">
                <Button type="button" variant="outline" onClick={onCancel} disabled={processing}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : 'Save'}
                </Button>
            </SheetFooter>
        </form>
    );
}

function submitter(form, record, storeRoute, updateRoute, onDone) {
    return (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (record) form.put(updateRoute(record), options);
        else form.post(storeRoute, options);
    };
}

function GstinForm({ company, record, onDone }) {
    const form = useForm({
        gstin: record?.gstin ?? '',
        legal_name: record?.legal_name ?? company.name,
        trade_name: record?.trade_name ?? '',
        registered_on: record?.registered_on ?? '',
    });
    const { lookup, loading } = useCompanyLookup();
    const [notice, setNotice] = useState(null);

    const gstin = record ? record.gstin : form.data.gstin;
    const clientError = record ? null : gstinError(form.data.gstin, company.pan);
    const canFetch = gstin.length === 15 && !clientError;

    /** Fills names and the registration date from the GST portal (explicit click, so it overwrites). */
    const fetchDetails = async () => {
        try {
            const { data: found, existing } = await lookup('gstin', gstin);
            form.setData((d) => ({
                ...d,
                legal_name: found.legal_name ?? d.legal_name,
                trade_name: found.trade_name ?? d.trade_name,
                registered_on: found.registered_on ?? d.registered_on,
            }));
            setNotice(
                existing && existing.id !== company.id
                    ? { kind: 'existing', existing }
                    : !found.is_active
                      ? { kind: 'status', status: found.status, cancelledOn: found.cancelled_on }
                      : null,
            );
            toast.success('Details filled from the GST portal. Check them before saving.');
        } catch (error) {
            toast.error(error.message);
        }
    };

    const fetchButton = (
        <Button
            type="button"
            variant="outline"
            onClick={fetchDetails}
            disabled={!canFetch || loading !== null}
            title={canFetch ? undefined : 'Enter a valid GSTIN first'}
        >
            {loading ? <Spinner /> : <SearchIcon />} Fetch
        </Button>
    );

    return (
        <SheetForm
            title={record ? `Edit GSTIN ${record.gstin}` : 'Add GSTIN'}
            processing={form.processing}
            onCancel={onDone}
            onSubmit={submitter(
                form,
                record,
                route('companies.gstins.store', company.id),
                (r) => route('companies.gstins.update', [company.id, r.id]),
                onDone,
            )}
        >
            {record ? (
                <Field>
                    <FieldLabel>GSTIN</FieldLabel>
                    <div className="flex items-center justify-between gap-2">
                        <p className="font-mono text-[13px]">{record.gstin}</p>
                        {fetchButton}
                    </div>
                    <FieldDescription>
                        {record.state} ({record.state_code}). A saved GSTIN can&apos;t be changed;
                        deactivate it and add the correct one.
                    </FieldDescription>
                </Field>
            ) : (
                <TextField
                    form={form}
                    name="gstin"
                    label="GSTIN"
                    required
                    autoFocus
                    maxLength={15}
                    className="font-mono uppercase"
                    placeholder="27AAACB1234C1Z5"
                    onChange={(e) => form.setData('gstin', normaliseIdentifier(e.target.value))}
                    error={clientError}
                    hint="The state is taken from the first two digits."
                    action={fetchButton}
                />
            )}
            {notice && (
                <Alert variant="destructive">
                    {notice.kind === 'existing' ? (
                        <>
                            <AlertTitle>Registered to another company</AlertTitle>
                            <AlertDescription>
                                <span>
                                    This GSTIN belongs to{' '}
                                    <Link
                                        href={route('companies.show', notice.existing.id)}
                                        className="font-medium underline"
                                    >
                                        {notice.existing.name}
                                    </Link>
                                    .
                                </span>
                            </AlertDescription>
                        </>
                    ) : (
                        <>
                            <AlertTitle>GST portal status: {notice.status}</AlertTitle>
                            <AlertDescription>
                                {notice.cancelledOn
                                    ? `Cancelled on ${formatDate(notice.cancelledOn)}. `
                                    : ''}
                                Invoices can&apos;t be raised against an inactive GSTIN.
                            </AlertDescription>
                        </>
                    )}
                </Alert>
            )}
            <TextField form={form} name="legal_name" label="Legal name" />
            <TextField form={form} name="trade_name" label="Trade name" />
            <TextField form={form} name="registered_on" label="Registration date" type="date" />
        </SheetForm>
    );
}

function AddressForm({ company, record, gstins, options, onDone }) {
    const form = useForm({
        type: record?.type ?? (company.has_registered ? 'billing' : 'registered'),
        billing_name: record?.billing_name ?? '',
        line1: record?.line1 ?? '',
        line2: record?.line2 ?? '',
        city: record?.city ?? '',
        pincode: record?.pincode ?? '',
        state_id: record?.state_id ?? null,
        company_gstin_id: record?.company_gstin_id ?? null,
    });

    // Active GSTINs, plus the one this address already uses even if it has since been deactivated.
    const gstinOptions = gstins
        .filter((g) => g.is_active || g.id === record?.company_gstin_id)
        .map((g) => ({ value: g.id, label: `${g.gstin} · ${g.state}`, state_id: g.state_id }));

    const linked = gstinOptions.find((g) => String(g.value) === String(form.data.company_gstin_id));

    return (
        <SheetForm
            title={record ? 'Edit address' : 'Add address'}
            processing={form.processing}
            onCancel={onDone}
            onSubmit={submitter(
                form,
                record,
                route('companies.addresses.store', company.id),
                (r) => route('companies.addresses.update', [company.id, r.id]),
                onDone,
            )}
        >
            <SelectField
                form={form}
                name="type"
                label="Type"
                required
                allowNone={false}
                items={options.addressTypes}
            />
            <SelectField
                form={form}
                name="company_gstin_id"
                label="GSTIN"
                items={gstinOptions}
                onValueChange={(v) => {
                    const gstin = gstinOptions.find((g) => String(g.value) === v);
                    form.setData((d) => ({
                        ...d,
                        company_gstin_id: v === null ? null : Number(v),
                        state_id: gstin ? gstin.state_id : d.state_id,
                    }));
                }}
            />
            <TextField
                form={form}
                name="billing_name"
                label="Billing name"
                hint="Only if invoices should carry a different name from the company name."
            />
            <TextField form={form} name="line1" label="Address line 1" required />
            <TextField form={form} name="line2" label="Address line 2" />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField form={form} name="city" label="City" required />
                <TextField
                    form={form}
                    name="pincode"
                    label="Pincode"
                    required
                    inputMode="numeric"
                    maxLength={6}
                    onChange={(e) =>
                        form.setData('pincode', e.target.value.replace(/\D/g, '').slice(0, 6))
                    }
                />
            </div>
            <Field data-invalid={!!form.errors.state_id || undefined}>
                <FieldLabel htmlFor="f-state_id">
                    State<span className="text-destructive">*</span>
                </FieldLabel>
                <Combobox
                    id="f-state_id"
                    value={form.data.state_id}
                    onChange={(v) => form.setData('state_id', v)}
                    options={options.states.map((s) => ({
                        value: s.id,
                        label: s.name,
                        description: s.gst_code,
                    }))}
                    allowClear={false}
                    disabled={Boolean(linked)}
                    invalid={!!form.errors.state_id}
                />
                {linked && !form.errors.state_id && (
                    <FieldDescription>Set by the GSTIN&apos;s state.</FieldDescription>
                )}
                <FieldError>{form.errors.state_id}</FieldError>
            </Field>
        </SheetForm>
    );
}

function ContactForm({ company, record, options, onDone }) {
    const form = useForm({
        contact_type_id: record?.contact_type_id ?? null,
        salutation: record?.salutation ?? null,
        name: record?.name ?? '',
        designation: record?.designation ?? '',
        department: record?.department ?? '',
        email: record?.email ?? '',
        mobile: record?.mobile ?? '',
        landline: record?.landline ?? '',
    });

    const contactTypes = options.contactTypes.map((t) => ({ value: t.id, label: t.name }));
    if (record?.contact_type_id && !contactTypes.some((t) => t.value === record.contact_type_id)) {
        contactTypes.push({ value: record.contact_type_id, label: record.contact_type });
    }

    return (
        <SheetForm
            title={record ? `Edit ${record.name}` : 'Add contact'}
            processing={form.processing}
            onCancel={onDone}
            onSubmit={submitter(
                form,
                record,
                route('companies.contacts.store', company.id),
                (r) => route('companies.contacts.update', [company.id, r.id]),
                onDone,
            )}
        >
            <div className="grid grid-cols-[7rem_1fr] gap-4">
                <SelectField
                    form={form}
                    name="salutation"
                    label="Title"
                    items={options.salutations.map((s) => ({ value: s, label: s }))}
                />
                <TextField form={form} name="name" label="Name" required autoFocus={!record} />
            </div>
            <SelectField
                form={form}
                name="contact_type_id"
                label="Contact type"
                items={contactTypes}
                onValueChange={(v) =>
                    form.setData('contact_type_id', v === null ? null : Number(v))
                }
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField form={form} name="designation" label="Designation" />
                <TextField form={form} name="department" label="Department" />
            </div>
            <TextField
                form={form}
                name="email"
                label="Email"
                type="email"
                hint="An email or a mobile number is required."
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    form={form}
                    name="mobile"
                    label="Mobile"
                    inputMode="tel"
                    placeholder="+919876543210"
                />
                <TextField form={form} name="landline" label="Landline" inputMode="tel" />
            </div>
        </SheetForm>
    );
}

function RowActions({ onEdit, toggleHref, active, noun, deactivateHint }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label="Row actions">
                    <MoreHorizontalIcon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onEdit}>Edit</DropdownMenuItem>
                <ConfirmAction
                    href={toggleHref}
                    preserveState
                    title={active ? `Deactivate this ${noun}?` : `Activate this ${noun}?`}
                    description={active ? deactivateHint : `It will be offered in forms again.`}
                    confirmLabel={active ? 'Deactivate' : 'Activate'}
                    trigger={
                        <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                            {active ? 'Deactivate' : 'Activate'}
                        </DropdownMenuItem>
                    }
                />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function EmptyState({ title, description }) {
    return (
        <Empty className="border border-dashed">
            <EmptyHeader>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

export default function CompanyShow({ company, gstins, addresses, contacts, options, can }) {
    const [tab, setTab] = useState('gstins');
    const [sheet, setSheet] = useState({ open: false, kind: null, record: null, key: 0 });

    const open = (kind, record = null) =>
        setSheet((s) => ({ open: true, kind, record, key: s.key + 1 }));
    const close = () => setSheet((s) => ({ ...s, open: false }));

    const hasRegistered = addresses.some((a) => a.type === 'registered' && a.is_active);
    const addLabel = { gstins: 'Add GSTIN', addresses: 'Add address', contacts: 'Add contact' }[
        tab
    ];

    return (
        <AppLayout
            title={company.name}
            breadcrumbs={[
                { title: 'Companies', href: route('companies.index') },
                { title: company.name },
            ]}
        >
            <PageHeader
                title={company.name}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        {company.cin && <span className="font-mono">{company.cin}</span>}
                        <StatusBadge active={company.is_active} />
                        {company.is_listed && <Badge variant="brand">Listed</Badge>}
                    </span>
                }
                actions={
                    can.update && (
                        <>
                            <ConfirmAction
                                href={route('companies.toggle-active', company.id)}
                                preserveState
                                title={
                                    company.is_active
                                        ? 'Deactivate this company?'
                                        : 'Activate this company?'
                                }
                                description={
                                    company.is_active
                                        ? "It won't be offered for new transactions. Existing deals keep it."
                                        : 'It will be offered for new transactions again.'
                                }
                                confirmLabel={company.is_active ? 'Deactivate' : 'Activate'}
                                destructive={company.is_active}
                                trigger={
                                    <Button variant="outline">
                                        {company.is_active ? 'Deactivate' : 'Activate'}
                                    </Button>
                                }
                            />
                            <Button asChild>
                                <Link href={route('companies.edit', company.id)}>
                                    <PencilIcon /> Edit
                                </Link>
                            </Button>
                        </>
                    )
                }
            />

            <Card>
                <CardContent>
                    <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail label="Entity type">{company.entity_label}</Detail>
                        <Detail label="PAN" mono>
                            {company.pan}
                        </Detail>
                        <Detail label="Class">{company.class_label}</Detail>
                        <Detail label="Category">{company.category_label}</Detail>
                        <Detail label="Incorporated">{formatDate(company.incorporated_on)}</Detail>
                        <Detail label="Formerly known as">{company.formerly_known_as}</Detail>
                        <Detail label="Added">{formatDate(company.created_at)}</Detail>
                        <Detail label="Last updated">{formatDate(company.updated_at)}</Detail>
                    </dl>
                </CardContent>
            </Card>

            <Tabs value={tab} onValueChange={setTab}>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="-mx-1 max-w-full overflow-x-auto px-1">
                        <TabsList>
                            <TabsTrigger value="gstins">GSTINs ({gstins.length})</TabsTrigger>
                            <TabsTrigger value="addresses">
                                Addresses ({addresses.length})
                            </TabsTrigger>
                            <TabsTrigger value="contacts">Contacts ({contacts.length})</TabsTrigger>
                        </TabsList>
                    </div>
                    {can.update && (
                        <Button
                            variant="outline"
                            onClick={() => open(tab)}
                            disabled={tab === 'gstins' && !company.pan}
                            title={
                                tab === 'gstins' && !company.pan
                                    ? "Add the company's PAN first"
                                    : undefined
                            }
                        >
                            <PlusIcon /> {addLabel}
                        </Button>
                    )}
                </div>

                <TabsContent value="gstins">
                    {gstins.length === 0 ? (
                        <EmptyState
                            title="No GSTINs yet"
                            description={
                                company.pan
                                    ? 'Add each GST registration of this company. Billing addresses link to one.'
                                    : "Add the company's PAN first; GSTINs are checked against it."
                            }
                        />
                    ) : (
                        <Card className="py-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>GSTIN</TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>Legal / trade name</TableHead>
                                        <TableHead>Registered</TableHead>
                                        <TableHead className="text-right">Addresses</TableHead>
                                        <TableHead>Status</TableHead>
                                        {can.update && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {gstins.map((g) => (
                                        <TableRow key={g.id}>
                                            <TableCell className="font-mono text-xs">
                                                {g.gstin}
                                            </TableCell>
                                            <TableCell>
                                                {g.state}{' '}
                                                <span className="text-muted-foreground">
                                                    ({g.state_code})
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div>{g.legal_name || dash}</div>
                                                {g.trade_name && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {g.trade_name}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {g.registered_on
                                                    ? formatDate(g.registered_on)
                                                    : dash}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {g.addresses_count}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge active={g.is_active} />
                                            </TableCell>
                                            {can.update && (
                                                <TableCell className="text-right">
                                                    <RowActions
                                                        noun="GSTIN"
                                                        active={g.is_active}
                                                        onEdit={() => open('gstins', g)}
                                                        toggleHref={route(
                                                            'companies.gstins.toggle',
                                                            [company.id, g.id],
                                                        )}
                                                        deactivateHint="It can't be linked to new addresses. Addresses that use it must be moved first."
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    )}
                </TabsContent>

                <TabsContent value="addresses">
                    {addresses.length === 0 ? (
                        <EmptyState
                            title="No addresses yet"
                            description="Add the registered office and the billing addresses invoices go to."
                        />
                    ) : (
                        <Card className="py-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Address</TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>GSTIN</TableHead>
                                        <TableHead>Status</TableHead>
                                        {can.update && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {addresses.map((a) => (
                                        <TableRow key={a.id}>
                                            <TableCell>{a.type_label}</TableCell>
                                            <TableCell className="max-w-md whitespace-normal">
                                                {a.billing_name && (
                                                    <div className="font-medium">
                                                        {a.billing_name}
                                                    </div>
                                                )}
                                                <div>
                                                    {[a.line1, a.line2].filter(Boolean).join(', ')}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {a.city} {a.pincode}
                                                </div>
                                            </TableCell>
                                            <TableCell>{a.state}</TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {a.gstin || dash}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge active={a.is_active} />
                                            </TableCell>
                                            {can.update && (
                                                <TableCell className="text-right">
                                                    <RowActions
                                                        noun="address"
                                                        active={a.is_active}
                                                        onEdit={() => open('addresses', a)}
                                                        toggleHref={route(
                                                            'companies.addresses.toggle',
                                                            [company.id, a.id],
                                                        )}
                                                        deactivateHint="It won't be offered for billing. Existing deals keep it."
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    )}
                </TabsContent>

                <TabsContent value="contacts">
                    {contacts.length === 0 ? (
                        <EmptyState
                            title="No contacts yet"
                            description="Add the people Beacon deals with at this company."
                        />
                    ) : (
                        <Card className="py-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead>Status</TableHead>
                                        {can.update && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {contacts.map((c) => (
                                        <TableRow key={c.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {[c.salutation, c.name]
                                                        .filter(Boolean)
                                                        .join(' ')}
                                                </div>
                                                {(c.designation || c.department) && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {[c.designation, c.department]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>{c.contact_type || dash}</TableCell>
                                            <TableCell>{c.email || dash}</TableCell>
                                            <TableCell className="tabular-nums">
                                                <div>{c.mobile || dash}</div>
                                                {c.landline && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {c.landline}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge active={c.is_active} />
                                            </TableCell>
                                            {can.update && (
                                                <TableCell className="text-right">
                                                    <RowActions
                                                        noun="contact"
                                                        active={c.is_active}
                                                        onEdit={() => open('contacts', c)}
                                                        toggleHref={route(
                                                            'companies.contacts.toggle',
                                                            [company.id, c.id],
                                                        )}
                                                        deactivateHint="They won't be offered as a contact on new deals."
                                                    />
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    )}
                </TabsContent>
            </Tabs>

            <Sheet open={sheet.open} onOpenChange={(next) => !next && close()}>
                <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                    {sheet.open && sheet.kind === 'gstins' && (
                        <GstinForm
                            key={sheet.key}
                            company={company}
                            record={sheet.record}
                            onDone={close}
                        />
                    )}
                    {sheet.open && sheet.kind === 'addresses' && (
                        <AddressForm
                            key={sheet.key}
                            company={{ ...company, has_registered: hasRegistered }}
                            record={sheet.record}
                            gstins={gstins}
                            options={options}
                            onDone={close}
                        />
                    )}
                    {sheet.open && sheet.kind === 'contacts' && (
                        <ContactForm
                            key={sheet.key}
                            company={company}
                            record={sheet.record}
                            options={options}
                            onDone={close}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
