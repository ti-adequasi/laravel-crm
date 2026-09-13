<?php

namespace Webkul\Pbx\Support;

use Webkul\Pbx\Exceptions\InvalidPhoneNumberException;

/**
 * Turns whatever a Person's phone-number field happens to contain into the
 * plain national-format digit string the PBX's `telefone` parameter expects
 * (DDD + subscriber number, no `+55`/`55` country code, no formatting).
 *
 * The CRM itself has no phone normalization anywhere today — this is new
 * logic, not a port of an existing helper. It deliberately rejects (throws)
 * rather than guesses when the input doesn't confidently resolve to a real
 * Brazilian number, because sending a malformed `telefone` to the PBX dials
 * whatever number the PBX's own parsing happens to produce, silently.
 *
 * Brazilian numbering rules this relies on:
 * - DDD (area code) is always 2 digits, 11-99.
 * - A landline subscriber number is 8 digits (10 total with DDD).
 * - A mobile subscriber number is 9 digits and the first of those 9 is
 *   always literally '9' (the nationwide "9th digit" migration completed
 *   around 2016 — every mobile number has it, so its absence means the
 *   input isn't a genuine local mobile number, not that it's an old-style
 *   one) — 11 total with DDD.
 * - `+55`/`55` country code adds 2 digits on top (12 total for a landline,
 *   13 for a mobile).
 * - A single leading trunk `0` (as in "0 <DDD> <number>") adds 1 digit on
 *   top of the local form. At 12 digits this never collides with a
 *   country-code reading: one requires the string to start with "55", the
 *   other with a single "0" — they can't both be true of the same string.
 */
class PhoneNumberNormalizer
{
    /**
     * What POST /v1/dialer/calls' own `telefone` actually needs to
     * route correctly — confirmed directly with the user after they fixed
     * the PBX's own outbound dial-plan rule, which requires a literal
     * leading "0" trunk-prefix digit in front of the plain national number
     * normalize() produces, for landline and mobile numbers alike (not
     * just a "dial 0 for an outside line" mobile-only convention). Kept
     * separate from normalize()'s own return value on purpose:
     * normalize()'s output (no leading 0) is the canonical form used for
     * de-duplication and for querying GET /v1/calls' own partial-match
     * `number` filter, and a stored CDR's own destination_number is a
     * substring match either way regardless of a leading 0 — only the
     * outbound POST body itself needs this extra digit.
     *
     * @throws InvalidPhoneNumberException
     */
    public static function normalizeForDialing(?string $raw): string
    {
        return '0'.self::normalize($raw);
    }

    /**
     * @throws InvalidPhoneNumberException
     */
    public static function normalize(?string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        if ($digits === '') {
            throw new InvalidPhoneNumberException((string) $raw);
        }

        $national = match (strlen($digits)) {
            10 => $digits,
            11 => self::asLocalMobile($digits),
            12 => str_starts_with($digits, '55')
                ? self::asLocalLandline(substr($digits, 2))
                : (str_starts_with($digits, '0')
                    ? self::asLocalMobile(substr($digits, 1))
                    : null),
            13 => str_starts_with($digits, '55')
                ? self::asLocalMobile(substr($digits, 2))
                : null,
            default => null,
        };

        if ($national === null) {
            throw new InvalidPhoneNumberException((string) $raw);
        }

        return $national;
    }

    /**
     * Validates a 10-digit string as DDD (11-99) + 8-digit landline number.
     */
    private static function asLocalLandline(string $tenDigits): ?string
    {
        if (strlen($tenDigits) !== 10 || ! self::hasValidDdd($tenDigits)) {
            return null;
        }

        return $tenDigits;
    }

    /**
     * Validates an 11-digit string as DDD (11-99) + 9-digit mobile number
     * whose first subscriber digit must be '9'.
     */
    private static function asLocalMobile(string $elevenDigits): ?string
    {
        if (strlen($elevenDigits) !== 11 || ! self::hasValidDdd($elevenDigits) || $elevenDigits[2] !== '9') {
            return null;
        }

        return $elevenDigits;
    }

    private static function hasValidDdd(string $digits): bool
    {
        $ddd = (int) substr($digits, 0, 2);

        return $ddd >= 11 && $ddd <= 99;
    }
}
