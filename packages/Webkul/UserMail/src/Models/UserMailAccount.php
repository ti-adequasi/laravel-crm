<?php

namespace Webkul\UserMail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Tenant\Traits\BelongsToTenant;
use Webkul\User\Models\UserProxy;
use Webkul\UserMail\Contracts\UserMailAccount as UserMailAccountContract;

class UserMailAccount extends Model implements UserMailAccountContract
{
    use BelongsToTenant;

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'user_mail_accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'is_active',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the user that owns this mail account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }
}
