import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/Components/ui/sidebar';
import { useTheme } from '@/hooks/use-theme';
import { Link, router } from '@inertiajs/react';
import { ChevronsUpDownIcon, LogOutIcon, MoonIcon, SunIcon, UserIcon } from 'lucide-react';

export function initials(name = '') {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

export function NavUser({ user }) {
    const { isMobile } = useSidebar();
    const { theme, setTheme } = useTheme();

    const toggleTheme = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        router.patch(
            route('preferences.theme'),
            { theme: next },
            { preserveScroll: true, preserveState: true },
        );
    };

    const identity = (
        <>
            <Avatar className="size-8 rounded-full">
                <AvatarFallback className="rounded-full bg-primary/15 text-xs text-primary">
                    {initials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium text-foreground">{user.name}</span>
                <span className="truncate text-xs text-muted-foreground">
                    {user.emp_code ?? user.email}
                </span>
            </div>
        </>
    );

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            {identity}
                            <ChevronsUpDownIcon className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-56"
                        side={isMobile ? 'bottom' : 'right'}
                        align="end"
                        sideOffset={4}
                    >
                        <DropdownMenuLabel className="p-0 font-normal">
                            <div className="flex items-center gap-2 px-1 py-1.5">{identity}</div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem asChild>
                                <Link href={route('profile.edit')}>
                                    <UserIcon />
                                    Profile
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={toggleTheme}>
                                {theme === 'dark' ? <SunIcon /> : <MoonIcon />}
                                {theme === 'dark' ? 'Light mode' : 'Dark mode'}
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="w-full"
                            >
                                <LogOutIcon />
                                Log out
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
