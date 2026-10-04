<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The documents of a deal's due diligence (Stack's due diligence screen). */
enum DiligenceKind: string
{
    use HasOptions;

    case RocSearch = 'roc_search';
    case SecurityCertificate = 'security_certificate';
    case Noc = 'noc';
    case SecurityCover = 'security_cover';
    case AnnexureA = 'annexure_a';
    case AnnexureB = 'annexure_b';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RocSearch => 'ROC search report',
            self::SecurityCertificate => 'Security certificate',
            self::Noc => 'NOC from charge holder',
            self::SecurityCover => 'Security cover certificate',
            self::AnnexureA => 'Annexure A',
            self::AnnexureB => 'Annexure B',
            self::Other => 'Other document',
        };
    }

    /** Whether the item is about one of the deal's securities. */
    public function needsSecurity(): bool
    {
        return in_array($this, [self::SecurityCertificate, self::Noc], true);
    }

    /** Whether the item is about one asset owner. */
    public function needsAssetOwner(): bool
    {
        return $this === self::RocSearch;
    }
}
