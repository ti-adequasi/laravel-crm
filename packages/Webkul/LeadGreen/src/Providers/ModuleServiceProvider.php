<?php

namespace Webkul\LeadGreen\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\LeadGreen\Models\LeadGreen;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        LeadGreen::class,
    ];
}
