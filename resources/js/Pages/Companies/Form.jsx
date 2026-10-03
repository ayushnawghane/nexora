import { PageHeader } from '@/Components/page-header';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldTitle,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import AppLayout from '@/Layouts/AppLayout';
import { cinError, describeCin, normaliseIdentifier, panError } from '@/lib/identifiers';
import { Link, useForm } from '@inertiajs/react';

const NONE = '__none__';

const CIN_CLASSES = {
    PLC: 'Public',
    PTC: 'Private',
    OPC: 'One person company',
    NPL: 'Section 8 (not for profit)',
    GOI: 'Government company',
    SGC: 'Government company',
    GAP: 'Government company',
    GAT: 'Government company',
    FLC: 'Foreign company',
    FTC: 'Foreign company',
    ULL: 'Unlimited',
    ULT: 'Unlimited',
};

export default function CompanyForm({ company, options }) {
    const editing = Boolean(company);
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        entity_type: company?.entity_type ?? 'company',
        cin: company?.cin ?? '',
        name: company?.name ?? '',
        formerly_known_as: company?.formerly_known_as ?? '',
        pan: company?.pan ?? '',
        company_class: company?.company_class ?? null,
        category: company?.category ?? null,
        incorporated_on: company?.incorporated_on ?? '',
        is_listed: company?.is_listed ?? false,
    });

    const isCompany = data.entity_type === 'company';
    const hasNumber = data.entity_type !== 'other';
    const cin = describeCin(data.cin);

    // Server errors win; otherwise show the client-side check so mistakes appear while typing.
    const fieldErrors = {
        ...errors,
        cin: errors.cin ?? (hasNumber ? cinError(data.cin, data.entity_type) : null),
        pan: errors.pan ?? panError(data.pan, data.entity_type),
        incorporated_on:
            errors.incorporated_on ??
            (isCompany &&
            cin &&
            data.incorporated_on &&
            Number(data.incorporated_on.slice(0, 4)) !== cin.year
                ? `The incorporation year must match the year in the CIN (${cin.year}).`
                : null),
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) put(route('companies.update', company.id), { preserveScroll: true });
        else post(route('companies.store'));
    };

    const text = (name, label, props = {}) => (
        <Field data-invalid={!!fieldErrors[name] || undefined}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                value={data[name] ?? ''}
                onChange={(e) => setData(name, e.target.value)}
                aria-invalid={!!fieldErrors[name] || undefined}
                autoComplete="off"
                {...props}
            />
            <FieldError>{fieldErrors[name]}</FieldError>
        </Field>
    );

    const select = (name, label, items) => (
        <Field data-invalid={!!errors[name] || undefined}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Select
                value={data[name] ?? NONE}
                onValueChange={(v) => setData(name, v === NONE ? null : v)}
            >
                <SelectTrigger
                    id={name}
                    aria-invalid={!!errors[name] || undefined}
                    className="w-full"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>Not set</SelectItem>
                    {items.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <FieldError>{errors[name]}</FieldError>
        </Field>
    );

    const title = editing ? company.name : 'New company';
    const identifier = (name) => ({
        className: 'font-mono uppercase',
        onChange: (e) => setData(name, normaliseIdentifier(e.target.value)),
    });

    return (
        <AppLayout
            title={title}
            breadcrumbs={[
                { title: 'Companies', href: route('companies.index') },
                ...(editing
                    ? [
                          { title: company.name, href: route('companies.show', company.id) },
                          { title: 'Edit' },
                      ]
                    : [{ title: 'New company' }]),
            ]}
        >
            <PageHeader
                title={editing ? `Edit ${company.name}` : 'New company'}
                description="GSTINs, addresses and contacts are added on the company page after saving."
            />
            <form onSubmit={submit} noValidate className="flex max-w-3xl flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Entity type</CardTitle>
                        <CardDescription>
                            Decides which registration number applies.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <RadioGroup
                            value={data.entity_type}
                            onValueChange={(v) =>
                                setData((d) => ({
                                    ...d,
                                    entity_type: v,
                                    cin: v === 'other' ? '' : d.cin,
                                }))
                            }
                            className="grid gap-2 sm:grid-cols-3"
                        >
                            {options.entityTypes.map((type) => (
                                <FieldLabel key={type.value} htmlFor={`entity-${type.value}`}>
                                    <Field orientation="horizontal">
                                        <RadioGroupItem
                                            value={type.value}
                                            id={`entity-${type.value}`}
                                        />
                                        <FieldContent>
                                            <FieldTitle>{type.label}</FieldTitle>
                                        </FieldContent>
                                    </Field>
                                </FieldLabel>
                            ))}
                        </RadioGroup>
                        <FieldError>{errors.entity_type}</FieldError>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Identity</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                            {hasNumber &&
                                text('cin', data.entity_type === 'llp' ? 'LLPIN' : 'CIN', {
                                    ...identifier('cin'),
                                    maxLength: 21,
                                    autoFocus: !editing,
                                    placeholder:
                                        data.entity_type === 'llp'
                                            ? 'AAB-1234'
                                            : 'U65990MH2010PTC123456',
                                })}
                            {text('pan', 'PAN', {
                                ...identifier('pan'),
                                maxLength: 10,
                                placeholder: 'AAACB1234C',
                            })}
                            <div className="sm:col-span-2">
                                {text('name', 'Name', { autoFocus: !editing && !hasNumber })}
                            </div>
                            <div className="sm:col-span-2">
                                {text('formerly_known_as', 'Formerly known as')}
                            </div>
                        </FieldGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                        {isCompany && (
                            <CardDescription>
                                Class and listing status are read from the CIN.
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent>
                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                            {isCompany ? (
                                <Field>
                                    <FieldLabel>Class</FieldLabel>
                                    <FieldDescription className="text-foreground">
                                        {cin
                                            ? `${CIN_CLASSES[cin.ownership] ?? 'Not recognised'} · ${cin.listed ? 'Listed' : 'Unlisted'}`
                                            : 'Enter the CIN'}
                                    </FieldDescription>
                                </Field>
                            ) : (
                                select('company_class', 'Class', options.classes)
                            )}
                            {select('category', 'Category', options.categories)}
                            {text('incorporated_on', 'Date of incorporation', { type: 'date' })}
                            {!isCompany && (
                                <Field orientation="horizontal" className="self-end">
                                    <Switch
                                        id="is_listed"
                                        checked={data.is_listed}
                                        onCheckedChange={(v) => setData('is_listed', v)}
                                    />
                                    <FieldLabel htmlFor="is_listed" className="font-normal">
                                        Listed
                                    </FieldLabel>
                                </Field>
                            )}
                        </FieldGroup>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing || (editing && !isDirty)}>
                        {processing ? 'Saving…' : editing ? 'Save changes' : 'Create company'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                editing
                                    ? route('companies.show', company.id)
                                    : route('companies.index')
                            }
                        >
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
