<?php

$outputPath = $argv[4] ?? null;
if (! is_string($outputPath)) {
    exit(2);
}

file_put_contents($outputPath, json_encode([
    'match_info' => [
        'map_name' => 'de_dust2',
        'team1' => ['name' => 'Team A', 'players' => ['Player A']],
        'team2' => ['name' => 'Team B', 'players' => ['Player B']],
        'match_winner' => 'Team A',
        'final_score' => ['team1' => 13, 'team2' => 9],
    ],
    'rounds' => [
        ['round_number' => 1, 'round_winner' => 'CT', 'kills' => [['killer' => 'Player A', 'victim' => 'Player B']]],
    ],
    'statistics' => ['total_rounds' => 1, 'total_kills' => 1, 'match_duration_minutes' => 2.5],
    'player_statistics' => ['Player A' => ['kills' => 1, 'deaths' => 0, 'kd_ratio' => 1]],
    'economy' => ['total_purchases' => 2],
], JSON_THROW_ON_ERROR));