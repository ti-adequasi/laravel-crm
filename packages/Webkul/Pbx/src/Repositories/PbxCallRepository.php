<?php

namespace Webkul\Pbx\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\Pbx\Contracts\PbxCall;

class PbxCallRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return PbxCall::class;
    }

    /**
     * A call by its PBX-assigned UUID, scoped to the current tenant like
     * any other read through this repository (BelongsToTenant's global
     * scope) — never another tenant's call, regardless of who guesses a
     * valid-looking UUID.
     */
    public function findByCallUuid(string $callUuid): ?PbxCall
    {
        return $this->model->where('call_uuid', $callUuid)->first();
    }

    /**
     * Calls that never reached a terminal state through normal polling —
     * either the browser tab was closed mid-call, or the poll requests
     * themselves failed. Consumed by the reconciliation command
     * (ReconcilePbxCalls), never by the browser-facing endpoints, and
     * deliberately not tenant-scoped: the command sweeps every tenant in
     * one pass, so it queries across the global scope explicitly.
     */
    public function findStuck(\DateTimeInterface $olderThan)
    {
        return $this->model->withoutGlobalScopes()
            ->whereNull('ended_at')
            ->where('created_at', '<', $olderThan)
            ->get();
    }
}
