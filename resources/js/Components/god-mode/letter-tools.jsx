import { ReasonDialog, ReasonField } from '@/Components/god-mode/shared';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
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
import { Label } from '@/Components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { formatDate, formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { FileTextIcon, HashIcon, PencilLineIcon, RefreshCwIcon, UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';

/**
 * Edits the letter as it looks, in a sandboxed frame (no scripts run) with the browser's own
 * rich-text editing, so tables and layout stay intact. Only the body is sent; the server cleans it
 * and keeps the letter's original styles.
 */
function WordingDialog({ dealId, letter }) {
    const [open, setOpen] = useState(false);
    const frame = useRef(null);
    const form = useForm({ reason: '', body: '' });

    const enableEditing = () => {
        const doc = frame.current?.contentDocument;
        if (doc) doc.designMode = 'on';
    };

    const submit = (e) => {
        e.preventDefault();
        const body = frame.current?.contentDocument?.body?.innerHTML ?? '';
        form.transform((data) => ({ ...data, body }));
        form.post(route('god-mode.letter.wording', dealId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <Button variant="outline" onClick={() => setOpen(true)}>
                <PencilLineIcon /> Edit wording
            </Button>
            <Dialog open={open} onOpenChange={(o) => !form.processing && setOpen(o)}>
                <DialogContent className="max-h-[95vh] overflow-y-auto sm:max-w-4xl">
                    <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Edit the letter&apos;s wording</DialogTitle>
                            <DialogDescription>
                                Click into the letter and type. The change applies to this deal only
                                and is saved as a new version. Links, scripts and inline styles are
                                removed when it&apos;s saved.
                            </DialogDescription>
                        </DialogHeader>
                        <iframe
                            ref={frame}
                            title="Letter editor"
                            sandbox="allow-same-origin"
                            srcDoc={letter.document}
                            onLoad={enableEditing}
                            className="h-[55vh] w-full rounded-md border bg-white"
                        />
                        <FieldError>{form.errors.body}</FieldError>
                        <ReasonField form={form} id="wording-reason" />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save as new version'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function NumberFields({ form, letter }) {
    return (
        <>
            <Field>
                <FieldLabel>EL number</FieldLabel>
                <RadioGroup
                    value={form.data.number_mode}
                    onValueChange={(v) => form.setData('number_mode', v)}
                >
                    {[
                        ['keep', `Keep ${letter.el_number}`],
                        ['next', 'Take the next number for the financial year'],
                        ['manual', 'Enter a number'],
                    ].map(([value, label]) => (
                        <div key={value} className="flex items-center gap-2">
                            <RadioGroupItem value={value} id={`mode-${value}`} />
                            <Label htmlFor={`mode-${value}`} className="font-normal">
                                {label}
                            </Label>
                        </div>
                    ))}
                </RadioGroup>
                <FieldDescription>
                    A replaced number is retired for good and never issued again.
                </FieldDescription>
            </Field>
            {form.data.number_mode === 'manual' && (
                <Field data-invalid={!!form.errors.el_number || undefined}>
                    <FieldLabel htmlFor="el_number">New EL number</FieldLabel>
                    <Input
                        id="el_number"
                        value={form.data.el_number}
                        onChange={(e) => form.setData('el_number', e.target.value)}
                        className="font-mono"
                        placeholder="BTL/DEB/EL/25-26/123"
                    />
                </Field>
            )}
            <FieldError>{form.errors.el_number}</FieldError>
            <Field data-invalid={!!form.errors.el_date || undefined} className="max-w-48">
                <FieldLabel htmlFor="el_date">EL date</FieldLabel>
                <Input
                    id="el_date"
                    type="date"
                    value={form.data.el_date}
                    min={letter.min_date ?? undefined}
                    max={letter.max_date}
                    onChange={(e) => form.setData('el_date', e.target.value)}
                />
                <FieldError>{form.errors.el_date}</FieldError>
            </Field>
            {letter.fixed_date && (
                <p className="text-xs text-muted-foreground">
                    A fee runs from the EL date ({formatDate(letter.fixed_date)}): correct the fee
                    start date first if the date must change.
                </p>
            )}
        </>
    );
}

/** Letter versions and the four corrections; each one adds a version and keeps the old ones. */
export function LetterTools({ dealId, letter }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Engagement letter</CardTitle>
                <CardDescription>
                    Every correction adds a new version; earlier versions stay as issued.
                    {letter.retired.length > 0 && ` Retired numbers: ${letter.retired.join(', ')}.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <ReasonDialog
                        action={route('god-mode.letter.regenerate', dealId)}
                        title="Regenerate the letter?"
                        description="It is rendered again from today's data, with the same number and date. Wording edits made for this deal are replaced."
                        confirmLabel="Regenerate"
                        errorKeys={['letter']}
                        trigger={
                            <Button variant="outline">
                                <RefreshCwIcon /> Regenerate from data
                            </Button>
                        }
                    />
                    <WordingDialog dealId={dealId} letter={letter} />
                    <ReasonDialog
                        action={route('god-mode.letter.number', dealId)}
                        title="Change the EL number or date"
                        description="The new number and date are written into the current wording and saved as a new version."
                        data={{ number_mode: 'keep', el_number: '', el_date: letter.el_date }}
                        confirmLabel="Save"
                        trigger={
                            <Button variant="outline">
                                <HashIcon /> Number / date
                            </Button>
                        }
                    >
                        {(form) => <NumberFields form={form} letter={letter} />}
                    </ReasonDialog>
                    <ReasonDialog
                        action={route('god-mode.letter.pdf', dealId)}
                        title="Replace the PDF"
                        description="Upload a signed or corrected PDF (up to 10 MB). It becomes the new version."
                        data={{ pdf: null }}
                        confirmLabel="Upload"
                        trigger={
                            <Button variant="outline">
                                <UploadIcon /> Replace PDF
                            </Button>
                        }
                    >
                        {(form) => (
                            <Field data-invalid={!!form.errors.pdf || undefined}>
                                <FieldLabel htmlFor="letter-pdf">PDF</FieldLabel>
                                <Input
                                    id="letter-pdf"
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    onChange={(e) =>
                                        form.setData('pdf', e.target.files?.[0] ?? null)
                                    }
                                />
                                <FieldError>{form.errors.pdf}</FieldError>
                            </Field>
                        )}
                    </ReasonDialog>
                </div>

                <ul className="flex flex-col divide-y rounded-lg border">
                    {letter.versions.map((v, index) => (
                        <li
                            key={v.version}
                            className="flex flex-wrap items-center gap-3 px-3 py-2.5"
                        >
                            <FileTextIcon className="size-4 text-muted-foreground" />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2 font-mono text-[13px]">
                                    {v.el_number}
                                    <span className="text-muted-foreground">· v{v.version}</span>
                                    {index === 0 && <Badge variant="success">Current</Badge>}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Dated {formatDate(v.el_date)} · {v.generated_by},{' '}
                                    {formatDateTime(v.generated_at)}
                                    {v.reason && ` · ${v.reason}`}
                                </div>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <a href={v.href} target="_blank" rel="noreferrer">
                                    Open PDF
                                </a>
                            </Button>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
