<?php

namespace Webkul\User\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Tenant\Traits\BelongsToTenant;
use Webkul\User\Contracts\Group as GroupContract;

class Group extends Model implements GroupContract
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
    ];

    /**
     * The users that belong to the group.
     */
    public function users()
    {
        return $this->belongsToMany(UserProxy::modelClass(), 'user_groups');
    }
}
