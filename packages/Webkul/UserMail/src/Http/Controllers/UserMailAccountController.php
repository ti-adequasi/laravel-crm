<?php

namespace Webkul\UserMail\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\UserMail\Repositories\UserMailAccountRepository;

class UserMailAccountController extends Controller
{
    public function __construct(
        protected UserMailAccountRepository $userMailAccountRepository
    ) {}

    /**
     * Translated validation messages — this screen is fully localized
     * otherwise, and Laravel's own default field-validation text
     * ("The password field is required.") would be the one untranslated
     * string on it if left unset.
     */
    protected function validationMessages(): array
    {
        return [
            'host.required' => trans('user_mail::app.account.validation.host-required'),
            'port.required' => trans('user_mail::app.account.validation.port-required'),
            'port.integer' => trans('user_mail::app.account.validation.port-invalid'),
            'username.required' => trans('user_mail::app.account.validation.username-required'),
            'password.required' => trans('user_mail::app.account.validation.password-required'),
            'from_address.required' => trans('user_mail::app.account.validation.from-address-required'),
            'from_address.email' => trans('user_mail::app.account.validation.from-address-invalid'),
            'current_password.required' => trans('user_mail::app.account.validation.current-password-required'),
        ];
    }

    /**
     * Save (create or update) the current user's mail account.
     *
     * Returns JSON rather than a redirect — this is called via AJAX (see
     * the "My Email Account" accordion partial), and a redirect response
     * here would just have the browser's XHR silently follow it and hit
     * whatever route sits at the resulting URL, method mismatches included.
     */
    public function update(): JsonResponse
    {
        $user = auth()->guard('user')->user();

        $this->validate(request(), [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'nullable|in:tls,ssl',
            'from_address' => 'required|email|max:255',
            'current_password' => 'required|min:6',
        ], $this->validationMessages());

        if (! Hash::check(request('current_password'), $user->password)) {
            return response()->json([
                'message' => trans('user_mail::app.account.invalid-password'),
            ], 422);
        }

        $existing = $this->userMailAccountRepository->findOneWhere(['user_id' => $user->id]);

        $data = request()->only(['host', 'port', 'username', 'encryption', 'from_address']);

        $data['user_id'] = $user->id;
        $data['is_active'] = true;

        // Keep the stored password when the field is left blank on an
        // update — otherwise every save would have to include it again.
        if (request()->filled('password')) {
            $data['password'] = request('password');
        } elseif (! $existing) {
            return response()->json([
                'message' => trans('user_mail::app.account.password-required'),
            ], 422);
        }

        if ($existing) {
            $this->userMailAccountRepository->update($data, $existing->id);
        } else {
            $this->userMailAccountRepository->create($data);
        }

        return response()->json([
            'message' => trans('user_mail::app.account.update-success'),
        ]);
    }

    /**
     * Remove the current user's mail account — their emails go back to
     * sending through the system's default mailer.
     */
    public function destroy(): JsonResponse
    {
        $user = auth()->guard('user')->user();

        $existing = $this->userMailAccountRepository->findOneWhere(['user_id' => $user->id]);

        if ($existing) {
            $this->userMailAccountRepository->delete($existing->id);
        }

        return response()->json([
            'message' => trans('user_mail::app.account.delete-success'),
        ]);
    }

    /**
     * Attempt a live SMTP handshake with the submitted credentials,
     * without saving them or sending a real message.
     */
    public function test(): JsonResponse
    {
        $this->validate(request(), [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'encryption' => 'nullable|in:tls,ssl',
        ], $this->validationMessages());

        $scheme = match (request('encryption')) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => 'smtp',
        };

        $dsn = sprintf(
            '%s://%s:%s@%s:%s',
            $scheme,
            rawurlencode(request('username')),
            rawurlencode(request('password')),
            request('host'),
            request('port')
        );

        try {
            $transport = Transport::fromDsn($dsn);

            // Only SmtpTransport (and its EsmtpTransport subclass, which is
            // what smtp:// / smtps:// DSNs actually resolve to) exposes a
            // connect-without-sending start() — the generic interface
            // doesn't declare it, so this guards against a hard fatal error
            // if some other transport class were ever resolved here.
            if (! $transport instanceof SmtpTransport) {
                throw new Exception(trans('user_mail::app.account.test-unsupported-transport'));
            }

            $transport->start();

            return response()->json([
                'message' => trans('user_mail::app.account.test-success'),
            ]);
        } catch (TransportExceptionInterface|Exception $e) {
            return response()->json([
                'message' => trans('user_mail::app.account.test-failed', ['error' => $e->getMessage()]),
            ], 422);
        }
    }
}
