import {
    Building2,
    FileSignature,
    LayoutDashboard,
    Library,
    ShieldAlert,
    ShieldCheck,
    Users,
    Workflow,
} from 'lucide-react';

/**
 * Sidebar navigation. Each item names a route and the permission needed to see it.
 * Items whose route isn't registered yet, or that the user isn't allowed to see, are hidden,
 * so the menu only ever shows working links.
 */
export const navigation = [
    {
        label: 'Main',
        items: [
            { title: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
            {
                title: 'Transactions',
                icon: FileSignature,
                items: [
                    {
                        title: 'New transaction',
                        route: 'transactions.create',
                        permission: 'transactions.create',
                    },
                    {
                        title: 'Drafts',
                        route: 'transactions.drafts',
                        permission: 'transactions.view',
                    },
                    {
                        title: 'Pending approval',
                        route: 'transactions.pending',
                        permission: 'transactions.view',
                    },
                    { title: 'Active', route: 'deals.index', permission: 'deals.view' },
                    {
                        title: 'Closed',
                        route: 'deals.closed',
                        permission: 'deals.view',
                    },
                ],
            },
            {
                title: 'Approvals',
                route: 'approvals.index',
                permission: 'approvals.vote',
                icon: Workflow,
            },
        ],
    },
    {
        label: 'Masters',
        items: [
            {
                title: 'Companies',
                route: 'companies.index',
                permission: 'companies.view',
                icon: Building2,
            },
            {
                title: 'Masters',
                route: 'masters.index',
                permission: 'masters.view',
                icon: Library,
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            { title: 'Users', route: 'users.index', permission: 'users.view', icon: Users },
            {
                title: 'Roles & permissions',
                route: 'roles.index',
                permission: 'roles.view',
                icon: ShieldCheck,
            },
            {
                title: 'God Mode',
                route: 'god-mode.index',
                permission: 'god_mode.access',
                icon: ShieldAlert,
            },
        ],
    },
];
