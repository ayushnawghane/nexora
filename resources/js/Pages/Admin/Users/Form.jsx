import { Combobox } from '@/Components/combobox';
import { ConfirmAction } from '@/Components/confirm-action';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';

const NONE = '__none__';

function toggle(list, value, checked) {
    return checked ? [...new Set([...list, value])] : list.filter((item) => item !== value);
}

function CheckboxList({ name, items, selected, onChange, getValue, getLabel, error }) {
    return (
        <Field data-invalid={!!error || undefined}>
            <div className="grid gap-2 sm:grid-cols-2">
                {items.map((item) => {
                    const value = getValue(item);
                    const id = `${name}-${value}`;
                    return (
                        <Field key={value} orientation="horizontal">
                            <Checkbox
                                id={id}
                                checked={selected.includes(value)}
                                onCheckedChange={(checked) =>
                                    onChange(toggle(selected, value, checked === true))
                                }
                            />
                            <FieldLabel htmlFor={id} className="font-normal">
                                {getLabel(item)}
                            </FieldLabel>
                        </Field>
                    );
                })}
            </div>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export default function UserForm({ user, options, can = {} }) {
    const editing = Boolean(user);
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        emp_code: user?.emp_code ?? '',
        name: user?.name ?? '',
        email: user?.email ?? '',
        mobile: user?.mobile ?? '',
        department_id: user?.department_id ?? null,
        designation_id: user?.designation_id ?? null,
        reporting_manager_id: user?.reporting_manager_id ?? null,
        date_of_joining: user?.date_of_joining ?? '',
        is_authorised_signatory: user?.is_authorised_signatory ?? false,
        roles: user?.roles ?? [],
        vertical_ids: user?.vertical_ids ?? [],
        vertical_team_ids: user?.vertical_team_ids ?? [],
        product_ids: user?.product_ids ?? [],
    });

    const submit = (e) => {
        e.preventDefault();
        if (editing) put(route('users.update', user.id), { preserveScroll: true });
        else post(route('users.store'));
    };

    const text = (name, label, props = {}) => (
        <Field data-invalid={!!errors[name] || undefined}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                value={data[name] ?? ''}
                onChange={(e) => setData(name, e.target.value)}
                aria-invalid={!!errors[name] || undefined}
                {...props}
            />
            <FieldError>{errors[name]}</FieldError>
        </Field>
    );

    const select = (name, label, items) => (
        <Field data-invalid={!!errors[name] || undefined}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Select
                value={data[name] ? String(data[name]) : NONE}
                onValueChange={(v) => setData(name, v === NONE ? null : Number(v))}
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
                        <SelectItem key={item.id} value={String(item.id)}>
                            {item.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <FieldError>{errors[name]}</FieldError>
        </Field>
    );

    const title = editing ? user.name : 'New user';

    return (
        <AppLayout
            title={title}
            breadcrumbs={[
                { title: 'Administration' },
                { title: 'Users', href: route('users.index') },
                { title },
            ]}
        >
            <PageHeader
                title={title}
                description={
                    editing ? (
                        <span className="flex items-center gap-2">
                            {user.emp_code}
                            {user.is_active ? (
                                <Badge variant="success">Active</Badge>
                            ) : (
                                <Badge variant="danger">Inactive</Badge>
                            )}
                            {!user.two_factor && <Badge variant="warning">2FA not set up</Badge>}
                        </span>
                    ) : (
                        'A temporary password is generated; the user changes it and sets up 2FA at first sign-in.'
                    )
                }
            />

            <form onSubmit={submit} noValidate className="grid gap-4 lg:grid-cols-3">
                <div className="flex flex-col gap-4 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Identity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                {text('emp_code', 'Employee code', {
                                    onChange: (e) =>
                                        setData('emp_code', e.target.value.toUpperCase()),
                                    autoFocus: !editing,
                                })}
                                {text('name', 'Full name', { autoComplete: 'off' })}
                                {text('email', 'Work email', {
                                    type: 'email',
                                    autoComplete: 'off',
                                })}
                                {text('mobile', 'Mobile', {
                                    inputMode: 'tel',
                                    placeholder: '+919876543210',
                                })}
                                {text('date_of_joining', 'Date of joining', { type: 'date' })}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Organisation</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {select('department_id', 'Department', options.departments)}
                                    {select('designation_id', 'Designation', options.designations)}
                                </div>
                                <Field data-invalid={!!errors.reporting_manager_id || undefined}>
                                    <FieldLabel htmlFor="reporting_manager_id">
                                        Reports to
                                    </FieldLabel>
                                    <Combobox
                                        id="reporting_manager_id"
                                        value={data.reporting_manager_id}
                                        onChange={(v) => setData('reporting_manager_id', v)}
                                        options={options.managers}
                                        placeholder="Select manager"
                                        invalid={!!errors.reporting_manager_id}
                                    />
                                    <FieldError>{errors.reporting_manager_id}</FieldError>
                                </Field>

                                {options.verticals.length > 0 && (
                                    <FieldSet>
                                        <FieldLegend variant="label">Verticals & teams</FieldLegend>
                                        <div className="flex flex-col gap-3">
                                            {options.verticals.map((vertical) => (
                                                <div
                                                    key={vertical.id}
                                                    className="rounded-md border p-3"
                                                >
                                                    <CheckboxList
                                                        name="vertical"
                                                        items={[vertical]}
                                                        selected={data.vertical_ids}
                                                        onChange={(v) => setData('vertical_ids', v)}
                                                        getValue={(v) => v.id}
                                                        getLabel={(v) => `${v.name} (${v.code})`}
                                                    />
                                                    {vertical.teams.length > 0 && (
                                                        <div className="mt-2 ml-6">
                                                            <CheckboxList
                                                                name="team"
                                                                items={vertical.teams}
                                                                selected={data.vertical_team_ids}
                                                                onChange={(v) =>
                                                                    setData('vertical_team_ids', v)
                                                                }
                                                                getValue={(t) => t.id}
                                                                getLabel={(t) => t.name}
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                        <FieldError>
                                            {errors.vertical_ids || errors.vertical_team_ids}
                                        </FieldError>
                                    </FieldSet>
                                )}

                                <FieldSet>
                                    <FieldLegend variant="label">Products</FieldLegend>
                                    <FieldDescription>
                                        Products this user works on.
                                    </FieldDescription>
                                    <CheckboxList
                                        name="product"
                                        items={options.products}
                                        selected={data.product_ids}
                                        onChange={(v) => setData('product_ids', v)}
                                        getValue={(p) => p.id}
                                        getLabel={(p) => `${p.name} (${p.code})`}
                                        error={errors.product_ids}
                                    />
                                </FieldSet>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Access</CardTitle>
                            <CardDescription>
                                Roles decide what the user can see and do.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <FieldSet>
                                    <FieldLegend variant="label">Roles</FieldLegend>
                                    <CheckboxList
                                        name="role"
                                        items={options.roles}
                                        selected={data.roles}
                                        onChange={(v) => setData('roles', v)}
                                        getValue={(r) => r}
                                        getLabel={(r) => r}
                                        error={errors.roles || errors['roles.0']}
                                    />
                                </FieldSet>
                                <Field orientation="horizontal">
                                    <Switch
                                        id="is_authorised_signatory"
                                        checked={data.is_authorised_signatory}
                                        onCheckedChange={(v) =>
                                            setData('is_authorised_signatory', v)
                                        }
                                    />
                                    <FieldLabel
                                        htmlFor="is_authorised_signatory"
                                        className="font-normal"
                                    >
                                        Authorised signatory
                                    </FieldLabel>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing || (editing && !isDirty)}>
                            {processing ? 'Saving…' : editing ? 'Save changes' : 'Create user'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={route('users.index')}>Cancel</Link>
                        </Button>
                    </div>

                    {editing && (can.toggle_active || can.reset_security) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Security</CardTitle>
                                <CardDescription>
                                    Each action signs the user out everywhere.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {can.reset_security && (
                                    <>
                                        <ConfirmAction
                                            href={route('users.reset-password', user.id)}
                                            title="Reset password?"
                                            description="A new temporary password will be generated and shown once. The user must change it at next sign-in."
                                            confirmLabel="Reset password"
                                            trigger={
                                                <Button type="button" variant="outline">
                                                    Reset password
                                                </Button>
                                            }
                                        />
                                        <ConfirmAction
                                            href={route('users.reset-two-factor', user.id)}
                                            title="Reset two-factor authentication?"
                                            description="The user will have to scan a new QR code at next sign-in."
                                            confirmLabel="Reset 2FA"
                                            trigger={
                                                <Button type="button" variant="outline">
                                                    Reset 2FA
                                                </Button>
                                            }
                                        />
                                    </>
                                )}
                                {can.toggle_active && (
                                    <ConfirmAction
                                        href={route('users.toggle-active', user.id)}
                                        title={
                                            user.is_active
                                                ? 'Deactivate this user?'
                                                : 'Activate this user?'
                                        }
                                        description={
                                            user.is_active
                                                ? 'They will be signed out and unable to sign in until reactivated.'
                                                : 'They will be able to sign in again.'
                                        }
                                        confirmLabel={user.is_active ? 'Deactivate' : 'Activate'}
                                        destructive={user.is_active}
                                        trigger={
                                            <Button
                                                type="button"
                                                variant={user.is_active ? 'destructive' : 'outline'}
                                            >
                                                {user.is_active
                                                    ? 'Deactivate user'
                                                    : 'Activate user'}
                                            </Button>
                                        }
                                    />
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}
