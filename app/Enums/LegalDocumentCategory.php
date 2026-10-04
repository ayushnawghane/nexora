<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a legal document is grouped on the deal's documentation list (Stack's document categories). */
enum LegalDocumentCategory: string
{
    use HasOptions;

    case Transaction = 'transaction';
    case Security = 'security';
    case Undertaking = 'undertaking';

    public function label(): string
    {
        return match ($this) {
            self::Transaction => 'Transaction document',
            self::Security => 'Security document',
            self::Undertaking => 'Undertaking / letter / declaration',
        };
    }
}
