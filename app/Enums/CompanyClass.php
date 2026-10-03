<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CompanyClass: string
{
    use HasOptions;

    case Public = 'public';
    case Private = 'private';
    case OnePerson = 'one_person';
    case Section8 = 'section_8';
    case Government = 'government';
    case Foreign = 'foreign';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
            self::OnePerson => 'One person company',
            self::Section8 => 'Section 8 (not for profit)',
            self::Government => 'Government company',
            self::Foreign => 'Foreign company',
            self::Unlimited => 'Unlimited',
        };
    }

    /** Maps the MCA ownership code in a CIN (e.g. PLC, PTC) to a class. */
    public static function fromCinCode(string $code): ?self
    {
        return match ($code) {
            'PLC' => self::Public,
            'PTC' => self::Private,
            'OPC' => self::OnePerson,
            'NPL' => self::Section8,
            'GOI', 'SGC', 'GAP', 'GAT' => self::Government,
            'FLC', 'FTC' => self::Foreign,
            'ULL', 'ULT' => self::Unlimited,
            default => null,
        };
    }
}
