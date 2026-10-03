import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { CopyIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

/** Shows a newly generated temporary password exactly once. It is never stored in plain text. */
export function TemporaryPasswordDialog({ credentials }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (credentials?.password) setOpen(true);
    }, [credentials]);

    if (!credentials?.password) return null;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(credentials.password);
            toast.success('Password copied');
        } catch {
            toast.error('Copy failed — select the password and copy it manually.');
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Temporary password</AlertDialogTitle>
                    <AlertDialogDescription>
                        Share this with {credentials.emp_code} securely. It is shown only once; they
                        must change it and set up 2FA at first sign-in.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div className="flex items-center gap-2 rounded-md border bg-surface-3 p-3">
                    <code className="flex-1 font-mono text-base tracking-wide select-all">
                        {credentials.password}
                    </code>
                    <Button variant="outline" size="sm" onClick={copy}>
                        <CopyIcon /> Copy
                    </Button>
                </div>
                <AlertDialogFooter>
                    <AlertDialogAction>I've noted it</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
