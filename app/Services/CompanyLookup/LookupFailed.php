<?php

namespace App\Services\CompanyLookup;

use RuntimeException;

/** The message is shown to the user, so keep it plain and free of service internals. */
class LookupFailed extends RuntimeException
{
    public static function notFound(string $what): self
    {
        return new self("No {$what} record was found. Check the number, or enter the details by hand.");
    }

    public static function unavailable(): self
    {
        return new self('The lookup service is not responding right now. Enter the details by hand, or try again later.');
    }
}
