import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title="Reset your password"
            description="Enter your work email and we'll send you a reset link."
            footer={
                <Link href={route('login')} className="underline-offset-4 hover:underline">
                    Back to sign in
                </Link>
            }
        >
            {status && (
                <Alert className="mb-4">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!errors.email || undefined}>
                        <FieldLabel htmlFor="email">Work email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="email"
                            autoFocus
                            required
                            aria-invalid={!!errors.email || undefined}
                        />
                        <FieldError>{errors.email}</FieldError>
                    </Field>
                    <Button type="submit" size="lg" className="w-full" disabled={processing}>
                        {processing ? 'Sending…' : 'Send reset link'}
                    </Button>
                </FieldGroup>
            </form>
        </AuthLayout>
    );
}
