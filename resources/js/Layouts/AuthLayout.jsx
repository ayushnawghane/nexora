import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Head } from '@inertiajs/react';
import { Hexagon } from 'lucide-react';

/** Centered card layout for login, 2FA and password pages (shadcn login-03 block). */
export default function AuthLayout({ title, description, children, footer }) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <Head title={title} />
            <div className="flex w-full max-w-sm flex-col gap-6">
                <div className="flex items-center gap-2 self-center font-medium">
                    <div className="flex size-7 items-center justify-center rounded-md bg-primary text-primary-foreground shadow-brand">
                        <Hexagon className="size-4" />
                    </div>
                    Nexora
                </div>
                <Card>
                    <CardHeader className="text-center">
                        <CardTitle className="text-xl">{title}</CardTitle>
                        {description && <CardDescription>{description}</CardDescription>}
                    </CardHeader>
                    <CardContent>{children}</CardContent>
                </Card>
                {footer && (
                    <p className="px-6 text-center text-xs text-muted-foreground">{footer}</p>
                )}
            </div>
        </div>
    );
}
