<?php

namespace App\Services\Promotion;

use RuntimeException;

/**
 * A happy hour that would overlap an existing one in the same module.
 *
 * Thrown rather than returned so the surrounding transaction rolls back the row that was written
 * before the schedule could be checked -- the conflict is only knowable once the windows are
 * expanded, and expanding needs the saved model.
 *
 * Carries the clashing date and window because a bare "time conflict" leaves the admin to find it
 * by hand; the form renders it inline rather than as a toast.
 */
class HappyHourConflict extends RuntimeException
{
    public function __construct(public readonly array $conflict)
    {
        parent::__construct($conflict['message'] ?? translate('messages.Time_conflict_with_an_existing_happy_hour'));
    }
}
