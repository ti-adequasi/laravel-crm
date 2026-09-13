<?php

namespace Webkul\Pbx\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Pbx\Exceptions\InvalidPhoneNumberException;
use Webkul\Pbx\Exceptions\NoExtensionConfiguredException;
use Webkul\Pbx\Exceptions\PbxNotConfiguredException;
use Webkul\Pbx\Exceptions\PbxOriginateResponseException;
use Webkul\Pbx\Models\PbxCall;
use Webkul\Pbx\Repositories\PbxCallRepository;
use Webkul\Pbx\Repositories\PbxSettingRepository;
use Webkul\Pbx\Support\PbxCallStatusInterpreter;
use Webkul\Pbx\Support\PhoneNumberNormalizer;
use Webkul\Tenant\Support\CurrentTenant;

/**
 * Orchestrates a click-to-call attempt end to end: originate, poll, hang
 * up, and — the part that must not depend on the browser tab staying open
 * — auto-logging the Activity as soon as a terminal state is observed,
 * whether that observation comes from the browser's own polling
 * (pollStatus(), called from PbxCallController) or from the reconciliation
 * command sweeping calls the browser never got a terminal answer for
 * (ReconcilePbxCalls).
 *
 * Tenant context: originate()/pollStatus()/hangup() all trust the ambient
 * CurrentTenant (exactly like PbxClient itself does) rather than switching
 * it themselves — correct for the live per-request path, where the
 * `tenant` middleware already bound the right one. ReconcilePbxCalls is
 * the one caller that processes calls across every tenant in a single
 * run, so it's the one responsible for wrapping each call's processing in
 * CurrentTenant::runAs()/runAsNullTenant() before calling in here.
 */
class PbxCallService
{
    public function __construct(
        protected PbxClient $pbxClient,
        protected PbxCallRepository $callRepository,
        protected PbxSettingRepository $settingRepository,
        protected ActivityRepository $activityRepository,
    ) {}

    /**
     * @throws PbxNotConfiguredException
     * @throws NoExtensionConfiguredException
     * @throws InvalidPhoneNumberException
     * @throws PbxOriginateResponseException
     */
    public function originate(int $leadId, ?int $personId, string $rawPhone): PbxCall
    {
        // Checked explicitly (not just left to PbxClient::originate()'s own
        // client() guard) so a missing extension is reported before the
        // PBX is ever contacted, not as a confusing failure partway
        // through.
        if (! $this->pbxClient->isConfigured()) {
            throw new PbxNotConfiguredException;
        }

        $ramal = auth()->user()->extension;

        if (empty($ramal)) {
            throw new NoExtensionConfiguredException;
        }

        // Throws InvalidPhoneNumberException on anything it can't
        // confidently resolve — deliberately left to propagate rather than
        // caught here, so the PBX is never sent an unnormalized number.
        // normalizeForDialing() (not normalize()) is what the PBX's own
        // dial-plan actually requires — see its own docblock.
        $normalized = PhoneNumberNormalizer::normalizeForDialing($rawPhone);

        $originateResponse = $this->pbxClient->originate($ramal, $normalized);

        $callUuid = PbxCallStatusInterpreter::extractCallUuid($originateResponse);

        if ($callUuid === null) {
            throw new PbxOriginateResponseException($originateResponse);
        }

        return $this->callRepository->create([
            'tenant_id' => CurrentTenant::id(),
            'call_uuid' => $callUuid,
            'initiated_by_user_id' => auth()->id(),
            'lead_id' => $leadId,
            'person_id' => $personId,
            'ramal' => $ramal,
            'telefone_raw' => $rawPhone,
            'telefone' => $normalized,
            'direction' => 'outbound',
            'status' => PbxCallStatusInterpreter::statusLabel($originateResponse),
            'raw_originate_response' => $originateResponse,
        ]);
    }

