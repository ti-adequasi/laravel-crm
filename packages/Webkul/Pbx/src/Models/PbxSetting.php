<?php

namespace Webkul\Pbx\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Pbx\Contracts\PbxSetting as PbxSettingContract;
use Webkul\Tenant\Traits\BelongsToTenant;

/**
 * One row per tenant, enforced by a unique index on tenant_id — deliberately
 * NOT stored in core_config. core_config's own read path
 * (SystemConfig::getConfigData()) falls back to a global (tenant_id NULL)
 * row when a tenant hasn't configured its own value, which is exactly right
 * for something like a shared default Magic AI key but is a real
 * cross-tenant leak here: this server hosts every tenant's PBX domain
 * behind the same api_key-only auth, so a tenant with no key configured
 * must see "not connected", never silently inherit another tenant's key
 * and start dialing through their domain. BelongsToTenant's global scope
 * has no fallback at all (unlike core_config's bespoke repository code),
 * so a plain scoped lookup already gives the right behavior for free.
 */
class PbxSetting extends Model implements PbxSettingContract
{
    use BelongsToTenant;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'pbx_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'enabled',
        'auto_log_activity',
        'api_key',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'enabled' => 'boolean',
        'auto_log_activity' => 'boolean',
        'api_key' => 'encrypted',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'api_key',
    ];
}
