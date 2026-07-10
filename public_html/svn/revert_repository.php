<?php
/*
	AJAX (JSON): revert a site to a specific revision, two ways, run as the site's unix user
	(web1 via the monitor_runas root wrapper; off-web1 over SSH as the working-copy owner):

	  method=update  (default) — "svn update -r REV": roll the LIVE working copy back to REV.
	                             Fast, no new commit; the WC is left pinned at REV (behind HEAD)
	                             until the next normal deploy re-advances it.
	  method=merge             — reverse-merge + commit: svn update; svn merge -r HEAD:REV .;
	                             svn commit. Records the rollback as a NEW revision (WC stays at
	                             HEAD). Refuses if the WC has local modifications; aborts (and
	                             reverts the merge) if the reverse-merge hits conflicts.

	@param repository
	@param revision   (positive integer)
	@param method     update | merge

	Returns { ok, method, revision, updatedTo, committed, output }
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
$method = (GetParam("method") === 'merge') ? 'merge' : 'update';

if ($svn_login === '' || $svn_password === '') rev_json(array('ok' => false, 'error' => 'Your SVN login is not set.'));
$auth = '--non-interactive --no-auth-cache --username ' . escapeshellarg($svn_login) . ' --password ' . escapeshellarg($svn_password);

$host = svn_host_for($repository);     // non-null => off-web1
$wc_local = '';
if ($host) {
	$wc = rtrim($host['wc_base'], '/') . '/' . $repository;
} else {
	$wc = svn_repo_wc_path($repository);
	if ($wc === '') rev_json(array('ok' => false, 'error' => 'Working copy not found for ' . $repository . '.'));
	$wc_local = $wc;
}

// Build the command run inside the site user's shell.
if ($method === 'merge') {
	$msg = 'revert to r' . $rev . ' (reverse-merge via monitor)';
	// Reverse-merge HEAD..REV, then commit ONLY the files the merge touched — so unrelated local
	// modifications already in the working copy aren't swept into the revert commit. Aborts (and
	// reverts the merge) on conflicts; no-ops cleanly when HEAD already equals REV.
	$inner = "cd " . escapeshellarg($wc) . " || exit 1\n"
		. "svn update " . $auth . " >/dev/null 2>&1\n"
		. "MO=\$(svn merge -r HEAD:" . $rev . " . " . $auth . " 2>&1) || { echo \"\$MO\"; echo '>> merge failed'; exit 7; }\n"
		. "echo \"\$MO\"\n"
		. "if printf '%s\\n' \"\$MO\" | grep -qiE 'conflict'; then echo '>> reverse-merge hit conflicts — reverting, nothing committed'; svn revert -R . >/dev/null 2>&1; exit 9; fi\n"
		. "TARGETS=\$(printf '%s\\n' \"\$MO\" | sed -nE 's/^[ADUGR] +(.+)\$/\\1/p')\n"
		. "if [ -z \"\$TARGETS\" ]; then echo '>> No differences between HEAD and r" . $rev . " — nothing to revert.'; exit 0; fi\n"
		. "TF=\$(mktemp) && printf '%s\\n' \"\$TARGETS\" > \"\$TF\"\n"
		. "svn commit --targets \"\$TF\" -m " . escapeshellarg($msg) . " " . $auth . "; rc=\$?\n"
		. "rm -f \"\$TF\"; exit \$rc\n";
} else {
	$inner = 'cd ' . escapeshellarg($wc) . ' && svn update -r ' . $rev . ' ' . $auth;
}

// Run it as the site user.
$output = ''; $rc = 0;
if ($host) {
	$remote = 'wc=' . escapeshellarg($wc) . '; owner=$(stat -c %U "$wc" 2>/dev/null); '
		. 'if [ -z "$owner" ] || [ "$owner" = root ]; then echo "bad or missing working copy owner"; exit 5; fi; '
		. 'sudo -u "$owner" timeout --signal=TERM --kill-after=10s 300s /bin/bash -lc ' . escapeshellarg($inner) . ' 2>&1';
	$out = array();
	@exec(svn_host_ssh($host) . ' ' . escapeshellarg($remote), $out, $rc);
	$output = implode("\n", $out);
} else {
	$uid = @fileowner($wc); $user = '';
	if ($uid !== false && function_exists('posix_getpwuid')) { $pw = @posix_getpwuid($uid); if ($pw && !empty($pw['name'])) $user = $pw['name']; }
	if ($user === '' || $user === 'root') rev_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));
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

// Success + what the live site is now at.
$committed = (preg_match('/Committed revision\s+(\d+)/i', $output, $cm)) ? $cm[1] : '';
$updatedTo = svn_parse_revision_from_gateway_response($output);
$blocked   = (bool) preg_match('/reverse-merge hit conflicts|merge failed|bad or missing|svn:\s*(warning:\s*)?E\d+/i', $output);
$noop      = false;
if ($method === 'merge') {
	$noop  = !$blocked && $committed === '' && (bool) preg_match('/nothing to revert|Nothing to commit/i', $output);
	$done  = !$blocked && ($committed !== '' || $noop);
	$nowAt = $committed;
} else {
	$done = !$blocked && (bool) preg_match('/Updated to revision|At revision/i', $output);
	$nowAt = ($updatedTo !== '') ? $updatedTo : (string) $rev;
}

if ($done && !$noop) {
	// Log the rollback in the deploy log so History reflects where the live site now is.
	$logrev = ($nowAt !== '') ? $nowAt : (string) $rev;
	$msg = ($method === 'merge') ? ('reverse-merge revert to r' . $rev) : ('reverted to r' . $rev);
	if ($wc_local !== '' && $method === 'update') { $m2 = svn_wc_log_message_for_revision($wc_local, (string) $rev); if ($m2 !== '') $msg = 'revert to r' . $rev . ': ' . $m2; }
	$repo_sql = ToSQL($repository, "text");
	if (svn_updates_has_revision_columns($db)) {
		$db->query("INSERT INTO svn_updates (user_id,date_added,repository,revision,commit_message) VALUES ("
			. (int) $user_id . ",NOW()," . $repo_sql . "," . ToSQL($logrev, "text") . "," . ToSQL($msg, "text") . ")");
	} else {
		$db->query("INSERT INTO svn_updates (user_id,date_added,repository) VALUES (" . (int) $user_id . ",NOW()," . $repo_sql . ")");
	}
}

rev_json(array('ok' => $done, 'method' => $method, 'revision' => $rev, 'updatedTo' => $updatedTo,
	'committed' => $committed, 'output' => $output, 'error' => $done ? '' : 'The revert did not complete — see the output.'));
