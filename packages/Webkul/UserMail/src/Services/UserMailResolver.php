<?php

namespace Webkul\UserMail\Services;

use Illuminate\Support\Facades\Config;
use Webkul\User\Contracts\User;
use Webkul\UserMail\Repositories\UserMailAccountRepository;

class UserMailResolver
{
    public function __construct(
        protected UserMailAccountRepository $userMailAccountRepository
    ) {}

    /**
     * Resolve the given user's active mail account, if any.
     */
    protected function activeAccountFor(?User $user)
    {
        if (! $user) {
            return null;
        }

        return $this->userMailAccountRepository->findOneWhere([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /**
     * Resolve what EmailController should send with for the given user:
     * a runtime-registered mailer name and a from-address override, or
     * both null when the user has no active account configured — the
     * caller should fall back to the system default mailer/from-address.
     */
    public function resolveFor(?User $user): array
    {
        $account = $this->activeAccountFor($user);

        if (! $account) {
            return [
                'mailer' => null,
                'from' => null,
            ];
        }

        $mailerName = 'user_'.$account->user_id;

        // Registering this at request time is enough — MailManager::resolve()
        // reads config('mail.mailers.*') lazily, the same array Config::set()
        // writes to, no cache/restart involved.
        Config::set("mail.mailers.{$mailerName}", [
            'transport' => 'smtp',
            'host' => $account->host,
            'port' => $account->port,
            'encryption' => $account->encryption ?: null,
            'username' => $account->username,
            'password' => $account->password,
        ]);

        return [
            'mailer' => $mailerName,
            'from' => $account->from_address,
        ];
    }
}
