<?php


$projectRoot = dirname(__DIR__, 3);
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
	require_once $autoloadPath;
}
if (class_exists('Dotenv\\Dotenv') && is_file($projectRoot . '/.env')) {
	Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
}
if (is_file($projectRoot . '/.env')) {
	$environmentValues = parse_ini_file($projectRoot . '/.env', false, INI_SCANNER_RAW);
	if (is_array($environmentValues)) {
		foreach ($environmentValues as $environmentKey => $environmentValue) {
			if (!is_string($environmentValue) || getenv($environmentKey) !== false) {
				continue;
			}
			putenv($environmentKey . '=' . $environmentValue);
			$_ENV[$environmentKey] = $environmentValue;
		}
	}
}

$skinsEnv = static function (string $key, string $default = '', ?string $fallbackKey = null): string {
	$value = getenv($key);
	if ($value === false && isset($_ENV[$key])) {
		$value = $_ENV[$key];
	}
	if (($value === false || $value === '') && $fallbackKey !== null) {
		$value = getenv($fallbackKey);
		if ($value === false && isset($_ENV[$fallbackKey])) {
			$value = $_ENV[$fallbackKey];
		}
	}
	return is_string($value) && $value !== '' ? $value : $default;
};

define('SKIN_LANGUAGE', $skinsEnv('SKINS_LANGUAGE', 'skins_en'));
define('DB_HOST', $skinsEnv('SKINS_DB_HOST', '', 'DB_HOST'));
define('DB_PORT', $skinsEnv('SKINS_DB_PORT', '3306', 'DB_PORT'));
define('DB_NAME', $skinsEnv('SKINS_DB_DATABASE', '', 'DB_DATABASE'));
define('DB_USER', $skinsEnv('SKINS_DB_USERNAME', '', 'DB_USERNAME'));
define('DB_PASS', $skinsEnv('SKINS_DB_PASSWORD', '', 'DB_PASSWORD'));
define('SKINS_BRIDGE_SECRET', $skinsEnv('SKINS_BRIDGE_SECRET'));

define('WEB_STYLE_DARK', true);

define('STEAM_API_KEY', $skinsEnv('STEAM_API_KEY'));
define('STEAM_DOMAIN_NAME', $skinsEnv('STEAM_DOMAIN'));
define('STEAM_LOGOUT_PAGE', $skinsEnv('STEAM_LOGOUT_PAGE'));
define('STEAM_LOGIN_PAGE', $skinsEnv('STEAM_LOGIN_PAGE'));