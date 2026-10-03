import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Empty, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Textarea } from '@/Components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { ArrowRightIcon, Undo2Icon } from 'lucide-react';
import { useState } from 'react';

/** The mandatory "why" every God Mode write carries. Bound to form.data.reason. */
export function ReasonField({ form, id = 'god-reason' }) {
    return (
        <Field data-invalid={!!form.errors.reason || undefined}>
            <FieldLabel htmlFor={id}>
                Reason<span className="text-destructive">*</span>
            </FieldLabel>
            <Textarea
                id={id}
                rows={3}
                maxLength={2000}
                value={form.data.reason}
                onChange={(e) => form.setData('reason', e.target.value)}
                aria-invalid={!!form.errors.reason || undefined}
            />
            <FieldDescription>Kept permanently with the change, with your name.</FieldDescription>
            <FieldError>{form.errors.reason}</FieldError>
        </Field>
    );
}

/**
 * A God Mode action that only needs a reason (and optionally extra fields, rendered as children
 * with access to the form). The dialog stays open on validation errors so they can be fixed.
 */
export function ReasonDialog({
    trigger,
    title,
    description,
    action,
    data = {},
    confirmLabel = 'Confirm',
    destructive = false,
    children,
    errorKeys = [],
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '', ...data });

    const submit = (e) => {
        e.preventDefault();
        form.post(action, {
            preserveScroll: true,
            forceFormData: Object.values(form.data).some((v) => v instanceof File),
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <span onClick={() => setOpen(true)}>{trigger}</span>
            <Dialog open={open} onOpenChange={(o) => !form.processing && setOpen(o)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>{title}</DialogTitle>
                            {description && <DialogDescription>{description}</DialogDescription>}
                        </DialogHeader>
                        {children?.(form)}
                        <ReasonField form={form} />
                        {errorKeys.map((key) => (
                            <FieldError key={key}>{form.errors[key]}</FieldError>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant={destructive ? 'destructive' : 'default'}
                                disabled={form.processing}
                            >
                                {form.processing ? 'Working…' : confirmLabel}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function show(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    return String(value);
}

/** The God Mode change log for a record (or everything, on the start page), newest first. */
export function History({ changes, showUndo = true }) {
    if (changes.length === 0) {
        return (
            <Empty className="border">
                <EmptyHeader>
                    <EmptyTitle>No God Mode changes yet</EmptyTitle>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <ul className="flex flex-col gap-3">
            {changes.map((change) => (
                <li key={change.id} className="flex flex-col gap-2 rounded-lg border p-3">
                    <div className="flex flex-wrap items-center gap-2 text-[13px]">
                        <span className="font-medium">{change.what}</span>
                        {change.is_undo && <Badge variant="warning">Undo</Badge>}
                        {change.undone && <Badge variant="neutral">Undone</Badge>}
                        <span className="text-muted-foreground">
                            {change.by} · {formatDateTime(change.at)}
                        </span>
                        {showUndo && change.can_undo && (
                            <span className="ml-auto">
                                <ReasonDialog
                                    action={route('god-mode.rollback', change.id)}
                                    title="Undo this correction?"
                                    description="The old values go back, checked against today's rules. Refused if the record has changed since."
                                    confirmLabel="Undo"
                                    destructive
                                    errorKeys={['rollback']}
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            <Undo2Icon /> Undo
                                        </Button>
                                    }
                                />
                            </span>
                        )}
                    </div>
                    <p className="text-[13px] text-muted-foreground">“{change.reason}”</p>
                    {change.diff.length > 0 && (
                        <ul className="flex flex-col gap-1 rounded-md bg-surface-3 px-3 py-2 text-xs">
                            {change.diff.map((row) => (
                                <li key={row.field} className="flex flex-wrap items-center gap-1.5">
                                    <span className="font-mono text-muted-foreground">
                                        {row.field}
                                    </span>
                                    <span className="break-all line-through decoration-danger/60">
                                        {show(row.before)}
                                    </span>
                                    <ArrowRightIcon className="size-3 shrink-0 text-muted-foreground" />
                                    <span className="break-all">{show(row.after)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ul>
    );
}
