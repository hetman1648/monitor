<?php
/*
	AJAX (JSON): run a single cron command as a site's unix user and return its output, for the
	SVN Updater's cron-jobs panel. The command is executed (combined stdout+stderr) with a hard
	timeout and shown back to the developer — useful for testing a cron job without waiting for it
	to fire.

	Security: identical model to cron_manage.php. The target user is resolved server-side from the
	site directory owner (never user-supplied) and the command runs only as that vhost site user via
	a root-owned, tightly-scoped sudo wrapper (/usr/local/bin/monitor_runas) that refuses any system
	account (uid<1000 / non-vhost home). Off-web1 sites run on their own server over SSH as the
	per-site cron user from the host map.

	@param action: run
	@param repository
	@param command   the command to execute (the part of a crontab line after the schedule)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');

function cron_run_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
$action     = GetParam("action");
$command    = (string) GetParam("command");

if ($repository === '' || preg_match('#[/\\\\\x00]#', $repository) || strpos($repository, '..') !== false) {
	cron_run_json(array('ok' => false, 'error' => 'Invalid repository.'));
}
if ($action !== 'run') {
	cron_run_json(array('ok' => false, 'error' => 'Unknown action.'));
}
$command = str_replace("\r", "", $command);
$command = trim($command);
if ($command === '') {
	cron_run_json(array('ok' => false, 'error' => 'No command to run.'));
}
if (strlen($command) > 8192) {
	cron_run_json(array('ok' => false, 'error' => 'Command is too long.'));
}
// Single command line only — defend against smuggling a second line into the run.
if (strpos($command, "\n") !== false) {
	cron_run_json(array('ok' => false, 'error' => 'Only a single command line can be run.'));
}

// Sites hosted off web1: run on the site's own server over SSH as the per-site cron user
// (from the host map — a per-site account that can never be root).
$host = svn_host_for($repository);
if ($host) {
	$cuser = svn_host_cron_user($repository);
	if ($cuser === '' || $cuser === 'root' || !preg_match('/^[a-z][a-z0-9_-]*$/i', $cuser)) {
		cron_run_json(array('ok' => false, 'error' => 'Cron user for ' . $repository . ' is not configured on ' . $host['ssh_host'] . '.'));
	}
	$ssh    = svn_host_ssh($host);
	$inner  = 'cd ~ 2>/dev/null; ' . $command;
	$remote = 'sudo -u ' . escapeshellarg($cuser) . ' timeout --signal=TERM --kill-after=10s 120s /bin/bash -lc ' . escapeshellarg($inner) . ' 2>&1';
	$out = array(); $rc = 0;
	@exec($ssh . ' ' . escapeshellarg($remote), $out, $rc);
	$output = implode("\n", $out);
	if (function_exists('ensure_utf8')) $output = ensure_utf8($output);
	cron_run_json(array('ok' => true, 'host' => $host['ssh_host'], 'user' => $cuser, 'rc' => (int) $rc,
		'timedout' => ($rc === 124), 'output' => $output));
}

// web1: resolve the site's unix user from the directory owner (authoritative, not user-supplied).
function cron_run_site_user($repository) {
	$cands = array('/home/vhosts/' . $repository, '/mnt/drive2/vhosts/' . $repository, '/mnt/drive2/webclients/' . $repository);
	foreach ($cands as $d) {
		if (@is_dir($d)) {
			$uid = @fileowner($d);
			if ($uid !== false && function_exists('posix_getpwuid')) {
				$pw = @posix_getpwuid($uid);
				if ($pw && !empty($pw['name'])) return $pw['name'];
			}
		}
	}
	return '';
}
$user = cron_run_site_user($repository);
if ($user === '') {
	cron_run_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));
}

// Run via the root-owned wrapper; the command goes in on stdin (never the argv).
$cmd = 'sudo -n /usr/local/bin/monitor_runas ' . escapeshellarg($user);
$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
$proc = proc_open($cmd, $desc, $pipes);
if (!is_resource($proc)) {
	cron_run_json(array('ok' => false, 'user' => $user, 'error' => 'Could not start the run helper.'));
}
fwrite($pipes[0], $command);
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]); fclose($pipes[1]);
$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
$rc  = proc_close($proc);

// The wrapper merges the command's stderr into stdout; $err only carries wrapper-level
// rejections (bad user etc.). Surface those as an error.
if (trim($out) === '' && trim($err) !== '' && $rc !== 0 && $rc !== 124) {
	cron_run_json(array('ok' => false, 'user' => $user, 'rc' => (int) $rc, 'error' => trim($err)));
}
$output = $out;
if (function_exists('ensure_utf8')) $output = ensure_utf8($output);
cron_run_json(array('ok' => true, 'user' => $user, 'rc' => (int) $rc,
	'timedout' => ($rc === 124), 'output' => $output));
