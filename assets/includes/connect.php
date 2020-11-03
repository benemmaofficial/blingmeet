<?php

function appLang($lang)
{
	global $mysqli;
	global $sm;
	$result = [];
	$lang = secureEncode($lang);
	$query = $mysqli->query('SELECT * FROM app_lang where lang_id = \'' . $lang . '\' ORDER BY id ASC');

	while ($row = $query->fetch_assoc()) {
		$result[$row['id']] = ['id' => $row['id'], 'text' => $row['text']];
	}

	return $result;
}

function themeSetting($theme)
{
	global $mysqli;
	global $sm;
	$result = [];
	$query = $mysqli->query('SELECT * FROM theme_settings where theme = \'' . $theme . '\'');

	while ($row = $query->fetch_assoc()) {
		$result[$row['setting']] = ['val' => $row['setting_val']];
	}

	return $result;
}

function sitePlugins()
{
	global $mysqli;
	global $sm;
	$result = [];
	$query = $mysqli->query('SELECT * FROM plugins_settings');

	while ($row = $query->fetch_assoc()) {
		$filter = 'where setting = "' . $row['setting'] . '" and plugin = "' . $row['plugin'] . '"';
		$clientSetting = getData('plugins_settings_values', 'setting_val', $filter);

		if ($clientSetting == 'noData') {
			$row['setting_val'] = $row['setting_val'];
		}
		else {
			$row['setting_val'] = $clientSetting;
		}

		if (isset($result[$row['plugin']])) {
			$result[$row['plugin']][$row['setting']] = $row['setting_val'];
			continue;
		}

		$result[$row['plugin']] = [$row['setting'] => $row['setting_val']];
	}

	return $result;
}

function sitePlugin()
{
	global $mysqli;
	global $sm;
	$result = [];
	$query = $mysqli->query('SELECT * FROM plugins');

	while ($row = $query->fetch_assoc()) {
		$result[] = $row;
	}

	return $result;
}

function isSecure()
{
	if ((!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] !== 'off')) || ($_SERVER['SERVER_PORT'] == 443)) {
		return 1;
	}
	else {
		return 0;
	}
}

function siteSettings()
{
	global $mysqli;
	global $sm;
	$result = [];
	$query = $mysqli->query('SELECT * FROM settings');

	while ($row = $query->fetch_assoc()) {
		$result[$row['setting']] = $row['setting_val'];
	}

	return $result;
}

function emailLang($lang)
{
	global $mysqli;
	global $sm;
	$result = [];
	$lang = secureEncode($lang);
	$query = $mysqli->query('SELECT * FROM email_lang where lang_id = \'' . $lang . '\' ORDER BY id ASC');

	while ($row = $query->fetch_assoc()) {
		$result[$row['id']] = ['id' => $row['id'], 'text' => $row['text']];
	}

	return $result;
}

function seoLang($lang)
{
	global $mysqli;
	global $sm;
	$result = [];
	$lang = secureEncode($lang);
	$query = $mysqli->query('SELECT * FROM seo_lang where lang_id = \'' . $lang . '\' ORDER BY page ASC');

	while ($row = $query->fetch_assoc()) {
		$result[$row['page']][$row['id']] = ['text' => $row['text']];
	}

	return $result;
}

function landingLang($lang, $theme, $preset)
{
	global $mysqli;
	global $sm;
	$result = [];
	$lang = secureEncode($lang);
	$query = $mysqli->query('SELECT * FROM landing_lang where lang_id = \'' . $lang . '\' and theme = \'' . $theme . '\' and preset = \'' . $preset . '\' ORDER BY id ASC');

	while ($row = $query->fetch_assoc()) {
		$result[$row['id']] = ['id' => $row['id'], 'text' => $row['text']];
	}

	return $result;
}

function siteConfig($val)
{
	global $mysqli;
	global $sm;
	$config = $mysqli->query('SELECT * FROM config');
	$result = $config->fetch_object();
	return $result->{$val};
}

