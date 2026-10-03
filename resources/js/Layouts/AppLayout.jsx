import { AppSidebar } from '@/Components/app-sidebar';
import { TemporaryPasswordDialog } from '@/Components/temporary-password-dialog';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { Separator } from '@/Components/ui/separator';
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/Components/ui/sidebar';
import { Toaster } from '@/Components/ui/sonner';
import { useMediaQuery } from '@/hooks/use-media-query';
import { Head, Link, usePage } from '@inertiajs/react';
import { Fragment, useEffect, useState } from 'react';
import { toast } from 'sonner';

/**
 * Authenticated app shell (DESIGN.md §5): collapsible sidebar, top bar with breadcrumbs,
 * page content, and flash messages surfaced as toasts.
 *
 * breadcrumbs: [{ title, href? }] — the last item is the current page.
 */
export default function AppLayout({ title, breadcrumbs = [], actions, children }) {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast.warning(flash.warning);
        if (flash?.info) toast.info(flash.info);
    }, [flash]);

    // DESIGN.md §5: full sidebar from 1024px, icon rail from 768px (below that it's a drawer).
    const isDesktop = useMediaQuery('(min-width: 1024px)');
    const [sidebarOpen, setSidebarOpen] = useState(isDesktop);
    useEffect(() => setSidebarOpen(isDesktop), [isDesktop]);

    const crumbs = breadcrumbs.length ? breadcrumbs : title ? [{ title }] : [];

    return (
        <SidebarProvider open={sidebarOpen} onOpenChange={setSidebarOpen}>
            {title && <Head title={title} />}
            <AppSidebar />
            <SidebarInset className="min-w-0 bg-background">
                <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b bg-background/80 px-4 backdrop-blur">
                    <SidebarTrigger className="-ml-1" />
                    <Separator
                        orientation="vertical"
                        className="mr-2 data-[orientation=vertical]:h-4"
                    />
                    <Breadcrumb>
                        <BreadcrumbList>
                            {crumbs.map((crumb, index) => (
                                <Fragment key={`${crumb.title}-${index}`}>
                                    {index > 0 && <BreadcrumbSeparator />}
                                    <BreadcrumbItem>
                                        {index === crumbs.length - 1 || !crumb.href ? (
                                            <BreadcrumbPage>{crumb.title}</BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={crumb.href}>{crumb.title}</Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                </Fragment>
                            ))}
                        </BreadcrumbList>
                    </Breadcrumb>
                    {actions && <div className="ml-auto flex items-center gap-2">{actions}</div>}
                </header>
                <main className="flex flex-1 flex-col gap-4 p-4 md:p-6">{children}</main>
            </SidebarInset>
            <Toaster position="top-right" richColors={false} closeButton />
            <TemporaryPasswordDialog credentials={flash?.temporary_password} />
        </SidebarProvider>
    );
}
