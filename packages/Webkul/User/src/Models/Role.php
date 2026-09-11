<?php

namespace Webkul\User\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Tenant\Traits\BelongsToTenant;
use Webkul\User\Contracts\Role as RoleContract;

class Role extends Model implements RoleContract
{
    use BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'permission_type',
        'permissions',
        'created_by',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the users.
     */
    public function users()
    {
        return $this->hasMany(UserProxy::modelClass());
    }
}