session_cache_limiter('none');
session_start();
require 'config.php';
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'pusher.php';
$mysqli = new mysqli($db_host, $db_username, $db_password, $db_name);

if (mysqli_connect_errno($mysqli)) {
	exit(mysqli_connect_error());
}

$mysqli->set_charset('utf8mb4');
$sm = [];
$sm['plugins'] = siteplugins();
$sm['plugin'] = siteplugin();
$sm['settings'] = sitesettings();
$sm['creditsPackages'] = getArray('config_credits', '', 'id asc');
$sm['premiumPackages'] = getArray('config_premium', '', 'id asc');
date_default_timezone_set($sm['plugins']['settings']['timezone']);
$cronData = getArray('cron', '', 'cron DESC');

foreach ($cronData as $cron) {
	$diff = time() - $cron['cron_last_run'];

	if ($cron['cron'] == 'cron') {
		$cron['cron_run'] = 86400;
	}

	if ($cron['cron_run'] < $diff) {
		if ($cron['cron'] == 'onlineDay') {
			$today = date('w');
			$today = 'day' . $today;
			cronUpdateOnlineDay($today);
			$filter = 'WHERE cron = "onlineDay"';
			updateData('cron', 'cron_last_run', time(), $filter);
		}

		if ($cron['cron'] == 'cron') {
			$url = 'https://www.premiumdatingscript.com/clients/client.php?url=' . urlencode($_SERVER['HTTP_HOST']) . '&license=' . urlencode($sm['settings']['license']) . '&type=' . urlencode('pds');
			$callApi = curl_get_contents($url);
			$client = json_decode($callApi);
			updateData('client', 'client', json_encode($callApi, JSON_UNESCAPED_UNICODE));
			updateData('settings', 'setting_val', $client->fakeUsers, 'WHERE setting = "fakeUserLimit"');
			updateData('settings', 'setting_val', $client->fakeUsersUsage, 'WHERE setting = "fakeUserUsage"');
			updateData('settings', 'setting_val', $client->premium, 'WHERE setting = "premium"');
			updateData('settings', 'setting_val', $client->domainsLimit, 'WHERE setting = "domainsLimit"');
			updateData('settings', 'setting_val', $client->domainsUsage, 'WHERE setting = "domainsUsage"');
			$filter = 'WHERE cron = "cron"';
			updateData('cron', 'cron_last_run', time(), $filter);

			if (file_exists(_DIR_ . '/domain.php')) {
				$domainkey = $client->domainKey;
				file_put_contents(_DIR_ . '/domain.php', $domainkey);
			}
			else {
				$licenseErrorHtml = str_replace('[INVALID-MESSAGE]', 'The file domain.php that should be located in assets/includes/ folder was not found on this server, the software requires this file to work.<br>Please re-upload the file or contact support if you need assistance', $licenseErrorHtml);
				$licenseErrorHtml = str_replace('[INVALID-TITLE]', 'DOMAIN.PHP FILE NOT FOUND', $licenseErrorHtml);
				echo $licenseErrorHtml;
				exit();
			}

			if ($client->active == 0) {
				updateData('settings', 'setting_val', $client->reason, 'WHERE setting = "licenseError"');
				$licenseErrorHtml = str_replace('[INVALID-MESSAGE]', $client->reason, $licenseErrorHtml);
				$licenseErrorHtml = str_replace('[INVALID-TITLE]', 'INVALID DOMAIN', $licenseErrorHtml);
				echo $licenseErrorHtml;
				exit();
			}
		}
	}
}

