import { ConfirmAction } from '@/Components/confirm-action';
import { PageHeader } from '@/Components/page-header';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';

export default function RoleForm({ role, sections }) {
    const editing = Boolean(role);
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });

    const has = (name) => data.permissions.includes(name);
    const setMany = (names, checked) =>
        setData(
            'permissions',
            checked
                ? [...new Set([...data.permissions, ...names])]
                : data.permissions.filter((p) => !names.includes(p)),
        );

    const submit = (e) => {
        e.preventDefault();
        if (editing) put(route('roles.update', role.id), { preserveScroll: true });
        else post(route('roles.store'));
    };

    const title = editing ? role.name : 'New role';

    return (
        <AppLayout
            title={title}
            breadcrumbs={[
                { title: 'Administration' },
                { title: 'Roles & permissions', href: route('roles.index') },
                { title },
            ]}
        >
            <PageHeader
                title={title}
                description={
                    editing
                        ? `${role.users_count} user(s) hold this role. Changes apply to them immediately.`
                        : 'Name the role and tick the permissions it grants.'
                }
                actions={
                    editing && (
                        <ConfirmAction
                            method="delete"
                            href={route('roles.destroy', role.id)}
                            title="Delete this role?"
                            description="Roles that are still assigned to users can't be deleted."
                            confirmLabel="Delete role"
                            destructive
                            trigger={
                                <Button type="button" variant="destructive">
                                    Delete role
                                </Button>
                            }
                        />
                    )
                }
            />
            <form onSubmit={submit} noValidate className="flex max-w-4xl flex-col gap-4">
                <Card>
                    <CardContent>
                        <Field data-invalid={!!errors.name || undefined} className="max-w-sm">
                            <FieldLabel htmlFor="name">Role name</FieldLabel>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. dt-operations"
                                aria-invalid={!!errors.name || undefined}
                                autoFocus={!editing}
                            />
                            {!errors.name && (
                                <FieldDescription>
                                    Lowercase letters, numbers and hyphens.
                                </FieldDescription>
                            )}
                            <FieldError>{errors.name}</FieldError>
                        </Field>
                    </CardContent>
                </Card>

                {errors.permissions && <FieldError>{errors.permissions}</FieldError>}

                <div className="grid gap-4 md:grid-cols-2">
                    {sections.map((section) => {
                        const names = section.permissions.map((p) => p.name);
                        const selectedCount = names.filter(has).length;
                        const all = selectedCount === names.length;
                        const some = selectedCount > 0 && !all;

                        return (
                            <Card key={section.key}>
                                <CardHeader className="flex flex-row items-center justify-between gap-2">
                                    <CardTitle>{section.label}</CardTitle>
                                    <Field orientation="horizontal" className="w-auto">
                                        <Checkbox
                                            id={`section-${section.key}`}
                                            checked={all ? true : some ? 'indeterminate' : false}
                                            onCheckedChange={(checked) =>
                                                setMany(names, checked === true)
                                            }
                                        />
                                        <FieldLabel
                                            htmlFor={`section-${section.key}`}
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            All
                                        </FieldLabel>
                                    </Field>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-2.5">
                                    {section.permissions.map((permission) => {
                                        const id = `perm-${permission.name}`;
                                        return (
                                            <Field key={permission.name} orientation="horizontal">
                                                <Checkbox
                                                    id={id}
                                                    checked={has(permission.name)}
                                                    onCheckedChange={(checked) =>
                                                        setMany([permission.name], checked === true)
                                                    }
                                                />
                                                <FieldLabel htmlFor={id} className="font-normal">
                                                    {permission.label}
                                                </FieldLabel>
                                            </Field>
                                        );
                                    })}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={processing || (editing && !isDirty)}>
                        {processing ? 'Saving…' : editing ? 'Save role' : 'Create role'}
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('roles.index')}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
