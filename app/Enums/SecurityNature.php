<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The kind of security a legal document creates. */
enum SecurityNature: string
{
    use HasOptions;

    case Hypothecation = 'hypothecation';
    case Mortgage = 'mortgage';
    case Pledge = 'pledge';
    case Guarantee = 'guarantee';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hypothecation => 'Hypothecation',
            self::Mortgage => 'Mortgage',
            self::Pledge => 'Pledge',
            self::Guarantee => 'Guarantee',
            self::Other => 'Other',
        };
    }

    /**
     * The registrations this kind of security can have.
     *
     * @return list<RegistrationKind>
     */
    public function registrationKinds(): array
    {
        return match ($this) {
            self::Pledge => [RegistrationKind::Pledge],
            self::Guarantee => [],
            default => [RegistrationKind::Roc, RegistrationKind::Cersai],
        };
    }
}
