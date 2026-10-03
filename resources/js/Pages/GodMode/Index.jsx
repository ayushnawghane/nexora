import { History } from '@/Components/god-mode/shared';
import { PageHeader } from '@/Components/page-header';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';
import { ChevronRightIcon, SearchIcon, ShieldAlertIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

function ResultList({ title, items }) {
    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="pb-3">
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="px-0">
                {items.length === 0 ? (
                    <p className="border-t px-4 py-3 text-[13px] text-muted-foreground">
                        Nothing found.
                    </p>
                ) : (
                    <ul className="divide-y border-t">
                        {items.map((item) => (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    className="group flex items-center gap-3 px-4 py-3 hover:bg-surface-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-[13px] font-medium group-hover:text-brand-text">
                                                {item.title}
                                            </span>
                                            {item.inactive && (
                                                <Badge variant="neutral">Inactive</Badge>
                                            )}
                                        </div>
                                        <div className="truncate font-mono text-xs text-muted-foreground">
                                            {item.subtitle}
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
    );
}

export default function GodModeIndex({ q, results, recent }) {
    const [term, setTerm] = useState(q);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return undefined;
        }
        const timer = setTimeout(() => {
            router.get(route('god-mode.index'), term.trim() ? { q: term.trim() } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
        return () => clearTimeout(timer);
    }, [term]);

    return (
        <AppLayout title="God Mode" breadcrumbs={[{ title: 'God Mode' }]}>
            <PageHeader
                title="God Mode"
                description="Correct any business record. Every change needs a reason, is logged permanently and can be undone."
            />
            <Alert>
                <ShieldAlertIcon />
                <AlertDescription>
                    Corrections here skip the normal approvals but not the normal rules: values are
                    checked exactly as on the regular screens.
                </AlertDescription>
            </Alert>

            <div className="relative w-full sm:max-w-md">
                <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={term}
                    onChange={(e) => setTerm(e.target.value)}
                    placeholder="Company, CIN, PAN, GSTIN, EL number or deal code"
                    className="pl-8"
                    aria-label="Search records"
                    autoFocus
                />
            </div>

            {results && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <ResultList title="Companies" items={results.companies} />
                    <ResultList title="Transactions and deals" items={results.transactions} />
                </div>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Recent changes</CardTitle>
                </CardHeader>
                <CardContent>
                    <History changes={recent} showUndo={false} />
                </CardContent>
            </Card>
        </AppLayout>
    );
}
