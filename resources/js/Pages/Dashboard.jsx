import { PageHeader } from '@/Components/page-header';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/Components/ui/empty';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

const crore = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

/** Big issue sizes read better in crore on a KPI card; the exact amount is in the tooltip. */
function moneyHeadline(value) {
    const amount = Number(value);
    return amount >= 1e7 ? `₹${crore.format(amount / 1e7)} cr` : formatMoney(value);
}

function Kpi({ kpi }) {
    return (
        <Link href={kpi.href} className="group">
            <Card className="h-full gap-1 transition-colors group-hover:border-brand-500/50">
                <CardContent className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">{kpi.label}</span>
                    <span
                        className="text-2xl font-semibold tracking-tight tabular-nums"
                        title={kpi.money ? formatMoney(kpi.value) : undefined}
                    >
                        {kpi.money ? moneyHeadline(kpi.value) : kpi.value.toLocaleString('en-IN')}
                    </span>
                </CardContent>
            </Card>
        </Link>
    );
}

export default function Dashboard({ kpis, queue }) {
    const { auth } = usePage().props;

    return (
        <AppLayout title="Dashboard">
            <PageHeader
                title={`Welcome, ${auth.user.name.split(' ')[0]}`}
                description="Your deals and the work waiting on you."
            />

            {kpis && kpis.length > 0 && (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
                    {kpis.map((kpi) => (
                        <Kpi key={kpi.key} kpi={kpi} />
                    ))}
                </div>
            )}

            <Card className="gap-0 pb-0">
                <CardHeader className="pb-4">
                    <CardTitle>Waiting on you</CardTitle>
                    <CardDescription>
                        Approvals to vote on, status changes for your team, and job sheet entries to
                        check or fix. Oldest first.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-0">
                    {queue.length === 0 ? (
                        <Empty className="mx-4 mb-4 border">
                            <EmptyHeader>
                                <EmptyTitle>You&apos;re all caught up</EmptyTitle>
                                <EmptyDescription>
                                    Nothing is waiting on you right now.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <ul className="divide-y border-t">
                            {queue.map((item) => (
                                <li key={item.id}>
                                    <Link
                                        href={item.href}
                                        className="group flex items-center gap-3 px-4 py-3 hover:bg-surface-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge variant="neutral">{item.kind}</Badge>
                                                <span className="truncate text-[13px] font-medium group-hover:text-brand-text">
                                                    {item.title}
                                                </span>
                                            </div>
                                            <div className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {item.detail}
                                                {item.at && ` · ${formatDateTime(item.at)}`}
                                            </div>
                                        </div>
                                        <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
