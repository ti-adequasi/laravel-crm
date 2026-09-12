<?php

namespace Webkul\Pbx\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Pbx\Models\PbxCall;
use Webkul\Pbx\Models\PbxSetting;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        PbxSetting::class,
        PbxCall::class,
    ];
}
