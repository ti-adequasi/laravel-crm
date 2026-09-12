<?php

namespace Webkul\Pbx\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Webkul\Pbx\Exceptions\PbxNotConfiguredException;
use Webkul\Pbx\Repositories\PbxSettingRepository;

/**
 * Thin wrapper over the PBX Inovalen REST API — every method reads the
 * CURRENT tenant's own api_key via PbxSettingRepository (BelongsToTenant,
 * no fallback) and talks to the one shared base URL every tenant on this
 * install uses (config('pbx.base_url')). Never called from the browser
 * directly; every caller is a CRM controller, so the key never leaves the
 * server.
 */
class PbxClient
{
    public function __construct(protected PbxSettingRepository $settingRepository) {}

    /**
     * Whether the current tenant has a usable PBX connection — the single
     * check every PBX-surfaced screen/endpoint should gate on before doing
     * anything else.
     */
    public function isConfigured(): bool
    {
        $setting = $this->settingRepository->findForCurrentTenant();

        return $setting->exists && $setting->enabled && ! empty($setting->api_key);
    }

    /**
     * Identifies the current tenant's own key: {username, domain_uuid,
     * domain_name, is_reseller}. Used by the settings screen's "Testar
     * Conexão" action.
     */
    public function me(): array
    {
        return $this->client()->get('/v1/me')->throw()->json();
    }

    /**
     * Same as me(), but against an arbitrary key rather than the current
     * tenant's saved one — for the settings screen's "Testar Conexão",
     * which must be able to test a key just typed into the form before
     * it's ever saved (mirroring UserMail's own test-without-saving UX).
     */
    public function meWithKey(string $apiKey): array
    {
        return Http::baseUrl(config('pbx.base_url'))
            ->withHeaders(['X-API-Key' => $apiKey])
            ->withOptions(['verify' => config('pbx.verify_ssl')])
            ->timeout(config('pbx.timeout'))
            ->get('/v1/me')
            ->throw()
            ->json();
    }

    /**
     * The PBX's own user/extension directory for this tenant's domain —
     * used to validate an agent's typed extension actually exists.
     */
    public function users(): array
    {
        return $this->client()->get('/v1/users')->throw()->json();
    }

    /**
     * Originates a call: rings $ramal first, then bridges to $telefone
     * once answered. $telefone must already be normalized (national
     * format, no +55/55) — see Helpers\PhoneNumber.
     */
    public function originate(string $ramal, string $telefone): array
    {
        return $this->client()->post('/v1/dialer/calls', [
            'ramal' => $ramal,
            'telefone' => $telefone,
        ])->throw()->json();
    }

    /**
     * Current status of a call this tenant originated.
     */
    public function callStatus(string $callUuid): array
    {
        return $this->client()->get("/v1/dialer/calls/{$callUuid}")->throw()->json();
    }

    /**
     * Hangs up a call this tenant originated.
     */
    public function hangup(string $callUuid): array
    {
        return $this->client()->delete("/v1/dialer/calls/{$callUuid}")->throw()->json();
    }

    /**
     * Paginated CDR list for this tenant, with the API's own filters
     * (number, date_start/end, direction, answered, has_recording, ...).
     */
    public function calls(array $filters = []): array
    {
        return $this->client()->get('/v1/calls', $filters)->throw()->json();
    }

    /**
     * A clean, human-readable message from one of the PBX's own error
     * response shapes, or null if the response isn't shaped like either
     * one — checked directly against the live PBX, not assumed: GET
     * /v1/me responds `{"detail": "..."}` on a bad key, while POST
     * /v1/dialer/calls responds `{"error": {"code", "message"}}` on the
     * same kind of failure. Every caller falls back to
     * $e->getMessage() (Laravel's own "HTTP request returned status code
     * ...: {raw body}" string) when this returns null, rather than ever
     * showing that raw string to a user when a clean one was available.
     */
    public static function errorDetail(RequestException $e): ?string
    {
        $body = $e->response->json();

        foreach ([$body['detail'] ?? null, $body['error']['message'] ?? null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * A ready-to-use HTTP client bound to the current tenant's own key —
     * throws PbxNotConfiguredException up front rather than letting an
     * unconfigured tenant reach the PBX with an empty/missing key.
     */
    protected function client(): PendingRequest
    {
        $setting = $this->settingRepository->findForCurrentTenant();

        // Mirrors isConfigured() exactly — a tenant that has flipped the
        // "Enabled" switch off must be unable to reach the PBX at all
        // through any method here, not just see the settings screen as
        // disconnected. Before this check existed, only isConfigured()
        // (a UI-visibility check) looked at `enabled`; every actual PBX
        // call below it — originate() included — would still have gone
        // through on a saved key alone.
        if (! $setting->exists || ! $setting->enabled || empty($setting->api_key)) {
            throw new PbxNotConfiguredException;
        }

        return Http::baseUrl(config('pbx.base_url'))
            ->withHeaders(['X-API-Key' => $setting->api_key])
            ->withOptions(['verify' => config('pbx.verify_ssl')])
            ->timeout(config('pbx.timeout'));
    }
}
