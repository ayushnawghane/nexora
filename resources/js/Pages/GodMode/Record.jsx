import { displayValue, EditorFields, getPath } from '@/Components/god-mode/editor-form';
import { LetterTools } from '@/Components/god-mode/letter-tools';
import { History, ReasonDialog, ReasonField } from '@/Components/god-mode/shared';
import { PageHeader } from '@/Components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import { FieldError, FieldGroup } from '@/Components/ui/field';
import { ScrollArea } from '@/Components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import AppLayout from '@/Layouts/AppLayout';
import { Link, useForm } from '@inertiajs/react';
import { ChevronRightIcon, PencilIcon, TriangleAlertIcon } from 'lucide-react';
import { useState } from 'react';

const dash = <span className="text-subtle-foreground">—</span>;

function CorrectionSheet({ item, onDone }) {
    const form = useForm({ values: item.values, fingerprint: item.fingerprint, reason: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('god-mode.correct', [item.editor, item.id]), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex h-full flex-col">
            <SheetHeader>
                <SheetTitle>Correct: {item.title}</SheetTitle>
                <SheetDescription>
                    Checked with the same rules as the normal screen. Fields marked * are required.
                </SheetDescription>
            </SheetHeader>
            <ScrollArea className="min-h-0 flex-1 px-4">
                <FieldGroup className="pb-4">
                    <FieldError>{form.errors.fingerprint}</FieldError>
                    <EditorFields form={form} fields={item.fields} />
                    <ReasonField form={form} />
                </FieldGroup>
            </ScrollArea>
            <SheetFooter>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save correction'}
                </Button>
            </SheetFooter>
        </form>
    );
}

/** One editable part of the record, with its current values at a glance. */
function ItemCard({ item, onEdit }) {
    const summary = (item.summary ? item.fields : [])
        .filter((f) => f.type !== 'group')
        .slice(0, 6)
        .map((f) => ({ label: f.label, value: displayValue(f, getPath(item.values, f.name)) }));
    const groups = item.fields.filter((f) => f.type === 'group');

    return (
        <li className="flex flex-col gap-3 px-4 py-3">
            <div className="flex flex-wrap items-start gap-2">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-[13px] font-medium">{item.title}</span>
                        {item.inactive && <Badge variant="neutral">Inactive</Badge>}
                    </div>
                    {item.subtitle && (
                        <div className="text-xs break-words text-muted-foreground">
                            {item.subtitle}
                        </div>
                    )}
                </div>
                <Button variant="outline" size="sm" onClick={() => onEdit(item)}>
                    <PencilIcon /> Correct
                </Button>
            </div>
            {summary.length > 0 && (
                <dl className="grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                    {summary.map((row) => (
                        <div key={row.label} className="min-w-0">
                            <dt className="text-xs text-muted-foreground">{row.label}</dt>
                            <dd className="text-[13px] break-words">{row.value ?? dash}</dd>
                        </div>
                    ))}
                </dl>
            )}
            {groups.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    {groups
                        .map(
                            (g) =>
                                `${g.label}: ${getPath(item.values, `${g.name}.enabled`) ? 'charged' : 'not charged'}`,
                        )
                        .join(' · ')}
                </p>
            )}
        </li>
    );
}

export default function GodModeRecord({ record, sections, links, prompts, letter, history }) {
    const [editing, setEditing] = useState(null);

    return (
        <AppLayout
            title={`God Mode: ${record.title}`}
            breadcrumbs={[
                { title: 'God Mode', href: route('god-mode.index') },
                { title: record.title },
            ]}
        >
            <PageHeader
                title={record.title}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="font-mono">{record.subtitle}</span>
                        {record.status && <Badge variant="neutral">{record.status}</Badge>}
                    </span>
                }
                actions={
                    record.kind === 'transaction' && (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={record.company_href}>Company record</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={record.deal_href}>Open normal view</Link>
                            </Button>
                        </>
                    )
                }
            />

            {prompts?.verify_schedule && (
                <Alert>
                    <TriangleAlertIcon />
                    <AlertTitle>The fee schedule was rebuilt and isn&apos;t verified</AlertTitle>
                    <AlertDescription className="flex flex-wrap items-center gap-3">
                        Check it on the normal view, then confirm it here.
                        <ReasonDialog
                            action={route('god-mode.schedule.verify', record.id)}
                            title="Mark the schedule as verified?"
                            description="Confirm you have checked the rebuilt schedule against the fees."
                            confirmLabel="Verify"
                            errorKeys={['schedule']}
                            trigger={<Button size="sm">Verify schedule</Button>}
                        />
                    </AlertDescription>
                </Alert>
            )}
            {prompts?.letter_outdated && (
                <Alert>
                    <TriangleAlertIcon />
                    <AlertTitle>The engagement letter may be out of date</AlertTitle>
                    <AlertDescription>
                        Data in the letter was corrected after its latest version. Regenerate it, or
                        edit its wording, below.
                    </AlertDescription>
                </Alert>
            )}

            {sections.map((section) => (
                <Card key={section.title} className="gap-0 pb-0">
                    <CardHeader className="pb-3">
                        <CardTitle>{section.title}</CardTitle>
                    </CardHeader>
                    <CardContent className="px-0">
                        {section.items.length === 0 ? (
                            <Empty className="mx-4 mb-4 border">
                                <EmptyHeader>
                                    <EmptyTitle>Nothing here</EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <ul className="divide-y border-t">
                                {section.items.map((item) => (
                                    <ItemCard
                                        key={`${item.editor}-${item.id}`}
                                        item={item}
                                        onEdit={setEditing}
                                    />
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            ))}

            {links.length > 0 && (
                <Card className="gap-0 pb-0">
                    <CardHeader className="pb-3">
                        <CardTitle>Transactions</CardTitle>
                    </CardHeader>
                    <CardContent className="px-0">
                        <ul className="divide-y border-t">
                            {links.map((link) => (
                                <li key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="group flex items-center gap-3 px-4 py-3 hover:bg-surface-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-mono text-[13px] group-hover:text-brand-text">
                                                {link.title}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {link.subtitle}
                                            </div>
                                        </div>
                                        <ChevronRightIcon className="size-4 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            {letter && <LetterTools dealId={record.id} letter={letter} />}

            <Card>
                <CardHeader>
                    <CardTitle>Change history</CardTitle>
                </CardHeader>
                <CardContent>
                    <History changes={history} />
                </CardContent>
            </Card>

            <Sheet open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <SheetContent className="flex w-full flex-col gap-0 sm:max-w-md">
                    {editing && (
                        <CorrectionSheet
                            key={`${editing.editor}-${editing.id}-${editing.fingerprint}`}
                            item={editing}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
