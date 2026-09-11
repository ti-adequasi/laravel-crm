<?php

namespace Webkul\UserMail\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\UserMail\Models\UserMailAccount;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        UserMailAccount::class,
    ];
}
