<?php
/*
	Client File API — shared config, helpers and auth.
	Included by place.php (publish) and servers.php (domain/server list) so the token, IP allowlist
	and bearer check live in exactly one place.
*/

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

if (!defined('CLIENT_FILE_API_TOKEN')) {
	$env = (function_exists('getenv') && getenv('CLIENT_FILE_API_TOKEN')) ? getenv('CLIENT_FILE_API_TOKEN') : '';
	// Fallback shared secret (used when the env var isn't set). Keep in sync with Copilot's CLIENT_FILE_API_TOKEN.
	define('CLIENT_FILE_API_TOKEN', $env !== '' ? $env : '2d38a810458cf1a688c87085f5c346111a98dc8ba9eb2179c91ce61a5d97155b');
}

// Optional IP allowlist (defence in depth on top of the bearer token). Empty array => skip the check.
// 78.46.105.205 is Copilot's egress (copilot.sayu.co.uk); the 217.160.107.* / loopback entries let
// Monitor's own host call the endpoints for testing.
function cfa_ip_allow() {
	return array(
		'78.46.105.205',
		'127.0.0.1', '::1',
		'217.160.107.24', '217.160.107.180', '217.160.107.211', '217.160.107.219',
	);
}

// Per-site DB credentials, returned in /servers so Copilot can write the article row (va_articles
// etc.) straight into the client database after /place has put the assets in. Keyed by domain (each
// site has its own DB). The MySQL user is granted SELECT/INSERT/UPDATE/DELETE (no DDL) on that DB
// only, from Copilot's egress IP. Password via env override, else the literal set when the grant was
// created. Add a domain here only once its copilot_pub'@'<copilot-ip>' grant exists on that server.
function cfa_db_creds() {
	$web2 = getenv('CFA_DB_WEB2') ? getenv('CFA_DB_WEB2') : 'L9AoMjG7cZlYgsD7lMnKbaX5UrHl';
	return array(
		'puregusto.co.uk' => array(
			'host'     => 'puregusto.co.uk',
			'port'     => 3306,
			'database' => 'puregusto',
			'username' => 'copilot_pub',
			'password' => getenv('CFA_DB_PUREGUSTO') ? getenv('CFA_DB_PUREGUSTO') : 'p0jz0rRtpKMd3C19wgrOCvEBfEB0',
		),
		// web2 sites — one copilot_pub'@'78.46.105.205' user, granted per-DB; reached at web2:3306.
		'richdiamonds.com' => array('host' => 'web2.sayu.co.uk', 'port' => 3306, 'database' => 'richdiamonds', 'username' => 'copilot_pub', 'password' => $web2),
		'watchcentre.com'  => array('host' => 'web2.sayu.co.uk', 'port' => 3306, 'database' => 'watchcentre',  'username' => 'copilot_pub', 'password' => $web2),
		'tressoro.com'     => array('host' => 'web2.sayu.co.uk', 'port' => 3306, 'database' => 'tressoro',     'username' => 'copilot_pub', 'password' => $web2),
	);
}

// The host map (pure functions, no side effects): svn_host_for(), svn_site_host_map(), svn_host_servers()…
require_once dirname(__FILE__) . '/../svn/svn_hosts.php';

header('Content-Type: application/json; charset=utf-8');

function cfa_g($a, $k, $d = '') { return (is_array($a) && isset($a[$k])) ? $a[$k] : $d; }
function cfa_out($arr, $code = 200) {
	http_response_code($code);
	echo json_encode($arr);
	exit;
}
function cfa_fail($msg, $code = 400, $extra = array()) {
	cfa_out(array_merge(array('ok' => false, 'error' => $msg), $extra), $code);
}
function cfa_authz_header() {
	// PHP-FPM frequently hides Authorization; CGIPassAuth (vhost) / the .htaccess copy it through.
	foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $k) {
		if (!empty($_SERVER[$k])) return $_SERVER[$k];
	}
	if (function_exists('apache_request_headers')) {
		foreach (apache_request_headers() as $k => $v) {
			if (strcasecmp($k, 'Authorization') === 0) return $v;
		}
	}
	return '';
}
function cfa_rmtree($dir) {
	if (!is_dir($dir)) { @unlink($dir); return; }
	foreach (scandir($dir) as $e) {
		if ($e === '.' || $e === '..') continue;
		$p = $dir . '/' . $e;
		is_dir($p) ? cfa_rmtree($p) : @unlink($p);
	}
	@rmdir($dir);
}

// IP allowlist (if any) + constant-time bearer-token check. Exits with a JSON error on failure.
function cfa_require_auth() {
	$allow = cfa_ip_allow();
	if (!empty($allow) && !in_array(cfa_g($_SERVER, 'REMOTE_ADDR', ''), $allow, true)) {
		cfa_fail('Not allowed.', 403);
	}
	$authz = cfa_authz_header();
	$token = (stripos($authz, 'Bearer ') === 0) ? trim(substr($authz, 7)) : '';
	if ($token === '' || !hash_equals(CLIENT_FILE_API_TOKEN, $token)) cfa_fail('Unauthorized.', 401);
}
