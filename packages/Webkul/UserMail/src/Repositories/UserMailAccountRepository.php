<?php

namespace Webkul\UserMail\Repositories;

use Webkul\Core\Eloquent\Repository;
use Webkul\UserMail\Contracts\UserMailAccount;

class UserMailAccountRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return UserMailAccount::class;
    }
}
