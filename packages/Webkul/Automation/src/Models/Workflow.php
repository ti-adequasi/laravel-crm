<?php

namespace Webkul\Automation\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Automation\Contracts\Workflow as WorkflowContract;
use Webkul\Tenant\Traits\BelongsToTenant;

class Workflow extends Model implements WorkflowContract
{
    use BelongsToTenant;

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'entity_type',
        'event',
        'condition_type',
        'conditions',
        'actions',
    ];
}
