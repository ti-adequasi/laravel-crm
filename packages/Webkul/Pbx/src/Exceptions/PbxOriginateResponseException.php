<?php

namespace Webkul\Pbx\Exceptions;

use Exception;

/**
 * Thrown when the PBX's response to POST /v1/dialer/calls doesn't contain
 * any of the field names PbxCallStatusInterpreter::extractCallUuid()
 * recognizes as a call identifier. The OpenAPI spec declares this response
 * as an untyped `{}`, so this is a real, expected possibility, not a
 * theoretical one — callers must not proceed without a call_uuid to poll
 * and hang up by.
 */
class PbxOriginateResponseException extends Exception
{
    public function __construct(array $response)
    {
        parent::__construct(
            'The PBX accepted the call but its response did not include a recognizable call UUID: '
            .json_encode($response)
        );
    }
}
