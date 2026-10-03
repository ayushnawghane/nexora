import { ConfirmAction } from '@/Components/confirm-action';
import { PageHeader } from '@/Components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { gstinError, normaliseIdentifier } from '@/lib/identifiers';
import { useForm } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';

const STATUS = {
    current: <Badge variant="success">In force</Badge>,
    scheduled: <Badge variant="warning">Scheduled</Badge>,
    past: <Badge variant="neutral">Past</Badge>,
};

function GstinCard({ beacon, canManage }) {
    const form = useForm({ gstin: beacon.gstin ?? '' });
    const error = form.errors.gstin ?? gstinError(form.data.gstin);

    const submit = (e) => {
        e.preventDefault();
        form.put(route('settings.tax.gstin'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Beacon&apos;s GST registration</CardTitle>
                <CardDescription>
                    Its state is the home state: billing in the same state carries CGST + SGST,
                    billing elsewhere carries IGST.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} noValidate className="flex max-w-md flex-col gap-3">
                    <Field data-invalid={!!error || undefined}>
                        <FieldLabel htmlFor="gstin">GSTIN</FieldLabel>
                        <div className="flex gap-2">
                            <Input
                                id="gstin"
                                value={form.data.gstin}
                                onChange={(e) =>
                                    form.setData('gstin', normaliseIdentifier(e.target.value))
                                }
                                className="font-mono uppercase"
                                maxLength={15}
                                placeholder="27AAACB1234C1Z5"
                                disabled={!canManage}
                                aria-invalid={!!error || undefined}
                                autoComplete="off"
                            />
                            {canManage && (
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty || !!error}
                                >
                                    {form.processing ? 'Saving…' : 'Save'}
                                </Button>
                            )}
                        </div>
                        {!error && (
                            <FieldDescription>
                                {beacon.state ? `Home state: ${beacon.state}` : 'Not set yet.'}
                            </FieldDescription>
                        )}
                        <FieldError>{error}</FieldError>
                    </Field>
                </form>
            </CardContent>
        </Card>
    );
}

function RateDialog({ open, onOpenChange }) {
    const form = useForm({ effective_from: '', cgst: '9.00', sgst: '9.00', igst: '18.00' });

    const submit = (e) => {
        e.preventDefault();
        form.post(route('settings.tax.rates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    const rateField = (name, label) => (
        <Field data-invalid={!!form.errors[name] || undefined}>
            <FieldLabel htmlFor={`rate-${name}`}>{label} %</FieldLabel>
            <Input
                id={`rate-${name}`}
                inputMode="decimal"
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value.replace(/[^0-9.]/g, ''))}
                className="tabular-nums"
                aria-invalid={!!form.errors[name] || undefined}
            />
            <FieldError>{form.errors[name]}</FieldError>
        </Field>
    );

    return (
        <Dialog open={open} onOpenChange={(next) => !form.processing && onOpenChange(next)}>
            <DialogContent>
                <form onSubmit={submit} noValidate className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Add GST rate</DialogTitle>
                        <DialogDescription>
                            The new rate applies to invoices dated on or after its effective date.
                            Earlier invoices keep the rate they were raised at.
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.effective_from || undefined}>
                            <FieldLabel htmlFor="rate-effective_from">Effective from</FieldLabel>
                            <Input
                                id="rate-effective_from"
                                type="date"
                                value={form.data.effective_from}
                                onChange={(e) => form.setData('effective_from', e.target.value)}
                                aria-invalid={!!form.errors.effective_from || undefined}
                            />
                            <FieldError>{form.errors.effective_from}</FieldError>
                        </Field>
                        <div className="grid grid-cols-3 gap-3">
                            {rateField('cgst', 'CGST')}
                            {rateField('sgst', 'SGST')}
                            {rateField('igst', 'IGST')}
                        </div>
                    </FieldGroup>
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
                            {form.processing ? 'Saving…' : 'Add rate'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function TaxSettings({ beacon, rates, can }) {
    const [adding, setAdding] = useState(false);

    return (
        <AppLayout
            title="Tax settings"
            breadcrumbs={[{ title: 'Administration' }, { title: 'Tax settings' }]}
        >
            <PageHeader
                title="Tax settings"
                description="GST rates and Beacon's own GST registration, used on fee quotes and invoices."
            />

            {(!beacon.gstin || rates.length === 0) && (
                <Alert variant="destructive">
                    <AlertTitle>Tax settings are incomplete</AlertTitle>
                    <AlertDescription>
                        GST can&apos;t be worked out until Beacon&apos;s GSTIN and at least one rate
                        are set.
                    </AlertDescription>
                </Alert>
            )}

            <GstinCard beacon={beacon} canManage={can.manage} />

            <Card>
                <CardHeader>
                    <CardTitle>GST rates</CardTitle>
                    <CardDescription>
                        Rates that have taken effect are kept as history and can&apos;t be changed.
                        To change the rate, add a new one with a later date.
                    </CardDescription>
                    {can.manage && (
                        <CardAction>
                            <Button variant="outline" onClick={() => setAdding(true)}>
                                <PlusIcon /> Add rate
                            </Button>
                        </CardAction>
                    )}
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Effective from</TableHead>
                                <TableHead className="text-right">CGST</TableHead>
                                <TableHead className="text-right">SGST</TableHead>
                                <TableHead className="text-right">IGST</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Added by</TableHead>
                                {can.manage && <TableHead />}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rates.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-center text-muted-foreground"
                                    >
                                        No rates yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {rates.map((rate) => (
                                <TableRow key={rate.id}>
                                    <TableCell>{formatDate(rate.effective_from)}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rate.cgst}%
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rate.sgst}%
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {rate.igst}%
                                    </TableCell>
                                    <TableCell>{STATUS[rate.status]}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {rate.created_by ?? 'System'}
                                    </TableCell>
                                    {can.manage && (
                                        <TableCell className="text-right">
                                            {rate.status === 'scheduled' && (
                                                <ConfirmAction
                                                    method="delete"
                                                    href={route(
                                                        'settings.tax.rates.destroy',
                                                        rate.id,
                                                    )}
                                                    title="Withdraw this scheduled rate?"
                                                    description="It hasn't taken effect yet, so no invoice uses it."
                                                    confirmLabel="Withdraw"
                                                    destructive
                                                    trigger={
                                                        <Button variant="ghost" size="sm">
                                                            Withdraw
                                                        </Button>
                                                    }
                                                />
                                            )}
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {can.manage && <RateDialog open={adding} onOpenChange={setAdding} />}
        </AppLayout>
    );
}