if (isset($_GET['verifyLicense'])) {
	if ($_GET['verifyLicense'] == 'Yes') {
		$url = 'https://www.premiumdatingscript.com/clients/client.php?url=' . urlencode($_SERVER['HTTP_HOST']) . '&license=' . urlencode($sm['settings']['license']) . '&type=' . urlencode('pds');
		$callApi = curl_get_contents($url);
		$client = json_decode($callApi);
		updateData('client', 'client', json_encode($callApi, JSON_UNESCAPED_UNICODE));
		updateData('settings', 'setting_val', $client->fakeUsers, 'WHERE setting = "fakeUserLimit"');
		updateData('settings', 'setting_val', $client->fakeUsersUsage, 'WHERE setting = "fakeUserUsage"');
		updateData('settings', 'setting_val', $client->premium, 'WHERE setting = "premium"');
		updateData('settings', 'setting_val', $client->domainsLimit, 'WHERE setting = "domainsLimit"');
		updateData('settings', 'setting_val', $client->domainsUsage, 'WHERE setting = "domainsUsage"');
		$filter = 'WHERE cron = "cron"';
		updateData('cron', 'cron_last_run', time(), $filter);

		if (file_exists(_DIR_ . '/domain.php')) {
			$domainkey = $client->domainKey;
			file_put_contents(_DIR_ . '/domain.php', $domainkey);
		}
		else {
			$licenseErrorHtml = str_replace('[INVALID-MESSAGE]', 'The file domain.php that should be located in assets/includes/ folder was not found on this server, the software requires this file to work.<br>Please re-upload the file or contact support if you need assistance', $licenseErrorHtml);
			$licenseErrorHtml = str_replace('[INVALID-TITLE]', 'DOMAIN.PHP FILE NOT FOUND', $licenseErrorHtml);
			echo $licenseErrorHtml;
			exit();
		}

		if ($client->active == 0) {
			updateData('settings', 'setting_val', $client->reason, 'WHERE setting = "licenseError"');
			$licenseErrorHtml = str_replace('[INVALID-MESSAGE]', $client->reason, $licenseErrorHtml);
			$licenseErrorHtml = str_replace('[INVALID-TITLE]', 'INVALID DOMAIN', $licenseErrorHtml);
			echo $licenseErrorHtml;
			exit();
		}
	}
}

$options = ['encrypted' => false, 'cluster' => $sm['plugins']['pusher']['cluster']];
$sm['push'] = new Pusher($sm['plugins']['pusher']['key'], $sm['plugins']['pusher']['secret'], $sm['plugins']['pusher']['id'], $options);
$sm['price'] = sitePrices();
$sm['basic'] = siteAccountsBasic();
$sm['premium'] = siteAccountsPremium();
$sm['config_email'] = configEmail();
$mobile = false;
$sm['mobile'] = 0;
$sm['config']['name'] = $sm['plugins']['settings']['siteName'];
$https = issecure();

if ($sm['plugins']['settings']['forceSSL'] == 'Yes') {
	$site_url = str_replace('http://', 'https://', $site_url);
}
if (($https == 0) && ($sm['plugins']['settings']['forceSSL'] == 'Yes')) {
	header('Location: ' . $site_url);
	exit();
}

if (isset($_SERVER['HTTP_HOST'])) {
	if ((strpos($_SERVER['HTTP_HOST'], 'www') !== false) && (strpos($site_url, 'www') === false)) {
		header('Location: ' . $site_url);
		exit();
	}
	if ((strpos($_SERVER['HTTP_HOST'], 'www') === false) && (strpos($site_url, 'www') !== false)) {
		header('Location: ' . $site_url);
		exit();
	}
}

$check_bar = substr($site_url, -1);

if ($check_bar == '/') {
	$sm['config']['site_url'] = $site_url;
}
else {
	$sm['config']['site_url'] = $site_url . '/';
}

$sm['admin_ajax'] = false;
$sm['version'] = $sm['settings']['currentVersion'];
$sm['config']['like_back'] = siteconfig('like_back');
$sm['config']['visit_back'] = siteconfig('visit_back');
$sm['config']['domain'] = preg_replace('/www\\./i', '', $_SERVER['SERVER_NAME']);
$sm['config']['theme'] = $sm['settings']['desktopTheme'];
$sm['config']['currency'] = $sm['plugins']['settings']['currency'];

if (isset($_GET['preset'])) {
	$checkPreset = checkIfExist('theme_preset', 'preset', $_GET['preset']);

	if ($checkPreset == 0) {
		header('Location: ' . $site_url);
		exit();
	}

	$_SESSION['preset'] = $_GET['preset'];
}

