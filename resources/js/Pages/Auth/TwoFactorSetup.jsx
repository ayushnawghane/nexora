import { OtpForm } from '@/Components/otp-form';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';
import AuthLayout from '@/Layouts/AuthLayout';
import { Link } from '@inertiajs/react';

export default function TwoFactorSetup({ qrCodeSvg, secret }) {
    return (
        <AuthLayout
            title="Set up two-factor authentication"
            description="Required for every account. Scan the code with Google Authenticator, Microsoft Authenticator or a similar app."
            footer={
                <Button variant="link" size="sm" asChild>
                    <Link href={route('logout')} method="post" as="button">
                        Sign out
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-col items-center gap-4">
                <div
                    className="rounded-lg bg-white p-3"
                    // SVG generated server-side by bacon-qr-code from the user's own secret.
                    dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                />
                <div className="text-center text-xs text-muted-foreground">
                    Can't scan? Enter this key manually:
                    <div className="mt-1 font-mono text-sm tracking-wider text-foreground select-all">
                        {secret}
                    </div>
                </div>
            </div>
            <Separator className="my-5" />
            <p className="mb-3 text-center text-sm text-muted-foreground">
                Then enter the 6-digit code the app shows
            </p>
            <OtpForm action={route('two-factor.confirm')} submitLabel="Turn on 2FA" />
        </AuthLayout>
    );
}
