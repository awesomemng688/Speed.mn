<?php
require_once __DIR__ . '/class/config.php';
require_once __DIR__ . '/class/database.php';
require_once __DIR__ . '/steamauth/steamauth.php';
require_once __DIR__ . '/class/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$csrfToken = $_SESSION['skins_csrf'] ?? '';
if (!is_string($csrfToken) || strlen($csrfToken) < 32) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['skins_csrf'] = $csrfToken;
}

if (isset($_GET['bridge'])) {
    $parts = explode('.', (string) $_GET['bridge'], 2);
    $encodedPayload = $parts[0] ?? '';
    $signature = $parts[1] ?? '';
    $decoded = base64_decode(strtr($encodedPayload, '-_', '+/') . str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4), true);
    $payload = is_string($decoded) ? json_decode($decoded, true) : null;
    $valid = is_array($payload)
        && preg_match('/^\d{17}$/', (string) ($payload['steam_id'] ?? ''))
        && isset($payload['expires_at']);
    if ($valid && (int) $payload['expires_at'] < time()) {
        header('Location: /login?redirect=%2Fskins%2Fbridge&expired=1');
        exit;
    }
    $expected = hash_hmac('sha256', $encodedPayload, (string) SKINS_BRIDGE_SECRET);
    if (!$valid || SKINS_BRIDGE_SECRET === '' || !hash_equals($expected, $signature)) {
        http_response_code(403);
        exit('Invalid skin login link.');
    }
    session_regenerate_id(true);
    $_SESSION['steamid'] = (string) $payload['steam_id'];
    header('Location: /skins/');
    exit;
}

if (!isset($_SESSION['steamid'])) {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="style.css?v=skins-brand-1"><title>Speed.mn CS2 Skins</title></head><body><main class="login-panel"><div class="brand-mark"><img src="/img/hero/logo.jfif" alt="Speed.mn logo"></div><h1>CS2 Skins</h1><p>Sign in with Steam to manage your loadout.</p>';
    loginbutton('rectangle');
    echo '</main></body></html>';
    exit;
}

if (
    (empty($_SESSION['steam_personaname']) || empty($_SESSION['steam_avatarfull']))
    && defined('STEAM_API_KEY')
    && STEAM_API_KEY !== ''
) {
    require_once __DIR__ . '/steamauth/userInfo.php';
}

