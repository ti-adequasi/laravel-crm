<?php

namespace Webkul\Pbx\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Pbx\Exceptions\PbxNotConfiguredException;
use Webkul\Pbx\Services\PbxClient;
use Webkul\Pbx\Support\PbxCallStatusInterpreter;
use Webkul\Pbx\Support\PhoneNumberNormalizer;

/**
 * Read-only, live proxy to the PBX's own CDR (call detail record) list —
 * deliberately not synced into this app's own database (see the plan's own
 * "what's out of scope" list): the PBX is the source of truth for calls
 * placed or received outside the CRM entirely (a physical deskphone's own
 * inbound/outbound activity), and duplicating all of it into Activity rows
 * would just be two copies of the same data going out of sync.
 */
class PbxCallHistoryController extends Controller
{
    public function __construct(
        protected PbxClient $pbxClient,
        protected PersonRepository $personRepository,
    ) {}

    /**
     * Every CDR entry touching any of this Person's own phone numbers,
     * across a single call to /v1/calls per number (its own `number` filter
     * matches origin OR destination, but only takes one number at a time —
     * and does its own partial match, confirmed live: a call placed to
     * "965963302" is found by that filter even though it isn't a complete
     * national number by PhoneNumberNormalizer's own rules, e.g. missing a
     * DDD), merged and de-duplicated by xml_cdr_uuid, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->pbxClient->isConfigured()) {
            return response()->json(['message' => trans('pbx::app.calls.not-configured')], 422);
        }

        $person = $this->personRepository->find((int) $request->query('person_id'));

        if (! $person) {
            return response()->json(['message' => trans('pbx::app.calls.history-person-not-found')], 404);
        }

        $numbers = collect($person->contact_numbers ?? [])
            ->pluck('value')
            ->filter()
            ->map(fn ($raw) => $this->matchableNumber($raw))
            ->filter()
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return response()->json(['calls' => []]);
        }

        try {
            $calls = $numbers
                ->flatMap(fn (string $number) => $this->pbxClient->calls(['number' => $number, 'limit' => 100])['items'] ?? [])
                ->unique('xml_cdr_uuid')
                ->sortByDesc('start_stamp')
                ->values();
        } catch (PbxNotConfiguredException $e) {
            return response()->json(['message' => trans('pbx::app.calls.not-configured')], 422);
        } catch (RequestException $e) {
            return response()->json([
                'message' => trans('pbx::app.calls.history-fetch-failed', ['error' => PbxClient::errorDetail($e) ?? $e->getMessage()]),
            ], 502);
        }

        return response()->json(['calls' => $calls]);
    }

    /**
     * A short-lived playback URL for one CDR entry's recording — fetched
     * on demand (when the user actually presses play), not eagerly for
     * every row in the list, since each one is a separate PBX request and
     * most rows in a call list are never played back.
     */
    public function recordingUrl(string $xmlCdrUuid): JsonResponse
    {
        try {
            $response = $this->pbxClient->recordingSignedUrl($xmlCdrUuid);
        } catch (PbxNotConfiguredException $e) {
            return response()->json(['message' => trans('pbx::app.calls.not-configured')], 422);
        } catch (RequestException $e) {
            return response()->json([
                'message' => trans('pbx::app.calls.recording-fetch-failed', ['error' => PbxClient::errorDetail($e) ?? $e->getMessage()]),
            ], 502);
        }

        $url = PbxCallStatusInterpreter::extractSignedUrl($response);

        if ($url === null) {
            return response()->json(['message' => trans('pbx::app.calls.recording-fetch-failed-generic')], 502);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * A best-effort string to query /v1/calls' own `number` filter with —
     * looser than PhoneNumberNormalizer::normalize(), deliberately: that
     * one exists to keep Phase 3 from ever *dialing* a malformed number,
     * a real safety concern this read-only lookup doesn't share. Prefers
     * the fully-normalized form when the number resolves cleanly, but
     * falls back to plain stripped digits otherwise — confirmed useful
     * against this PBX's own real CDR data, which contains numbers dialed
     * without a DDD that PhoneNumberNormalizer correctly refuses to treat
     * as complete. A handful of stray digits (a too-short string) is
     * still excluded, since /v1/calls' partial match would otherwise
     * return an unhelpfully broad result for basically any number sharing
     * those few digits.
     */
    private function matchableNumber(?string $raw): ?string
    {
        try {
            return PhoneNumberNormalizer::normalize($raw);
        } catch (\Throwable) {
            $digits = preg_replace('/\D/', '', (string) $raw);

            return strlen($digits) >= 6 ? $digits : null;
        }
    }
}
