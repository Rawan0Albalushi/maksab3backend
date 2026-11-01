<?php

namespace App\Exceptions;

use Exception;

/**
 * DESIGN RULE E1 — one ETA configuration per (zone, module).
 *
 * Thrown by EtaConfigurationService::create() when the locked re-check inside the transaction
 * finds a conflict the form request could not have seen: two saves for the same pair arriving at
 * once both pass validation, and only the second reaches this.
 *
 * A panel concern, so it carries a message and nothing else — the controller turns it into the
 * same redirect-with-error an ordinary validation failure produces.
 */
class DuplicateEtaConfigurationException extends Exception
{
}
