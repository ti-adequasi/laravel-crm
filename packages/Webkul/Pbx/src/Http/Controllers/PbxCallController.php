<?php

namespace Webkul\Pbx\Http\Controllers;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Webkul\Pbx\Exceptions\InvalidPhoneNumberException;
use Webkul\Pbx\Exceptions\NoExtensionConfiguredException;
use Webkul\Pbx\Exceptions\PbxNotConfiguredException;
use Webkul\Pbx\Exceptions\PbxOriginateResponseException;
use Webkul\Pbx\Http\Requests\PbxCallOriginateForm;
use Webkul\Pbx\Models\PbxCall;
use Webkul\Pbx\Repositories\PbxCallRepository;
use Webkul\Pbx\Services\PbxCallService;
use Webkul\Pbx\Services\PbxClient;

/**
 * The browser never talks to the PBX directly (see PbxClient's own
 * docblock) — every action here is a thin, JSON-only wrapper around
 * PbxCallService, all under the `tenant`+`user` middleware like every
 * other Pbx route (see routes.php).
 */
class PbxCallController extends Controller
{
    public function __construct(
        protected PbxCallService $callService,
        protected PbxCallRepository $callRepository,
    ) {}

    /**
     * Originates a call from the acting user's own extension to `phone`,
     * on behalf of the given Lead/Person. See PbxCallService::originate()
     * for what each failure mode below actually means.
     */
    public function originate(PbxCallOriginateForm $request): JsonResponse
    {
        try {
            $call = $this->callService->originate(
                (int) $request->input('lead_id'),
                $request->filled('person_id') ? (int) $request->input('person_id') : null,
                $request->input('phone'),
            );
        } catch (PbxNotConfiguredException $e) {
            return response()->json(['message' => trans('pbx::app.calls.not-configured')], 422);
        } catch (NoExtensionConfiguredException $e) {
            return response()->json(['message' => trans('pbx::app.calls.no-extension')], 422);
        } catch (InvalidPhoneNumberException $e) {
            return response()->json(['message' => trans('pbx::app.calls.invalid-phone')], 422);
        } catch (PbxOriginateResponseException $e) {
            // The PBX accepted the call (so it may well be ringing right
            // now) but responded in a shape this app doesn't recognize —
            // logged for later inspection precisely because the API's own
            // spec leaves this response untyped, so a real, unanticipated
            // shape is expected sooner or later, not a bug to just fix and
            // forget.
            report($e);

            return response()->json(['message' => trans('pbx::app.calls.originate-failed-generic')], 502);
        } catch (RequestException $e) {
            return response()->json([
                'message' => trans('pbx::app.calls.originate-failed', [
                    'error' => PbxClient::errorDetail($e) ?? $e->getMessage(),
                ]),
            ], 422);
        }

        return response()->json([
            'call_uuid' => $call->call_uuid,
            'status_url' => route('admin.pbx.calls.status', $call->call_uuid),
            'hangup_url' => route('admin.pbx.calls.hangup', $call->call_uuid),
        ]);
    }

    /**
     * Polled every few seconds by the calling button's own Vue component
     * until `ended` comes back true.
     */
    public function status(string $callUuid): JsonResponse
    {
        $call = $this->resolveOwnCall($callUuid);

        if ($call instanceof JsonResponse) {
            return $call;
        }

        return response()->json($this->present($this->callService->pollStatus($call)));
    }

    /**
     * Ends a call this same user originated — never another agent's, even
     * one placed from the same tenant (see PbxCallService's class docblock
     * on why the API key alone isn't enough isolation for this).
     */
    public function hangup(string $callUuid): JsonResponse
    {
        $call = $this->resolveOwnCall($callUuid);

        if ($call instanceof JsonResponse) {
            return $call;
        }

        try {
            $call = $this->callService->hangup($call);
        } catch (RequestException $e) {
            return response()->json(['message' => trans('pbx::app.calls.hangup-failed-generic')], 502);
        }

        return response()->json($this->present($call));
    }

    /**
     * Shared by status() and hangup(): the call must exist for the
     * current tenant (BelongsToTenant's global scope, via the repository —
     * a call_uuid that's valid but belongs to a different tenant is
     * indistinguishable from one that doesn't exist at all) and must have
     * been initiated by the acting user.
     */
    private function resolveOwnCall(string $callUuid): PbxCall|JsonResponse
    {
        $call = $this->callRepository->findByCallUuid($callUuid);

        if (! $call) {
            return response()->json(['message' => trans('pbx::app.calls.not-found')], 404);
        }

        if ($call->initiated_by_user_id !== auth()->id()) {
            return response()->json(['message' => trans('pbx::app.calls.not-yours')], 403);
        }

        return $call;
    }

    private function present(PbxCall $call): array
    {
        return [
            'status' => $call->status,
            'ended' => $call->hasEnded(),
        ];
    }
}
