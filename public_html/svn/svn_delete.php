<?php
/*
	AJAX (JSON): DELETE a file from a site — destructive.

	Two cases, decided by the file's real svn status on the server (never by what the client
	claims), because the consequences differ completely:

	  untracked ("?" / ignored "I") — the file exists only on the live disk, so this is a plain
	      rm. There is NO copy in SVN: it is PERMANENT and cannot be undone.
	  tracked                        — "svn rm" + commit: removed from the repository and from
	      the live site, and it disappears from every other working copy / dev copy on their next
	      update. Recoverable from SVN history (svn copy from the previous revision).

	Runs as the working copy's unix owner — web1 via the monitor_runas root wrapper, off-web1
	over SSH — same dual path as svn_ignore.php / revert_repository.php.

	Safety:
	  - refuses directories (this action is for files only)
	  - path is WC-root-relative; absolute paths / traversal rejected
	  - the parent dir is synced (--depth empty: metadata only, no files) before the commit,
	    otherwise svn refuses with "E160028 out of date" — see svn_ignore.php
	  - if the commit fails the svn rm is reverted, so the file is restored and the WC left clean

	@param repository
	@param file        path relative to the WC root, e.g. public_html/3.php

	Returns { ok, repository, file, mode:'disk'|'svn', committed, output, error }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function del_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	del_json(array('ok' => false, 'error' => 'Invalid repository.'));
}

$file = trim((string) GetParam("file"));
$file = str_replace('\\', '/', $file);
if (substr($file, 0, 2) === './') $file = substr($file, 2);
if ($file === '' || strpos($file, "\0") !== false || strpos($file, '..') !== false
	|| substr($file, 0, 1) === '/' || !preg_match('#^[A-Za-z0-9._/\- ]+$#', $file)) {
	del_json(array('ok' => false, 'error' => 'Invalid file path.'));
}

if ($svn_login === '' || $svn_password === '') del_json(array('ok' => false, 'error' => 'Your SVN login is not set.'));
$auth = '--non-interactive --no-auth-cache --username ' . escapeshellarg($svn_login) . ' --password ' . escapeshellarg($svn_password);

$host = svn_host_for($repository);   // non-null => off-web1
if ($host) {
	$wc = rtrim($host['wc_base'], '/') . '/' . $repository;
} else {
	$wc = svn_repo_wc_path($repository);
	if ($wc === '') del_json(array('ok' => false, 'error' => 'Working copy not found for ' . $repository . '.'));
}

// Library WCs (Common/Common8) are root-owned — there is no site user to run as, and deleting
// from a shared library deserves a human on the server anyway. (Ignore DOES work for them —
// svn_ignore.php commits the property from a scratch checkout.)
if (!$host && svn_is_library_repo($repository) && @fileowner($wc) === 0) {
	del_json(array('ok' => false, 'error' => 'Delete is not available for the shared library repositories — their working copies are root-owned.'));
}

$dir = dirname($file);
if ($dir === '' || $dir === '.') $dir = '.';
$msg = 'delete ' . $file . ' (via monitor)';
$f_q = escapeshellarg($file);

$inner = "cd " . escapeshellarg($wc) . " || exit 1\n"
	// Files only — never let this loose on a directory tree.
	. "if [ -d " . $f_q . " ] && [ ! -L " . $f_q . " ]; then echo '>> refusing: " . $file . " is a directory'; exit 9; fi\n"
	. "ST=\$(svn status " . $f_q . " 2>&1 | head -1)\n"
	. "case \"\$ST\" in\n"
	// Not in SVN (or ignored): nothing to commit — just remove it from disk. Unrecoverable.
	. "  '?'*|'I'*)\n"
	. "     if [ ! -e " . $f_q . " ] && [ ! -L " . $f_q . " ]; then echo '>> " . $file . " does not exist on disk'; exit 2; fi\n"
	. "     rm -f -- " . $f_q . " || { echo '>> could not remove the file from disk'; exit 3; }\n"
	. "     echo '>> DELETED from disk (was not in SVN — no copy to restore)'\n"
	. "     ;;\n"
	// svn couldn't even read it (missing path, not a working copy, …) — say so plainly rather
	// than falling through and failing later inside svn rm.
	. "  svn:*)\n"
	. "     echo \">> cannot read svn status for " . $file . ": \$ST\"; exit 8\n"
	. "     ;;\n"
	// Tracked: remove from SVN and disk, and commit it.
	. "  *)\n"
	. "     UP=\$(svn update --depth empty " . escapeshellarg($dir) . " " . $auth . " 2>&1) || { echo \"\$UP\"; echo '>> could not sync " . $dir . " before committing'; exit 4; }\n"
	. "     svn rm --force " . $f_q . " || { echo '>> svn rm failed'; exit 5; }\n"
	. "     OUT=\$(svn commit " . $f_q . " -m " . escapeshellarg($msg) . " " . $auth . " 2>&1); rc=\$?\n"
	. "     echo \"\$OUT\"\n"
	. "     if [ \$rc -ne 0 ]; then echo '>> commit failed — restoring the file'; svn revert " . $f_q . " >/dev/null 2>&1; exit \$rc; fi\n"
	. "     echo '>> REMOVED from SVN and from the live site'\n"
	. "     ;;\n"
	. "esac\n";

// Run it as the working copy's owner.
$output = ''; $rc = 0;
if ($host) {
	$remote = 'wc=' . escapeshellarg($wc) . '; owner=$(stat -c %U "$wc" 2>/dev/null); '
		. 'if [ -z "$owner" ] || [ "$owner" = root ]; then echo "bad or missing working copy owner"; exit 5; fi; '
		. 'sudo -u "$owner" timeout --signal=TERM --kill-after=10s 120s /bin/bash -lc ' . escapeshellarg($inner) . ' 2>&1';
	$out = array();
	@exec(svn_host_ssh($host) . ' ' . escapeshellarg($remote), $out, $rc);
	$output = implode("\n", $out);
} else {
	$uid = @fileowner($wc); $user = '';
	if ($uid !== false && function_exists('posix_getpwuid')) { $pw = @posix_getpwuid($uid); if ($pw && !empty($pw['name'])) $user = $pw['name']; }
	if ($user === '' || $user === 'root') del_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));
	$cmd = 'sudo -n /usr/local/bin/monitor_runas ' . escapeshellarg($user);
	$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$proc = proc_open($cmd, $desc, $pipes);
	if (!is_resource($proc)) del_json(array('ok' => false, 'error' => 'Could not start the delete helper.'));
	fwrite($pipes[0], $inner); fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$rc = proc_close($proc);
	if (trim($output) === '' && trim($err) !== '') $output = trim($err);
}
if (function_exists('ensure_utf8')) $output = ensure_utf8($output);

$disk      = (bool) preg_match('/DELETED from disk/i', $output);
$committed = (bool) preg_match('/Committed revision\s+\d+/i', $output);
$done      = ($disk || $committed);

del_json(array(
	'ok' => $done, 'repository' => $repository, 'file' => $file,
	'mode' => $disk ? 'disk' : 'svn', 'committed' => $committed, 'output' => trim($output),
	'error' => $done ? '' : 'Could not delete the file — see the output.',
));
