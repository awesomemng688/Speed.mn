<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$steamId = (string) ($_SESSION['steamid'] ?? $_SESSION['steam_steamid'] ?? '');
$defaults = [
    'steam_steamid' => $steamId,
    'steam_communityvisibilitystate' => 0,
    'steam_profilestate' => 0,
    'steam_personaname' => 'Steam player',
    'steam_lastlogoff' => 0,
    'steam_profileurl' => $steamId !== ''
        ? 'https://steamcommunity.com/profiles/' . rawurlencode($steamId)
        : 'https://steamcommunity.com/',
    'steam_avatar' => '',
    'steam_avatarmedium' => '',
    'steam_avatarfull' => '',
    'steam_personastate' => 0,
    'steam_realname' => 'Real name not given',
    'steam_primaryclanid' => '',
    'steam_timecreated' => 0,
];

foreach ($defaults as $key => $value) {
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = $value;
    }
}

$shouldRefresh = empty($_SESSION['steam_uptodate'])
    || $_SESSION['steam_personaname'] === 'Steam player'
    || (int) $_SESSION['steam_uptodate'] < time() - 1800;

if ($shouldRefresh && $steamId !== '') {
    $apiKey = defined('STEAM_API_KEY') ? (string) STEAM_API_KEY : '';
    $apiUrl = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key='
        . rawurlencode($apiKey) . '&steamids=' . rawurlencode($steamId);
    $responseBody = '';

    if ($apiKey !== '') {
        if (function_exists('curl_init')) {
            $curl = curl_init($apiUrl);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_FAILONERROR => false,
            ]);
            $response = curl_exec($curl);
            curl_close($curl);
            $responseBody = is_string($response) ? $response : '';
        } else {
            $response = @file_get_contents($apiUrl);
            $responseBody = is_string($response) ? $response : '';
        }
    }

    $decoded = $responseBody !== '' ? json_decode($responseBody, true) : null;
    $player = is_array($decoded) ? ($decoded['response']['players'][0] ?? []) : [];
    if (is_array($player) && !empty($player['steamid'])) {
        foreach ([
            'steam_steamid' => (string) ($player['steamid'] ?? $steamId),
            'steam_communityvisibilitystate' => (int) ($player['communityvisibilitystate'] ?? 0),
            'steam_profilestate' => (int) ($player['profilestate'] ?? 0),
            'steam_personaname' => (string) ($player['personaname'] ?? 'Steam player'),
            'steam_lastlogoff' => (int) ($player['lastlogoff'] ?? 0),
            'steam_profileurl' => (string) ($player['profileurl'] ?? $defaults['steam_profileurl']),
            'steam_avatar' => (string) ($player['avatar'] ?? ''),
            'steam_avatarmedium' => (string) ($player['avatarmedium'] ?? ''),
            'steam_avatarfull' => (string) ($player['avatarfull'] ?? ''),
            'steam_personastate' => (int) ($player['personastate'] ?? 0),
            'steam_realname' => (string) ($player['realname'] ?? 'Real name not given'),
            'steam_primaryclanid' => (string) ($player['primaryclanid'] ?? ''),
            'steam_timecreated' => (int) ($player['timecreated'] ?? 0),
        ] as $key => $value) {
            $_SESSION[$key] = $value;
        }
        $_SESSION['steam_uptodate'] = time();
    } else {
        $_SESSION['steam_uptodate'] = time() - 1500;
    }
}

$steamprofile = [
    'steamid' => (string) $_SESSION['steam_steamid'],
    'communityvisibilitystate' => (int) $_SESSION['steam_communityvisibilitystate'],
    'profilestate' => (int) $_SESSION['steam_profilestate'],
    'personaname' => (string) $_SESSION['steam_personaname'],
    'lastlogoff' => (int) $_SESSION['steam_lastlogoff'],
    'profileurl' => (string) $_SESSION['steam_profileurl'],
    'avatar' => (string) $_SESSION['steam_avatar'],
    'avatarmedium' => (string) $_SESSION['steam_avatarmedium'],
    'avatarfull' => (string) $_SESSION['steam_avatarfull'],
    'personastate' => (int) $_SESSION['steam_personastate'],
    'realname' => (string) $_SESSION['steam_realname'],
    'primaryclanid' => (string) $_SESSION['steam_primaryclanid'],
    'timecreated' => (int) $_SESSION['steam_timecreated'],
    'uptodate' => (int) ($_SESSION['steam_uptodate'] ?? 0),
];
