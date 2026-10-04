import { ConfirmAction } from '@/Components/confirm-action';
import { ComboField, SelectField, TextField } from '@/Components/form-fields';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { formatDate, formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import {
    ChevronDownIcon,
    FileDownIcon,
    MoreHorizontalIcon,
    PlusIcon,
    UploadIcon,
    XIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';

// Mirrors App\Models\DocumentFile: accepted types, 20 MB per file, 10 files per upload.
const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png';
const MAX_BYTES = 20 * 1024 * 1024;
const MAX_FILES = 10;

const STAGES = [
    {
        value: 'precedent',
        short: 'CP',
        title: 'Conditions precedent',
        description: 'Documents needed before the issue.',
    },
    {
        value: 'subsequent',
        short: 'CS',
        title: 'Conditions subsequent',
        description: 'Documents due after the issue.',
    },
];

function formatBytes(bytes) {
    if (!bytes) return null;
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Client-side copy of the server's file rules, so a wrong file is caught before uploading. */
function fileProblem(files) {
    if (files.length === 0) return 'Choose a file.';
    if (files.length > MAX_FILES) return `Upload at most ${MAX_FILES} files at a time.`;
    const ext = new Set(ACCEPT.split(','));
    for (const file of files) {
        const dot = file.name.lastIndexOf('.');
        if (dot < 0 || !ext.has(file.name.slice(dot).toLowerCase())) {
            return `${file.name}: upload PDF, Word, Excel or image files only.`;
        }
        if (file.size > MAX_BYTES) return `${file.name} is larger than 20 MB.`;
    }
    return null;
}

function FileLink({ dealId, file }) {
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

function UploadDialog({ title, description, href, multiple, open, onOpenChange }) {
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

function History({ dealId, label, files }) {
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

// ---------------------------------------------------------------- legal documents

function AddDocumentDialog({ dealId, options, open, onOpenChange }) {
    const form = useForm({ legal_document_type_id: null, kind: 'standard' });
    const chosen = options.types.find((t) => t.value === form.data.legal_document_type_id);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.documents.store', dealId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Add a legal document</DialogTitle>
                        <DialogDescription>
                            Add the document itself, or another copy, a supplement or an amendment
                            of one already on the deal.
                        </DialogDescription>
                    </DialogHeader>
                    <ComboField
                        form={form}
                        name="legal_document_type_id"
                        label="Document"
                        required
                        items={options.types}
                        placeholder="Choose a document…"
                    />
                    <SelectField
                        form={form}
                        name="kind"
                        label="Add as"
                        required
                        items={options.kinds}
                    />
                    {chosen?.on_deal && form.data.kind === 'standard' && (
                        <p className="text-[13px] text-warning">
                            {chosen.label} is already on this deal. Add another copy, a supplement
                            or an amendment instead.
                        </p>
                    )}
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
                            disabled={form.processing || !form.data.legal_document_type_id}
                        >
                            {form.processing ? 'Adding…' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DocumentRow({ dealId, document, canManage }) {
    const [uploading, setUploading] = useState(false);
    const { current } = document;

    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[13px] font-medium">{document.name}</span>
                    {document.kind !== 'standard' && (
                        <Badge variant="neutral">{document.kind_label}</Badge>
                    )}
                </div>
                {current ? (
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <FileLink dealId={dealId} file={current} />
                        <span className="text-xs text-muted-foreground">
                            Execution version uploaded by {current.uploaded_by},{' '}
                            {formatDateTime(current.uploaded_at)}
                        </span>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        No execution version uploaded yet.
                    </span>
                )}
                <History
                    dealId={dealId}
                    label={`${document.history.length} earlier ${document.history.length === 1 ? 'file' : 'files'}`}
                    files={document.history}
                />
            </div>
            {canManage && (
                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" onClick={() => setUploading(true)}>
                        <UploadIcon /> {current ? 'Replace' : 'Upload'}
                    </Button>
                    {current ? (
                        <ConfirmAction
                            href={route('deals.files.destroy', [dealId, current.id])}
                            method="delete"
                            title={`Remove the file of “${document.name}”?`}
                            description="The file stays in the document's history."
                            confirmLabel="Remove file"
                            destructive
                            trigger={
                                <Button size="sm" variant="ghost">
                                    Remove file
                                </Button>
                            }
                        />
                    ) : (
                        <ConfirmAction
                            href={route('deals.documents.destroy', [dealId, document.id])}
                            method="delete"
                            title={`Remove “${document.name}” from this deal?`}
                            description="It stays in the deal's activity history."
                            confirmLabel="Remove"
                            destructive
                            trigger={
                                <Button size="sm" variant="ghost">
                                    Remove
                                </Button>
                            }
                        />
                    )}
                </div>
            )}
            {uploading && (
                <UploadDialog
                    title={`${current ? 'Replace' : 'Upload'} execution version`}
                    description={`${document.name}. ${current ? 'The current file moves to the history.' : ''}`}
                    href={route('deals.documents.upload', [dealId, document.id])}
                    open
                    onOpenChange={() => setUploading(false)}
                />
            )}
        </li>
    );
}

function LegalDocuments({ dealId, documents, options, canManage }) {
    const [adding, setAdding] = useState(false);
    const uploaded = documents.filter((d) => d.current).length;
    const groups = useMemo(() => {
        const byCategory = new Map();
        for (const d of documents) {
            if (!byCategory.has(d.category))
                byCategory.set(d.category, { label: d.category_label, items: [] });
            byCategory.get(d.category).items.push(d);
        }
        return [...byCategory.values()];
    }, [documents]);

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                <div className="flex flex-col gap-1.5">
                    <CardTitle>Legal documents</CardTitle>
                    <CardDescription>
                        {documents.length > 0
                            ? `${uploaded} of ${documents.length} have an execution version.`
                            : 'The documents this deal is executed under.'}
                    </CardDescription>
                </div>
                {canManage && (
                    <Button size="sm" onClick={() => setAdding(true)}>
                        <PlusIcon /> Add document
                    </Button>
                )}
            </CardHeader>
            <CardContent className="px-0">
                {documents.length === 0 ? (
                    <Empty className="mx-4 mb-4 border">
                        <EmptyHeader>
                            <EmptyTitle>No legal documents yet</EmptyTitle>
                            <EmptyDescription>
                                Add the trust deed, hypothecation deed and the other documents of
                                this deal.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    groups.map((group) => (
                        <div key={group.label}>
                            <div className="border-t bg-surface-2 px-4 py-1.5 text-xs font-medium text-muted-foreground">
                                {group.label}
                            </div>
                            <ul className="divide-y border-t">
                                {group.items.map((d) => (
                                    <DocumentRow
                                        key={d.id}
                                        dealId={dealId}
                                        document={d}
                                        canManage={canManage}
                                    />
                                ))}
                            </ul>
                        </div>
                    ))
                )}
            </CardContent>
            {adding && (
                <AddDocumentDialog
                    dealId={dealId}
                    options={options}
                    open
                    onOpenChange={() => setAdding(false)}
                />
            )}
        </Card>
    );
}

// ---------------------------------------------------------------- CP / CS

function AddConditionDialog({ dealId, stage, options, issue, open, onOpenChange }) {
    const choices = useMemo(
        () =>
            options.conditions
                .filter((c) => c.stage === stage.value)
                .sort((a, b) => Number(b.suggested) - Number(a.suggested)),
        [options.conditions, stage.value],
    );
    const form = useForm({
        stage: stage.value,
        source: choices.length > 0 ? 'master' : 'custom',
        document_ids: [],
        name: '',
        issuing_authority_id: null,
        due_on: '',
    });
    const [search, setSearch] = useState('');
    const visible = choices.filter((c) =>
        `${c.label} ${c.authority ?? ''}`.toLowerCase().includes(search.trim().toLowerCase()),
    );

    const toggle = (id, on) =>
        form.setData(
            'document_ids',
            on ? [...form.data.document_ids, id] : form.data.document_ids.filter((v) => v !== id),
        );

    const submit = (e) => {
        e.preventDefault();
        form.post(route('deals.conditions.store', dealId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const listError =
        form.errors.document_ids ??
        Object.entries(form.errors).find(([k]) => k.startsWith('document_ids.'))?.[1];

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={submit} noValidate className="flex min-w-0 flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Add {stage.short} items</DialogTitle>
                        <DialogDescription>
                            {issue
                                ? `Suggested documents are the ones set for ${issue.toLowerCase()} issues.`
                                : 'Choose from the list, or write one for this deal only.'}
                        </DialogDescription>
                    </DialogHeader>
                    <Tabs value={form.data.source} onValueChange={(v) => form.setData('source', v)}>
                        <TabsList className="w-full">
                            <TabsTrigger value="master">From the list</TabsTrigger>
                            <TabsTrigger value="custom">Write your own</TabsTrigger>
                        </TabsList>
                        <TabsContent value="master" className="flex min-w-0 flex-col gap-2 pt-2">
                            {choices.length === 0 ? (
                                <p className="text-[13px] text-muted-foreground">
                                    Every {stage.short} document in the list is already on this
                                    deal.
                                </p>
                            ) : (
                                <>
                                    <Input
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Search documents…"
                                        aria-label="Search documents"
                                    />
                                    <div className="flex max-h-72 flex-col gap-1 overflow-y-auto rounded-md border p-2">
                                        {visible.map((c) => {
                                            const id = `condition-${c.value}`;
                                            return (
                                                <Field
                                                    key={c.value}
                                                    orientation="horizontal"
                                                    className="items-start rounded px-1 py-1.5 hover:bg-surface-3"
                                                >
                                                    <Checkbox
                                                        id={id}
                                                        checked={form.data.document_ids.includes(
                                                            c.value,
                                                        )}
                                                        onCheckedChange={(on) =>
                                                            toggle(c.value, on === true)
                                                        }
                                                    />
                                                    <FieldLabel
                                                        htmlFor={id}
                                                        className="flex min-w-0 flex-col items-start gap-0.5 font-normal"
                                                    >
                                                        <span className="break-words">
                                                            {c.label}
                                                        </span>
                                                        <span className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                            {c.authority}
                                                            {c.suggested && (
                                                                <Badge variant="success">
                                                                    Suggested
                                                                </Badge>
                                                            )}
                                                        </span>
                                                    </FieldLabel>
                                                </Field>
                                            );
                                        })}
                                        {visible.length === 0 && (
                                            <p className="px-1 py-2 text-[13px] text-muted-foreground">
                                                No match found.
                                            </p>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {form.data.document_ids.length} chosen
                                    </p>
                                    {listError && (
                                        <p className="text-[13px] text-destructive">{listError}</p>
                                    )}
                                </>
                            )}
                        </TabsContent>
                        <TabsContent value="custom" className="flex flex-col gap-4 pt-2">
                            <TextField
                                form={form}
                                name="name"
                                label="Document"
                                required
                                multiline
                                rows={2}
                                maxLength={2000}
                            />
                            <ComboField
                                form={form}
                                name="issuing_authority_id"
                                label="Issuing authority"
                                items={options.authorities}
                                placeholder="Choose…"
                            />
                        </TabsContent>
                    </Tabs>
                    <TextField
                        form={form}
                        name="due_on"
                        label="Due date"
                        type="date"
                        min="2000-01-01"
                        max="2100-12-31"
                        hint="Optional. Items past their due date are flagged as overdue."
                        className="w-48"
                    />
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
                            disabled={
                                form.processing ||
                                (form.data.source === 'master' &&
                                    form.data.document_ids.length === 0)
                            }
                        >
                            {form.processing ? 'Adding…' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ReasonDialog({
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

function DueDateDialog({ dealId, condition, open, onOpenChange }) {
    const form = useForm({ due_on: condition.due_on ?? '' });

    const submit = (e) => {
        e.preventDefault();
        form.put(route('deals.conditions.due-date', [dealId, condition.id]), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !form.processing && onOpenChange(o)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Due date</DialogTitle>
                        <DialogDescription>{condition.name}</DialogDescription>
                    </DialogHeader>
                    <TextField
                        form={form}
                        name="due_on"
                        label="Due date"
                        type="date"
                        min="2000-01-01"
                        max="2100-12-31"
                        hint="Leave empty for no due date."
                        className="w-48"
                    />
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
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ConditionRow({ dealId, condition, can }) {
    const [dialog, setDialog] = useState(null);
    const close = () => setDialog(null);
    const c = condition;
    const canManage = can.manageDocuments;
    const hasMenu = canManage && (c.is_open || c.can_remove);

    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-start sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="text-[13px] font-medium break-words">{c.name}</span>
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <Badge variant={c.status_tone}>{c.status_label}</Badge>
                    {c.overdue && <Badge variant="danger">Overdue</Badge>}
                    {c.issuing_authority && <span>{c.issuing_authority}</span>}
                    {c.due_on && <span>Due {formatDate(c.due_on)}</span>}
                    {!c.from_master && <span>Written for this deal</span>}
                </div>
                {c.files.length > 0 && (
                    <ul className="flex flex-col gap-1 pt-0.5">
                        {c.files.map((file) => (
                            <li key={file.id} className="flex min-w-0 items-center gap-1">
                                <FileLink dealId={dealId} file={file} />
                                {canManage && c.is_open && (
                                    <ConfirmAction
                                        href={route('deals.files.destroy', [dealId, file.id])}
                                        method="delete"
                                        title={`Remove ${file.name}?`}
                                        description="The file stays on record as removed."
                                        confirmLabel="Remove file"
                                        destructive
                                        trigger={
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                className="size-7 shrink-0"
                                                aria-label={`Remove ${file.name}`}
                                            >
                                                <XIcon />
                                            </Button>
                                        }
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <div className="flex flex-col gap-0.5 text-xs text-muted-foreground">
                    {c.submitted_by && (
                        <span>
                            Sent for checking by {c.submitted_by}, {formatDateTime(c.submitted_at)}
                        </span>
                    )}
                    {c.checker && (
                        <span>
                            {c.status === 'verified' ? 'Verified' : 'Sent back'} by {c.checker},{' '}
                            {formatDateTime(c.checked_at)}
                            {c.checker_comment && ` · “${c.checker_comment}”`}
                        </span>
                    )}
                    {c.waived_reason && <span>Not applicable: “{c.waived_reason}”</span>}
                </div>
                <History
                    dealId={dealId}
                    label={`${c.removed_files.length} removed ${c.removed_files.length === 1 ? 'file' : 'files'}`}
                    files={c.removed_files}
                />
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {canManage && c.is_open && (
                    <Button size="sm" variant="outline" onClick={() => setDialog('upload')}>
                        <UploadIcon /> Upload
                    </Button>
                )}
                {can.verifyDocuments && c.can_check && (
                    <>
                        <ConfirmAction
                            href={route('deals.conditions.check', [dealId, c.id])}
                            data={{ decision: 'verified' }}
                            title={`Verify “${c.name}”?`}
                            description="A verified item can't be changed afterwards."
                            confirmLabel="Verify"
                            trigger={<Button size="sm">Verify</Button>}
                        />
                        <Button size="sm" variant="destructive" onClick={() => setDialog('return')}>
                            Send back
                        </Button>
                    </>
                )}
                {can.verifyDocuments && c.status === 'submitted' && c.is_mine && (
                    <span className="text-xs text-muted-foreground">
                        You uploaded these files; someone else checks them.
                    </span>
                )}
                {hasMenu && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                className="size-8"
                                aria-label="More actions"
                            >
                                <MoreHorizontalIcon />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {c.is_open && (
                                <DropdownMenuItem onSelect={() => setDialog('due')}>
                                    Set due date
                                </DropdownMenuItem>
                            )}
                            {c.is_open && (
                                <DropdownMenuItem onSelect={() => setDialog('waive')}>
                                    Mark not applicable
                                </DropdownMenuItem>
                            )}
                            {c.can_remove && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setDialog('remove')}
                                >
                                    Remove
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            {dialog === 'upload' && (
                <UploadDialog
                    title="Upload files"
                    description={`${c.name}. Uploading sends the item for checking by someone else.`}
                    href={route('deals.conditions.upload', [dealId, c.id])}
                    multiple
                    open
                    onOpenChange={close}
                />
            )}
            {dialog === 'return' && (
                <ReasonDialog
                    title={`Send back: ${c.name}`}
                    description="Tell the maker what needs fixing."
                    label="What needs fixing"
                    field="comment"
                    href={route('deals.conditions.check', [dealId, c.id])}
                    submitLabel="Send back"
                    destructive
                    open
                    onOpenChange={close}
                />
            )}
            {dialog === 'waive' && (
                <ReasonDialog
                    title="Mark not applicable"
                    description={`${c.name}. Its files stay on record.`}
                    label="Why it doesn't apply"
                    field="reason"
                    href={route('deals.conditions.waive', [dealId, c.id])}
                    submitLabel="Mark not applicable"
                    open
                    onOpenChange={close}
                />
            )}
            {dialog === 'due' && (
                <DueDateDialog dealId={dealId} condition={c} open onOpenChange={close} />
            )}
            {dialog === 'remove' && (
                <RemoveConditionDialog dealId={dealId} condition={c} onClose={close} />
            )}
        </li>
    );
}

/** Opened from the row menu, so it controls its own open state instead of using a trigger. */
function RemoveConditionDialog({ dealId, condition, onClose }) {
    const form = useForm({});

    return (
        <Dialog open onOpenChange={(o) => !o && !form.processing && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove “{condition.name}”?</DialogTitle>
                    <DialogDescription>
                        Use this for an item added by mistake. It stays in the activity history.
                    </DialogDescription>
                </DialogHeader>
                <FieldError>{form.errors.condition}</FieldError>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={form.processing}>
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={form.processing}
                        onClick={() =>
                            form.delete(route('deals.conditions.destroy', [dealId, condition.id]), {
                                preserveScroll: true,
                                onSuccess: onClose,
                            })
                        }
                    >
                        {form.processing ? 'Removing…' : 'Remove'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ConditionsCard({ dealId, stage, items, options, issue, can }) {
    const [adding, setAdding] = useState(false);
    const applicable = items.filter((c) => c.status !== 'waived');
    const verified = items.filter((c) => c.status === 'verified').length;
    const overdue = items.filter((c) => c.overdue).length;
    const waiting = items.filter((c) => c.status === 'submitted').length;

    const summary = [
        `${verified} of ${applicable.length} verified`,
        waiting > 0 && `${waiting} waiting for a check`,
        overdue > 0 && `${overdue} overdue`,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 pb-4">
                <div className="flex flex-col gap-1.5">
                    <CardTitle>
                        {stage.title} ({stage.short})
                    </CardTitle>
                    <CardDescription>
                        {items.length > 0
                            ? `${summary}. The checker can never be the uploader.`
                            : stage.description}
                    </CardDescription>
                </div>
                {can.manageDocuments && (
                    <Button size="sm" onClick={() => setAdding(true)}>
                        <PlusIcon /> Add {stage.short} items
                    </Button>
                )}
            </CardHeader>
            <CardContent className="px-0">
                {items.length === 0 ? (
                    <Empty className="mx-4 mb-4 border">
                        <EmptyHeader>
                            <EmptyTitle>No {stage.short} items yet</EmptyTitle>
                            <EmptyDescription>{stage.description}</EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="divide-y border-t">
                        {items.map((c) => (
                            <ConditionRow key={c.id} dealId={dealId} condition={c} can={can} />
                        ))}
                    </ul>
                )}
            </CardContent>
            {adding && (
                <AddConditionDialog
                    dealId={dealId}
                    stage={stage}
                    options={options}
                    issue={issue}
                    open
                    onOpenChange={() => setAdding(false)}
                />
            )}
        </Card>
    );
}

/** The deal's documentation: its legal documents and its CP / CS checklists. */
export function DocumentationPanel({ dealId, documentation, can }) {
    return (
        <div className="flex flex-col gap-4">
            <LegalDocuments
                dealId={dealId}
                documents={documentation.documents}
                options={documentation.options}
                canManage={can.manageDocuments}
            />
            {STAGES.map((stage) => (
                <ConditionsCard
                    key={stage.value}
                    dealId={dealId}
                    stage={stage}
                    items={documentation.conditions.filter((c) => c.stage === stage.value)}
                    options={documentation.options}
                    issue={documentation.issue}
                    can={can}
                />
            ))}
        </div>
    );
}
