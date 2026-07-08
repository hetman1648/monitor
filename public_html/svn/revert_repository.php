<?php
/*
	AJAX (JSON): roll a site's live working copy back to a specific revision — "svn update -r REV"
	run as the site's unix user (web1 via the monitor_runas root wrapper; off-web1 over SSH as the
	working-copy owner). Newer commits stay in SVN and can be re-deployed later.

	@param repository
	@param revision   (positive integer)

	Returns { ok, revision, updatedTo, output }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function rev_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || preg_match('#[/\\\\\x00]#', $repository) || strpos($repository, '..') !== false) {
	rev_json(array('ok' => false, 'error' => 'Invalid repository.'));
}
$rev = (int) GetParam("revision");
if ($rev <= 0) rev_json(array('ok' => false, 'error' => 'Invalid revision.'));

if ($svn_login === '' || $svn_password === '') {
	rev_json(array('ok' => false, 'error' => 'Your SVN login is not set.'));
}
$svnargs = '--non-interactive --no-auth-cache --username ' . escapeshellarg($svn_login) . ' --password ' . escapeshellarg($svn_password);

$host = svn_host_for($repository);     // non-null => off-web1
$output = ''; $rc = 0; $wc_local = '';

if ($host) {
	// off-web1: SSH to the site's server, run svn update as the working-copy owner (never root).
	$wc    = rtrim($host['wc_base'], '/') . '/' . $repository;
	$inner = 'cd ' . escapeshellarg($wc) . ' && svn update -r ' . $rev . ' ' . $svnargs;
	$remote = 'wc=' . escapeshellarg($wc) . '; owner=$(stat -c %U "$wc" 2>/dev/null); '
		. 'if [ -z "$owner" ] || [ "$owner" = root ]; then echo "bad or missing working copy owner"; exit 5; fi; '
		. 'sudo -u "$owner" timeout --signal=TERM --kill-after=10s 180s /bin/bash -lc ' . escapeshellarg($inner) . ' 2>&1';
	$out = array();
	@exec(svn_host_ssh($host) . ' ' . escapeshellarg($remote), $out, $rc);
	$output = implode("\n", $out);
} else {
	// web1: run as the site's unix user (WC dir owner) via the root-owned monitor_runas wrapper.
	$wc = svn_repo_wc_path($repository);
	if ($wc === '') rev_json(array('ok' => false, 'error' => 'Working copy not found for ' . $repository . '.'));
	$wc_local = $wc;
	$uid = @fileowner($wc); $user = '';
	if ($uid !== false && function_exists('posix_getpwuid')) { $pw = @posix_getpwuid($uid); if ($pw && !empty($pw['name'])) $user = $pw['name']; }
	if ($user === '' || $user === 'root') rev_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));

	$inner = 'cd ' . escapeshellarg($wc) . ' && svn update -r ' . $rev . ' ' . $svnargs;
	$cmd = 'sudo -n /usr/local/bin/monitor_runas ' . escapeshellarg($user);
	$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$proc = proc_open($cmd, $desc, $pipes);
	if (!is_resource($proc)) rev_json(array('ok' => false, 'error' => 'Could not start the revert helper.'));
	fwrite($pipes[0], $inner); fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$rc = proc_close($proc);
	if (trim($output) === '' && trim($err) !== '') $output = trim($err);
}

if (function_exists('ensure_utf8')) $output = ensure_utf8($output);
$updatedTo = svn_parse_revision_from_gateway_response($output);
$done = ($updatedTo !== '') || (bool) preg_match('/Updated to revision|At revision/i', $output);

if ($done) {
	// Record the rollback in the deploy log (revision = target rev) so the History "deployed" markers
	// reflect that the live site is now at $rev. Matches update_repository.php's logging.
	$msg = 'reverted to r' . $rev;
	if ($wc_local !== '') { $m2 = svn_wc_log_message_for_revision($wc_local, (string) $rev); if ($m2 !== '') $msg = 'revert to r' . $rev . ': ' . $m2; }
	$repo_sql = ToSQL($repository, "text");
	if (svn_updates_has_revision_columns($db)) {
		$db->query("INSERT INTO svn_updates (user_id,date_added,repository,revision,commit_message) VALUES ("
			. (int) $user_id . ",NOW()," . $repo_sql . "," . ToSQL((string) $rev, "text") . "," . ToSQL($msg, "text") . ")");
	} else {
		$db->query("INSERT INTO svn_updates (user_id,date_added,repository) VALUES (" . (int) $user_id . ",NOW()," . $repo_sql . ")");
	}
}

rev_json(array('ok' => $done, 'revision' => $rev, 'updatedTo' => $updatedTo, 'output' => $output,
	'error' => $done ? '' : 'svn update did not report success — see the output.'));
