import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Link, useForm } from '@inertiajs/react';

export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        emp_code: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout
            title="Sign in"
            description="Use your employee code and password"
            footer="Access is restricted to authorised Beacon staff. All sign-ins are logged."
        >
            {status && (
                <Alert className="mb-4">
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!errors.emp_code || undefined}>
                        <FieldLabel htmlFor="emp_code">Employee code</FieldLabel>
                        <Input
                            id="emp_code"
                            value={data.emp_code}
                            onChange={(e) => setData('emp_code', e.target.value.toUpperCase())}
                            autoComplete="username"
                            autoFocus
                            required
                            aria-invalid={!!errors.emp_code || undefined}
                        />
                        <FieldError>{errors.emp_code}</FieldError>
                    </Field>
                    <Field data-invalid={!!errors.password || undefined}>
                        <div className="flex items-center">
                            <FieldLabel htmlFor="password">Password</FieldLabel>
                            <Link
                                href={route('password.request')}
                                className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
                            required
                            aria-invalid={!!errors.password || undefined}
                        />
                        <FieldError>{errors.password}</FieldError>
                    </Field>
                    <Field orientation="horizontal">
                        <Checkbox
                            id="remember"
                            checked={data.remember}
                            onCheckedChange={(v) => setData('remember', v === true)}
                        />
                        <FieldLabel htmlFor="remember" className="font-normal">
                            Keep me signed in on this device
                        </FieldLabel>
                    </Field>
                    <Button type="submit" size="lg" disabled={processing} className="w-full">
                        {processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </FieldGroup>
            </form>
        </AuthLayout>
    );
}
