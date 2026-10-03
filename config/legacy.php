<?php

/*
| Settings for `php artisan legacy:import`.
|
| permission_map: legacy permission slug => Nexora permissions it grants. Legacy permissions are
| per screen (about 270 of them, most for modules not yet in Nexora), so only slugs whose screen
| clearly matches a Nexora permission are listed. Anything unlisted grants nothing; roles can be
| finished by hand on the Roles screen. ⏳ To be confirmed by the project owner (docs/PLAN.md §6).
*/

return [
    'permission_map' => [
        // Transactions
        'transaction_view' => ['transactions.view'],
        'Create_view' => ['transactions.view', 'transactions.create'],
        'draft_transaction' => ['transactions.view'],
        'transaction_draft_view' => ['transactions.view'],
        'transaction_draft_edit' => ['transactions.create', 'transactions.submit'],
        'auto_el' => ['transactions.issue_el'],

        // Deals
        'active_transaction' => ['transactions.view', 'deals.view'],
        'active_view' => ['deals.view'],
        'dealdash' => ['deals.view'],
        'dealdash_view' => ['deals.view'],
        'overview' => ['deals.view'],
        'el_overview' => ['deals.view'],
        'el_overview_view' => ['deals.view'],
        'contact' => ['deals.view', 'deals.edit'],
        'closed' => ['deals.view'],

        // Companies and masters
        'master' => ['masters.view', 'masters.manage', 'companies.view', 'companies.manage'],
    ],
];
