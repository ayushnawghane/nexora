<?php

namespace App\Services\CompanyLookup;

final readonly class GstinDetails
{
    public function __construct(
        public string $gstin,
        public ?string $legalName = null,
        public ?string $tradeName = null,
        public ?string $registeredOn = null,
        public ?string $status = null,
        public ?string $cancelledOn = null,
        public ?string $constitution = null,
        public ?string $principalAddress = null,
    ) {}

    /** GSTN reports "Active", "Cancelled", "Suspended"…; anything but Active needs a second look. */
    public function isActive(): bool
    {
        return $this->status === null || strcasecmp($this->status, 'Active') === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gstin' => $this->gstin,
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'registered_on' => $this->registeredOn,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'cancelled_on' => $this->cancelledOn,
            'constitution' => $this->constitution,
            'principal_address' => $this->principalAddress,
        ];
    }
}
