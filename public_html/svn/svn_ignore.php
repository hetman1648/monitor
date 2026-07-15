<?php
/*
	AJAX (JSON): add an UNTRACKED file to svn:ignore on its parent directory and commit that
	property, so `svn status` stops listing it — for the SVN Updater and for everyone else
	(dev copies, plain svn users). Same recipe as the runtime/compiled cleanup.

	Runs as the working copy's unix owner: web1 via the monitor_runas root wrapper, off-web1
	over SSH (tema has passwordless sudo there) — same dual path as revert_repository.php.

	Safety:
	  - refuses unless the file is genuinely unversioned ("?"); svn:ignore has no effect on a
	    tracked file, so ignoring one would be a silent no-op.
	  - APPENDS to any existing svn:ignore patterns (never clobbers them), preserving order.
	  - commits with --depth empty so ONLY the directory's own property change goes in — never
	    any modified files sitting inside that directory.
	  - if the commit fails, the local propset is reverted, so the WC is not left dirty (a
	    locally-modified dir would show as update-list noise — worse than the original problem).

	@param repository
	@param file        path relative to the WC root, e.g. public_html/js/gdpr-cookie.js

	Returns { ok, repository, file, already, committed, output, error }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function ig_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	ig_json(array('ok' => false, 'error' => 'Invalid repository.'));
}

// WC-root-relative path. Reject absolute paths / traversal / nulls outright — only a leading
// "./" is normalised away (stripping leading "../" instead would silently mangle the path).
$file = trim((string) GetParam("file"));
$file = str_replace('\\', '/', $file);
if (substr($file, 0, 2) === './') $file = substr($file, 2);
if ($file === '' || strpos($file, "\0") !== false || strpos($file, '..') !== false
	|| substr($file, 0, 1) === '/' || !preg_match('#^[A-Za-z0-9._/\- ]+$#', $file)) {
	ig_json(array('ok' => false, 'error' => 'Invalid file path.'));
}

if ($svn_login === '' || $svn_password === '') ig_json(array('ok' => false, 'error' => 'Your SVN login is not set.'));
$auth = '--non-interactive --no-auth-cache --username ' . escapeshellarg($svn_login) . ' --password ' . escapeshellarg($svn_password);

$host = svn_host_for($repository);   // non-null => off-web1
if ($host) {
	$wc = rtrim($host['wc_base'], '/') . '/' . $repository;
} else {
	$wc = svn_repo_wc_path($repository);
	if ($wc === '') ig_json(array('ok' => false, 'error' => 'Working copy not found for ' . $repository . '.'));
}

$dir  = dirname($file);
if ($dir === '' || $dir === '.') $dir = '.';
$base = basename($file);
$msg  = 'ignore ' . $file . ' (via monitor)';

// Built for the site user's shell, run from the WC root.
$inner = "cd " . escapeshellarg($wc) . " || exit 1\n"
	// Only act on genuinely untracked files. Unversioned => "? <path>"; an explicitly-queried
	// already-ignored file => "I <path>"; tracked-and-clean => empty. Anything else (M/A/D/…)
	// means it IS tracked — refuse, since svn:ignore would be a silent no-op on it.
	. "ST=\$(svn status " . escapeshellarg($file) . " 2>&1 | head -1)\n"
	. "case \"\$ST\" in\n"
	. "  '?'*) ;;\n"
	. "  'I'*) echo '>> " . $base . " is already ignored — nothing to do'; exit 0 ;;\n"
	. "  '') echo '>> " . $base . " is already tracked — leaving it alone'; exit 0 ;;\n"
	. "  *) echo \">> refusing: unexpected svn status: \$ST\"; exit 8 ;;\n"
	. "esac\n"
	. "svn info " . escapeshellarg($dir) . " >/dev/null 2>&1 || { echo '>> parent directory " . $dir . " is not versioned — cannot set svn:ignore'; exit 6; }\n"
	. "CUR=\$(svn propget svn:ignore " . escapeshellarg($dir) . " 2>/dev/null)\n"
	. "if printf '%s\\n' \"\$CUR\" | grep -qxF " . escapeshellarg($base) . "; then echo '>> already in svn:ignore'; exit 0; fi\n"
	// Append, preserving existing order; drop blank lines.
	. "TF=\$(mktemp) || exit 1\n"
	. "printf '%s\\n' \"\$CUR\" | sed '/^[[:space:]]*\$/d' > \"\$TF\"\n"
	. "printf '%s\\n' " . escapeshellarg($base) . " >> \"\$TF\"\n"
	. "svn propset svn:ignore -F \"\$TF\" " . escapeshellarg($dir) . " || { rm -f \"\$TF\"; echo '>> propset failed'; exit 7; }\n"
	. "rm -f \"\$TF\"\n"
	// --depth empty: commit ONLY this directory's property change, not anything inside it.
	. "OUT=\$(svn commit --depth empty " . escapeshellarg($dir) . " -m " . escapeshellarg($msg) . " " . $auth . " 2>&1); rc=\$?\n"
	. "echo \"\$OUT\"\n"
	. "if [ \$rc -ne 0 ]; then echo '>> commit failed — reverting the local property change'; svn revert --depth empty " . escapeshellarg($dir) . " >/dev/null 2>&1; exit \$rc; fi\n"
	. "echo \">> ignored " . $base . " in " . $dir . "\"\n";

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
	if ($user === '' || $user === 'root') ig_json(array('ok' => false, 'error' => 'Could not determine the unix user for ' . $repository . '.'));
	$cmd = 'sudo -n /usr/local/bin/monitor_runas ' . escapeshellarg($user);
	$desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$proc = proc_open($cmd, $desc, $pipes);
	if (!is_resource($proc)) ig_json(array('ok' => false, 'error' => 'Could not start the ignore helper.'));
	fwrite($pipes[0], $inner); fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$rc = proc_close($proc);
	if (trim($output) === '' && trim($err) !== '') $output = trim($err);
}
if (function_exists('ensure_utf8')) $output = ensure_utf8($output);

$already   = (bool) preg_match('/already in svn:ignore|already ignored|already tracked/i', $output);
$committed = (bool) preg_match('/Committed revision\s+\d+/i', $output);
$done      = ($already || $committed);

ig_json(array(
	'ok' => $done, 'repository' => $repository, 'file' => $file,
	'already' => $already, 'committed' => $committed, 'output' => trim($output),
	'error' => $done ? '' : 'Could not ignore the file — see the output.',
));