if (!isset($_SESSION['preset'])) {
	$_SESSION['preset'] = $sm['settings']['desktopThemePreset'];
}

if (isset($_GET['landingPreset'])) {
	$checkPreset = checkIfExist('theme_preset', 'preset', $_GET['landingPreset']);

	if ($checkPreset == 0) {
		header('Location: ' . $site_url);
		exit();
	}

	$_SESSION['landingPreset'] = $_GET['landingPreset'];
}

if (!isset($_SESSION['landingPreset'])) {
	$_SESSION['landingPreset'] = $sm['settings']['landingThemePreset'];
}

if (isset($_GET['landing'])) {
	$checkLanding = checkIfExist('config_themes', 'folder', $_GET['landing']);

	if ($checkLanding == 0) {
		header('Location: ' . $site_url);
		exit();
	}

	$landingTheme = $_GET['landing'];
}
else {
	$_SESSION['landingPreset'] = $sm['settings']['landingThemePreset'];
}

if (!isset($_GET['landing'])) {
	$landingTheme = $sm['settings']['landingTheme'];
}

$sm['config']['landing'] = $landingTheme;
$themeFilter = 'WHERE theme ="' . $sm['settings']['desktopTheme'] . '" AND preset = "' . $_SESSION['preset'] . '"';
$sm['theme'] = json_decode(getData('theme_preset', 'theme_settings', $themeFilter), true);
$landingThemeFilter = 'WHERE theme ="' . $landingTheme . '" AND preset = "' . $_SESSION['landingPreset'] . '"';
$sm['landing'] = json_decode(getData('theme_preset', 'theme_settings', $landingThemeFilter), true);
$sm['liveTheme'] = true;
$themeLivePreset = $sm['settings']['desktopThemePreset'];

if ($_SESSION['preset'] != $themeLivePreset) {
	$sm['liveTheme'] = false;
}

$sm['config']['fb_app_ok'] = 1;
$sm['config']['theme_mobile'] = $sm['settings']['mobileTheme'];
$sm['config']['theme_email'] = $sm['settings']['emailTheme'];
$sm['config']['theme_landing'] = $sm['config']['landing'];
$sm['config']['logo'] = $sm['theme']['logo']['val'];
$sm['config']['title'] = siteconfig('title');
$sm['config']['description'] = siteconfig('description');
$sm['config']['keywords'] = siteconfig('keywords');
$sm['config']['lang'] = $sm['plugins']['settings']['defaultLang'];
$sm['config']['email'] = $sm['plugins']['settings']['siteEmail'];
$sm['config']['admin_url'] = $site_url . 'administrator';
$sm['config']['theme_url'] = $site_url . 'themes/' . $sm['config']['theme'];
$sm['config']['theme_url_landing'] = $site_url . 'themes/' . $sm['config']['theme_landing'];
$sm['config']['theme_url_mobile'] = $site_url . 'themes/' . $sm['config']['theme_mobile'];
$sm['config']['theme_url_email'] = $site_url . 'themes/' . $sm['config']['theme_email'];
$sm['config']['ajax_path'] = $site_url . 'requests';
$sm['config']['max_upload'] = getMaximumFileUploadSize();
$sm['genders'] = siteGenders(1);
$sm['interests'] = getSiteInterests();
$sm['lang'] = siteLang($sm['config']['lang']);
$sm['alang'] = applang($sm['config']['lang']);
$sm['elang'] = emaillang($sm['config']['lang']);
$sm['seoLang'] = seolang($sm['config']['lang']);
$sm['landingLang'] = landinglang($sm['config']['lang'], $landingTheme, $_SESSION['landingPreset']);

if (!isset($_SESSION['lang'])) {
	$_SESSION['lang'] = $sm['config']['lang'];
}

