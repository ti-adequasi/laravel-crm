<?php

namespace Webkul\Pbx\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Pbx\Models\PbxSetting;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        PbxSetting::class,
    ];
}
