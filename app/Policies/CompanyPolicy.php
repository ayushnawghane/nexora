<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/** GSTINs, addresses and contacts are part of the company record and use its `update` ability. */
class CompanyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('companies.view');
    }

    public function view(User $actor, Company $company): bool
    {
        return $actor->can('companies.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('companies.manage');
    }

    public function update(User $actor, Company $company): bool
    {
        return $actor->can('companies.manage');
    }
}
