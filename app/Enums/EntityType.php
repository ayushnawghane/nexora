<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** What kind of legal entity a company record is; decides which registration number applies. */
enum EntityType: string
{
    use HasOptions;

    case Company = 'company';
    case Llp = 'llp';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Company (CIN)',
            self::Llp => 'LLP (LLPIN)',
            self::Other => 'Other entity (firm, trust, bank, body corporate…)',
        };
    }

    /**
     * PAN holder-type letters (4th PAN character) the entity's PAN may carry; empty means any.
     *
     * @return list<string>
     */
    public function panHolderTypes(): array
    {
        return match ($this) {
            self::Company => ['C'],
            self::Llp => ['F', 'E'],
            self::Other => [],
        };
    }
}
