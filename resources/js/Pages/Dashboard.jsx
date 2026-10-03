import { PageHeader } from '@/Components/page-header';
import AppLayout from '@/Layouts/AppLayout';
import { usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <AppLayout title="Dashboard">
            <PageHeader
                title={`Welcome, ${auth.user.name.split(' ')[0]}`}
                description="Your deals, approvals and pending work will appear here."
            />
        </AppLayout>
    );
}
