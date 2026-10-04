<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);

        $twoFactorRoutes = [
            'admin.two-factor.setup',
            'admin.two-factor.confirm',
            'admin.two-factor.challenge',
            'admin.two-factor.verify',
        ];
        $routeName = $request->route()?->getName();

        if (! $user->admin_totp_confirmed_at) {
            return in_array($routeName, ['admin.two-factor.setup', 'admin.two-factor.confirm'], true)
                ? $next($request)
                : redirect()->route('admin.two-factor.setup');
        }

        $verifiedAt = $request->session()->get('admin_totp_verified_at');
        $twoFactorVerified = $verifiedAt && now()->subHours(12)->timestamp <= (int) $verifiedAt;
        if (! $twoFactorVerified && ! in_array($routeName, ['admin.two-factor.challenge', 'admin.two-factor.verify'], true)) {
            return redirect()->route('admin.two-factor.challenge');
        }

        if ($twoFactorVerified && in_array($routeName, ['admin.two-factor.challenge', 'admin.two-factor.verify'], true)) {
            return redirect()->route('admin.servers.index');
        }

        return $next($request);
    }
}
