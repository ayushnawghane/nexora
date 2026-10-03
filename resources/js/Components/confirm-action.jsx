import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';

/**
 * Confirmation step for actions that change important state. Sends the request only after the
 * user confirms, and keeps the dialog open (with a disabled button) until the server responds so
 * the action can't be fired twice. If the server refuses it (a validation error), the dialog closes
 * and the reason is shown as a toast, since there is no form field to show it under.
 */
export function ConfirmAction({
    trigger,
    title,
    description,
    confirmLabel = 'Confirm',
    destructive = false,
    method = 'post',
    href,
    data = {},
    onSuccess,
    preserveState = false,
}) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const run = (e) => {
        e.preventDefault();
        router.visit(href, {
            method,
            data,
            preserveScroll: true,
            preserveState,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setOpen(false);
                onSuccess?.();
            },
            onError: (errors) => {
                setOpen(false);
                const message = Object.values(errors)[0];
                if (message) toast.error(message);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={(next) => !processing && setOpen(next)}>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && <AlertDialogDescription>{description}</AlertDialogDescription>}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={run}
                        disabled={processing}
                        className={
                            destructive
                                ? 'bg-destructive text-white hover:bg-destructive/90 hover:shadow-none'
                                : undefined
                        }
                    >
                        {processing ? 'Working…' : confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