$logged = false;
$user = [];
$available_languages = availableLanguages();
$langs = prefered_language($available_languages, $_SERVER['HTTP_ACCEPT_LANGUAGE']);
$lang = key($langs);
if (($lang != '') && ($sm['plugins']['settings']['browserLanguage'] == 'Yes')) {
	$_SESSION['lang'] = checkUserLang($lang);
	$sm['lang'] = siteLang(checkUserLang($lang));
	$sm['alang'] = applang(checkUserLang($lang));
	$sm['elang'] = emaillang(checkUserLang($lang));
	$sm['seoLang'] = seolang(checkUserLang($lang));
	$sm['landingLang'] = landinglang(checkUserLang($lang), $landingTheme, $_SESSION['landingPreset']);
}
else {
	$_SESSION['lang'] = $sm['config']['lang'];
	$sm['lang'] = siteLang($sm['config']['lang']);
	$sm['alang'] = applang($sm['config']['lang']);
	$sm['elang'] = emaillang($sm['config']['lang']);
	$sm['seoLang'] = seolang($sm['config']['lang']);
	$sm['landingLang'] = landinglang($sm['config']['lang'], $landingTheme, $_SESSION['landingPreset']);
}
if (isset($_SESSION['user']) && is_numeric($_SESSION['user']) && (0 < $_SESSION['user'])) {
	setcookie('user', $_SESSION['user'], 2147483647);
	getUserInfo($_SESSION['user'], 0);
	checkUserPremium($_SESSION['user']);
	$sm['user_notifications'] = userNotifications($_SESSION['user']);
	$_SESSION['lang'] = $sm['user']['lang'];
	$sm['lang'] = siteLang($sm['user']['lang']);
	$sm['alang'] = applang($sm['user']['lang']);
	$sm['elang'] = emaillang($sm['user']['lang']);
	$sm['seoLang'] = seolang($sm['user']['lang']);
	$sm['landingLang'] = landinglang($sm['user']['lang'], $landingTheme, $_SESSION['landingPreset']);
	$sm['genders'] = siteGenders($sm['user']['lang']);
	$modPermission = [];

	if (1 <= $sm['user']['admin']) {
		$moderationList = getArray('moderation_list', '', 'moderation ASC');

		foreach ($moderationList as $mod) {
			if ($sm['user']['admin'] == 1) {
				$modPermission[$mod['moderation']] = 'Yes';
			}
			else {
				$modVal = getData('moderators_permission', 'setting_val', 'WHERE setting = "' . $mod['moderation'] . '" AND id = "' . $sm['user']['moderator'] . '"');
				$modPermission[$mod['moderation']] = $modVal;
			}
		}
	}

	$sm['moderator'] = $modPermission;
	$time = time();
	$logged = true;
	$ip = getUserIpAddr();

	if ($sm['user']['ip'] != $ip) {
		$mysqli->query('UPDATE users set ip = \'' . $ip . '\' where id = \'' . $_SESSION['user'] . '\'');
	}
	if (($sm['user']['last_access'] < $time) || ($sm['user']['last_access'] == 0)) {
		$mysqli->query('UPDATE users set last_access = \'' . $time . '\' where id = \'' . $_SESSION['user'] . '\'');
	}
}

$sm['logged'] = $logged;
require_once 'custom/app_core.php';

if (!empty($_GET['lang'])) {
	$slang = secureEncode($_GET['lang']);
	$_SESSION['lang'] = $slang;
	$sm['lang'] = siteLang($_SESSION['lang']);
	$sm['alang'] = applang($_SESSION['lang']);
	$sm['elang'] = emaillang($_SESSION['lang']);
	$sm['genders'] = siteGenders($_SESSION['lang']);
	$sm['seoLang'] = seolang($_SESSION['lang']);
	$sm['landingLang'] = landinglang($_SESSION['lang'], $landingTheme, $_SESSION['landingPreset']);

	if ($logged) {
		$mysqli->query('UPDATE users SET lang = \'' . $slang . '\' WHERE id = \'' . $user_id . '\'');
	}
}

if (!$logged) {
	unset($_SESSION['user']);
	unset($user);
}

?>