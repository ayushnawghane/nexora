<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A transaction's lifecycle. Only the transitions listed in allowedNext() are possible; every
 * status change goes through Transaction::transitionTo(), which enforces this.
 */
enum TransactionStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Rejected => 'Rejected',
            self::Approved => 'Approved',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval, self::Cancelled],
            self::PendingApproval => [self::Approved, self::Rejected],
            // A rejected transaction goes back to draft to be corrected and re-submitted.
            self::Rejected => [self::Draft, self::Cancelled],
            // Approved → Active when the engagement letter is issued.
            self::Approved => [self::Active, self::Cancelled],
            self::Active => [self::Closed],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Only drafts (including rejected ones sent back) can be edited through the normal screens. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Pill colour (DESIGN.md §2.4). */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::PendingApproval => 'warning',
            self::Rejected, self::Cancelled => 'danger',
            self::Approved, self::Active => 'success',
            self::Closed => 'neutral',
        };
    }
}
