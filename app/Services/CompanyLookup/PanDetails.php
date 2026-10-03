<?php

namespace App\Services\CompanyLookup;

final readonly class PanDetails
{
    public function __construct(public string $pan, public string $name) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['pan' => $this->pan, 'name' => $this->name];
    }
}
