import { PageHeader } from '@/Components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import AppLayout from '@/Layouts/AppLayout';
import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

export default function MastersIndex({ groups }) {
    return (
        <AppLayout title="Masters" breadcrumbs={[{ title: 'Masters' }]}>
            <PageHeader title="Masters" description="Reference lists used across Nexora." />
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {groups.map((group) => (
                    <Card key={group.key}>
                        <CardHeader>
                            <CardTitle className="text-overline font-medium tracking-[0.06em] text-muted-foreground uppercase">
                                {group.label}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col">
                            {group.masters.map((master) => (
                                <Link
                                    key={master.key}
                                    href={route('masters.show', master.key)}
                                    className="flex items-center justify-between rounded-md px-2 py-2 text-sm hover:bg-hover"
                                >
                                    {master.label}
                                    <ChevronRightIcon className="size-4 text-muted-foreground" />
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
