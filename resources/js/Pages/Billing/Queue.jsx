import { DraftInvoiceSheet } from '@/Components/billing/draft-invoice-sheet';
import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
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
import { formatDate, formatMoney } from '@/lib/format';
import { Link, router } from '@inertiajs/react';
import { FilePlusIcon, SearchIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/** Fee periods ready to bill, one card per deal, each raised as a draft proforma. */
export default function BillingQueue({ deals, queueDays, search: initialSearch, canRaise }) {
    const [search, setSearch] = useState(initialSearch);
    const [drafting, setDrafting] = useState(null);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return undefined;
        }
        const timer = setTimeout(
            () =>
                router.get(route('billing.queue'), search ? { search } : {}, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }),
            300,
        );
        return () => clearTimeout(timer);
    }, [search]);

    return (
        <AppLayout
            title="Billing queue"
            breadcrumbs={[
                { title: 'Invoices', href: route('invoices.index') },
                { title: 'Billing queue' },
            ]}
        >
            <PageHeader
                title="Billing queue"
                description={`Fee periods not yet billed whose bill date has passed or falls in the next ${queueDays} days, on open deals.`}
            />
            <div className="relative w-full sm:w-80">
                <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search company or EL number"
                    className="pl-8"
                    aria-label="Search the billing queue"
                />
            </div>

            {deals.data.length === 0 ? (
                <Empty className="border">
                    <EmptyHeader>
                        <EmptyTitle>Nothing to bill</EmptyTitle>
                        <EmptyDescription>
                            Every fee period due so far is on an invoice.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <div className="flex flex-col gap-4">
                    {deals.data.map((deal) => (
                        <Card key={deal.id} className="gap-0 py-0">
                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 border-b py-4">
                                <div className="min-w-0">
                                    <CardTitle>
                                        <Link
                                            href={route('deals.show', {
                                                transaction: deal.id,
                                                tab: 'invoices',
                                            })}
                                            className="hover:text-brand-text"
                                        >
                                            {deal.company}
                                        </Link>
                                    </CardTitle>
                                    <CardDescription className="flex flex-wrap items-center gap-2">
                                        <span className="font-mono">{deal.el_number}</span>
                                        <span>{deal.deal_status_label}</span>
                                        {deal.overdue && (
                                            <Badge variant="danger">Bill date passed</Badge>
                                        )}
                                        {!deal.has_billing && (
                                            <Badge variant="warning">No billing details</Badge>
                                        )}
                                    </CardDescription>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-[13px] font-semibold tabular-nums">
                                        {formatMoney(deal.total)}
                                    </span>
                                    {canRaise && (
                                        <Button
                                            size="sm"
                                            disabled={!deal.has_billing}
                                            onClick={() => setDrafting(deal)}
                                        >
                                            <FilePlusIcon /> Draft proforma
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="px-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Fee</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Period
                                            </TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Bill date
                                            </TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {deal.periods.map((p) => (
                                            <TableRow key={p.id}>
                                                <TableCell className="whitespace-normal">
                                                    {p.fee}
                                                    <span className="block text-xs text-muted-foreground md:hidden">
                                                        {formatDate(p.from)} – {formatDate(p.to)} ·
                                                        bill {formatDate(p.bill_date)}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="hidden whitespace-nowrap md:table-cell">
                                                    {formatDate(p.from)} – {formatDate(p.to)}
                                                </TableCell>
                                                <TableCell className="hidden whitespace-nowrap text-muted-foreground md:table-cell">
                                                    {formatDate(p.bill_date)}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(p.amount)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {deals.last_page > 1 && (
                <div className="flex items-center justify-between gap-2 text-[13px] text-muted-foreground">
                    <span>
                        {deals.from}–{deals.to} of {deals.total} deals
                    </span>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild={!!deals.prev_page_url}
                            disabled={!deals.prev_page_url}
                        >
                            {deals.prev_page_url ? (
                                <Link href={deals.prev_page_url} preserveScroll={false}>
                                    Previous
                                </Link>
                            ) : (
                                <span>Previous</span>
                            )}
                        </Button>
                        <span>
                            Page {deals.current_page} of {deals.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild={!!deals.next_page_url}
                            disabled={!deals.next_page_url}
                        >
                            {deals.next_page_url ? (
                                <Link href={deals.next_page_url} preserveScroll={false}>
                                    Next
                                </Link>
                            ) : (
                                <span>Next</span>
                            )}
                        </Button>
                    </div>
                </div>
            )}

            {drafting && (
                <DraftInvoiceSheet
                    key={drafting.id}
                    open
                    onOpenChange={(o) => !o && setDrafting(null)}
                    dealId={drafting.id}
                    periods={drafting.periods}
                    initial={{ periods: drafting.periods.map((p) => p.id) }}
                />
            )}
        </AppLayout>
    );
}
