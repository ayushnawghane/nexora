import { PageHeader } from '@/Components/page-header';
import { PasswordForm } from '@/Components/password-form';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { useForm, usePage } from '@inertiajs/react';

export default function Edit() {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <AppLayout title="Profile">
            <PageHeader title="Profile" description={`Employee code ${user.emp_code}`} />
            <div className="grid max-w-3xl gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Your details</CardTitle>
                        <CardDescription>Name and work email used across Nexora.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} noValidate>
                            <FieldGroup>
                                <Field data-invalid={!!errors.name || undefined}>
                                    <FieldLabel htmlFor="name">Name</FieldLabel>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        autoComplete="name"
                                        aria-invalid={!!errors.name || undefined}
                                    />
                                    <FieldError>{errors.name}</FieldError>
                                </Field>
                                <Field data-invalid={!!errors.email || undefined}>
                                    <FieldLabel htmlFor="email">Work email</FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        autoComplete="email"
                                        aria-invalid={!!errors.email || undefined}
                                    />
                                    <FieldError>{errors.email}</FieldError>
                                </Field>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full sm:w-auto"
                                >
                                    {processing ? 'Saving…' : 'Save'}
                                </Button>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Password</CardTitle>
                        <CardDescription>
                            Passwords expire every 90 days and can't be reused.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PasswordForm />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
