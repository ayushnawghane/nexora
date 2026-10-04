<?php

namespace App\Http\Requests\Deals\Security;

use App\Http\Requests\Deals\Documents\DealConditionCheckRequest;

/** The same decision rules as CP/CS checks, for the due diligence checker. */
class DiligenceCheckRequest extends DealConditionCheckRequest
{
    protected string $ability = 'verifySecurity';
}
