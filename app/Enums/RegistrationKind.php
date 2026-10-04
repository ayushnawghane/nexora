<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Where a security is registered: a charge with the ROC, CERSAI, or a pledge with a depository. */
enum RegistrationKind: string
{
    use HasOptions;

    case Roc = 'roc';
    case Cersai = 'cersai';
    case Pledge = 'pledge';

    public function label(): string
    {
        return match ($this) {
            self::Roc => 'ROC charge',
            self::Cersai => 'CERSAI',
            self::Pledge => 'Pledge',
        };
    }

    /** What the registration's reference is called. */
    public function referenceLabel(): string
    {
        return match ($this) {
            self::Roc => 'Charge ID',
            self::Cersai => 'Security interest ID',
            self::Pledge => 'ISIN',
        };
    }

    /** What the filing number of an event is called. */
    public function filingLabel(): string
    {
        return match ($this) {
            self::Roc => 'SRN',
            self::Cersai => 'Transaction ID',
            self::Pledge => 'PSN',
        };
    }
}
