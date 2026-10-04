<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class SkinBridgeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $secret = (string) config('services.skins.bridge_secret');
        abort_if($secret === '', 500, 'Skin bridge is not configured.');
        abort_if(!preg_match('/^\d{17}$/', (string) auth()->user()->steam_id), 500, 'Steam account is not configured.');

        $payload = json_encode([
            'steam_id' => auth()->user()->steam_id,
            'expires_at' => now()->addMinutes(2)->timestamp,
            'nonce' => Str::random(24),
        ], JSON_THROW_ON_ERROR);
        $encodedPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $secret);

        return redirect('/skins/?bridge='.$encodedPayload.'.'.$signature);
    }
}
