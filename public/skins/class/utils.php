<?php
class UtilsClass
{
    public static function catalogFromJson(string $category): array
    {
        $category = $category === 'agent' ? 'agents' : $category;
        $allowed = ['gloves', 'agents', 'collectibles', 'stickers', 'music'];
        if (!in_array($category, $allowed, true)) {
            return [];
        }

        // Weapon data uses "skins_en.json", while the other catalog files use
        // the locale suffix directly, for example "gloves_en.json".
        $locale = preg_replace('/^skins_/', '', (string) SKIN_LANGUAGE);
        $paths = [
            __DIR__ . "/../data/{$category}_{$locale}.json",
            __DIR__ . "/../data/{$category}_" . SKIN_LANGUAGE . ".json",
        ];
        $path = '';
        foreach ($paths as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }
        $json = $path !== '' ? json_decode(file_get_contents($path), true) : [];
        if (!is_array($json)) {
            return [];
        }
        $items = [];
        foreach ($json as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = $item['paint_name'] ?? $item['agent_name'] ?? $item['name'] ?? '';
            $id = $item['paint'] ?? $item['id'] ?? $item['agent_id'] ?? $item['model'] ?? $index;
            $image = $item['image'] ?? '';
            if ($name === '') {
                continue;
            }
            $items[] = [
                'id' => (string) $id,
                'name' => (string) $name,
                'image_url' => (string) $image,
                'team' => isset($item['team']) ? (string) $item['team'] : '',
                'defindex' => (int) ($item['weapon_defindex'] ?? 0),
            ];
        }
        return $items;
    }

    public static function skinsFromJson(): array
    {
        $skins = [];
        $json = json_decode(file_get_contents(__DIR__ . "/../data/".SKIN_LANGUAGE.".json"), true);

        foreach ($json as $skin) {
            $skins[(int) $skin['weapon_defindex']][(int) $skin['paint']] = [
                'weapon_name' => $skin['weapon_name'],
                'paint_name' => $skin['paint_name'],
                'image_url' => $skin['image'],
            ];
        }

        return $skins;
    }

    public static function getWeaponsFromArray()
    {
        $weapons = [];
        $temp = self::skinsFromJson();

        foreach ($temp as $key => $value) {
            if (key_exists($key, $weapons)) {
                continue;
            }

            $firstSkin = reset($value);
            if (!is_array($firstSkin)) {
                continue;
            }

            $weapons[$key] = [
                'weapon_name' => $firstSkin['weapon_name'],
                'paint_name' => $firstSkin['paint_name'],
                'image_url' => $firstSkin['image_url'],
                'team' => self::getWeaponTeam($firstSkin['weapon_name']),
                'category' => self::getWeaponCategory($firstSkin['weapon_name']),
            ];
        }

        return $weapons;
    }

    public static function displayWeaponName(string $weaponName): string
    {
        $names = [
            'weapon_ak47' => 'AK-47',
            'weapon_aug' => 'AUG',
            'weapon_awp' => 'AWP',
            'weapon_cz75a' => 'CZ75-Auto',
            'weapon_deagle' => 'Desert Eagle',
            'weapon_elite' => 'Dual Berettas',
            'weapon_famas' => 'FAMAS',
            'weapon_fiveseven' => 'Five-SeveN',
            'weapon_g3sg1' => 'G3SG1',
            'weapon_galilar' => 'Galil AR',
            'weapon_glock' => 'Glock-18',
            'weapon_hkp2000' => 'P2000',
            'weapon_m4a1' => 'M4A4',
            'weapon_m4a1_silencer' => 'M4A1-S',
            'weapon_mac10' => 'MAC-10',
            'weapon_mag7' => 'MAG-7',
            'weapon_m249' => 'M249',
            'weapon_mp5sd' => 'MP5-SD',
            'weapon_mp7' => 'MP7',
            'weapon_mp9' => 'MP9',
            'weapon_negev' => 'Negev',
            'weapon_nova' => 'Nova',
            'weapon_p90' => 'P90',
            'weapon_p250' => 'P250',
            'weapon_pp_bizon' => 'PP-Bizon',
            'weapon_revolver' => 'R8 Revolver',
            'weapon_sawedoff' => 'Sawed-Off',
            'weapon_scar20' => 'SCAR-20',
            'weapon_sg556' => 'SG 553',
            'weapon_ssg08' => 'SSG 08',
            'weapon_tec9' => 'Tec-9',
            'weapon_ump45' => 'UMP-45',
            'weapon_usp_silencer' => 'USP-S',
            'weapon_xm1014' => 'XM1014',
            'weapon_knife' => 'Knife',
            'weapon_knife_t' => 'T Knife',
        ];

        if (isset($names[$weaponName])) {
            return $names[$weaponName];
        }

        $label = preg_replace('/^weapon_/', '', strtolower($weaponName));
        $label = str_replace('_', ' ', (string) $label);
        return ucwords($label);
    }

