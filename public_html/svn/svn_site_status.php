<?php
/*
	AJAX (JSON): status of a single SVN repository for the multi-site SVN Updater.
	@param: repository
	Returns: { ok, repository, status:'update'|'current'|'error', behind, headRev,
	           lastBy, lastAt, errorMsg, files:[{kind,status,status_badge,version,file_path,file_name,rel_path}] }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");

header('Content-Type: application/json; charset=utf-8');

function svn_status_json($a) {
	echo json_encode($a);
	exit;
}

$repository = trim((string) GetParam("repository"));
if ($repository === '') {
	svn_status_json(array('ok' => false, 'error' => 'No repository specified.'));
}

// Build a one-click admin auto-login URL (mirrors create_client.php's logic).
function svn_build_admin_url($web_address, $admin_web_address, $login, $password) {
	$web = str_ireplace(array('http://', 'https://'), '', (string) $web_address);
	$adm = str_ireplace(array('http://', 'https://'), '', (string) $admin_web_address);
	$login = (string) $login;
	if ($adm === '' || $login === '') return '';
	$base = $adm;
	if (stripos($base, 'http') === 0) {
		$base = preg_replace('#^http://#i', 'https://', $base);
	} else {
		$fs = strpos($base, '/');
		if ($fs !== false && strpos(substr($base, 0, $fs), '.') !== false) {
			$base = 'https://' . $base;
		} else {
			$base = 'https://' . rtrim($web, '/') . '/' . ltrim($base, '/');
		}
	}
	return rtrim($base, '/') . '/admin_login.php?operation=login&login=' . urlencode($login) . '&password=' . urlencode((string) $password);
}

// Resolve the Monitor client + admin-login URL for this domain (host matched precisely).
function svn_site_admin(&$db, $repository) {
	$d = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $repository));
	if ($d === '') return array('clientId' => 0, 'adminUrl' => '');
	$db->query("SELECT client_id,web_address,admin_web_address,admin_web_site_login,admin_web_site_password FROM clients_sites WHERE web_address LIKE " . ToSQL('%' . $d . '%', "text") . " ORDER BY client_id LIMIT 50");
	$rows = array();
	while ($db->next_record()) {
		$rows[] = array(
			'cid'   => (int) $db->f("client_id"),
			'wa'    => (string) $db->f("web_address"),
			'awa'   => (string) $db->f("admin_web_address"),
			'login' => (string) $db->f("admin_web_site_login"),
			'pass'  => (string) $db->f("admin_web_site_password"),
		);
	}
	$tail = '.' . $d; $matched = null;
	foreach ($rows as $r) {
		$wa = strtolower(trim($r['wa']));
		$host = parse_url($wa, PHP_URL_HOST);
		if (!$host) { $host = parse_url('http://' . ltrim($wa, '/'), PHP_URL_HOST); }
		$host = strtolower((string) $host);
		$m = ($host === $d || $host === 'www.' . $d || (strlen($host) > strlen($tail) && substr($host, -strlen($tail)) === $tail));
		if (!$m && preg_match('#[/~]' . preg_quote($d, '#') . '(?:/|$)#', $wa)) { $m = true; }
		if ($m) { $matched = $r; break; }
	}
	if (!$matched) return array('clientId' => 0, 'adminUrl' => '');
	$cid = $matched['cid'];
	$url = svn_build_admin_url($matched['wa'], $matched['awa'], $matched['login'], $matched['pass']);
	if ($url === '') {
		// fall back to another site of the same client that has admin credentials
		$db->query("SELECT web_address,admin_web_address,admin_web_site_login,admin_web_site_password FROM clients_sites WHERE client_id=" . $cid . " AND admin_web_address<>'' AND admin_web_site_login<>'' LIMIT 5");
		while ($db->next_record()) {
			$u = svn_build_admin_url($db->f("web_address"), $db->f("admin_web_address"), $db->f("admin_web_site_login"), $db->f("admin_web_site_password"));
			if ($u !== '') { $url = $u; break; }
		}
	}
	return array('clientId' => $cid, 'adminUrl' => $url);
}
$admin = svn_site_admin($db, $repository);
$client_id = $admin['clientId'];
$admin_url = $admin['adminUrl'];

$command = "index.php?action=showupdates&username=" . $svn_login . "&password=" . $svn_password . "&repository=" . $repository;
$res = get_page($svn_path . $command);

// last update (who / when) from history table — used for both ok and error responses
$last_by = '';
$last_at = '';
$last_uid = 0;
$sql = "SELECT date_added,user_id FROM svn_updates WHERE repository=" . ToSQL($repository, "text") . " ORDER BY date_added DESC LIMIT 1";
$db->query($sql);
if ($db->next_record()) {
	$last_at = svn_relative_time($db->f("date_added"));
	$last_uid = (int) $db->f("user_id");
}
if ($last_uid) {
	$db->query("SELECT first_name,last_name FROM users WHERE user_id=" . $last_uid);
	if ($db->next_record()) {
		$last_by = trim($db->f("first_name") . " " . $db->f("last_name"));
	}
}

if (strpos($res, 'Server response is: +OK') === false) {
	$msg = trim((string) $res);
	if ($msg === '') {
		$msg = 'No response from SVN gateway.';
	}
	// keep it short for the UI
	$msg = svn_history_truncate_message(preg_replace('/\s+/', ' ', $msg), 240);
	svn_status_json(array(
		'ok' => true, 'repository' => $repository, 'status' => 'error',
		'behind' => 0, 'headRev' => '', 'lastBy' => $last_by, 'lastAt' => $last_at,
		'errorMsg' => $msg, 'files' => array(), 'clientId' => $client_id, 'adminUrl' => $admin_url,
	));
}

$files = svn_status_parse_files($res);
$behind = count($files);

$head_rev = 0;
foreach ($files as $f) {
	if (ctype_digit((string) $f['version']) && (int) $f['version'] > $head_rev) {
		$head_rev = (int) $f['version'];
	}
}

svn_status_json(array(
	'ok'        => true,
	'repository'=> $repository,
	'status'    => $behind > 0 ? 'update' : 'current',
	'behind'    => $behind,
	'headRev'   => $head_rev ? (string) $head_rev : '',
	'lastBy'    => $last_by,
	'lastAt'    => $last_at,
	'errorMsg'  => '',
	'files'     => $files,
	'clientId'  => $client_id,
	'adminUrl'  => $admin_url,
));
