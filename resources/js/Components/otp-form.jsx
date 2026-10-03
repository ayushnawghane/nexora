import { Button } from '@/Components/ui/button';
import { Field, FieldError, FieldGroup } from '@/Components/ui/field';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSeparator,
    InputOTPSlot,
} from '@/Components/ui/input-otp';
import { useForm } from '@inertiajs/react';

/** 6-digit authenticator code entry, shared by 2FA setup and challenge. Submits automatically. */
export function OtpForm({ action, submitLabel = 'Verify' }) {
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });

    const submit = (code = data.code) => {
        if (processing || code.length !== 6) return;
        post(action, { onError: () => reset('code'), preserveScroll: true });
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <FieldGroup>
                <Field data-invalid={!!errors.code || undefined} className="items-center">
                    <InputOTP
                        maxLength={6}
                        value={data.code}
                        onChange={(value) => setData('code', value.replace(/\D/g, ''))}
                        onComplete={(value) => submit(value)}
                        autoFocus
                        inputMode="numeric"
                        aria-label="Authenticator code"
                        aria-invalid={!!errors.code || undefined}
                    >
                        <InputOTPGroup>
                            <InputOTPSlot index={0} />
                            <InputOTPSlot index={1} />
                            <InputOTPSlot index={2} />
                        </InputOTPGroup>
                        <InputOTPSeparator />
                        <InputOTPGroup>
                            <InputOTPSlot index={3} />
                            <InputOTPSlot index={4} />
                            <InputOTPSlot index={5} />
                        </InputOTPGroup>
                    </InputOTP>
                    <FieldError className="text-center">{errors.code}</FieldError>
                </Field>
                <Button
                    type="submit"
                    size="lg"
                    className="w-full"
                    disabled={processing || data.code.length !== 6}
                >
                    {processing ? 'Checking…' : submitLabel}
                </Button>
            </FieldGroup>
        </form>
    );
}
