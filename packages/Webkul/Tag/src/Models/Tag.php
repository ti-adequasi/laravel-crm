<?php

namespace Webkul\Tag\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Tag\Contracts\Tag as TagContract;
use Webkul\Tenant\Traits\BelongsToTenant;
use Webkul\User\Models\UserProxy;

class Tag extends Model implements TagContract
{
    use BelongsToTenant;

    protected $table = 'tags';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'user_id',
    ];

    /**
     * Get the user that owns the tag.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
