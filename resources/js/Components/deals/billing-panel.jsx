import { ComboField, SelectField } from '@/Components/form-fields';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/Components/ui/field';
import { ScrollArea } from '@/Components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { formatDateTime } from '@/lib/format';
import { Link, useForm } from '@inertiajs/react';
import { PencilIcon } from 'lucide-react';
import { useState } from 'react';

const dash = <span className="text-subtle-foreground">—</span>;

function TaxMode({ mode }) {
    if (mode === 'intra') return <Badge variant="neutral">CGST + SGST</Badge>;
    if (mode === 'inter') return <Badge variant="neutral">IGST</Badge>;
    return dash;
}

function Detail({ label, children }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-[13px] break-words">{children || dash}</dd>
        </div>
    );
}

function BillingForm({ dealId, billing, companyId, onDone }) {
    const { options, current } = billing;
    const form = useForm({
        company_address_id: current?.company_address_id ?? null,
        company_gstin_id: current?.company_gstin_id ?? null,
        contact_ids: current?.contact_ids ?? [],
    });

    const address = options.addresses.find((a) => a.value === form.data.company_address_id);
    // An address tied to a GSTIN bills under it; otherwise only GSTINs of the address's state fit.
    const fixedGstin = address?.company_gstin_id ?? null;
    const gstins = address ? options.gstins.filter((g) => g.state_id === address.state_id) : [];

    const pickAddress = (id) => {
        const picked = options.addresses.find((a) => a.value === id);
        form.setData((data) => ({
            ...data,
            company_address_id: id,
            company_gstin_id: picked?.company_gstin_id ?? null,
        }));
    };

    const toggleContact = (id, on) =>
        form.setData(
            'contact_ids',
            on ? [...form.data.contact_ids, id] : form.data.contact_ids.filter((c) => c !== id),
        );

    const submit = (e) => {
        e.preventDefault();
        form.put(route('deals.billing.update', dealId), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex h-full flex-col">
            <SheetHeader>
                <SheetTitle>Billing details</SheetTitle>
                <SheetDescription>
                    Choose from the company&apos;s addresses, GSTINs and contacts.{' '}
                    <Link
                        href={route('companies.show', companyId)}
                        className="text-brand-text hover:underline"
                    >
                        Manage them on the company
                    </Link>
                    .
                </SheetDescription>
            </SheetHeader>
            <ScrollArea className="min-h-0 flex-1 px-4">
                <FieldGroup className="pb-4">
                    <ComboField
                        form={form}
                        name="company_address_id"
                        label="Billing address"
                        required
                        items={options.addresses}
                        set={pickAddress}
                        placeholder="Select an address…"
                    />
                    {address && fixedGstin && (
                        <Field>
                            <FieldLabel>GSTIN</FieldLabel>
                            <p className="font-mono text-[13px]">
                                {gstins.find((g) => g.value === fixedGstin)?.label}
                            </p>
                            <FieldDescription>
                                This address is registered under this GSTIN.
                            </FieldDescription>
                        </Field>
                    )}
                    {address && !fixedGstin && (
                        <SelectField
                            form={form}
                            name="company_gstin_id"
                            label="GSTIN"
                            items={gstins}
                            numeric
                            hint={
                                gstins.length === 0
                                    ? 'No GSTIN in this state: invoices go out without one.'
                                    : undefined
                            }
                        />
                    )}
                    <FieldError>{fixedGstin && form.errors.company_gstin_id}</FieldError>
                    {address && (
                        <p className="flex items-center gap-2 text-[13px] text-muted-foreground">
                            Tax on invoices: <TaxMode mode={address.tax_mode} />
                        </p>
                    )}
                    <FieldSet data-invalid={!!form.errors.contact_ids || undefined}>
                        <FieldLegend variant="label">
                            Billing contacts<span className="text-destructive">*</span>
                        </FieldLegend>
                        {options.contacts.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">
                                The company has no active contacts yet.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-2 rounded-md border p-3">
                                {options.contacts.map((c) => {
                                    const id = `billing-contact-${c.id}`;
                                    return (
                                        <Field key={c.id} orientation="horizontal">
                                            <Checkbox
                                                id={id}
                                                checked={form.data.contact_ids.includes(c.id)}
                                                onCheckedChange={(on) =>
                                                    toggleContact(c.id, on === true)
                                                }
                                            />
                                            <FieldLabel htmlFor={id} className="font-normal">
                                                <span>
                                                    {c.name}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {[c.designation, c.email ?? 'No email']
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                </span>
                                            </FieldLabel>
                                        </Field>
                                    );
                                })}
                            </div>
                        )}
                        <FieldError>{form.errors.contact_ids}</FieldError>
                    </FieldSet>
                </FieldGroup>
            </ScrollArea>
            <SheetFooter>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save billing details'}
                </Button>
            </SheetFooter>
        </form>
    );
}

/** Who the deal is billed to, and whether invoices carry CGST + SGST or IGST. */
export function BillingPanel({ deal, billing, canEdit }) {
    const [editing, setEditing] = useState(false);
    const { current } = billing;

    return (
        <Card>
            <CardHeader className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <CardTitle>Billing</CardTitle>
                    <CardDescription>
                        Same state as Beacon&apos;s GSTIN means CGST + SGST; any other state means
                        IGST.
                    </CardDescription>
                </div>
                {canEdit && (
                    <Button variant="outline" onClick={() => setEditing(true)}>
                        <PencilIcon /> {current ? 'Edit' : 'Set up billing'}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {!billing.home_state_configured && (
                    <Alert>
                        <AlertDescription>
                            Beacon&apos;s GSTIN isn&apos;t set, so the tax type can&apos;t be worked
                            out yet. An administrator can add it under Tax settings.
                        </AlertDescription>
                    </Alert>
                )}
                {current ? (
                    <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <Detail label="Billing address">{current.address}</Detail>
                        <Detail label="GSTIN">
                            {current.gstin && <span className="font-mono">{current.gstin}</span>}
                        </Detail>
                        <Detail label="Place of supply">{current.place_of_supply}</Detail>
                        <Detail label="Tax">
                            <TaxMode mode={current.tax_mode} />
                        </Detail>
                        <Detail label="Billing contacts">
                            {current.contacts.map((c) => (
                                <span key={c.id} className="block">
                                    {c.name}
                                    {c.email && (
                                        <span className="text-muted-foreground"> · {c.email}</span>
                                    )}
                                </span>
                            ))}
                        </Detail>
                        <Detail label="Last changed">
                            {current.updated_by}, {formatDateTime(current.updated_at)}
                        </Detail>
                    </dl>
                ) : (
                    <Empty className="border">
                        <EmptyHeader>
                            <EmptyTitle>Billing isn&apos;t set up</EmptyTitle>
                            <EmptyDescription>
                                Choose the address, GSTIN and contacts invoices for this deal go to.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                )}
            </CardContent>
            {canEdit && (
                <Sheet open={editing} onOpenChange={setEditing}>
                    <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                        <BillingForm
                            dealId={deal.id}
                            billing={billing}
                            companyId={deal.company.id}
                            onDone={() => setEditing(false)}
                        />
                    </SheetContent>
                </Sheet>
            )}
        </Card>
    );
}
