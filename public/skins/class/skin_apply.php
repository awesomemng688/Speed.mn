<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

session_start();

require_once __DIR__ . '/../class/config.php';
require_once __DIR__ . '/../class/database.php';

function respond(bool $success, string $message, int $httpStatus = 200, array $extra = []): void {
    http_response_code($httpStatus);
    $payload = ['success' => $success, 'message' => $message];
    foreach ($extra as $key => $value) {
        $payload[$key] = $value;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$sessionToken = $_SESSION['skins_csrf'] ?? '';
$postedToken = (string) ($_POST['csrf_token'] ?? '');
if (!is_string($sessionToken) || $sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    respond(false, 'Invalid or expired form token.', 419);
}

function fetchColumnNames(DataBase $db, string $table): array {
    $rows = $db->select('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    if (!is_array($rows)) {
        return [];
    }

    $columns = [];
    foreach ($rows as $row) {
        $field = strtolower(trim((string)($row['Field'] ?? '')));
        if ($field !== '') {
            $columns[] = $field;
        }
    }

    return $columns;
}

function normalizeTeamInput($team): array {
    $value = strtoupper(trim((string)$team));
    if ($value === 'T' || $value === '2') {
        return [2];
    }
    if ($value === 'CT' || $value === '3') {
        return [3];
    }
    if ($value === 'BOTH' || $value === 'ALL' || $value === '0' || $value === '') {
        return [2, 3];
    }

    return [2, 3];
}

function upsertTable(DataBase $db, string $table, array $values): bool {
    if ($table === '' || empty($values)) {
        return false;
    }

    $columns = array_keys($values);
    $columnList = implode('`, `', $columns);
    $placeholders = [];
    foreach ($columns as $column) {
        $placeholders[] = ':' . $column;
    }
    $updateParts = [];
    foreach ($columns as $column) {
        if ($column === 'steamid') {
            continue;
        }
        $updateParts[] = '`' . $column . '` = VALUES(`' . $column . '`)';
    }

    $sql = 'INSERT INTO `' . $table . '` (`' . $columnList . '`) VALUES (' . implode(', ', $placeholders) . ') ';
    if (!empty($updateParts)) {
        $sql .= 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
    }

    return $db->query($sql, $values);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Only POST requests are allowed.', 405);
}

$steamid = trim((string)($_SESSION['steamid'] ?? ''));
if ($steamid === '') {
    respond(false, 'SteamID is required.', 400);
}

$teamInput = trim((string)($_POST['team'] ?? 'both'));
$teamList = normalizeTeamInput($teamInput);
if (!in_array($teamInput, ['2', '3', 'T', 'CT'], true)) {
    respond(false, 'A single valid team must be selected.', 400);
}

$rawWeaponDefindex = $_POST['weapon_defindex'] ?? $_POST['defindex'] ?? 0;
$weaponDefindex = (int)$rawWeaponDefindex;
$paintId = (int)($_POST['skin_paint'] ?? $_POST['paint'] ?? $_POST['skin_id'] ?? 0);
$wear = isset($_POST['wear']) ? (float)$_POST['wear'] : 0.0;
$seed = isset($_POST['seed']) ? (int)$_POST['seed'] : 0;
$weapon = trim((string)($_POST['weapon'] ?? ''));
$skinCategory = strtolower(trim((string)($_POST['skin_category'] ?? '')));

$db = new DataBase();
$successCount = 0;
$attempted = 0;

foreach ($teamList as $team) {
    $attempted++;

    $table = 'wp_player_skins';
    $columns = fetchColumnNames($db, $table);
    if (empty($columns)) {
        $tables = $db->select('SHOW TABLES');
        $found = false;
        foreach ($tables as $row) {
            foreach ($row as $value) {
                if (strtolower((string)$value) === 'wp_player_skins') {
                    $found = true;
                    break 2;
                }
            }
        }

        if (!$found) {
            break;
        }
    }

    $skinValues = [];
    foreach (['steamid', 'weapon_team', 'weapon_defindex', 'weapon_paint_id', 'weapon_seed', 'weapon_wear'] as $column) {
        if (!in_array($column, $columns, true)) {
            continue;
        }

        $value = match ($column) {
            'steamid' => $steamid,
            'weapon_team' => (int)$team,
            'weapon_defindex' => $weaponDefindex,
            'weapon_paint_id' => $paintId,
            'weapon_seed' => $seed,
            'weapon_wear' => $wear,
            default => null,
        };

        if ($value !== null) {
            $skinValues[$column] = $value;
        }
    }

    if (!empty($skinValues)) {
        if (upsertTable($db, $table, $skinValues)) {
            $successCount++;
        }
    }

    if (str_contains($skinCategory, 'knife') || str_contains($weapon, 'knife') || stripos($skinCategory, 'knife') !== false) {
        $knifeTable = 'wp_player_knife';
        $knifeColumns = fetchColumnNames($db, $knifeTable);
        if (!empty($knifeColumns)) {
            $knifeValue = $weapon !== '' ? $weapon : 'weapon_knife';
            $knifeValues = [];
            foreach (['steamid', 'weapon_team'] as $col) {
                if (in_array($col, $knifeColumns, true)) {
                    $knifeValues[$col] = $col === 'steamid' ? $steamid : (int)$team;
                }
            }

            if (in_array('knife', $knifeColumns, true)) {
                $knifeValues['knife'] = $knifeValue;
            } elseif (in_array('weapon_name', $knifeColumns, true)) {
                $knifeValues['weapon_name'] = $knifeValue;
            } elseif (in_array('weapon_defindex', $knifeColumns, true)) {
                $knifeValues['weapon_defindex'] = $weaponDefindex;
            }

            if (!empty($knifeValues)) {
                upsertTable($db, $knifeTable, $knifeValues);
            }
        }
    }

    if (str_contains($skinCategory, 'glove') || str_contains($weapon, 'glove') || stripos($skinCategory, 'glove') !== false) {
        $gloveTable = 'wp_player_gloves';
        $gloveColumns = fetchColumnNames($db, $gloveTable);
        if (!empty($gloveColumns)) {
            $gloveValue = $weapon !== '' ? $weapon : 'gloves';
            $gloveValues = [];
            foreach (['steamid', 'weapon_team'] as $col) {
                if (in_array($col, $gloveColumns, true)) {
                    $gloveValues[$col] = $col === 'steamid' ? $steamid : (int)$team;
                }
            }

            if (in_array('glove', $gloveColumns, true)) {
                $gloveValues['glove'] = $gloveValue;
            } elseif (in_array('gloves', $gloveColumns, true)) {
                $gloveValues['gloves'] = $gloveValue;
            } elseif (in_array('weapon_name', $gloveColumns, true)) {
                $gloveValues['weapon_name'] = $gloveValue;
            } elseif (in_array('weapon_defindex', $gloveColumns, true)) {
                $gloveValues['weapon_defindex'] = $weaponDefindex;
            }

            if (!empty($gloveValues)) {
                upsertTable($db, $gloveTable, $gloveValues);
            }
        }
    }
}

if ($successCount > 0 || $attempted === 0) {
    respond(true, 'Skin saved successfully to the server database.', 200, ['team' => $teamInput, 'updated_rows' => $successCount]);
}

respond(false, 'No matching skin table was found for the selected skin.', 500, ['team' => $teamInput]);
