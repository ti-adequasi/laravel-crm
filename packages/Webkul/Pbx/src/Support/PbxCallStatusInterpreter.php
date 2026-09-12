<?php

namespace Webkul\Pbx\Support;

/**
 * The PBX's own OpenAPI spec (`/pbxapi/openapi.json`) declares the dialer
 * endpoints' response bodies as a bare `{}` (an untyped object) for all
 * three of originate/status/hangup — verified directly against the spec,
 * not assumed. There is therefore no confirmed field name for "is this
 * call still going" or "what's its call_uuid"; this class is a deliberately
 * best-effort, defensive reading of a handful of plausible shapes, meant to
 * be corrected once a real API key makes a live test call possible (the
 * same live-verification gap the plan already flags for Phase 5's
 * `/v1/callcenter/status`). Every raw response is kept in full alongside
 * whatever this class extracts (see `pbx_calls.raw_last_status` /
 * `raw_originate_response`), so a wrong guess here is recoverable from data
 * already on hand rather than only from the next live call.
 */
class PbxCallStatusInterpreter
{
    /**
     * Status/state words treated as "the call is over" when found
     * (case-insensitively) under a `status` or `state` key.
     */
    private const TERMINAL_STATUS_WORDS = [
        'ended', 'end', 'hangup', 'hung_up', 'completed', 'complete', 'finished',
        'done', 'failed', 'failure', 'error', 'no_answer', 'no-answer', 'noanswer',
        'busy', 'cancelled', 'canceled', 'answered_and_hungup', 'terminated',
        'not_found', 'not-found',
    ];

    /**
     * Best-effort extraction of the identifier the PBX assigned this call,
     * from the originate response — the only place this ever needs to be
     * read, since every later call uses whatever this returns as the
     * `call_uuid` path parameter.
     */
    public static function extractCallUuid(array $originateResponse): ?string
    {
        foreach (['call_uuid', 'uuid', 'id', 'callUuid', 'call_id'] as $key) {
            $value = $originateResponse[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Whether a status-poll response indicates the call has reached a
     * terminal state. Errs toward "not ended" when the shape isn't
     * recognized at all — a false "still going" just means one more poll
     * a few seconds later, while a false "ended" would stop polling (and
     * log the Activity) too early.
     */
    public static function isEnded(array $statusResponse): bool
    {
        if (! empty($statusResponse['hangup_cause'])) {
            return true;
        }

        foreach (['ended_at', 'end_time', 'end_stamp'] as $key) {
            if (! empty($statusResponse[$key])) {
                return true;
            }
        }

        foreach (['active', 'is_active', 'ongoing', 'in_progress'] as $key) {
            if (array_key_exists($key, $statusResponse) && $statusResponse[$key] === false) {
                return true;
            }
        }

        foreach (['status', 'state'] as $key) {
            $value = $statusResponse[$key] ?? null;

            if (is_string($value) && in_array(strtolower($value), self::TERMINAL_STATUS_WORDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A short, storable label for the call's current state — display/
     * `pbx_calls.status` only, never used to decide `isEnded()` (that
     * method re-derives it from the raw response every time).
     */
    public static function statusLabel(array $statusResponse): string
    {
        foreach (['status', 'state'] as $key) {
            $value = $statusResponse[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return strtolower($value);
            }
        }

        return self::isEnded($statusResponse) ? 'ended' : 'unknown';
    }

    /**
     * Best-effort extraction of the actual URL from
     * GET /v1/calls/{uuid}/recording/signed-url's response — also declared
     * untyped in the OpenAPI spec, same reasoning as extractCallUuid().
     */
    public static function extractSignedUrl(array $signedUrlResponse): ?string
    {
        foreach (['url', 'signed_url', 'recording_url'] as $key) {
            $value = $signedUrlResponse[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Best-effort call duration in seconds, if the response happens to
     * carry one under a recognizable key — used only to enrich the
     * auto-logged Activity's `additional` payload, never anything
     * authoritative (the CDR list in Phase 4 is the real source of truth
     * for billed duration).
     */
    public static function extractDurationSeconds(array $statusResponse): ?int
    {
        foreach (['duration', 'billsec', 'duration_seconds'] as $key) {
            $value = $statusResponse[$key] ?? null;

            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return (int) $value;
            }
        }

        return null;
    }
}
