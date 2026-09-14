<?php

namespace Webkul\LeadPeering\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\LeadPeering\Models\LeadPeering;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        LeadPeering::class,
    ];
}