    /**
     * Refreshes a call's status from the PBX. A transient failure (network
     * blip, PBX 5xx) leaves the call exactly as it was — silently, so the
     * browser's poll loop just tries again a few seconds later — except a
     * 404, treated as "the PBX no longer has this call in its live
     * registry," which in practice means it ended and was already cleaned
     * up server-side; that inference is marked explicitly in the stored
     * raw response (`_inferred_from_404`) rather than presented as
     * something the PBX itself said, since it's this app's guess, not a
     * confirmed fact — see PbxCallStatusInterpreter's own docblock on why
     * that distinction matters here.
     */
    public function pollStatus(PbxCall $call): PbxCall
    {
        if ($call->hasEnded()) {
            return $call;
        }

        try {
            $statusResponse = $this->pbxClient->callStatus($call->call_uuid);
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                return $call;
            }

            $statusResponse = ['status' => 'ended', '_inferred_from_404' => true];
        }

        $call->raw_last_status = $statusResponse;
        $call->status = PbxCallStatusInterpreter::statusLabel($statusResponse);

        if (! $call->hasEnded() && PbxCallStatusInterpreter::isEnded($statusResponse)) {
            $call->ended_at = now();
        }

        $call->save();

        if ($call->hasEnded()) {
            $this->logActivityIfNeeded($call);
        }

        return $call;
    }

    /**
     * Hangs up a call this tenant originated, then immediately re-polls so
     * the caller gets the PBX's own post-hangup status back rather than
     * having to poll again separately.
     */
    public function hangup(PbxCall $call): PbxCall
    {
        $this->pbxClient->hangup($call->call_uuid);

        return $this->pollStatus($call);
    }

    /**
     * Creates the auto-logged Activity for a just-ended call — once. Locks
     * the row for the duration of the check-and-mark so two near-
     * simultaneous callers (a browser poll and the reconciliation sweep
     * landing on the same call) can't both pass the "not logged yet" check
     * and create two Activities.
     */
    protected function logActivityIfNeeded(PbxCall $call): void
    {
        DB::transaction(function () use ($call) {
            $locked = $call->newQuery()->withoutGlobalScopes()->lockForUpdate()->find($call->id);

            if (! $locked || $locked->activity_logged_at !== null) {
                return;
            }

            $setting = $this->settingRepository->findForCurrentTenant();

            if ($setting->auto_log_activity) {
                $activity = $this->activityRepository->create([
                    'tenant_id' => $locked->tenant_id,
                    'type' => 'call',
                    'title' => trans('pbx::app.calls.activity-title', ['phone' => $locked->telefone]),
                    'comment' => trans('pbx::app.calls.activity-comment', [
                        'phone' => $locked->telefone,
                        'ramal' => $locked->ramal,
                    ]),
                    'is_done' => true,
                    'schedule_from' => $locked->created_at,
                    'schedule_to' => $locked->ended_at ?? now(),
                    'user_id' => $locked->initiated_by_user_id,
                    // Activity::$casts doesn't cast `additional` to/from
                    // array (verified against the model directly), so it's
                    // encoded by hand here and must be decoded by hand by
                    // anything that reads it back later.
                    'additional' => json_encode([
                        'call_uuid' => $locked->call_uuid,
                        'direction' => $locked->direction,
                        'telefone' => $locked->telefone,
                        'ramal' => $locked->ramal,
                        'duration_seconds' => PbxCallStatusInterpreter::extractDurationSeconds($locked->raw_last_status ?? []),
                        'status' => $locked->status,
                    ]),
                ]);

                if ($locked->lead_id) {
                    $activity->leads()->attach($locked->lead_id);
                }

                if ($locked->person_id) {
                    $activity->persons()->attach($locked->person_id);
                }
            }

            // Marked regardless of the toggle: a tenant with auto-logging
            // off has still "handled" this call as far as this method is
            // concerned — see the class docblock on ReconcilePbxCalls for
            // why re-checking forever would otherwise be wasted work, not
            // a way to retroactively log a call once the toggle flips back
            // on later.
            $locked->forceFill(['activity_logged_at' => now()])->save();
        });
    }
}
