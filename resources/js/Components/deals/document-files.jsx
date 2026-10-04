import { TextField } from '@/Components/form-fields';
import { Button } from '@/Components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { ChevronDownIcon, FileDownIcon } from 'lucide-react';
import { useState } from 'react';

/*
 * Files on deal documents, CP/CS items and executions: links, history and the upload and reason
 * dialogs those screens share.
 */

// Mirrors App\Models\DocumentFile: accepted types, 20 MB per file, 10 files per upload.
export const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png';
export const MAX_BYTES = 20 * 1024 * 1024;
export const MAX_FILES = 10;

export function formatBytes(bytes) {
    if (!bytes) return null;
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Client-side copy of the server's file rules, so a wrong file is caught before uploading. */
export function fileProblem(
    files,
    { accept = ACCEPT, label = 'PDF, Word, Excel or image files' } = {},
) {
    if (files.length === 0) return 'Choose a file.';
    if (files.length > MAX_FILES) return `Upload at most ${MAX_FILES} files at a time.`;
    const ext = new Set(accept.split(','));
    for (const file of files) {
        const dot = file.name.lastIndexOf('.');
        if (dot < 0 || !ext.has(file.name.slice(dot).toLowerCase())) {
            return `${file.name}: upload ${label} only.`;
        }
        if (file.size > MAX_BYTES) return `${file.name} is larger than 20 MB.`;
    }
    return null;
}

export function FileLink({ dealId, file }) {
    if (!file.available) {
        return (
            <span className="flex min-w-0 items-start gap-1.5 text-[13px] text-muted-foreground">
                <FileDownIcon className="mt-0.5 size-4 shrink-0" />
                <span className="min-w-0 [overflow-wrap:anywhere]">
                    {file.name}{' '}
                    <span className="text-xs whitespace-nowrap text-subtle-foreground">
                        · not copied from Stack yet
                    </span>
                </span>
            </span>
        );
    }
    return (
        <a
            href={route('deals.files.download', [dealId, file.id])}
            className="flex min-w-0 items-start gap-1.5 text-[13px] text-brand-text hover:underline"
        >
            <FileDownIcon className="mt-0.5 size-4 shrink-0" />
            <span className="min-w-0 [overflow-wrap:anywhere]">
                {file.name}
                {file.size && (
                    <span className="text-xs whitespace-nowrap text-muted-foreground">
                        {' '}
                        · {formatBytes(file.size)}
                    </span>
                )}
            </span>
        </a>
    );
}

export function UploadDialog({ title, description, href, multiple, open, onOpenChange }) {
    const field = multiple ? 'files' : 'file';
    const form = useForm({ [field]: multiple ? [] : null });
    const [problem, setProblem] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        const files = multiple ? form.data.files : form.data.file ? [form.data.file] : [];
        const issue = fileProblem(files);
        setProblem(issue);
        if (issue) return;
        form.post(href, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const error =
        problem ??
        form.errors[field] ??
        Object.entries(form.errors).find(([k]) => k.startsWith(`${field}.`))?.[1];

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <Field data-invalid={!!error || undefined}>
                        <FieldLabel htmlFor="document-upload">
                            {multiple ? 'Files' : 'File'}
                            <span className="text-destructive">*</span>
                        </FieldLabel>
                        <Input
                            id="document-upload"
                            type="file"
                            accept={ACCEPT}
                            multiple={multiple}
                            onChange={(e) => {
                                const chosen = Array.from(e.target.files ?? []);
                                setProblem(null);
                                form.setData(field, multiple ? chosen : (chosen[0] ?? null));
                            }}
                            aria-invalid={!!error || undefined}
                        />
                        <FieldDescription>
                            PDF, Word, Excel or image, up to 20 MB each
                            {multiple ? `, ${MAX_FILES} at a time.` : '.'}
                        </FieldDescription>
                        <FieldError>{error}</FieldError>
                    </Field>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Uploading…' : 'Upload'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function History({ dealId, label, files }) {
    if (files.length === 0) return null;
    return (
        <Collapsible>
            <CollapsibleTrigger className="group inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                {label}
                <ChevronDownIcon className="size-3.5 transition-transform group-data-[state=open]:rotate-180" />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul className="mt-1.5 flex flex-col gap-1 border-l pl-3">
                    {files.map((file) => (
                        <li key={file.id} className="flex flex-col gap-0.5">
                            <FileLink dealId={dealId} file={file} />
                            <span className="text-xs text-muted-foreground">
                                Uploaded by {file.uploaded_by}, {formatDateTime(file.uploaded_at)}
                                {file.removed &&
                                    ` · removed by ${file.removed_by}, ${formatDateTime(file.removed_at)}`}
                            </span>
                        </li>
                    ))}
                </ul>
            </CollapsibleContent>
        </Collapsible>
    );
}

export function ReasonDialog({
    title,
    description,
    label,
    href,
    field,
    method = 'post',
    submitLabel,
    destructive,
    open,
    onOpenChange,
}) {
    const form = useForm({ [field]: '', ...(field === 'comment' ? { decision: 'returned' } : {}) });

    const submit = (e) => {
        e.preventDefault();
        form.submit(method, href, { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <TextField
                        form={form}
                        name={field}
                        label={label}
                        required
                        multiline
                        rows={3}
                        maxLength={2000}
                        autoFocus
                    />
                    <FieldError>{form.errors.decision}</FieldError>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={form.processing}
                        >
                            {form.processing ? 'Saving…' : submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
