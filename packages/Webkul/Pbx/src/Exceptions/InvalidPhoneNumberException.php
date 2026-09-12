<?php

namespace Webkul\Pbx\Exceptions;

use Exception;

/**
 * Thrown when a phone number can't be confidently normalized into the
 * national format the PBX expects (no +55/55 prefix). Deliberately fails
 * closed — callers must not fall back to sending the raw, unnormalized
 * input to the PBX, since a malformed `telefone` value dials whatever
 * number the PBX's own loose parsing happens to produce.
 */
class InvalidPhoneNumberException extends Exception
{
    public function __construct(string $raw)
    {
        parent::__construct("\"{$raw}\" doesn't look like a valid Brazilian phone number.");
    }
}
