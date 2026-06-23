<?php
/*
	AJAX (JSON): read / write the crontab of a site's unix user, for the SVN Updater.
	Writes go through a root-owned, tightly-scoped sudo wrapper (/usr/local/bin/monitor_crontab)
	that only manages crontabs of vhost site users.

	@param action: get | save
	@param repository
	@param crontab   (for save: full crontab text)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');

function cron_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
$action     = GetParam("action");
if ($repository === '' || preg_match('#[/\\\\\x00]#', $repository) || strpos($repository, '..') !== false) {
	cron_json(array('ok' => false, 'error' => 'Invalid repository.'));
}

// Sites hosted off web1: read/write the per-site user's crontab on its own server over SSH.
// The target user comes ONLY from the host map (a per-site account) and can never be root.
$host = svn_host_for($repository);
if ($host) {
	$cuser = svn_host_cron_user($repository);
	if ($cuser === '' || $cuser === 'root' || !preg_match('/^[a-z][a-z0-9_-]*$/i', $cuser)) {
		cron_json(array('ok' => false, 'error' => 'Cron user for ' . $repository . ' is not configured on ' . $host['ssh_host'] . '.'));
	}
	$ssh   = svn_host_ssh($host);
	$readq = 'sudo crontab -u ' . escapeshellarg($cuser) . ' -l';

	if ($action === 'get') {
		$out = array();
		exec($ssh . ' ' . escapeshellarg($readq) . ' 2>/dev/null', $out);
		cron_json(array('ok' => true, 'user' => $cuser, 'crontab' => implode("\n", $out)));
	}

	if ($action === 'save') {
		$crontab = str_replace("\r", "", (string) GetParam("crontab"));
		if (strlen($crontab) > 65536) cron_json(array('ok' => false, 'error' => 'Crontab is too large.'));
		if (trim($crontab) === '') {
			exec($ssh . ' ' . escapeshellarg('sudo crontab -u ' . escapeshellarg($cuser) . ' -r') . ' 2>/dev/null');
		} else {
			if (substr($crontab, -1) !== "\n") $crontab .= "\n";
			$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
			$proc = proc_open($ssh . ' ' . escapeshellarg('sudo crontab -u ' . escapeshellarg($cuser) . ' -'), $desc, $pipes);
			if (!is_resource($proc)) cron_json(array('ok' => false, 'error' => 'Could not start remote crontab.'));
			fwrite($pipes[0], $crontab); fclose($pipes[0]);
			$o = stream_get_contents($pipes[1]); fclose($pipes[1]);
			$e = stream_get_contents($pipes[2]); fclose($pipes[2]);
			$rc = proc_close($proc);
			if ($rc !== 0) {
				$msg = trim($e) !== '' ? trim($e) : trim($o);
				cron_json(array('ok' => false, 'user' => $cuser, 'error' => ($msg !== '' ? $msg : 'crontab was rejected.')));
			}
		}
		$out2 = array();
		exec($ssh . ' ' . escapeshellarg($readq) . ' 2>/dev/null', $out2);
		cron_json(array('ok' => true, 'user' => $cuser, 'crontab' => implode("\n", $out2)));
	}

	cron_json(array('ok' => false, 'error' => 'Unknown action.'));
}

// Resolve the site's unix user from the site directory owner (authoritative).
function cron_site_user($repository) {
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
$user = cron_site_user($repository);
if ($user === '') {
	cron_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));
}

define('CRON_WRAPPER', '/usr/local/bin/monitor_crontab');

/** Run "sudo -n <wrapper> <op> <user>" optionally piping $stdin. Returns [rc, stdout, stderr]. */
function cron_wrapper_run($op, $user, $stdin = null) {
	$cmd = 'sudo -n ' . CRON_WRAPPER . ' ' . escapeshellarg($op) . ' ' . escapeshellarg($user);
	$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$proc = proc_open($cmd, $desc, $pipes);
	if (!is_resource($proc)) {
		return array(127, '', 'Could not start crontab helper.');
	}
	if ($stdin !== null) {
		fwrite($pipes[0], $stdin);
	}
	fclose($pipes[0]);
	$out = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$rc = proc_close($proc);
	return array($rc, $out, $err);
}

if ($action === 'get') {
	list($rc, $out, $err) = cron_wrapper_run('get', $user);
	if ($rc !== 0) {
		cron_json(array('ok' => false, 'user' => $user, 'error' => 'Could not read crontab: ' . trim($err)));
	}
	cron_json(array('ok' => true, 'user' => $user, 'crontab' => $out));
}

if ($action === 'save') {
	$crontab = (string) GetParam("crontab");
	$crontab = str_replace("\r", "", $crontab);
	if (strlen($crontab) > 65536) {
		cron_json(array('ok' => false, 'error' => 'Crontab is too large.'));
	}
	if ($crontab !== '' && substr($crontab, -1) !== "\n") {
		$crontab .= "\n";
	}
	if (trim($crontab) === '') {
		// empty -> remove the crontab entirely
		list($rc, $out, $err) = cron_wrapper_run('remove', $user);
	} else {
		list($rc, $out, $err) = cron_wrapper_run('set', $user, $crontab);
	}
	if ($rc !== 0) {
		$msg = trim($err) !== '' ? trim($err) : trim($out);
		cron_json(array('ok' => false, 'user' => $user, 'error' => ($msg !== '' ? $msg : 'crontab was rejected.')));
	}
	list($rc2, $out2) = cron_wrapper_run('get', $user);
	cron_json(array('ok' => true, 'user' => $user, 'crontab' => ($rc2 === 0 ? $out2 : $crontab)));
}

cron_json(array('ok' => false, 'error' => 'Unknown action.'));
