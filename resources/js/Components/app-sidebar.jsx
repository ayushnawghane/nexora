import { NavMain } from '@/Components/nav-main';
import { NavUser } from '@/Components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/Components/ui/sidebar';
import { filterNavigation, usePermissions } from '@/lib/permissions';
import { navigation } from '@/navigation';
import { Link, usePage } from '@inertiajs/react';
import { Hexagon } from 'lucide-react';
import { useMemo } from 'react';

export function AppSidebar(props) {
    const { auth } = usePage().props;
    const can = usePermissions();
    const groups = useMemo(() => filterNavigation(navigation, can), [can]);

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('dashboard')}>
                                <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground shadow-brand">
                                    <Hexagon className="size-4" />
                                </div>
                                <div className="grid flex-1 text-left leading-tight">
                                    <span className="truncate text-sm font-semibold text-foreground">
                                        Nexora
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        Beacon Trusteeship
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                {groups.map((group) => (
                    <NavMain key={group.label} label={group.label} items={group.items} />
                ))}
            </SidebarContent>
            <SidebarFooter>
                <NavUser user={auth.user} />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
