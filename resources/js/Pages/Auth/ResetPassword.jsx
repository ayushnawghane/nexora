import { PASSWORD_RULE_TEXT } from '@/Components/password-form';
import { Button } from '@/Components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import AuthLayout from '@/Layouts/AuthLayout';
import { useForm } from '@inertiajs/react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Choose a new password">
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!errors.email || undefined}>
                        <FieldLabel htmlFor="email">Work email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                            aria-invalid={!!errors.email || undefined}
                        />
                        <FieldError>{errors.email}</FieldError>
                    </Field>
                    <Field data-invalid={!!errors.password || undefined}>
                        <FieldLabel htmlFor="password">New password</FieldLabel>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            autoFocus
                            aria-invalid={!!errors.password || undefined}
                        />
                        {!errors.password && (
                            <FieldDescription>{PASSWORD_RULE_TEXT}</FieldDescription>
                        )}
                        <FieldError>{errors.password}</FieldError>
                    </Field>
                    <Field data-invalid={!!errors.password_confirmation || undefined}>
                        <FieldLabel htmlFor="password_confirmation">
                            Confirm new password
                        </FieldLabel>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                            aria-invalid={!!errors.password_confirmation || undefined}
                        />
                        <FieldError>{errors.password_confirmation}</FieldError>
                    </Field>
                    <Button type="submit" size="lg" className="w-full" disabled={processing}>
                        {processing ? 'Saving…' : 'Reset password'}
                    </Button>
                </FieldGroup>
            </form>
        </AuthLayout>
    );
}
