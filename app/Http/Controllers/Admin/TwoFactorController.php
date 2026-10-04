<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function setup(Request $request, Google2FA $google2fa): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);

        if ($user->admin_totp_confirmed_at && ! $this->sessionIsVerified($request)) {
            return redirect()->route('admin.two-factor.challenge');
        }

        if ($user->admin_totp_confirmed_at) {
            return view('admin.two-factor.setup', ['enabled' => true]);
        }

        $secret = $request->session()->get('admin_totp_setup_secret');
        if (! $secret) {
            $secret = $google2fa->generateSecretKey();
            $request->session()->put('admin_totp_setup_secret', $secret);
        }

        return view('admin.two-factor.setup', [
            'enabled' => false,
            'secret' => $secret,
            'otpauthUrl' => $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ]);
    }

    public function confirmSetup(Request $request, Google2FA $google2fa, AdminAuditLogger $audit): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->get('admin_totp_setup_secret');
        abort_unless($secret, 419, 'Two-factor setup expired. Start setup again.');

        if ($this->rateLimited($request)) {
            return back()->withErrors(['code' => 'Too many attempts. Wait one minute and retry.']);
        }

        if (! $google2fa->verifyKey($secret, $validated['code'], 1)) {
            $this->recordFailedAttempt($request);

            return back()->withErrors(['code' => 'The authenticator code is invalid.'])->withInput();
        }

        $recoveryCodes = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
        $hashedCodes = array_map(fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)), $recoveryCodes);

        $user->forceFill([
            'admin_totp_secret' => $secret,
            'admin_totp_confirmed_at' => now(),
            'admin_totp_recovery_codes' => $hashedCodes,
        ])->save();
        $audit->record($request, 'admin.2fa_enabled', $user, ['recovery_codes_issued' => count($hashedCodes)]);

        $request->session()->forget('admin_totp_setup_secret');
        $this->markSessionVerified($request);
        RateLimiter::clear($this->rateLimitKey($request));

        return view('admin.two-factor.recovery', ['recoveryCodes' => $recoveryCodes]);
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);

        if (! $user->admin_totp_confirmed_at) {
            return redirect()->route('admin.two-factor.setup');
        }

        if ($this->sessionIsVerified($request)) {
            return redirect()->route('admin.servers.index');
        }

        return view('admin.two-factor.challenge');
    }

    public function verifyChallenge(Request $request, Google2FA $google2fa, AdminAuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);
        abort_unless($user->admin_totp_confirmed_at, 403);
        $validated = $request->validate(['code' => ['required', 'string', 'max:32']]);

        if ($this->rateLimited($request)) {
            return back()->withErrors(['code' => 'Too many attempts. Wait one minute and retry.']);
        }

        $code = trim($validated['code']);
        $verified = ctype_digit($code)
            && strlen($code) === 6
            && $google2fa->verifyKey($user->admin_totp_secret, $code, 1);
        $recoveryCodes = $user->admin_totp_recovery_codes ?? [];
        $recoveryIndex = null;

        if (! $verified) {
            $normalized = $this->normalizeRecoveryCode($code);
            foreach ($recoveryCodes as $index => $hashedCode) {
                if (Hash::check($normalized, $hashedCode)) {
                    $verified = true;
                    $recoveryIndex = $index;
                    break;
                }
            }
        }

        if (! $verified) {
            $this->recordFailedAttempt($request);

            return back()->withErrors(['code' => 'The code is invalid or has already been used.'])->withInput();
        }

        if ($recoveryIndex !== null) {
            unset($recoveryCodes[$recoveryIndex]);
            $user->forceFill(['admin_totp_recovery_codes' => array_values($recoveryCodes)])->save();
            $audit->record($request, 'admin.2fa_recovery_used', $user);
        }

        $this->markSessionVerified($request);
        RateLimiter::clear($this->rateLimitKey($request));

        return redirect()->intended(route('admin.servers.index'));
    }

    public function disable(Request $request, Google2FA $google2fa, AdminAuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        if ($this->rateLimited($request)) {
            return back()->withErrors(['code' => 'Too many attempts. Wait one minute and retry.']);
        }

        if (! $google2fa->verifyKey($user->admin_totp_secret, $validated['code'], 1)) {
            $this->recordFailedAttempt($request);

            return back()->withErrors(['code' => 'The authenticator code is invalid.']);
        }

        $user->forceFill([
            'admin_totp_secret' => null,
            'admin_totp_confirmed_at' => null,
            'admin_totp_recovery_codes' => null,
        ])->save();
        $request->session()->forget('admin_totp_verified_at');
        $audit->record($request, 'admin.2fa_disabled', $user);
        RateLimiter::clear($this->rateLimitKey($request));

        return redirect()->route('admin.two-factor.setup')->with('status', 'Two-factor authentication disabled.');
    }

    private function rateLimitKey(Request $request): string
    {
        return 'admin-totp:'.$request->user()->id.':'.$request->ip();
    }

    private function rateLimited(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->rateLimitKey($request), 5);
    }

    private function recordFailedAttempt(Request $request): void
    {
        RateLimiter::hit($this->rateLimitKey($request), 60);
    }

    private function markSessionVerified(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put('admin_totp_verified_at', now()->timestamp);
    }

    private function sessionIsVerified(Request $request): bool
    {
        $verifiedAt = $request->session()->get('admin_totp_verified_at');

        return $verifiedAt && now()->subHours(12)->timestamp <= (int) $verifiedAt;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper($code)) ?? '';
    }
}