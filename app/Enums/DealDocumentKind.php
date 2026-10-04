<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A deal carries one standard copy of a legal document type, plus any number of further copies,
 * supplements and amendments, each numbered from 1 per kind.
 */
enum DealDocumentKind: string
{
    use HasOptions;

    case Standard = 'standard';
    case Additional = 'additional';
    case Supplement = 'supplement';
    case Amendment = 'amendment';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Document',
            self::Additional => 'Another copy',
            self::Supplement => 'Supplement',
            self::Amendment => 'Amendment',
        };
    }

    /** The name the deal document gets, e.g. "Supplement Deed Of Hypothecation-2" (Stack's naming). */
    public function documentName(string $typeName, int $sequence): string
    {
        return match ($this) {
            self::Standard => $typeName,
            self::Additional => "{$typeName}-{$sequence}",
            self::Supplement => "Supplement {$typeName}-{$sequence}",
            self::Amendment => "Amendment {$typeName}-{$sequence}",
        };
    }
}
