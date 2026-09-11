<?php

namespace Webkul\Tenant\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Tenant\Models\Tenant;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Tenant::class,
    ];
}