$db = new DataBase();
$steamid = (string) $_SESSION['steamid'];
$steamName = (string) ($_SESSION['steam_personaname'] ?? 'Steam player');
$steamAvatar = (string) ($_SESSION['steam_avatarfull'] ?? $_SESSION['steam_avatarmedium'] ?? $_SESSION['steam_avatar'] ?? '');
$steamProfileUrl = (string) ($_SESSION['steam_profileurl'] ?? 'https://steamcommunity.com/profiles/' . rawurlencode($steamid));
$steamCreatedAt = !empty($_SESSION['steam_timecreated']) ? date('M Y', (int) $_SESSION['steam_timecreated']) : 'recently';
$skins = UtilsClass::skinsFromJson();
$weapons = UtilsClass::getWeaponsFromArray();
$knives = UtilsClass::getKnifeTypes();
$catalogs = [
    'gloves' => UtilsClass::catalogFromJson('gloves'),
    'agent' => UtilsClass::catalogFromJson('agents'),
    'music' => UtilsClass::catalogFromJson('music'),
    'pins' => UtilsClass::catalogFromJson('collectibles'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(419);
        exit('Invalid or expired form token.');
    }
    $team = (int) ($_POST['weapon_team'] ?? 2);
    if (!in_array($team, [2, 3], true)) {
        $team = 2;
    }
    $loadoutType = (string) ($_POST['loadout_type'] ?? '');
    $catalogId = (string) ($_POST['catalog_id'] ?? '');
    $catalogMapsForValidation = [];
    foreach ($catalogs as $catalogType => $items) {
        foreach ($items as $item) {
            $catalogMapsForValidation[$catalogType][(string) $item['id']] = true;
        }
    }
    if (in_array($loadoutType, ['gloves', 'agent', 'music', 'pins'], true) && $catalogId !== '') {
        if (!isset($catalogMapsForValidation[$loadoutType][$catalogId])) {
            http_response_code(400);
            exit('Invalid catalog item.');
        }
        if ($loadoutType === 'gloves') {
            $gloveItem = null;
            foreach ($catalogs['gloves'] as $item) {
                if ((string) $item['id'] === $catalogId) {
                    $gloveItem = $item;
                    break;
                }
            }
            if (!is_array($gloveItem) || (int) ($gloveItem['defindex'] ?? 0) <= 0) {
                http_response_code(400);
                exit('Invalid glove definition.');
            }
            $saveSucceeded = $db->query(
                'INSERT INTO wp_player_gloves (steamid, weapon_team, weapon_defindex)
                 VALUES (:steamid, :team, :defindex)
                 ON DUPLICATE KEY UPDATE weapon_defindex = :defindex',
                ['steamid' => $steamid, 'team' => $team, 'defindex' => (int) $gloveItem['defindex']]
            );
            if ($saveSucceeded === false) {
                http_response_code(500);
                exit('Failed to save gloves. Check wp_player_gloves table columns and unique key.');
            }
        } elseif ($loadoutType === 'agent') {
            $column = $team === 3 ? 'agent_ct' : 'agent_t';
            $saveSucceeded = $db->query(
                "INSERT INTO wp_player_agents (steamid, {$column}) VALUES (:steamid, :agent)
                 ON DUPLICATE KEY UPDATE {$column} = :agent",
                ['steamid' => $steamid, 'agent' => $catalogId]
            );
            if ($saveSucceeded === false) {
                http_response_code(500);
                exit('Failed to save agent. Check wp_player_agents table columns and unique key.');
            }
        } elseif ($loadoutType === 'music') {
            $db->query(
                'INSERT INTO wp_player_music (steamid, weapon_team, music_id, team)
                 VALUES (:steamid, :weapon_team, :music_id, :team)
                 ON DUPLICATE KEY UPDATE music_id = :music_id, team = :team',
                ['steamid' => $steamid, 'weapon_team' => $team, 'music_id' => $catalogId, 'team' => $team]
            );
        } else {
            $db->query(
                'INSERT INTO wp_player_pins (steamid, weapon_team, id)
                 VALUES (:steamid, :team, :id)
                 ON DUPLICATE KEY UPDATE id = :id',
                ['steamid' => $steamid, 'team' => $team, 'id' => (int) $catalogId]
            );
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
    $loadoutAction = (string) ($_POST['loadout_action'] ?? '');
    if ($loadoutAction === 'save') {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
    if ($loadoutAction === 'reset') {
        $db->query('DELETE FROM wp_player_skins WHERE steamid = :steamid AND weapon_team = :team', ['steamid' => $steamid, 'team' => $team]);
        $db->query('DELETE FROM wp_player_knife WHERE steamid = :steamid AND weapon_team = :team', ['steamid' => $steamid, 'team' => $team]);
        $db->query('DELETE FROM wp_player_gloves WHERE steamid = :steamid AND weapon_team = :team', ['steamid' => $steamid, 'team' => $team]);
        $db->query('DELETE FROM wp_player_music WHERE steamid = :steamid AND weapon_team = :team', ['steamid' => $steamid, 'team' => $team]);
        $db->query('DELETE FROM wp_player_pins WHERE steamid = :steamid AND weapon_team = :team', ['steamid' => $steamid, 'team' => $team]);
        $agentColumn = $team === 3 ? 'agent_ct' : 'agent_t';
        $db->query("UPDATE wp_player_agents SET {$agentColumn} = NULL WHERE steamid = :steamid", ['steamid' => $steamid]);
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
        exit;
    }
    $forma = explode('-', (string) ($_POST['forma'] ?? ''), 2);
    if (($forma[0] ?? '') === 'knife' && isset($knives[$forma[1] ?? ''])) {
        $saveSucceeded = $db->query(
            'INSERT INTO wp_player_knife (steamid, knife, weapon_team) VALUES (:steamid, :knife, :team)
             ON DUPLICATE KEY UPDATE knife = :knife',
            ['steamid' => $steamid, 'knife' => $knives[$forma[1]]['weapon_name'], 'team' => $team]
        );
        if ($saveSucceeded === false) {
            http_response_code(500);
            exit('Failed to save knife. Check wp_player_knife table columns and unique key.');
        }
    } elseif (isset($forma[0], $forma[1], $skins[$forma[0]][$forma[1]])) {
        $wear = (float) ($_POST['wear'] ?? 0);
        $seed = max(0, min(1000, (int) ($_POST['seed'] ?? 0)));
        $stattrak = (int) ($_POST['weapon_stattrak'] ?? 0) === 1 ? 1 : 0;
        if ($wear >= 0 && $wear <= 1) {
            $saveSucceeded = $db->query(
                'INSERT INTO wp_player_skins
                    (steamid, weapon_defindex, weapon_paint_id, weapon_wear, weapon_seed, weapon_stattrak, weapon_team)
                 VALUES (:steamid, :defindex, :paint, :wear, :seed, :stattrak, :team)
                 ON DUPLICATE KEY UPDATE weapon_paint_id = :paint, weapon_wear = :wear, weapon_seed = :seed, weapon_stattrak = :stattrak',
                [
                    'steamid' => $steamid, 'defindex' => (int) $forma[0], 'paint' => (int) $forma[1],
                    'wear' => $wear, 'seed' => $seed, 'stattrak' => $stattrak, 'team' => $team,
                ]
            );
            if ($saveSucceeded === false) {
                http_response_code(500);
                exit('Failed to save skin. Check your database connection and the wp_player_skins table schema.');
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1');
    exit;
}

$selectedRows = $db->select(
    'SELECT weapon_defindex, weapon_team, weapon_paint_id, weapon_wear, weapon_seed, weapon_stattrak
     FROM wp_player_skins WHERE steamid = :steamid',
    ['steamid' => $steamid]
);
$selected = [];
foreach ($selectedRows as $row) {
    $selected[(int) $row['weapon_defindex']][(int) $row['weapon_team']] = $row;
}
$selectedKnifeRows = $db->select(
    'SELECT knife, weapon_team FROM wp_player_knife WHERE steamid = :steamid',
    ['steamid' => $steamid]
);
$selectedKnives = [];
foreach ($selectedKnifeRows as $row) {
    $selectedKnives[(int) $row['weapon_team']] = $row['knife'];
}
$selectedGloves = [];
foreach ($db->select(
    'SELECT weapon_team, weapon_defindex FROM wp_player_gloves WHERE steamid = :steamid',
    ['steamid' => $steamid]
) as $row) {
    $selectedGloves[(int) $row['weapon_team']] = (string) $row['weapon_defindex'];
}
$selectedMusic = [];
foreach ($db->select(
    'SELECT weapon_team, music_id FROM wp_player_music WHERE steamid = :steamid',
    ['steamid' => $steamid]
) as $row) {
    $selectedMusic[(int) $row['weapon_team']] = (string) $row['music_id'];
}
$selectedAgents = $db->select(
    'SELECT agent_ct, agent_t FROM wp_player_agents WHERE steamid = :steamid LIMIT 1',
    ['steamid' => $steamid]
)[0] ?? [];
$selectedPins = [];
foreach ($db->select(
    'SELECT weapon_team, id FROM wp_player_pins WHERE steamid = :steamid',
    ['steamid' => $steamid]
) as $row) {
    $selectedPins[(int) $row['weapon_team']] = (string) $row['id'];
}
$catalogMaps = [];
foreach ($catalogs as $catalogType => $items) {
    foreach ($items as $item) {
        $catalogMaps[$catalogType][(string) $item['id']] = $item;
    }
}
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$teamWeaponCounts = [2 => 0, 3 => 0];
foreach ($selected as $teams) {
    foreach ([2, 3] as $previewTeam) {
        if (isset($teams[$previewTeam])) {
            $teamWeaponCounts[$previewTeam]++;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css?v=loadout-20260924">
    <style>
        .steam-id{color:var(--muted);font-size:.68rem;font-weight:700;letter-spacing:.08em}
        .hero-actions{display:flex;align-items:center;gap:.8rem;margin-top:1.25rem}.hero-cta{padding:.7rem 1rem;border:0;border-radius:.45rem;color:#111;background:var(--accent);font-weight:800;cursor:pointer}.hero-cta:hover{filter:brightness(1.1)}.hero-hint{color:var(--muted);font-size:.72rem}
        .results-count{align-self:center;color:var(--muted);font-size:.72rem;white-space:nowrap}.preview-empty{display:flex;align-items:center;gap:.8rem;padding:1.2rem;border:1px dashed var(--line);border-radius:.8rem;color:var(--muted);background:#111923}.preview-empty strong{color:var(--text)}.preview-empty span{flex:1}.preview-empty button{padding:.55rem .8rem;border:1px solid var(--accent);border-radius:.4rem;color:var(--accent);background:transparent;cursor:pointer}.preview-empty button:hover{color:#111;background:var(--accent)}
        .loadout-card.is-empty .card{border-style:dashed;background:linear-gradient(160deg,#141d29,#0d141d)}.loadout-card.is-empty .item-name{color:var(--muted)}.slot-status{padding:.25rem .4rem;border-radius:.25rem;color:#fbbf24;background:#3c2d12;font-size:.6rem;font-weight:800}.empty-slot-icon{display:grid;place-items:center;width:5rem;height:5rem;margin:auto;border:1px dashed #536274;border-radius:50%;color:#718096;font-size:2rem}.slot-action{width:100%;margin-top:auto;padding:.6rem;border:1px solid #536274;border-radius:.45rem;color:var(--muted);background:transparent;cursor:pointer}.slot-action:hover{border-color:var(--accent);color:var(--accent)}
        .inventory-layout{display:grid;grid-template-columns:190px minmax(0,1fr);gap:1.25rem;align-items:start}.category-sidebar{position:sticky;top:.75rem;padding:.9rem;border:1px solid var(--line);border-radius:.85rem;background:#0d151f}.sidebar-heading{display:flex;flex-direction:column;gap:.35rem;padding:.35rem .4rem 1rem;border-bottom:1px solid var(--line)}.sidebar-heading strong{font-size:1rem}.category-sidebar .category-tabs{display:flex;flex-direction:column;gap:.35rem;padding:.8rem 0}.category-sidebar .category-tab{justify-content:space-between;width:100%;padding:.65rem .7rem}.category-sidebar .category-tab small{margin-left:auto}.sidebar-note{display:flex;align-items:flex-start;gap:.5rem;padding:.75rem .4rem .35rem;border-top:1px solid var(--line);color:var(--muted);font-size:.7rem}.sidebar-note strong,.sidebar-note small{display:block}.sidebar-note strong{color:var(--text);font-size:.68rem}.sidebar-note small{margin-top:.2rem}.live-dot{width:7px;height:7px;margin-top:.2rem;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px #22c55e22}.inventory-content{min-width:0}.inventory-content .loadout-tools{margin-top:0}
        .skins-profile{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(260px,.7fr);gap:1rem;margin-bottom:1.4rem}.skins-profile-card,.skins-profile-side{overflow:hidden;border:1px solid var(--line);border-radius:1rem;background:linear-gradient(145deg,#141c2a,#0e141d)}.skins-profile-cover{height:120px;background:radial-gradient(circle at 72% 20%,#2c4f91 0,transparent 42%),linear-gradient(115deg,#16233d,#10151f);border-bottom:1px solid var(--line)}.skins-profile-main{display:flex;align-items:center;gap:1rem;padding:0 1.3rem 1.1rem;margin-top:-34px}.skins-profile-avatar{display:grid;place-items:center;width:76px;height:76px;flex:0 0 76px;border:4px solid #111923;border-radius:50%;object-fit:cover;background:#243b68;box-shadow:0 10px 25px #0008;font-size:1.6rem;font-weight:800}.skins-profile-identity{min-width:0;flex:1}.skins-profile-name{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}.skins-profile-name h2{margin:0;font-size:1.2rem}.steam-connected{padding:.25rem .45rem;border:1px solid #2b8060;border-radius:.35rem;color:#70e2a6;background:#1b5b4422;font-size:.58rem;font-weight:800;letter-spacing:.04em}.skins-profile-identity p{margin:.25rem 0 0;color:var(--muted);font-size:.75rem}.skins-profile-link{padding:.6rem .75rem;border:1px solid var(--line);border-radius:.45rem;color:var(--text);font-size:.72rem;font-weight:700}.skins-profile-link:hover{border-color:var(--accent);color:var(--accent)}.skins-profile-details{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;padding:1rem 1.3rem;border-top:1px solid var(--line)}.skins-profile-details small{display:block;color:var(--muted);font-size:.57rem;letter-spacing:.08em}.skins-profile-details strong{display:block;margin-top:.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.72rem}.profile-verified-text{color:#70e2a6}.skins-profile-side{padding:1.3rem}.skins-profile-side .eyebrow{display:block;margin-bottom:.6rem}.skins-profile-side h3{margin:.2rem 0 .55rem;font-size:1.2rem}.skins-profile-side p{margin:0 0 1rem;color:var(--muted);font-size:.75rem}.skins-profile-side .profile-side-link{display:block;color:var(--accent);font-size:.72rem;text-align:center}.skins-profile-side .profile-side-link:hover{text-decoration:underline}
        @media(max-width:900px){.inventory-layout{grid-template-columns:150px minmax(0,1fr)}.category-sidebar{padding:.65rem}.category-sidebar .category-tab{font-size:.78rem}.skins-profile{grid-template-columns:1fr}}
        @media(max-width:700px){.header-actions{width:100%;justify-content:space-between}.steam-id{display:none}.hero-actions{align-items:flex-start;flex-direction:column}.preview-empty{align-items:flex-start;flex-direction:column}.results-count{display:block;margin-top:.5rem}.inventory-layout{display:block}.category-sidebar{position:static;margin-bottom:1rem;padding:.65rem}.sidebar-heading{flex-direction:row;align-items:center;justify-content:space-between;padding-bottom:.65rem}.category-sidebar .category-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:.65rem 0}.sidebar-note{display:none}.skins-profile-main{align-items:flex-start;flex-wrap:wrap;padding-inline:.9rem}.skins-profile-link{margin-left:auto}.skins-profile-details{padding-inline:.9rem;gap:.5rem}.skins-profile-details strong{font-size:.65rem}}
    </style>
    <style>
        body{background-color:#070b11;background-image:linear-gradient(#ffffff03 1px,transparent 1px),linear-gradient(90deg,#ffffff03 1px,transparent 1px),radial-gradient(circle at 80% 0,#26354b55,transparent 34%);background-size:32px 32px,32px 32px,100% 100%;}
        .skins-shell{padding-top:1.5rem}
        .loadout-heading{margin-top:1.8rem}
        .loadout-team-tabs{padding:.3rem;border:1px solid var(--line);border-radius:.7rem;background:#0b121b;width:max-content}
        .loadout-team-tab{min-width:7.5rem;border-color:transparent;transition:background .18s,border-color .18s,color .18s,transform .18s}
        .loadout-team-tab:hover{border-color:#ff8a2470;color:var(--text);transform:translateY(-1px)}
        .loadout-team-tab.active{box-shadow:0 8px 22px #ff8a2430}
        .loadout-preview-heading{align-items:center;padding:.2rem .1rem .65rem;border-bottom:1px solid #263548}
        .loadout-preview-heading>span{font-size:.8rem;letter-spacing:.1em}
        .loadout-grid{grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:.8rem}
        .loadout-card .card{height:100%;border:1px solid #2a3b4f;border-radius:.85rem;background:linear-gradient(150deg,#142231,#0d151e);box-shadow:0 12px 30px #0003;transition:transform .2s,border-color .2s,box-shadow .2s}
        .loadout-card .card:hover{border-color:#ff8a2499;box-shadow:0 16px 36px #0006}
        .loadout-card.is-empty .card{background:linear-gradient(150deg,#111c29,#0b121a);border-color:#34485c}
        .loadout-card.is-empty .card:hover{border-color:#ff8a24aa}
        .empty-slot-icon{background:#0b141f;box-shadow:inset 0 0 0 5px #ffffff03}
        .loadout-tools{box-shadow:0 10px 26px #0003}
        .category-sidebar{box-shadow:0 12px 30px #0003}
        .skin-card .card,.catalog-card button{box-shadow:0 10px 24px #0003;transition:transform .2s,border-color .2s,box-shadow .2s}
        .skin-card .card:hover,.catalog-card button:hover{box-shadow:0 16px 32px #0006}
        .favorite-button{display:grid;place-items:center;width:1.8rem;height:1.8rem;padding:0;border:1px solid transparent;border-radius:.4rem;color:#718096;background:transparent;cursor:pointer;font-size:1rem;line-height:1;transition:color .18s,border-color .18s,background .18s}
        .favorite-button:hover,.favorite-button.is-favorite{border-color:#ff8a2470;color:var(--accent);background:#ff8a2418}
        .favorite-button.is-favorite{color:#ffd166}
        .card-topline-actions{display:flex;align-items:center;gap:.35rem}
        .filter-button[data-filter="favorites"]{color:#ffd166}
        @media(max-width:700px){.loadout-team-tabs{width:100%}.loadout-team-tab{flex:1;min-width:0}.loadout-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        .save-state{display:inline-flex;align-items:center;gap:.35rem;color:var(--muted);font-size:.68rem;font-weight:700}.save-state.is-saving{color:#fbbf24}.save-state.is-saved{color:#86efac}.save-state.is-unsaved{color:#ffb15c}.save-state:before{content:'●';font-size:.55rem}
        .inventory-toggle{display:none;width:100%;justify-content:space-between;padding:.7rem;border:1px solid var(--line);border-radius:.5rem;color:var(--text);background:#111923;font-weight:800;cursor:pointer}
        .wear-value{display:inline-flex;align-items:center;padding:.2rem .4rem;border-radius:.25rem;font-size:.62rem;font-weight:800}.wear-value.factory-new{color:#86efac;background:#123d32}.wear-value.minimal-wear{color:#93c5fd;background:#172f52}.wear-value.field-tested{color:#fbbf24;background:#3c2d12}.wear-value.well-worn,.wear-value.battle-scarred{color:#fb923c;background:#482515}
        .preview-panel-image{width:min(100%,28rem);height:15rem;object-fit:contain;margin:.5rem auto 1rem;filter:drop-shadow(0 20px 14px #0009)}
        .named-loadouts{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin:-.6rem 0 1.4rem}.named-loadouts input,.named-loadouts select{min-height:2.35rem;padding:.55rem .7rem;border:1px solid var(--line);border-radius:.45rem;color:var(--text);background:#111923}.named-loadouts input{width:12rem}.named-loadouts button{min-height:2.35rem;padding:.55rem .75rem;border:1px solid var(--line);border-radius:.45rem;color:var(--muted);background:#111923;cursor:pointer}.named-loadouts button:hover{border-color:var(--accent);color:var(--accent)}
        .catalog-card > .catalog-team-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;width:100%;padding:.7rem 0 0}
        .catalog-card > .catalog-team-actions button{display:block;min-height:2.4rem;padding:.55rem .4rem;border:1px solid var(--line);border-radius:.45rem;color:var(--muted);background:#111923;font-size:.7rem;font-weight:800;transform:none}
        .catalog-card > .catalog-team-actions button:hover{border-color:var(--accent);color:#111;background:var(--accent);transform:none}
        .catalog-card > .catalog-team-actions button:first-child{border-color:#3b82f655}.catalog-card > .catalog-team-actions button:first-child:hover{background:#60a5fa;border-color:#60a5fa}.catalog-card > .catalog-team-actions button:last-child{border-color:#f59e0b55}.catalog-card > .catalog-team-actions button:last-child:hover{background:#f59e0b;border-color:#f59e0b}
        .catalog-card.is-equipped>img{filter:drop-shadow(0 0 12px #ff8a2488)}.catalog-card.is-equipped .catalog-card-title{color:var(--accent)}.catalog-card .equipped-badge{display:inline-block;margin-left:.3rem}.catalog-card > .catalog-team-actions .is-equipped-team{color:#111;background:#86efac;border-color:#86efac}
        .modal-option.is-selected button{border-color:var(--accent);box-shadow:0 0 0 2px #ff8a2444,0 12px 28px #0007}.modal-option.is-selected b{color:#86efac}
        @media(max-width:700px){.inventory-toggle{display:flex}.category-sidebar{display:none}.category-sidebar.is-open{display:block}.preview-panel-image{height:11rem}}
    </style>
    <title>Speed.mn CS2 Skins</title>
</head>
<body>
<header class="skins-header"><div class="brand-lockup"><div class="brand-mark"><img src="/img/hero/logo.jfif" alt="Speed.mn logo"></div><div><span class="eyebrow">SPEED.MN / CS2</span><h1>CS2 Skins</h1><p>Customize your loadout.</p></div></div><div class="header-actions"><a class="skins-back-link" href="/" aria-label="Буцах үндсэн сайт руу">← Буцах</a><span class="live-pill"><i></i> LIVE</span><span class="steam-id">STEAM <?= $h(substr($steamid, -6)) ?></span><a class="logout-link" href="?logout">Logout</a></div></header>
<main class="skins-shell">
    <section class="skins-profile" aria-label="Steam profile">
        <article class="skins-profile-card">
            <div class="skins-profile-cover"></div>
            <div class="skins-profile-main">
                <?php if ($steamAvatar !== ''): ?>
                    <img class="skins-profile-avatar" src="<?= $h($steamAvatar) ?>" alt="<?= $h($steamName) ?> avatar">
                <?php else: ?>
                    <div class="skins-profile-avatar"><?= $h(strtoupper(substr($steamName, 0, 1))) ?></div>
                <?php endif; ?>
                <div class="skins-profile-identity">
                    <div class="skins-profile-name"><h2><?= $h($steamName) ?></h2><span class="steam-connected">STEAM CONNECTED</span></div>
                    <p>Member since <?= $h($steamCreatedAt) ?></p>
                </div>
                <a class="skins-profile-link" href="<?= $h($steamProfileUrl) ?>" target="_blank" rel="noreferrer">Steam profile ↗</a>
            </div>
            <div class="skins-profile-details">
                <div><small>STEAM ID</small><strong><?= $h($steamid) ?></strong></div>
                <div><small>ACCOUNT STATUS</small><strong class="profile-verified-text">Verified</strong></div>
                <div><small>LOADOUT STATUS</small><strong><?= count($selected) ?> weapon slots</strong></div>
            </div>
        </article>
        <aside class="skins-profile-side">
            <span class="eyebrow">STEAM PROFILE</span>
            <h3>Ready to customize?</h3>
            <p>Your Steam identity is connected. Build CT and T loadouts separately and sync them to your account.</p>
            <a class="profile-side-link" href="#loadoutCategories">Open loadout studio ↓</a>
        </aside>
    </section>
    <section class="loadout-hero"><div><span class="hero-kicker">CS2 LOADOUT STUDIO</span><h2>Make every round<br><em>look legendary.</em></h2><p>Choose a finish and save it separately for CT or T.</p><div class="hero-actions"><button type="button" class="hero-cta" data-jump-loadout>Open loadout <span>↓</span></button><span class="hero-hint">Changes are saved per team</span></div></div><div class="hero-stats"><div><strong><?= $teamWeaponCounts[2] ?></strong><span>CT WEAPONS</span></div><div><strong><?= $teamWeaponCounts[3] ?></strong><span>T WEAPONS</span></div><div><strong><?= count($catalogs['pins']) ?></strong><span>PINS</span></div></div></section>
    <div class="loadout-heading" id="loadoutCategories"><div><span class="section-kicker">LOADOUT CATEGORIES</span><h2>Choose your skins</h2></div><span class="item-count"><?= count($weapons) ?> weapons · <?= count($catalogs['gloves']) + count($catalogs['agent']) + count($catalogs['music']) + count($catalogs['pins']) ?> extras</span></div>
    <div class="named-loadouts" aria-label="Named loadouts"><input id="loadoutName" type="text" maxlength="32" placeholder="Loadout name"><button type="button" id="saveNamedLoadout">Save snapshot</button><select id="namedLoadoutSelect" aria-label="Saved loadouts"><option value="">Saved loadouts</option></select><button type="button" id="loadNamedLoadout">Apply selected</button><button type="button" id="deleteNamedLoadout">Delete</button></div>
    <div class="inventory-layout">
    <button type="button" class="inventory-toggle" aria-expanded="false" aria-controls="inventorySidebar">Inventory <span>⌄</span></button>
    <aside class="category-sidebar" id="inventorySidebar">
        <div class="sidebar-heading"><span class="section-kicker">INVENTORY</span><strong>Collections</strong></div>
        <nav class="category-tabs" aria-label="Loadout categories">
        <?php foreach ([
            'loadout' => 'LOADOUT', 'knife' => 'Knife', 'gloves' => 'Gloves', 'agent' => 'Agent', 'music' => 'Music', 'pins' => 'Pins',
            'rifles' => 'Rifles', 'pistols' => 'Pistols', 'smgs' => 'SMGs', 'heavy' => 'Heavy',
        ] as $categoryId => $categoryLabel): ?>
            <button type="button" class="category-tab<?= $categoryId === 'loadout' ? ' active' : '' ?>" data-category-filter="<?= $h($categoryId) ?>"><?= $h($categoryLabel) ?><?php if (isset($catalogs[$categoryId])): ?><small><?= count($catalogs[$categoryId]) ?></small><?php endif; ?></button>
        <?php endforeach; ?>
        </nav>
        <div class="sidebar-note"><span class="live-dot"></span><div><strong>Loadout sync</strong><small>CT and T saved separately</small></div></div>
    </aside>
    <section class="inventory-content">
    <div class="loadout-tools sticky-toolbar"><label class="search-box"><span>⌕</span><input id="search" type="search" placeholder="Search weapon, skin, agent or pin..." aria-label="Search loadout items"></label><div class="team-filters" aria-label="Team filter"><button type="button" class="filter-button active" data-filter="all">★ All</button><button type="button" class="filter-button team-filter-ct" data-filter="ct"><img src="img/team/ct.png" alt=""> CT Base</button><button type="button" class="filter-button team-filter-t" data-filter="t"><img src="img/team/t.png" alt=""> T Base</button><button type="button" class="filter-button" data-filter="shared">◆ Shared</button><button type="button" class="filter-button" data-filter="favorites">☆ Favorites</button></div><span class="results-count" id="resultsCount" aria-live="polite"></span></div>
    <section class="loadout-preview" data-loadout-section>
        <div class="loadout-team-tabs"><button type="button" class="loadout-team-tab active" data-loadout-team="3">CT LOADOUT</button><button type="button" class="loadout-team-tab" data-loadout-team="2">T LOADOUT</button></div>
    <?php foreach ([2 => 'CT', 3 => 'T'] as $previewTeam => $previewLabel): ?>
        <div class="loadout-team-panel" data-loadout-team-panel="<?= $previewTeam ?>"<?= $previewTeam === 3 ? ' hidden' : '' ?>>
        <div class="loadout-preview-heading"><span><?= $previewLabel ?> BASE</span><div class="loadout-actions"><span class="save-state is-saved" data-save-state>Saved</span><small>Equipped items</small><form method="post"><input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="weapon_team" value="<?= $previewTeam ?>"><input type="hidden" name="loadout_action" value="save"><button type="submit">Save <?= $previewLabel ?></button></form><form method="post" onsubmit="return confirm('Reset <?= $previewLabel ?> loadout?');"><input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="weapon_team" value="<?= $previewTeam ?>"><input type="hidden" name="loadout_action" value="reset"><button type="submit">Reset</button></form></div></div>
        <div class="loadout-grid">
        <?php
        $previewItems = [];
        if (isset($selectedKnives[$previewTeam])) {
            $selectedKnifeName = $selectedKnives[$previewTeam];
            $selectedKnifeImage = '';
            $selectedKnifeLabel = $selectedKnifeName;
            foreach ($knives as $knifeId => $knife) {
                if ($knife['weapon_name'] !== $selectedKnifeName) {
                    continue;
                }
                $selectedKnife = $selected[$knifeId][$previewTeam] ?? null;
                $selectedKnifePaint = $selectedKnife && isset($skins[$knifeId][(int) $selectedKnife['weapon_paint_id']])
                    ? $skins[$knifeId][(int) $selectedKnife['weapon_paint_id']]
                    : $knife;
                $selectedKnifeImage = $selectedKnifePaint['image_url'];
                $selectedKnifeLabel = $selectedKnifePaint['paint_name'];
                break;
            }
            $previewItems[] = ['name' => 'Knife', 'value' => $selectedKnifeLabel, 'image' => $selectedKnifeImage];
        }
        $gloveDefindex = (int) ($selectedGloves[$previewTeam] ?? 0);
        $glovePaintId = (int) ($selected[$gloveDefindex][$previewTeam]['weapon_paint_id'] ?? 0);
        if ($gloveDefindex > 0 && isset($catalogMaps['gloves'][(string) $glovePaintId])) {
            $item = $catalogMaps['gloves'][(string) $glovePaintId];
            $previewItems[] = ['name' => 'Gloves', 'value' => $item['name'], 'image' => $item['image_url']];
        }
        $agentKey = $previewTeam === 3 ? 'agent_ct' : 'agent_t';
        if (!empty($selectedAgents[$agentKey]) && isset($catalogMaps['agent'][(string) $selectedAgents[$agentKey]])) {
            $item = $catalogMaps['agent'][(string) $selectedAgents[$agentKey]];
            $previewItems[] = ['name' => 'Agent', 'value' => $item['name'], 'image' => $item['image_url']];
        }
        if (isset($selectedMusic[$previewTeam], $catalogMaps['music'][$selectedMusic[$previewTeam]])) {
            $item = $catalogMaps['music'][$selectedMusic[$previewTeam]];
            $previewItems[] = ['name' => 'Music', 'value' => $item['name'], 'image' => $item['image_url']];
        }
        if (isset($selectedPins[$previewTeam], $catalogMaps['pins'][$selectedPins[$previewTeam]])) {
            $item = $catalogMaps['pins'][$selectedPins[$previewTeam]];
            $previewItems[] = ['name' => 'Pin', 'value' => $item['name'], 'image' => $item['image_url']];
        }
        $previewSlots = [
            'Knife' => ['category' => 'knife', 'selected' => isset($selectedKnives[$previewTeam])],
            'Gloves' => ['category' => 'gloves', 'selected' => isset($selectedGloves[$previewTeam])],
            'Agent' => ['category' => 'agent', 'selected' => !empty($selectedAgents[$agentKey])],
            'Music' => ['category' => 'music', 'selected' => isset($selectedMusic[$previewTeam])],
        ];
        foreach ($previewSlots as $slotName => $slot) {
            if (!$slot['selected']) {
                $previewItems[] = [
                    'name' => $slotName,
                    'value' => 'Not equipped',
                    'image' => '',
                    'category' => $slot['category'],
                    'empty' => true,
                ];
            }
        }
        foreach ($selected as $defindex => $teams):
            $saved = $teams[$previewTeam] ?? null;
            if (!$saved || !isset($weapons[$defindex])) continue;
        $loadoutPaint = $skins[$defindex][(int) $saved['weapon_paint_id']] ?? $weapons[$defindex];
        $previewItems[] = ['name' => UtilsClass::displayWeaponName($loadoutPaint['weapon_name']), 'value' => $loadoutPaint['paint_name'], 'image' => $loadoutPaint['image_url'], 'stattrak' => (int) ($saved['weapon_stattrak'] ?? 0)];
        endforeach;
        foreach ($previewItems as $previewItem):
    ?>
        <article class="loadout-card<?= !empty($previewItem['empty']) ? ' is-empty' : '' ?>" data-search="<?= $h(strtolower($previewItem['name'] . ' ' . $previewItem['value'])) ?>">
            <div class="card"><div class="card-body"><div class="card-topline"><span class="team-badge team-badge-<?= $previewTeam === 3 ? 'ct' : 't' ?>"><?= $h($previewLabel) ?> BASE</span><?php if (empty($previewItem['empty'])): ?><span class="equipped-badge">EQUIPPED</span><?php else: ?><span class="slot-status">EMPTY SLOT</span><?php endif; ?></div><h3 class="item-name"><?= $h($previewItem['value']) ?></h3><span class="weapon-name"><?= $h($previewItem['name']) ?><?php if (!empty($previewItem['stattrak'])): ?> <b class="stattrak-badge">StatTrak™</b><?php endif; ?></span><?php if ($previewItem['image'] !== ''): ?><img class="skin-image" src="<?= $h($previewItem['image']) ?>" alt="<?= $h($previewItem['value']) ?>" loading="lazy"><?php else: ?><div class="empty-slot-icon">＋</div><?php endif; ?><?php if (!empty($previewItem['empty'])): ?><button type="button" class="slot-action" data-open-category="<?= $h($previewItem['category']) ?>">Choose <?= $h($previewItem['name']) ?></button><?php endif; ?></div></div>
        </article>
    <?php endforeach; ?>
        </div><?php if ($previewItems === []): ?><div class="preview-empty"><strong><?= $previewLabel ?> loadout is empty</strong><span>Pick a weapon, agent, gloves or pin to start.</span><button type="button" data-open-category="rifles">Browse items</button></div><?php endif; ?></div>
    <?php endforeach; ?>
    </section>
    <section class="skin-grid">
    <?php foreach ($weapons as $defindex => $weapon):
        $team = $weapon['team'];
        $teamId = $team === 'ct' ? 3 : 2;
        $current = $selected[$defindex][$teamId] ?? null;
        $paint = $current && isset($skins[$defindex][(int) $current['weapon_paint_id']]) ? $skins[$defindex][(int) $current['weapon_paint_id']] : $weapon;
        $searchText = strtolower($weapon['weapon_name'] . ' ' . $paint['paint_name']);
    ?>
        <article class="skin-card" data-category="<?= $h($weapon['category']) ?>" data-team="<?= $h($team) ?>" data-search="<?= $h($searchText) ?>" data-defindex="<?= (int) $defindex ?>" data-current-team="<?= $teamId ?>" data-current-paint="<?= (int) ($current['weapon_paint_id'] ?? 0) ?>" data-current-wear="<?= $current ? $h($current['weapon_wear']) : '' ?>" data-current-seed="<?= (int) ($current['weapon_seed'] ?? 0) ?>" data-current-stattrak="<?= (int) ($current['weapon_stattrak'] ?? 0) ?>" data-weapon="<?= $h(UtilsClass::displayWeaponName($weapon['weapon_name'])) ?>">
            <div class="card"><div class="card-body"><div class="card-topline"><span class="team-badge team-badge-<?= $h($team) ?>"><?php if ($team === 'ct'): ?><img src="img/team/ct.png" alt=""> CT Base<?php elseif ($team === 't'): ?><img src="img/team/t.png" alt=""> T Base<?php else: ?>SHARED<?php endif; ?></span><span class="card-topline-actions"><?php if ($current): ?><span class="equipped-badge">EQUIPPED</span><?php endif; ?><button type="button" class="favorite-button" data-favorite aria-label="Add to favorites" title="Add to favorites">☆</button></span></div><h3 class="item-name"><?= $h($paint['paint_name']) ?></h3><span class="weapon-name"><?= $h(UtilsClass::displayWeaponName($weapon['weapon_name'])) ?></span><img class="skin-image" src="<?= $h($paint['image_url']) ?>" alt="<?= $h($paint['paint_name']) ?>" loading="lazy"></div><div class="card-footer"><button type="button" class="show-details" data-details data-image="<?= $h($paint['image_url']) ?>" data-weapon="<?= $h(UtilsClass::displayWeaponName($weapon['weapon_name'])) ?>" data-skin="<?= $h($paint['paint_name']) ?>" data-wear="<?= $h($current['weapon_wear'] ?? '0.00') ?>" data-seed="<?= (int) ($current['weapon_seed'] ?? 0) ?>" data-stattrak="<?= (int) ($current['weapon_stattrak'] ?? 0) ?>">Details</button><button type="button" class="change-skin" data-open-picker>Change skin <span>↗</span></button></div></div>
        </article>
    <?php endforeach; ?>
    </section>
    <section class="catalog-section" data-catalog-category="knife" hidden>
        <div class="catalog-heading"><span class="section-kicker">KNIFE COLLECTION</span><strong><?= count($knives) ?> items</strong></div>
        <div class="catalog-grid">
        <?php foreach ($knives as $knifeId => $knife): ?>
            <form class="catalog-card" data-catalog-item="knife" data-category="knife" data-defindex="<?= (int) $knifeId ?>" data-weapon="<?= $h($knife['weapon_name']) ?>" data-current-paint-ct="<?= (int) ($selected[$knifeId][3]['weapon_paint_id'] ?? 0) ?>" data-current-paint-t="<?= (int) ($selected[$knifeId][2]['weapon_paint_id'] ?? 0) ?>" data-search="<?= $h(strtolower($knife['weapon_name'] . ' ' . $knife['paint_name'])) ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="forma" value="knife-<?= $h($knifeId) ?>">
                <img src="<?= $h($knife['image_url']) ?>" alt="<?= $h($knife['paint_name']) ?>" loading="lazy">
                <span class="catalog-card-title"><?= $h($knife['paint_name']) ?><?php if (isset($selectedKnives[3]) && $selectedKnives[3] === $knife['weapon_name']): ?><b class="equipped-badge">CT EQUIPPED</b><?php endif; ?><?php if (isset($selectedKnives[2]) && $selectedKnives[2] === $knife['weapon_name']): ?><b class="equipped-badge">T EQUIPPED</b><?php endif; ?></span>
                <span class="catalog-team-actions"><button class="<?= isset($selectedKnives[3]) && $selectedKnives[3] === $knife['weapon_name'] ? 'is-equipped-team' : '' ?>" type="button" data-knife-picker data-team="3"><?= isset($selectedKnives[3]) && $selectedKnives[3] === $knife['weapon_name'] ? 'Edit CT' : 'Choose CT' ?></button><button class="<?= isset($selectedKnives[2]) && $selectedKnives[2] === $knife['weapon_name'] ? 'is-equipped-team' : '' ?>" type="button" data-knife-picker data-team="2"><?= isset($selectedKnives[2]) && $selectedKnives[2] === $knife['weapon_name'] ? 'Edit T' : 'Choose T' ?></button></span>
            </form>
        <?php endforeach; ?>
        </div>
    </section>
    <?php foreach (['gloves', 'agent', 'music', 'pins'] as $catalogCategory): ?>
        <section class="catalog-section" data-catalog-category="<?= $h($catalogCategory) ?>" hidden>
            <div class="catalog-heading"><span class="section-kicker"><?= $h(strtoupper($catalogCategory)) ?> COLLECTION</span><strong><?= count($catalogs[$catalogCategory]) ?> items</strong></div>
            <div class="catalog-grid">
        <?php foreach ($catalogs[$catalogCategory] as $item): ?>
            <?php
                $itemTeam = $catalogCategory === 'agent' && $item['team'] === '3' ? 3 : ($catalogCategory === 'gloves' ? 3 : 2);
                $equipped = $catalogCategory === 'gloves'
                    ? (($selected[$item['defindex'] ?? 0][2]['weapon_paint_id'] ?? '') === (string) $item['id'] || ($selected[$item['defindex'] ?? 0][3]['weapon_paint_id'] ?? '') === (string) $item['id'])
                    : ($catalogCategory === 'music'
                        ? (($selectedMusic[$itemTeam] ?? '') === (string) $item['id'])
                        : ($catalogCategory === 'agent'
                            ? (($selectedAgents[$itemTeam === 3 ? 'agent_ct' : 'agent_t'] ?? '') === (string) $item['id'])
                            : in_array((string) $item['id'], $selectedPins, true)));
            ?>
            <form class="catalog-card<?= $equipped ? ' is-equipped' : '' ?>" data-catalog-item="<?= $h($catalogCategory) ?>" data-category="<?= $h($catalogCategory) ?>" data-defindex="<?= (int) ($item['defindex'] ?? 0) ?>" data-current-team="<?= $itemTeam ?>" data-current-paint="<?= (int) ($selected[$item['defindex'] ?? 0][$itemTeam]['weapon_paint_id'] ?? 0) ?>" data-current-paint-ct="<?= (int) ($selected[$item['defindex'] ?? 0][3]['weapon_paint_id'] ?? 0) ?>" data-current-paint-t="<?= (int) ($selected[$item['defindex'] ?? 0][2]['weapon_paint_id'] ?? 0) ?>" data-search="<?= $h(strtolower($item['name'])) ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="loadout_type" value="<?= $h($catalogCategory) ?>">
                <input type="hidden" name="catalog_id" value="<?= $h($item['id']) ?>">
                <?php if ($catalogCategory === 'pins'): ?>
                    <div class="catalog-team-actions"><button type="submit" name="weapon_team" value="3">CT</button><button type="submit" name="weapon_team" value="2">T</button></div>
                <?php else: ?>
                    <input type="hidden" name="weapon_team" value="<?= $catalogCategory === 'agent' && $item['team'] === '3' ? '3' : '2' ?>">
                <?php endif; ?>
                    <button type="<?= $catalogCategory === 'gloves' ? 'button' : 'submit' ?>"<?= $catalogCategory === 'gloves' ? ' data-glove-picker' : '' ?>>
                    <img src="<?= $h($item['image_url'] !== '' ? $item['image_url'] : 'img/team/ct.png') ?>" alt="<?= $h($item['name']) ?>" loading="lazy">
                    <span class="catalog-card-title"><?= $h($item['name']) ?><?php if ($equipped): ?><b class="equipped-badge">EQUIPPED</b><?php endif; ?></span>
                    <b><?= $equipped ? 'EQUIPPED' : 'SELECT' ?></b>
                </button>
            </form>
        <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <div class="category-empty" id="categoryEmpty" hidden>Энэ ангилалд сонгосон item одоогоор алга.</div>
    </section>
    </div>
</main>
<div class="skin-modal" id="skinModal" aria-hidden="true"><div class="modal-backdrop" data-close></div><section class="modal-panel"><header class="modal-header"><div><span class="section-kicker">LOADOUT STUDIO</span><h2 id="modalTitle">Choose a skin</h2><p id="modalSubtitle"></p><div class="weapon-team-picker"><span>Save for:</span><button type="button" class="weapon-team-button active" data-team-id="3">CT</button><button type="button" class="weapon-team-button" data-team-id="2">T</button></div></div><button type="button" class="modal-close" data-close>×</button></header><div class="modal-toolbar"><label class="search-box"><span>⌕</span><input id="modalSearch" type="search" placeholder="Search skins..."></label><div class="wear-filters"><button type="button" class="wear-filter active" data-wear="0.08">Factory New</button><button type="button" class="wear-filter" data-wear="0.15">Minimal Wear</button><button type="button" class="wear-filter" data-wear="0.38">Field-Tested</button><button type="button" class="wear-filter" data-wear="0.45">Well-Worn</button><button type="button" class="wear-filter" data-wear="0.60">Battle-Scarred</button></div><label class="stattrak-sync-panel"><input id="modalStatTrak" type="checkbox"><span><strong>StatTrak™</strong><small>Sync with selected weapon</small></span></label></div><div class="modal-grid" id="modalGrid"></div></section></div>
<div class="details-modal" id="detailsModal" aria-hidden="true"><div class="modal-backdrop" data-details-close></div><section class="details-panel"><button type="button" class="modal-close" data-details-close>×</button><span class="section-kicker">ITEM DETAILS</span><h2 id="detailsSkin">Skin details</h2><p id="detailsWeapon"></p><img class="preview-panel-image" id="detailsImage" src="" alt=""><div class="details-stats"><div><small>WEAR / FLOAT</small><strong id="detailsWear">0.00</strong><span class="wear-value" id="detailsWearLabel">Factory New</span></div><div><small>SEED</small><strong id="detailsSeed">0</strong></div><div><small>STATTRAK</small><strong id="detailsStatTrak">OFF</strong></div></div></section></div>
<div class="toast" id="toast">Loadout saved.</div>
<script id="skinData" type="application/json"><?= json_encode(['skins' => $skins, 'gloves' => $catalogs['gloves']], $jsonFlags) ?></script>
<script>
const data = JSON.parse(document.getElementById('skinData').textContent), modal = document.getElementById('skinModal'), grid = document.getElementById('modalGrid'), stattrakToggle = document.getElementById('modalStatTrak');
const steamId = <?= json_encode((string) $steamid, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const skinApplyUrl = 'class/skin_apply.php';
let activeCard = null, activeTeam = 2, selectedWear = '0.08', activeStatTrak = 0;
const normalizeWear = value => {
    const numeric = Number(value ?? '0.08');
    if (!Number.isFinite(numeric) || numeric < 0 || numeric > 1) {
        return '0.08';
    }
    return String(numeric);
};
const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
function showToast(message) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast.timeoutId);
    showToast.timeoutId = setTimeout(() => toast.classList.remove('show'), 2200);
}
function setSaveState(state) { document.querySelectorAll('[data-save-state]').forEach(element => { element.className = `save-state is-${state}`; element.textContent = state === 'saving' ? 'Saving...' : state === 'unsaved' ? 'Unsaved changes' : 'Saved'; }); }
function submitSkinSelection(form) {
    if (!form || !activeCard) return false;
    const button = form.querySelector('button[name="forma"]');
    const formaValue = button ? button.value : '';
    if (!formaValue || !formaValue.includes('-')) return false;

    const [weaponDefindex, skinPaint] = formaValue.split('-');
    const payload = new URLSearchParams({
        csrf_token: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        team: String(activeTeam),
        weapon_defindex: String(weaponDefindex || 0),
        skin_paint: String(skinPaint || 0),
        wear: normalizeWear(selectedWear),
        seed: String(form.querySelector('input[name="seed"]')?.value || 0),
        weapon: String(activeCard.dataset.weapon || activeCard.querySelector('.weapon-name')?.textContent?.trim() || ''),
        skin_category: String(activeCard.dataset.category || '')
    });

    setSaveState('saving');
    fetch(skinApplyUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: payload.toString()
    })
    .then(async response => {
        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch (error) { json = null; }
        if (!response.ok) {
            throw new Error((json && json.message) || 'Failed to save skin.');
        }
        return json || { success: true, message: 'Skin saved successfully.' };
    })
    .then(result => {
        setSaveState('saved');
        showToast(result.message || 'Skin saved successfully.');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setTimeout(() => window.location.reload(), 500);
    })
    .catch(error => {
        setSaveState('unsaved');
        console.error(error);
        showToast(error.message || 'Unable to save skin.');
    });

    return false;
}
function renderOptions() {
    if (!activeCard) return;
    const options = activeCard.dataset.category === 'gloves'
        ? Object.fromEntries((data.gloves || []).map(item => [item.id, {paint_name: item.name, image_url: item.image_url, defindex: item.defindex}]))
        : data.skins[activeCard.dataset.defindex] || {};
    const query = document.getElementById('modalSearch').value.toLowerCase().trim();
    grid.innerHTML = Object.entries(options).filter(([, item]) => !query || item.paint_name.toLowerCase().includes(query)).map(([id, item]) =>
        `<form class="modal-option${String(activeCard.dataset.currentPaint) === String(id) ? ' is-selected' : ''}" method="post"><input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>"><input type="hidden" name="weapon_team" value="${activeTeam}"><input type="hidden" name="wear" value="${selectedWear}"><input type="hidden" name="seed" value="0"><input type="hidden" name="weapon_stattrak" value="${activeStatTrak}"><button name="forma" value="${activeCard.dataset.category === 'gloves' ? item.defindex : activeCard.dataset.defindex}-${id}" type="submit"><img src="${esc(item.image_url)}" alt="${esc(item.paint_name)}"><span>${esc(item.paint_name)}</span><b>${String(activeCard.dataset.currentPaint) === String(id) ? 'EQUIPPED' : 'SELECT'}</b></button></form>`
    ).join('') || '<p class="empty-state">No skins found.</p>';
    grid.querySelectorAll('.modal-option').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            submitSkinSelection(form);
        });
    });
}
function openModal(card, teamOverride = null) { activeCard = card; activeTeam = teamOverride || (card.dataset.currentTeam === '3' ? 3 : 2); activeStatTrak = card.dataset.currentStattrak === '1' ? 1 : 0; stattrakToggle.checked = activeStatTrak === 1; const currentOption = data.skins[card.dataset.defindex]?.[card.dataset.currentPaint]; document.getElementById('modalTitle').textContent = currentOption?.paint_name || card.querySelector('.item-name')?.textContent || card.querySelector('.catalog-card-title')?.textContent || card.dataset.weapon || 'Choose a skin'; document.getElementById('modalSearch').value = ''; document.querySelectorAll('.weapon-team-button').forEach(b => b.classList.toggle('active', Number(b.dataset.teamId) === activeTeam)); renderOptions(); modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); }
document.querySelectorAll('[data-open-picker]').forEach(button => button.addEventListener('click', e => { e.stopPropagation(); openModal(button.closest('.skin-card')); }));
document.querySelectorAll('[data-knife-picker]').forEach(button => button.addEventListener('click', event => { event.preventDefault(); const card = button.closest('.catalog-card'); card.dataset.currentTeam = button.dataset.team; card.dataset.currentPaint = button.dataset.team === '3' ? card.dataset.currentPaintCt : card.dataset.currentPaintT; openModal(card, Number(button.dataset.team)); }));
document.querySelectorAll('[data-glove-picker]').forEach(button => button.addEventListener('click', event => { event.preventDefault(); openModal(button.closest('.catalog-card'), Number(button.closest('.catalog-card').dataset.currentTeam)); }));
document.querySelectorAll('.weapon-team-button').forEach(button => button.addEventListener('click', () => { activeTeam = Number(button.dataset.teamId); if (activeCard?.dataset.category === 'knife' || activeCard?.dataset.category === 'gloves') activeCard.dataset.currentPaint = activeTeam === 3 ? activeCard.dataset.currentPaintCt : activeCard.dataset.currentPaintT; document.querySelectorAll('.weapon-team-button').forEach(b => b.classList.toggle('active', b === button)); renderOptions(); }));
document.getElementById('modalSearch').addEventListener('input', renderOptions);
stattrakToggle.addEventListener('change', () => { activeStatTrak = stattrakToggle.checked ? 1 : 0; renderOptions(); });
const detailsModal = document.getElementById('detailsModal');
const wearLabel = value => { const wear = Number(value || 0); if (wear <= .07) return ['Factory New', 'factory-new']; if (wear <= .15) return ['Minimal Wear', 'minimal-wear']; if (wear <= .38) return ['Field-Tested', 'field-tested']; if (wear <= .45) return ['Well-Worn', 'well-worn']; return ['Battle-Scarred', 'battle-scarred']; };
document.querySelectorAll('.skin-card[data-current-wear]').forEach(card => { if (!card.dataset.currentWear) return; const label = wearLabel(card.dataset.currentWear); const badge = document.createElement('span'); badge.className = `wear-value ${label[1]}`; badge.textContent = label[0]; card.querySelector('.weapon-name').after(badge); });
document.querySelectorAll('[data-details]').forEach(button => button.addEventListener('click', () => { const label = wearLabel(button.dataset.wear); document.getElementById('detailsSkin').textContent = button.dataset.skin; document.getElementById('detailsWeapon').textContent = button.dataset.weapon; document.getElementById('detailsImage').src = button.dataset.image || ''; document.getElementById('detailsImage').alt = button.dataset.skin; document.getElementById('detailsWear').textContent = Number(button.dataset.wear || 0).toFixed(3); document.getElementById('detailsWearLabel').textContent = label[0]; document.getElementById('detailsWearLabel').className = `wear-value ${label[1]}`; document.getElementById('detailsSeed').textContent = button.dataset.seed || '0'; document.getElementById('detailsStatTrak').textContent = button.dataset.stattrak === '1' ? 'ON' : 'OFF'; detailsModal.classList.add('is-open'); detailsModal.setAttribute('aria-hidden', 'false'); }));
document.querySelectorAll('[data-details-close]').forEach(element => element.addEventListener('click', () => { detailsModal.classList.remove('is-open'); detailsModal.setAttribute('aria-hidden', 'true'); }));
document.querySelectorAll('.wear-filter').forEach(button => button.addEventListener('click', () => { selectedWear = normalizeWear(button.dataset.wear); document.querySelectorAll('.wear-filter').forEach(b => b.classList.toggle('active', b === button)); }));
const defaultWearButton = [...document.querySelectorAll('.wear-filter')].find(button => Number(button.dataset.wear) === 0.08) || document.querySelector('.wear-filter.active');
if (defaultWearButton) {
    selectedWear = normalizeWear(defaultWearButton.dataset.wear || '0.08');
    document.querySelectorAll('.wear-filter').forEach(b => b.classList.toggle('active', b === defaultWearButton));
}
const namedLoadoutKey = `speedmn-named-loadouts-${steamId}`;
const namedLoadoutSelect = document.getElementById('namedLoadoutSelect');
function readNamedLoadouts() { try { const value = JSON.parse(localStorage.getItem(namedLoadoutKey) || '{}'); return value && typeof value === 'object' ? value : {}; } catch (error) { localStorage.removeItem(namedLoadoutKey); return {}; } }
function renderNamedLoadouts() { const loadouts = readNamedLoadouts(); namedLoadoutSelect.innerHTML = '<option value="">Saved loadouts</option>'; Object.keys(loadouts).sort().forEach(name => { const option = document.createElement('option'); option.value = name; option.textContent = name; namedLoadoutSelect.appendChild(option); }); }
document.getElementById('saveNamedLoadout').addEventListener('click', () => { const name = document.getElementById('loadoutName').value.trim(); if (!name) { showToast('Enter a loadout name first.'); return; } const snapshot = [...document.querySelectorAll('.skin-card')].filter(card => Number(card.dataset.currentPaint) > 0).map(card => ({ defindex: card.dataset.defindex, team: card.dataset.currentTeam, paint: card.dataset.currentPaint, wear: card.dataset.currentWear, seed: card.dataset.currentSeed, stattrak: card.dataset.currentStattrak, weapon: card.dataset.weapon, category: card.dataset.category })); const loadouts = readNamedLoadouts(); loadouts[name] = snapshot; localStorage.setItem(namedLoadoutKey, JSON.stringify(loadouts)); renderNamedLoadouts(); namedLoadoutSelect.value = name; showToast(`${name} saved.`); });
document.getElementById('loadNamedLoadout').addEventListener('click', async () => { const name = namedLoadoutSelect.value; const snapshot = readNamedLoadouts()[name] || []; if (!name || snapshot.length === 0) { showToast('Choose a saved loadout first.'); return; } setSaveState('saving'); try { const responses = await Promise.all(snapshot.map(item => fetch(skinApplyUrl, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: new URLSearchParams({ csrf_token: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, team: item.team, weapon_defindex: item.defindex, skin_paint: item.paint, wear: item.wear, seed: item.seed, weapon: item.weapon, skin_category: item.category }).toString() }))); if (responses.some(response => !response.ok)) throw new Error('Loadout apply failed.'); setSaveState('saved'); showToast(`${name} applied.`); setTimeout(() => window.location.reload(), 500); } catch (error) { setSaveState('unsaved'); showToast('Unable to apply loadout.'); } });
document.getElementById('deleteNamedLoadout').addEventListener('click', () => { const name = namedLoadoutSelect.value; if (!name) return; const loadouts = readNamedLoadouts(); delete loadouts[name]; localStorage.setItem(namedLoadoutKey, JSON.stringify(loadouts)); renderNamedLoadouts(); showToast(`${name} deleted.`); });
renderNamedLoadouts();
document.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); }));
const inventoryToggle = document.querySelector('.inventory-toggle'), inventorySidebar = document.getElementById('inventorySidebar');
inventoryToggle.addEventListener('click', () => { const open = inventorySidebar.classList.toggle('is-open'); inventoryToggle.setAttribute('aria-expanded', String(open)); });
const favoriteStorageKey = 'speedmn-skin-favorites';
let favoriteSkins = new Set();
try { favoriteSkins = new Set(JSON.parse(localStorage.getItem(favoriteStorageKey) || '[]')); } catch (error) { localStorage.removeItem(favoriteStorageKey); }
function updateFavoriteButton(button) { const isFavorite = favoriteSkins.has(button.closest('.skin-card').dataset.defindex); button.classList.toggle('is-favorite', isFavorite); button.textContent = isFavorite ? '★' : '☆'; button.setAttribute('aria-label', isFavorite ? 'Remove from favorites' : 'Add to favorites'); button.setAttribute('title', isFavorite ? 'Remove from favorites' : 'Add to favorites'); }
document.querySelectorAll('[data-favorite]').forEach(button => { updateFavoriteButton(button); button.addEventListener('click', () => { const defindex = button.closest('.skin-card').dataset.defindex; if (favoriteSkins.has(defindex)) favoriteSkins.delete(defindex); else favoriteSkins.add(defindex); localStorage.setItem(favoriteStorageKey, JSON.stringify([...favoriteSkins])); updateFavoriteButton(button); if (activeTeamFilter === 'favorites') applyFilters(); }); });
document.getElementById('search').addEventListener('input', () => { if (typeof applyFilters === 'function') applyFilters(); });
let activeCategory = 'loadout', activeTeamFilter = 'all';
function applyFilters() { const q = document.getElementById('search').value.toLowerCase().trim(); const cards = [...document.querySelectorAll('.skin-card')]; cards.forEach(c => { const matchesTeam = activeTeamFilter === 'all' || (activeTeamFilter === 'favorites' ? favoriteSkins.has(c.dataset.defindex) : c.dataset.team === activeTeamFilter); c.hidden = c.dataset.category !== activeCategory || !matchesTeam || (q !== '' && !c.dataset.search.includes(q)); }); const loadout = document.querySelector('[data-loadout-section]'); loadout.hidden = activeCategory !== 'loadout'; loadout.querySelectorAll('.loadout-card').forEach(card => { card.hidden = activeCategory !== 'loadout' || (q !== '' && !card.dataset.search.includes(q)); }); const catalog = document.querySelector(`[data-catalog-category="${activeCategory}"]`); document.querySelectorAll('.catalog-section').forEach(section => { const isActive = section.dataset.catalogCategory === activeCategory; section.hidden = !isActive; section.querySelectorAll('.catalog-card').forEach(card => { card.hidden = !isActive || (q !== '' && !card.dataset.search.includes(q)); }); }); const visibleCards = cards.filter(card => !card.hidden).length; const visibleCatalog = catalog ? [...catalog.querySelectorAll('.catalog-card')].filter(card => !card.hidden).length : 0; const visibleLoadout = activeCategory === 'loadout' ? [...loadout.querySelectorAll('.loadout-card')].filter(card => !card.hidden).length : 0; document.getElementById('resultsCount').textContent = `${visibleCards + visibleCatalog + visibleLoadout} results`; const hasVisibleCatalog = visibleCatalog > 0; const hasLoadout = visibleLoadout > 0; document.getElementById('categoryEmpty').hidden = visibleCards > 0 || hasVisibleCatalog || hasLoadout; }
document.querySelectorAll('.loadout-team-tab').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('.loadout-team-tab').forEach(tab => tab.classList.toggle('active', tab === button)); document.querySelectorAll('.loadout-team-panel').forEach(panel => { panel.hidden = panel.dataset.loadoutTeamPanel !== button.dataset.loadoutTeam; }); }));
document.querySelectorAll('.category-tab').forEach(button => button.addEventListener('click', () => { activeCategory = button.dataset.categoryFilter; document.querySelectorAll('.category-tab').forEach(b => b.classList.toggle('active', b === button)); document.querySelectorAll('.filter-button').forEach(b => b.classList.toggle('active', b.dataset.filter === 'all')); activeTeamFilter = 'all'; applyFilters(); }));
document.querySelectorAll('.filter-button').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('.filter-button').forEach(b => b.classList.toggle('active', b === button)); activeTeamFilter = button.dataset.filter; applyFilters(); }));
document.querySelectorAll('[data-open-category]').forEach(button => button.addEventListener('click', () => { const target = document.querySelector(`[data-category-filter="${button.dataset.openCategory}"]`); if (target) target.click(); }));
document.querySelector('[data-jump-loadout]').addEventListener('click', () => document.getElementById('loadoutCategories').scrollIntoView({behavior: 'smooth', block: 'start'}));
applyFilters();
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelector('[data-close]').click(); });
if (location.search.includes('saved=1')) document.getElementById('toast').classList.add('show');
</script>
</body>
</html>
