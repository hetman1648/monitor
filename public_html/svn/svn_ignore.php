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

	untrack=1 extends this to files that ARE tracked (generated artefacts that were committed by
	mistake — e.g. a nightly-rebuilt feed, or a committed file since replaced by a symlink, which
	shows as "~ Type changed"). svn:ignore alone does nothing to a tracked file, so it first runs
	`svn rm --keep-local` (stops tracking it, leaves the file/symlink untouched on the live site)
	and commits that together with the property. Same recipe as the runtime/compiled cleanup.
	NB: the commit removes it from the REPO, so other working copies / dev copies drop their copy
	on their next update — which is the point for generated files, but worth stating in the UI.

	@param repository
	@param file        path relative to the WC root, e.g. public_html/js/gdpr-cookie.js
	@param untrack     1 = the file is tracked: svn rm --keep-local it first, then ignore
	@param pattern     optional glob to ignore INSTEAD of the exact filename, applied to the
	                   directory of `file` — e.g. "*.css" or "*_nbn.css" for a folder full of
	                   generated backups. svn:ignore patterns are per-directory, so it must not
	                   contain a slash. Versioned files are never affected by svn:ignore, so a
	                   broad mask can't accidentally untrack anything.

	Returns { ok, repository, file, pattern, already, committed, output, error }
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

// Optional glob mask: ignore a pattern in $file's directory rather than just this one file.
// svn:ignore patterns are per-directory, so a slash is invalid.
$pattern = trim((string) GetParam("pattern"));
if ($pattern !== '') {
	if (strpos($pattern, '/') !== false || strpos($pattern, "\0") !== false || strpos($pattern, '..') !== false
		|| strlen($pattern) > 100 || !preg_match('#^[A-Za-z0-9._*?\[\]\-]+$#', $pattern)) {
		ig_json(array('ok' => false, 'error' => 'Invalid pattern.'));
	}
	$base = $pattern;
}
$is_mask = ($pattern !== '');
// Untracking only makes sense for one specific tracked file, not for a glob.
$untrack = (!$is_mask && GetParam("untrack")) ? true : false;
$msg = $is_mask
	? ('ignore ' . $pattern . ' in ' . $dir . ' (via monitor)')
	: (($untrack ? 'stop tracking + ignore ' : 'ignore ') . $file . ' (via monitor)');

// Built for the site user's shell, run from the WC root.
$inner = "cd " . escapeshellarg($wc) . " || exit 1\n";

// Single-file mode: only act on genuinely untracked files. Unversioned => "? <path>"; an
// explicitly-queried already-ignored file => "I <path>"; tracked-and-clean => empty. Anything
// else (M/A/D/…) means it IS tracked — refuse, since svn:ignore is a no-op on tracked files.
// A mask isn't a single file, so this check doesn't apply to it (and svn:ignore still can't
// affect whatever is versioned in that directory).
$inner .= "TRACKED=0\n";
if (!$is_mask) {
	// '' = tracked & clean, 'M' = edited on live, '~' = type changed (obstructed, e.g. the
	// committed file was replaced by a symlink), '!' = missing on live. All of those ARE
	// tracked, so svn:ignore alone would be a silent no-op — they need svn rm --keep-local
	// first, which only untrack=1 authorises.
	$tracked_action = $untrack
		? "TRACKED=1"
		: "echo '>> " . $base . " is tracked in SVN — svn:ignore has no effect on it. Use Stop tracking to untrack + ignore.'; exit 0";
	$inner .= "ST=\$(svn status " . escapeshellarg($file) . " 2>&1 | head -1)\n"
		. "case \"\$ST\" in\n"
		. "  '?'*) ;;\n"
		. "  'I'*) echo '>> " . $base . " is already ignored — nothing to do'; exit 0 ;;\n"
		. "  ''|'M'*|'~'*|'!'*) " . $tracked_action . " ;;\n"
		. "  svn:*) echo \">> cannot read svn status for " . $file . ": \$ST\"; exit 8 ;;\n"
		. "  *) echo \">> refusing: unexpected svn status: \$ST\"; exit 8 ;;\n"
		. "esac\n";
}

