<?php

/*
| Every permission in the app, grouped into sections for the role permission matrix.
| `php artisan db:seed --class=PermissionSeeder` syncs this list into the database; code must only
| reference permissions declared here (a test enforces it).
*/

return [
    'super_admin_role' => 'super-admin',

    'sections' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'permissions' => [
                'dashboard.view' => 'View dashboard',
            ],
        ],
        'transactions' => [
            'label' => 'Transactions',
            'permissions' => [
                'transactions.view' => 'View transactions',
                'transactions.create' => 'Create & edit draft transactions',
                'transactions.submit' => 'Send transactions for approval',
                'transactions.issue_el' => 'Issue engagement letters',
            ],
        ],
        'approvals' => [
            'label' => 'Approvals',
            'permissions' => [
                'approvals.vote' => 'Approve or reject transactions',
                'approvals.head' => 'Head approver (required vote)',
            ],
        ],
        'deals' => [
            'label' => 'Deals',
            'permissions' => [
                'deals.view' => 'View deals',
                'deals.edit' => 'Edit deal contacts & billing',
                'deals.status.request' => 'Request deal status changes',
                'deals.status.approve_management' => 'Approve status changes (Management)',
                'deals.status.approve_accounts' => 'Approve status changes (Accounts)',
                'deals.jobsheet.make' => 'Job sheet: maker',
                'deals.jobsheet.check' => 'Job sheet: checker',
                'deals.documents.manage' => 'Documents & CP/CS: maker (add, upload, remove)',
                'deals.documents.verify' => 'Documents & CP/CS: checker (verify, send back)',
            ],
        ],
        'companies' => [
            'label' => 'Companies',
            'permissions' => [
                'companies.view' => 'View companies',
                'companies.manage' => 'Create & edit companies, GSTs, addresses, contacts',
            ],
        ],
        'masters' => [
            'label' => 'Masters',
            'permissions' => [
                'masters.view' => 'View masters',
                'masters.manage' => 'Create & edit masters',
            ],
        ],
        'users' => [
            'label' => 'Users',
            'permissions' => [
                'users.view' => 'View users',
                'users.manage' => 'Create & edit users',
                'users.reset_security' => 'Reset passwords & 2FA',
            ],
        ],
        'roles' => [
            'label' => 'Roles',
            'permissions' => [
                'roles.view' => 'View roles',
                'roles.manage' => 'Create & edit roles and their permissions',
            ],
        ],
        'settings' => [
            'label' => 'Settings',
            'permissions' => [
                'settings.view' => 'View tax settings',
                'settings.manage' => 'Change GST rates and Beacon\'s GSTIN',
            ],
        ],
        'god_mode' => [
            'label' => 'God Mode',
            'permissions' => [
                'god_mode.access' => 'Use God Mode corrections (super-admins only)',
            ],
        ],
    ],
];
