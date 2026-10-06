<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'steam' => [
        'key' => env('STEAM_API_KEY'),
        'domain' => env('STEAM_DOMAIN'),
    ],

    'skins' => [
        'bridge_secret' => env('SKINS_BRIDGE_SECRET'),
    ],

    'discord' => [
        'admin_webhook' => env('DISCORD_ADMIN_WEBHOOK'),
        'players_webhook' => env('DISCORD_SERVER_PLAYERS_WEBHOOK'),
    ],

    'cs2_demo_parser' => [
        'python' => env('CS2_DEMO_PARSER_PYTHON', '/opt/speedmn-cs2-demo-parser/.venv/bin/python'),
        'entrypoint' => env('CS2_DEMO_PARSER_ENTRYPOINT', '/opt/speedmn-cs2-demo-parser/main.py'),
        'timeout_seconds' => (int) env('CS2_DEMO_PARSER_TIMEOUT_SECONDS', 1800),
        'source_dir' => env('CS2_DEMO_SOURCE_DIR', '/home/cs2/27025/game/csgo/MatchZy'),
    ],

];
