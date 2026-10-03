import { OtpForm } from '@/Components/otp-form';
import { Button } from '@/Components/ui/button';
import AuthLayout from '@/Layouts/AuthLayout';
import { Link } from '@inertiajs/react';

export default function TwoFactorChallenge({ reconfirm }) {
    return (
        <AuthLayout
            title={reconfirm ? 'Confirm it’s you' : 'Two-factor authentication'}
            description={
                reconfirm
                    ? 'This area needs a fresh code from your authenticator app.'
                    : 'Enter the 6-digit code from your authenticator app.'
            }
            footer={
                <Button variant="link" size="sm" asChild>
                    <Link href={route('logout')} method="post" as="button">
                        Sign out
                    </Link>
                </Button>
            }
        >
            <OtpForm action={route('two-factor.verify')} />
        </AuthLayout>
    );
}
