<?php

namespace Webkul\Pbx\Exceptions;

use Exception;

/**
 * Thrown when the current tenant has no PBX connection configured (or it's
 * disabled) — callers should catch this specifically to show a "connect
 * your PBX first" message rather than a generic HTTP failure.
 */
class PbxNotConfiguredException extends Exception
{
    public function __construct()
    {
        parent::__construct('PBX integration is not configured for the current tenant.');
    }
}
