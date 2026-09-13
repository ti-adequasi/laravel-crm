<?php

namespace Webkul\Attribute\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Attribute\Contracts\Attribute as AttributeContract;
use Webkul\Tenant\Traits\BelongsToTenantOrGlobal;

class Attribute extends Model implements AttributeContract
{
    // Not BelongsToTenant: every attribute (Person's "Contact Numbers",
    // Lead's "Title", ...) is a system-wide field DEFINITION, seeded once
    // with tenant_id NULL — a genuinely tenant-bound request (any real,
    // non-super-admin user) needs to see those *plus* whatever it may have
    // customized on its own, not just the latter (confirmed a real,
    // previously-unnoticed gap: with plain BelongsToTenant, such a request
    // saw zero attributes at all, for every entity type). See
    // TenantOrGlobalScope's own docblock for the general rule this is an
    // instance of.
    use BelongsToTenantOrGlobal;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'entity_type',
        'lookup_type',
        'is_required',
        'is_unique',
        'quick_add',
        'validation',
        'is_user_defined',
    ];

    /**
     * Get the options.
     */
    public function options()
    {
        return $this->hasMany(AttributeOptionProxy::modelClass());
    }
}
