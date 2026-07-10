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

// svn_build_admin_url() + svn_site_admin() now live in svn_repo_support.php (shared with site_admin.php).
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
