<?php

namespace Webkul\EmailTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\EmailTemplate\Contracts\EmailTemplate as EmailTemplateContract;
use Webkul\Tenant\Traits\BelongsToTenant;

class EmailTemplate extends Model implements EmailTemplateContract
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'subject',
        'content',
    ];
}
