import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/Components/ui/sidebar';
import { isActive } from '@/lib/permissions';
import { Link } from '@inertiajs/react';
import { ChevronRightIcon } from 'lucide-react';

// DESIGN.md §6: the active item gets an orange gradient wash and an orange icon.
const activeClass =
    'data-[active=true]:bg-gradient-to-r data-[active=true]:from-primary/20 data-[active=true]:to-transparent data-[active=true]:text-sidebar-accent-foreground data-[active=true]:[&>svg]:text-primary';

export function NavMain({ label, items }) {
    return (
        <SidebarGroup>
            <SidebarGroupLabel className="text-overline font-medium tracking-[0.06em] uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.items ? (
                        <Collapsible
                            key={item.title}
                            asChild
                            defaultOpen={isActive(item)}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton tooltip={item.title}>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                        <ChevronRightIcon className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        {item.items.map((subItem) => (
                                            <SidebarMenuSubItem key={subItem.title}>
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={isActive(subItem)}
                                                    className={activeClass}
                                                >
                                                    <Link href={route(subItem.route)}>
                                                        <span>{subItem.title}</span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </Collapsible>
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                tooltip={item.title}
                                isActive={isActive(item)}
                                className={activeClass}
                            >
                                <Link href={route(item.route)}>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
