ALTER TABLE `wp_player_skins`
    ADD COLUMN `weapon_stattrak` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `weapon_seed`;
