<?php

namespace Webkul\Sandbox\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Sandbox\Models\Note;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Note::class,
    ];
}
