<?php

namespace App\Exceptions;

use Exception;

/**
 * One Additional Delivery Charge setup per (zone, module).
 *
 * Thrown by AdditionalDeliveryChargeService::create() when the locked re-check inside the
 * transaction finds a clash the controller's guard could not have seen: two saves for the same
 * pair arriving at once both read an empty conflict list, and only the second reaches this.
 *
 * The same shape as DuplicateEtaConfigurationException — a panel concern carrying a message, which
 * the controller turns into the redirect-with-error an ordinary clash produces.
 */
class DuplicateAdditionalDeliveryChargeException extends Exception
{
}
