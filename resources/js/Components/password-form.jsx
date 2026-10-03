import { Button } from '@/Components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { useForm } from '@inertiajs/react';

export const PASSWORD_RULE_TEXT =
    'At least 10 characters with upper and lower case letters, a number and a symbol.';

/** Change-password form (current + new + confirm), used on the forced change page and in Profile. */
export function PasswordForm({ submitLabel = 'Update password', onSuccess }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onSuccess?.();
            },
            onError: () => reset('password', 'password_confirmation'),
        });
    };

    const field = (name, label, autoComplete, description) => (
        <Field data-invalid={!!errors[name] || undefined}>
            <FieldLabel htmlFor={name}>{label}</FieldLabel>
            <Input
                id={name}
                type="password"
                value={data[name]}
                onChange={(e) => setData(name, e.target.value)}
                autoComplete={autoComplete}
                required
                aria-invalid={!!errors[name] || undefined}
            />
            {description && !errors[name] && <FieldDescription>{description}</FieldDescription>}
            <FieldError>{errors[name]}</FieldError>
        </Field>
    );

    return (
        <form onSubmit={submit} noValidate>
            <FieldGroup>
                {field('current_password', 'Current password', 'current-password')}
                {field('password', 'New password', 'new-password', PASSWORD_RULE_TEXT)}
                {field('password_confirmation', 'Confirm new password', 'new-password')}
                <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                    {processing ? 'Saving…' : submitLabel}
                </Button>
            </FieldGroup>
        </form>
    );
}
