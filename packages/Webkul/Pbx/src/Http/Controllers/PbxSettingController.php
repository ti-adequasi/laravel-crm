<?php

namespace Webkul\Pbx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Webkul\Pbx\Http\Requests\PbxSettingForm;
use Webkul\Pbx\Repositories\PbxSettingRepository;
use Webkul\Pbx\Services\PbxClient;

class PbxSettingController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PbxSettingRepository $settingRepository,
        protected PbxClient $pbxClient,
    ) {}

    /**
     * Show the current tenant's own PBX connection settings.
     */
    public function edit(): View
    {
        $setting = $this->settingRepository->findForCurrentTenant();

        return view('pbx::settings.edit', compact('setting'));
    }

    /**
     * Save the current tenant's own settings — never any other tenant's,
     * see PbxSettingRepository::saveForCurrentTenant().
     *
     * JSON, not a redirect: this is called via $axios.put from the Vue
     * component (see settings/edit.blade.php), and a redirect response to
     * an XHR call gets silently followed by the browser inside that same
     * XHR — landing on the GET edit page's HTML, which axios then reports
     * as a failure since it isn't the JSON the .then()/.catch() handlers
     * expect. (See crm-package-development/SKILL.md's own note on this —
     * the same mistake, made once already this session, in a different
     * package.)
     */
    public function update(PbxSettingForm $request): JsonResponse
    {
        $this->settingRepository->saveForCurrentTenant($request->validated());

        return response()->json([
            'message' => trans('pbx::app.settings.save-success'),
        ]);
    }

    /**
     * Live handshake against the PBX — no side effects, nothing saved,
     * same shape as UserMail's own connection test. Tests whatever key
     * was just typed into the form; falls back to the already-saved key
     * on a blank submission, since the field never re-displays the real
     * (encrypted) value once saved.
     */
    public function test(PbxSettingForm $request): JsonResponse
    {
        $apiKey = $request->input('api_key');

        if (empty($apiKey)) {
            $existing = $this->settingRepository->findForCurrentTenant();
            $apiKey = $existing->exists ? $existing->api_key : null;
        }

        if (empty($apiKey)) {
            return response()->json([
                'message' => trans('pbx::app.settings.test-missing-key'),
            ], 422);
        }

        try {
            $identity = $this->pbxClient->meWithKey($apiKey);

            return response()->json([
                'message' => trans('pbx::app.settings.test-success', [
                    'domain' => $identity['domain_name'] ?? '?',
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => trans('pbx::app.settings.test-failed', ['error' => $e->getMessage()]),
            ], 422);
        }
    }
}
