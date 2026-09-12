<?php

namespace Webkul\Pbx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Pbx\Contracts\PbxCall as PbxCallContract;
use Webkul\Tenant\Traits\BelongsToTenant;
use Webkul\User\Models\UserProxy;

/**
 * One row per click-to-call attempt originated through the CRM (Phase 3).
 * See the migration for the reasoning behind `call_uuid` and the two raw
 * JSON snapshot columns.
 */
class PbxCall extends Model implements PbxCallContract
{
    use BelongsToTenant;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'pbx_calls';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'call_uuid',
        'initiated_by_user_id',
        'lead_id',
        'person_id',
        'ramal',
        'telefone_raw',
        'telefone',
        'direction',
        'status',
        'raw_originate_response',
        'raw_last_status',
        'ended_at',
        'activity_logged_at',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'raw_originate_response' => 'array',
        'raw_last_status' => 'array',
        'ended_at' => 'datetime',
        'activity_logged_at' => 'datetime',
    ];

    /**
     * Whether this call has reached a terminal state — the browser stops
     * polling once this is true, and it's what makes Activity logging
     * idempotent (see PbxCallService).
     */
    public function hasEnded(): bool
    {
        return $this->ended_at !== null;
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'initiated_by_user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }
}
