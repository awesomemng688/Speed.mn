<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SteamAuthService
{
    public function loginUrl(string $returnTo): string
    {
        return 'https://steamcommunity.com/openid/login?'.http_build_query([
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => parse_url($returnTo, PHP_URL_SCHEME).'://'.parse_url($returnTo, PHP_URL_HOST),
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ]);
    }

    public function authenticate(array $input): string
    {
        $input = $this->normalizeOpenIdInput($input);
        $claimedId = $input['openid.claimed_id'] ?? '';
        if (!preg_match('#^https://steamcommunity\.com/openid/id/(\d{17})$#', $claimedId, $matches)) {
            throw new RuntimeException('Invalid Steam identity response.');
        }

        $verification = Http::asForm()->timeout(8)->post('https://steamcommunity.com/openid/login', [
            ...$input,
            'openid.mode' => 'check_authentication',
        ]);

        if (!$verification->ok() || !str_contains($verification->body(), 'is_valid:true')) {
            throw new RuntimeException('Steam identity could not be verified (HTTP '.$verification->status().').');
        }

        return $matches[1];
    }

    private function normalizeOpenIdInput(array $input): array
    {
        $normalized = [];

        foreach ($input as $key => $value) {
            $normalized[str_starts_with($key, 'openid_') ? 'openid.'.substr($key, 7) : $key] = $value;
        }

        return $normalized;
    }

    public function profile(string $steamId): array
    {
        $key = config('services.steam.key');
        if (!$key) {
            return ['steamid' => $steamId];
        }

        $response = Http::timeout(8)->get('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/', [
            'key' => $key,
            'steamids' => $steamId,
        ]);

        if (!$response->ok()) {
            return ['steamid' => $steamId];
        }

        return $response->json('response.players.0') ?: ['steamid' => $steamId];
    }
}
