<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Webkul\UserMail\Repositories\UserMailAccountRepository;
use Webkul\UserMail\Services\UserMailResolver;

uses(DatabaseTransactions::class);

it('lets a user save their own SMTP account', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->put(route('admin.user_mail.account.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'admin@example.com',
            'password' => 'super-secret',
            'encryption' => 'tls',
            'from_address' => 'admin@example.com',
            'current_password' => 'admin123',
        ])
        ->assertOk();

    $account = app(UserMailAccountRepository::class)->findOneWhere(['user_id' => $admin->id]);

    expect($account)->not->toBeNull()
        ->and($account->host)->toBe('smtp.example.com')
        ->and($account->password)->toBe('super-secret') // decrypts transparently via the cast
        ->and($account->is_active)->toBeTrue();

    // The raw DB column must never hold the plaintext password.
    $raw = DB::table('user_mail_accounts')->where('user_id', $admin->id)->value('password');
    expect($raw)->not->toBe('super-secret');
});

it('rejects saving with the wrong current password', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->put(route('admin.user_mail.account.update'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'admin@example.com',
            'password' => 'super-secret',
            'from_address' => 'admin@example.com',
            'current_password' => 'definitely-wrong',
        ])
        ->assertStatus(422);

    expect(app(UserMailAccountRepository::class)->findOneWhere(['user_id' => $admin->id]))->toBeNull();
});

it('keeps the existing password when the field is left blank on update', function () {
    $admin = getDefaultAdmin();

    $repository = app(UserMailAccountRepository::class);

    $repository->create([
        'user_id' => $admin->id,
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'admin@example.com',
        'password' => 'original-secret',
        'encryption' => 'tls',
        'from_address' => 'admin@example.com',
        'is_active' => true,
    ]);

    test()->actingAs($admin)
        ->put(route('admin.user_mail.account.update'), [
            'host' => 'smtp.updated.com',
            'port' => 587,
            'username' => 'admin@example.com',
            'password' => '',
            'from_address' => 'admin@example.com',
            'current_password' => 'admin123',
        ])
        ->assertOk();

    $account = $repository->findOneWhere(['user_id' => $admin->id]);

    expect($account->host)->toBe('smtp.updated.com')
        ->and($account->password)->toBe('original-secret');
});

it('removes the account on destroy', function () {
    $admin = getDefaultAdmin();

    $repository = app(UserMailAccountRepository::class);

    $repository->create([
        'user_id' => $admin->id,
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'admin@example.com',
        'password' => 'secret',
        'from_address' => 'admin@example.com',
        'is_active' => true,
    ]);

    test()->actingAs($admin)
        ->delete(route('admin.user_mail.account.delete'))
        ->assertOk();

    expect($repository->findOneWhere(['user_id' => $admin->id]))->toBeNull();
});

it('resolves no mailer override for a user with no configured account', function () {
    $admin = getDefaultAdmin();

    $result = app(UserMailResolver::class)->resolveFor($admin);

    expect($result['mailer'])->toBeNull()
        ->and($result['from'])->toBeNull();
});

it('registers a runtime mailer and from-address for a user with an active account', function () {
    $admin = getDefaultAdmin();

    app(UserMailAccountRepository::class)->create([
        'user_id' => $admin->id,
        'host' => 'smtp.example.com',
        'port' => 2525,
        'username' => 'admin@example.com',
        'password' => 'secret',
        'encryption' => 'tls',
        'from_address' => 'me@example.com',
        'is_active' => true,
    ]);

    $result = app(UserMailResolver::class)->resolveFor($admin);

    expect($result['mailer'])->toBe('user_'.$admin->id)
        ->and($result['from'])->toBe('me@example.com')
        ->and(Config::get("mail.mailers.user_{$admin->id}.host"))->toBe('smtp.example.com')
        ->and(Config::get("mail.mailers.user_{$admin->id}.port"))->toBe(2525);
});

it('ignores an inactive account and falls back to the system default', function () {
    $admin = getDefaultAdmin();

    app(UserMailAccountRepository::class)->create([
        'user_id' => $admin->id,
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'admin@example.com',
        'password' => 'secret',
        'from_address' => 'me@example.com',
        'is_active' => false,
    ]);

    $result = app(UserMailResolver::class)->resolveFor($admin);

    expect($result['mailer'])->toBeNull()
        ->and($result['from'])->toBeNull();
});
