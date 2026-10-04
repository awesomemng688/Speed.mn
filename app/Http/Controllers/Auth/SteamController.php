<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SteamAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class SteamController extends Controller
{
    public function redirect(Request $request, SteamAuthService $steam): RedirectResponse
    {
        if ($request->filled('redirect')) {
            $redirect = (string) $request->query('redirect');
            if (str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
                $request->session()->put('url.intended', url($redirect));
            }
        }

        return redirect()->away($steam->loginUrl(route('steam.callback')));
    }

    public function callback(Request $request, SteamAuthService $steam): RedirectResponse
    {
        try {
            $steamId = $steam->authenticate($request->except('openid_mode'));
            $profile = $steam->profile($steamId);
            $user = User::updateOrCreate(
                ['steam_id' => $steamId],
                [
                    'name' => Str::limit($profile['personaname'] ?? 'Steam player', 255, ''),
                    'email' => $steamId.'@steam.local',
                    'password' => Str::random(40),
                    'steam_avatar' => $profile['avatarfull'] ?? $profile['avatar'] ?? null,
                ],
            );

            $request->session()->regenerate();
            Auth::login($user, true);
            return redirect()->intended(route('profile'));
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('servers.index')->with('error', 'Steam нэвтрэлтийг баталгаажуулж чадсангүй. Дахин оролдоно уу.');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