$inner .= "svn info " . escapeshellarg($dir) . " >/dev/null 2>&1 || { echo '>> parent directory " . $dir . " is not versioned — cannot set svn:ignore'; exit 6; }\n"
	// SVN refuses to commit a property on a directory whose node is behind HEAD
	// ("E160028: Directory '/x' is out of date"). That is the NORMAL case here: these live
	// working copies routinely lag the repo (exactly what the updater exists to show), and any
	// earlier ignore commit in a child directory also bumps this directory's node. So sync just
	// this directory node first. --depth empty touches ONLY the directory's own metadata — never
	// its children or files — so it cannot deploy anything to the live site, and it does not
	// change the working copy's sticky depth.
	. "UP=\$(svn update --depth empty " . escapeshellarg($dir) . " " . $auth . " 2>&1) || { echo \"\$UP\"; echo '>> could not sync " . $dir . " before setting the property'; exit 4; }\n"
	. "CUR=\$(svn propget svn:ignore " . escapeshellarg($dir) . " 2>/dev/null)\n"
	// Already listed? Nothing more to do — UNLESS we still have to untrack the file (svn:ignore
	// can be present yet useless while the file is still versioned).
	. "NEEDPROP=1\n"
	. "if printf '%s\\n' \"\$CUR\" | grep -qxF " . escapeshellarg($base) . "; then\n"
	. "  if [ \"\$TRACKED\" != 1 ]; then echo '>> already in svn:ignore'; exit 0; fi\n"
	. "  NEEDPROP=0\n"
	. "fi\n"
	// Untrack first: --keep-local leaves the file/symlink exactly as it is on the live site and
	// only stops SVN tracking it.
	. "if [ \"\$TRACKED\" = 1 ]; then\n"
	. "  svn rm --keep-local --force " . escapeshellarg($file) . " || { echo '>> svn rm --keep-local failed'; exit 7; }\n"
	. "fi\n"
	// Append to svn:ignore, preserving existing order; drop blank lines.
	. "if [ \"\$NEEDPROP\" = 1 ]; then\n"
	. "  TF=\$(mktemp) || exit 1\n"
	. "  printf '%s\\n' \"\$CUR\" | sed '/^[[:space:]]*\$/d' > \"\$TF\"\n"
	. "  printf '%s\\n' " . escapeshellarg($base) . " >> \"\$TF\"\n"
	. "  svn propset svn:ignore -F \"\$TF\" " . escapeshellarg($dir) . " || { rm -f \"\$TF\"; echo '>> propset failed'; exit 7; }\n"
	. "  rm -f \"\$TF\"\n"
	. "fi\n"
	// --depth empty: commit ONLY the directory's own property change and (when untracking) the
	// file's own deletion — never anything else sitting modified inside that directory.
	. "if [ \"\$TRACKED\" = 1 ]; then\n"
	. "  OUT=\$(svn commit --depth empty " . escapeshellarg($dir) . " " . escapeshellarg($file) . " -m " . escapeshellarg($msg) . " " . $auth . " 2>&1); rc=\$?\n"
	. "else\n"
	. "  OUT=\$(svn commit --depth empty " . escapeshellarg($dir) . " -m " . escapeshellarg($msg) . " " . $auth . " 2>&1); rc=\$?\n"
	. "fi\n"
	. "echo \"\$OUT\"\n"
	. "if [ \$rc -ne 0 ]; then\n"
	. "  echo '>> commit failed — undoing the local changes'\n"
	. "  svn revert --depth empty " . escapeshellarg($dir) . " >/dev/null 2>&1\n"
	. "  [ \"\$TRACKED\" = 1 ] && svn revert " . escapeshellarg($file) . " >/dev/null 2>&1\n"
	. "  exit \$rc\n"
	. "fi\n"
	. "if [ \"\$TRACKED\" = 1 ]; then echo \">> stopped tracking " . $base . " (kept on disk) and ignored it in " . $dir . "\"; "
	. "else echo \">> ignored " . $base . " in " . $dir . "\"; fi\n";

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

$already   = (bool) preg_match('/already in svn:ignore|already ignored/i', $output);
$committed = (bool) preg_match('/Committed revision\s+\d+/i', $output);
$blocked   = (bool) preg_match('/is tracked in SVN/i', $output);   // needs untrack=1
$done      = (($already || $committed) && !$blocked);

ig_json(array(
	'ok' => $done, 'repository' => $repository, 'file' => $file, 'pattern' => $pattern,
	'untracked' => $untrack, 'already' => $already, 'committed' => $committed, 'output' => trim($output),
	'error' => $done ? '' : ($blocked
		? 'That file is tracked in SVN — ignoring it needs Stop tracking.'
		: 'Could not ignore the ' . ($is_mask ? 'pattern' : 'file') . ' — see the output.'),
));
