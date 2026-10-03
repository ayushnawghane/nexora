<?php

namespace App\Services\CompanyLookup;

use App\Enums\CompanyCategory;

final readonly class CinDetails
{
    public function __construct(
        public string $cin,
        public string $name,
        public ?string $incorporatedOn = null,
        public ?CompanyCategory $category = null,
        public ?string $status = null,
        public ?string $email = null,
        public ?string $registeredAddress = null,
    ) {}

    /** Maps MCA's category wording ("Company limited by Shares") to our enum. */
    public static function categoryFrom(?string $mca): ?CompanyCategory
    {
        $mca = strtolower(trim((string) $mca));

        return match (true) {
            str_contains($mca, 'shares') => CompanyCategory::LimitedByShares,
            str_contains($mca, 'guarantee') => CompanyCategory::LimitedByGuarantee,
            str_contains($mca, 'unlimited') => CompanyCategory::Unlimited,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cin' => $this->cin,
            'name' => $this->name,
            'incorporated_on' => $this->incorporatedOn,
            'category' => $this->category?->value,
            'status' => $this->status,
            'email' => $this->email,
            'registered_address' => $this->registeredAddress,
        ];
    }
}
