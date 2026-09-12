<?php

namespace Webkul\Pbx\Exceptions;

use Exception;

/**
 * Thrown when the acting user has no `extension` (Ramal) set on their own
 * User record — click-to-call always originates from the logged-in user's
 * own extension (Phase 2), so there's nothing to ring first without one.
 */
class NoExtensionConfiguredException extends Exception
{
    public function __construct()
    {
        parent::__construct('The current user has no PBX extension (ramal) configured.');
    }
}
