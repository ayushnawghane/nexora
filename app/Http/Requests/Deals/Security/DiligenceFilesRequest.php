<?php

namespace App\Http\Requests\Deals\Security;

use App\Http\Requests\Deals\Documents\DealConditionFilesRequest;

/** The same file rules as CP/CS uploads, for security makers. */
class DiligenceFilesRequest extends DealConditionFilesRequest
{
    protected string $ability = 'manageSecurity';
}