    public static function getWeaponTeam(string $weaponName): string
    {
        $terrorist = [
            'weapon_ak47', 'weapon_galilar', 'weapon_g3sg1', 'weapon_sg556',
            'weapon_mac10', 'weapon_tec9', 'weapon_glock', 'weapon_sawedoff',
            'weapon_xm1014', 'weapon_knife_t', 'weapon_c4',
        ];
        $counterTerrorist = [
            'weapon_m4a1', 'weapon_m4a1_silencer', 'weapon_famas', 'weapon_aug',
            'weapon_mp9', 'weapon_usp_silencer', 'weapon_hkp2000', 'weapon_fiveseven',
            'weapon_mag7', 'weapon_scar20',
        ];

        if (in_array($weaponName, $terrorist, true)) {
            return 't';
        }

        if (in_array($weaponName, $counterTerrorist, true)) {
            return 'ct';
        }

        return 'shared';
    }

    public static function getWeaponCategory(string $weaponName): string
    {
        $name = strtolower($weaponName);
        if (strpos($name, 'knife') !== false || strpos($name, 'bayonet') !== false) {
            return 'knife';
        }

        $rifles = ['ak47', 'aug', 'famas', 'galilar', 'g3sg1', 'm4a1', 'm4a1_silencer', 'sg556', 'scar20', 'awp', 'ssg08'];
        $pistols = ['deagle', 'elite', 'fiveseven', 'glock', 'hkp2000', 'p250', 'revolver', 'tec9', 'usp_silencer', 'cz75a'];
        $smgs = ['mac10', 'mp5sd', 'mp7', 'mp9', 'p90', 'pp_bizon', 'ump45'];
        $heavy = ['mag7', 'nova', 'sawedoff', 'xm1014', 'm249', 'negev'];

        foreach (['rifles' => $rifles, 'pistols' => $pistols, 'smgs' => $smgs, 'heavy' => $heavy] as $category => $weapons) {
            foreach ($weapons as $weapon) {
                if (strpos($name, $weapon) !== false) {
                    return $category;
                }
            }
        }

        return 'rifles';
    }

    public static function getKnifeTypes()
    {
        $knifes = [];
        $temp = self::getWeaponsFromArray();
        $knifeNames = [
            500 => 'weapon_bayonet',
            503 => 'weapon_knife_css',
            505 => 'weapon_knife_flip',
            506 => 'weapon_knife_gut',
            507 => 'weapon_knife_karambit',
            508 => 'weapon_knife_m9_bayonet',
            509 => 'weapon_knife_tactical',
            512 => 'weapon_knife_falchion',
            514 => 'weapon_knife_survival_bowie',
            515 => 'weapon_knife_butterfly',
            516 => 'weapon_knife_push',
            517 => 'weapon_knife_cord',
            518 => 'weapon_knife_canis',
            519 => 'weapon_knife_ursus',
            520 => 'weapon_knife_gypsy_jackknife',
            521 => 'weapon_knife_outdoor',
            522 => 'weapon_knife_stiletto',
            523 => 'weapon_knife_widowmaker',
            525 => 'weapon_knife_skeleton',
            526 => 'weapon_knife_kukri',
        ];

        foreach ($knifeNames as $key => $knifeName) {
            if (!isset($temp[$key])) {
                continue;
            }
            $weapon = $temp[$key];

            $knifes[$key] = [
                'weapon_name' => $knifeName,
                'paint_name' => rtrim(explode("|", $weapon['paint_name'])[0]),
                'image_url' => $weapon['image_url'],
            ];
            $knifes[0] = [
                'weapon_name' => "weapon_knife",
                'paint_name' => "Default knife",
                'image_url' => "https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/weapon_knife.png",
            ];
        }

        ksort($knifes);
        return $knifes;
    }

    public static function getSelectedSkins(array $temp)
    {
        $selected = [];

        foreach ($temp as $weapon) {
            $teamId = (int) ($weapon['weapon_team'] ?? 2);
            $selected[$teamId][(int) $weapon['weapon_defindex']] = [
                'weapon_paint_id' => (int) $weapon['weapon_paint_id'],
                'weapon_seed' => (int) $weapon['weapon_seed'],
                'weapon_wear' => (float) $weapon['weapon_wear'],
                'weapon_stattrak' => (int) ($weapon['weapon_stattrak'] ?? 0),
            ];
        }

        return $selected;
    }
}
