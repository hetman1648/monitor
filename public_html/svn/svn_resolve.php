<?php
/*
	AJAX (JSON): resolve a CONFLICTED file in a live working copy.

	A conflict ("C") happens when `svn update` finds the live file changed on disk AND changed in
	the incoming revision, and can't merge them automatically. svn writes conflict markers into the
	file (breaking the live page) plus .mine/.rNNN sidecar copies, and blocks further updates on
	that path until it's resolved. This picks a side and clears that state:

	  side=theirs — take SVN's version (the committed revision). The live file becomes exactly what
	      is in the repo; the conflicting live edit is discarded. This is the normal "deploy what's
	      committed" outcome, and it leaves the path clean/up to date.
	  side=mine   — keep the live version (the file as it was on disk before the update). The
	      incoming change is dropped for this file, which then reads as a local edit (M) against the
	      repo — commit it separately if it should go into SVN.

	`svn resolve` is a purely LOCAL working-copy operation (no server round-trip, no commit), so it
	needs no SVN auth — only to run as the working copy's unix owner, same dual path as
	svn_delete.php: web1 via the monitor_runas root wrapper, off-web1 over SSH.

	@param repository
	@param file        path relative to the WC root, e.g. public_html/.htaccess
	@param side        theirs | mine

	Returns { ok, repository, file, side, output, error }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function res_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	res_json(array('ok' => false, 'error' => 'Invalid repository.'));
}

$file = trim((string) GetParam("file"));
$file = str_replace('\\', '/', $file);
if (substr($file, 0, 2) === './') $file = substr($file, 2);
if ($file === '' || strpos($file, "\0") !== false || strpos($file, '..') !== false
	|| substr($file, 0, 1) === '/' || !preg_match('#^[A-Za-z0-9._/\- ]+$#', $file)) {
	res_json(array('ok' => false, 'error' => 'Invalid file path.'));
}

$side = trim((string) GetParam("side"));
$accept = ($side === 'theirs') ? 'theirs-full' : (($side === 'mine') ? 'mine-full' : '');
if ($accept === '') res_json(array('ok' => false, 'error' => 'Invalid side.'));

$host = svn_host_for($repository);   // non-null => off-web1
if ($host) {
	$wc = rtrim($host['wc_base'], '/') . '/' . $repository;
} else {
	$wc = svn_repo_wc_path($repository);
	if ($wc === '') res_json(array('ok' => false, 'error' => 'Working copy not found for ' . $repository . '.'));
}

// Library WCs (Common/Common8) are root-owned — no site user to run as. A conflict there is rare
// (they're read-only-ish) and best resolved by a human on the box; refuse plainly, like delete.
if (!$host && svn_is_library_repo($repository) && @fileowner($wc) === 0) {
	res_json(array('ok' => false, 'error' => 'Resolve is not available for the shared library repositories — their working copies are root-owned.'));
}

$f_q = escapeshellarg($file);
$inner = "cd " . escapeshellarg($wc) . " || exit 1\n"
	. "ST=\$(svn status " . $f_q . " 2>&1 | head -1)\n"
	. "case \"\$ST\" in\n"
	// Only a genuinely conflicted file (col 1 = 'C' for a text conflict; 'C' in the props column
	// shows as 'XC' etc., caught by the wildcard). Anything else: say so rather than silently
	// 'resolving' — an empty status means it's already resolved.
	. "  C*|?C*) ;;\n"
	. "  svn:*) echo \">> cannot read svn status for " . $file . ": \$ST\"; exit 8 ;;\n"
	. "  *) echo \">> " . $file . " is not in conflict (status: '\$ST') — already resolved?\"; exit 2 ;;\n"
	. "esac\n"
	. "OUT=\$(svn resolve --accept " . $accept . " " . $f_q . " 2>&1); rc=\$?\n"
	. "echo \"\$OUT\"\n"
	. "if [ \$rc -ne 0 ]; then echo '>> resolve failed'; exit \$rc; fi\n"
	. "echo \">> resolved " . $file . " (" . $accept . ")\"\n";

// Run it as the working copy's owner (same channel as svn_delete.php).
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
	if ($user === '' || $user === 'root') res_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));
	$cmd = 'sudo -n /usr/local/bin/monitor_runas ' . escapeshellarg($user);
	$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$proc = proc_open($cmd, $desc, $pipes);
	if (!is_resource($proc)) res_json(array('ok' => false, 'error' => 'Could not start the resolve helper.'));
	fwrite($pipes[0], $inner); fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$rc = proc_close($proc);
	if (trim($output) === '' && trim($err) !== '') $output = trim($err);
}
if (function_exists('ensure_utf8')) $output = ensure_utf8($output);

// svn's success wording varies by version: 1.8-era "Resolved conflicted state of 'X'", 1.14
// "Merge conflicts in 'X' marked as resolved." — accept either, plus our own trailer.
$done = (bool) preg_match('/Resolved conflicted state|marked as resolved|>> resolved /i', $output);
res_json(array(
	'ok' => $done, 'repository' => $repository, 'file' => $file, 'side' => $side,
	'output' => trim($output),
	'error' => $done ? '' : 'Could not resolve the conflict — see the output.',
));
