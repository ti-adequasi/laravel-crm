<?php

namespace Webkul\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Tenant\Contracts\Tenant as TenantContract;

class Tenant extends Model implements TenantContract
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'tenants';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
