<?php

namespace Webkul\Pbx\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Pbx\Repositories\PbxCallRepository;
use Webkul\Pbx\Services\PbxCallService;
use Webkul\Tenant\Support\CurrentTenant;

/**
 * Catches click-to-call attempts that never reached a terminal state
 * through the browser's own polling — the browser tab was closed (or the
 * whole machine slept/crashed) before the call ended, so nothing ever
 * called PbxCallService::pollStatus() to notice and log the Activity. Every
 * *other* path (the live poll loop, an explicit hangup) already finalizes
 * a call the moment it ends; this command exists purely for the case where
 * neither happened, not as the primary way calls get finalized.
 *
 * Each stuck call is processed under its own tenant's context
 * (CurrentTenant::runAs()/runAsNullTenant(), matching CurrentTenant's own
 * eachActiveTenant() convention) — PbxClient resolves the tenant's api_key
 * from whatever tenant is currently bound, and this command sweeps every
 * tenant's stuck calls in one run, so getting this wrong would poll one
 * tenant's call using a different tenant's key.
 */
class ReconcilePbxCalls extends Command
{
    protected $signature = 'pbx:reconcile-calls
                            {--older-than=10 : Only process calls originated at least this many minutes ago}';

    protected $description = 'Finalize click-to-call attempts whose browser tab closed before the call ended';

    public function __construct(
        protected PbxCallRepository $callRepository,
        protected PbxCallService $callService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) $this->option('older-than'));

        $stuckCalls = $this->callRepository->findStuck($cutoff);

        $this->info("Found {$stuckCalls->count()} stuck call(s) originated before {$cutoff}.");

        foreach ($stuckCalls as $call) {
            $tenantId = $call->tenant_id;

            $run = fn () => $this->callService->pollStatus($call);

            $polled = $tenantId === null
                ? CurrentTenant::runAsNullTenant($run)
                : CurrentTenant::runAs($tenantId, $run);

            $this->line("  {$call->call_uuid}: ".($polled->hasEnded() ? 'finalized' : 'still not ended, left as is'));
        }

        return self::SUCCESS;
    }
}
