<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where an active deal is in its life. Set to Preliminary when the engagement letter is issued;
 * every later change goes through a status request (see App\Actions\Deals\RequestDealStatusChange).
 */
enum DealStatus: string
{
    use HasOptions;

    case Preliminary = 'preliminary';
    case Documentation = 'documentation';
    case Live = 'live';
    case Hold = 'hold';
    case Defaulted = 'defaulted';
    case Redeemed = 'redeemed';
    case Foreclosed = 'foreclosed';
    case Surrendered = 'surrendered';
    case Transferred = 'transferred';
    case Cancelled = 'cancelled';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Preliminary => 'Preliminary',
            self::Documentation => 'Documentation',
            self::Live => 'Live',
            self::Hold => 'On hold',
            self::Defaulted => 'Defaulted',
            self::Redeemed => 'Redeemed',
            self::Foreclosed => 'Foreclosed',
            self::Surrendered => 'Surrendered',
            self::Transferred => 'Transferred',
            self::Cancelled => 'Cancelled',
            self::Closed => 'Closed',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Preliminary => [self::Documentation, self::Live, self::Hold, self::Cancelled],
            self::Documentation => [self::Live, self::Hold, self::Cancelled],
            self::Live => [self::Hold, self::Defaulted, self::Redeemed, self::Foreclosed, self::Surrendered, self::Transferred, self::Closed],
            // A deal on hold resumes at the status it was put on hold from (checked against its history), or is cancelled.
            self::Hold => [self::Preliminary, self::Documentation, self::Live, self::Cancelled],
            self::Defaulted => [self::Live, self::Redeemed, self::Foreclosed, self::Closed],
            self::Redeemed, self::Foreclosed, self::Surrendered, self::Transferred, self::Cancelled, self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** The deal is over: the transaction closes with it and nothing more can change except through God Mode. */
    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    /**
     * The teams that must approve moving from this status to $next (same rules as legacy):
     * putting a deal on hold needs nobody, cancelling a preliminary deal needs Management, the rest
     * needs Management and Accounts.
     *
     * @return list<StatusApprovalTeam>
     */
    public function approvalTeamsFor(self $next): array
    {
        return match (true) {
            $next === self::Hold => [],
            $this === self::Preliminary && $next === self::Cancelled => [StatusApprovalTeam::Management],
            default => [StatusApprovalTeam::Management, StatusApprovalTeam::Accounts],
        };
    }

    /** Leaving Live needs the debenture holders' no-objection certificate uploaded with the request. */
    public function needsNocToLeave(): bool
    {
        return $this === self::Live;
    }

    /** Pill colour (DESIGN.md §2.4). */
    public function tone(): string
    {
        return match ($this) {
            self::Live => 'success',
            self::Preliminary, self::Documentation => 'brand',
            self::Hold => 'warning',
            self::Defaulted, self::Cancelled => 'danger',
            self::Redeemed, self::Foreclosed, self::Surrendered, self::Transferred, self::Closed => 'neutral',
        };
    }
}
