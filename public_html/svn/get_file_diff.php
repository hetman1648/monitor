<?php
/*
	AJAX: JSON { ok, diff } or { ok:false, error } for a repo-relative file path.

	Shows the INCOMING change a SVN update would bring (working-copy base -> repo HEAD).

	Strategy:
	  1) Locate the working copy (svn_repo_wc_path resolves /home/vhosts -> /mnt/drive2/vhosts)
	     to read its current (base) revision and the repository root URL.
	  2) Read the repository directly via file:// (it is a local FSFS repo under
	     /mnt/drive2/webclients/{repo}) so we never need svnserve credentials, and diff
	     base..HEAD for the file. Added/deleted files are rendered as +/- blocks.
	  3) Fallbacks: plain local `svn diff` in the WC, then the remote gateway.

	POST/GET: repository, file (e.g. public_html/.htaccess)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");
include_once ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');

function svn_diff_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
$file = GetParam("file");

if ($repository === '' || !strlen($file)) {
	svn_diff_json(array('ok' => false, 'error' => 'Repository and file path are required.'));
}
$file = ltrim(str_replace('\\', '/', trim($file)), '/');
if ($file === '' || strpos($file, '..') !== false) {
	svn_diff_json(array('ok' => false, 'error' => 'Invalid file path.'));
}
if (strpos($repository, '..') !== false || preg_match('#[/\\\\\x00]#', $repository)) {
	svn_diff_json(array('ok' => false, 'error' => 'Invalid repository name.'));
}

putenv('LANG=C.UTF-8');

// Sites hosted off web1: the live working copy (and its deployed BASE revision) is on the
// site's own server. We still read repo HEAD from the LOCAL FSFS repo via file:// below
// (no svnserve auth); here we only fetch the deployed BASE revision from the remote WC over
// SSH (`svn info` is local on that host — no server contact), then the file:// diff runs as
// usual (base_rev -> HEAD = the incoming change an update would bring).
$remote_base_rev = '';
$diff_host = svn_host_for($repository);
if ($diff_host) {
	$wcr = svn_host_wc_dir($repository);
	if ($wcr !== '') {
		$out = array();
		exec(svn_host_ssh($diff_host) . ' ' . escapeshellarg('svn info --non-interactive ' . escapeshellarg($wcr)) . ' 2>/dev/null', $out);
		foreach ($out as $l) { if (preg_match('/^Revision:\s*(\d+)/', $l, $m)) { $remote_base_rev = $m[1]; break; } }
	}
}

function svn_diff_run($cmd) {
	$out = shell_exec($cmd . ' 2>&1');
	return is_string($out) ? $out : '';
}
/** A genuine svn error (not just diff content that happens to contain "svn"). */
function svn_diff_is_fatal($out) {
	$t = trim($out);
	if ($t === '') return false;
	if (preg_match('/^(Index: |--- |\+\+\+ |@@ |diff )/m', $t)) return false;
	return (bool) preg_match('/\bsvn:\s*(warning:\s*)?E\d+/', $t);
}
function svn_diff_is_missing_path($out) {
	return (bool) preg_match('/E160013|E170000|E200009|path not found|was not found|non-existent|does not exist/i', $out);
}
function svn_diff_as_block($file, $content, $old_label, $new_label) {
	$content = rtrim($content, "\n");
	$lines = preg_split('/\r?\n/', $content);
	$sign = $old_label === '(nonexistent)' ? '+' : '-';
	$body = "Index: " . $file . "\n";
	$body .= "===================================================================\n";
	$body .= "--- " . $file . "\t" . $old_label . "\n";
	$body .= "+++ " . $file . "\t" . $new_label . "\n";
	foreach ($lines as $ln) { $body .= $sign . $ln . "\n"; }
	return $body;
}

// --- 1) working copy: base revision + repository root URL ---
$wc = svn_repo_wc_path($repository);
$base_rev = '';
$repo_root = '';
if ($wc !== '') {
	$info = svn_wc_run($wc, 'svn info --non-interactive');
	if (is_string($info)) {
		if (preg_match('/^Revision:\s*(\d+)/m', $info, $m)) $base_rev = $m[1];
		if (preg_match('/^Repository Root:\s*(\S+)/m', $info, $mm)) $repo_root = trim($mm[1]);
	}
}
// For sites hosted off web1 there is no local WC; the deployed BASE revision came from the
// remote WC over SSH above. Use it so the file:// diff shows the real incoming change.
if ($remote_base_rev !== '') $base_rev = $remote_base_rev;

// --- 2) local FSFS repo path readable via file:// (no svnserve auth) ---
function svn_diff_local_repo_path($repo_root, $repository) {
	$candidates = array();
	if ($repo_root !== '' && preg_match('#^[a-z][a-z0-9+\-.]*://[^/]*(/.+)$#i', $repo_root, $m)) {
		$candidates[] = $m[1];
	}
	$candidates[] = '/mnt/drive2/webclients/' . $repository;
	foreach ($candidates as $p) {
		$p = rtrim($p, '/');
		if ($p !== '' && strpos($p, '..') === false && is_file($p . '/format') && is_dir($p . '/db')) {
			return $p;
		}
	}
	return '';
}
$repo_path = svn_diff_local_repo_path($repo_root, $repository);

if ($repo_path !== '') {
	$base_url = 'file://' . $repo_path;
	$file_url = $base_url . '/' . $file;

	$head_rev = '';
	$hi = svn_diff_run('svn info --non-interactive ' . escapeshellarg($base_url));
	if (preg_match('/^Revision:\s*(\d+)/m', $hi, $mh)) $head_rev = $mh[1];
	if ($head_rev === '') $head_rev = 'HEAD';
	if ($base_rev === '') $base_rev = $head_rev;

	$old_peg = escapeshellarg($file_url . '@' . $base_rev);
	$new_peg = escapeshellarg($file_url . '@' . $head_rev);
	$out = svn_diff_run('svn diff --non-interactive ' . $old_peg . ' ' . $new_peg);
	$trim = trim($out);

	if ($trim !== '' && !svn_diff_is_fatal($out)) {
		svn_diff_json(array('ok' => true, 'diff' => $out));
	}

	// base side or head side missing -> incoming add / delete
	if ($trim === '' || svn_diff_is_missing_path($out)) {
		$head_info = svn_diff_run('svn info --non-interactive ' . $new_peg);
		$exists_head = !svn_diff_is_missing_path($head_info) && stripos($head_info, 'Node Kind:') !== false;
		if ($exists_head && stripos($head_info, 'Node Kind: directory') !== false) {
			svn_diff_json(array('ok' => true, 'diff' => "Index: " . $file . "\n(new directory — added on update)\n"));
		}
		if ($exists_head) {
			$cat = svn_diff_run('svn cat --non-interactive ' . $new_peg);
			if (trim($cat) !== '' && !svn_diff_is_fatal($cat)) {
				svn_diff_json(array('ok' => true, 'diff' => svn_diff_as_block($file, $cat, '(nonexistent)', '(revision ' . $head_rev . ')')));
			}
			if (trim($cat) === '') {
				svn_diff_json(array('ok' => true, 'diff' => "Index: " . $file . "\n(new empty file — added on update)\n"));
			}
		} else {
			// not at head -> incoming delete; show base content as removed
			$catb = svn_diff_run('svn cat --non-interactive ' . $old_peg);
			if (trim($catb) !== '' && !svn_diff_is_fatal($catb)) {
				svn_diff_json(array('ok' => true, 'diff' => svn_diff_as_block($file, $catb, '(revision ' . $base_rev . ')', '(deleted on update)')));
			}
		}
	}

	if ($trim !== '' && svn_diff_is_fatal($out) && !svn_diff_is_missing_path($out)) {
		svn_diff_json(array('ok' => false, 'error' => $trim));
	}
	if ($trim === '') {
		svn_diff_json(array('ok' => true, 'diff' => "No differences for this file (working copy is already current).\n"));
	}
}

// --- 3) fallback: plain local svn diff in the WC (local modifications) ---
if ($wc !== '') {
	$out = svn_wc_run($wc, 'svn diff --non-interactive ' . escapeshellarg($file));
	if (is_string($out)) {
		$trim = trim($out);
		if ($trim === '') {
			svn_diff_json(array('ok' => true, 'diff' => "No differences for this file in the working copy.\n"));
		}
		if (stripos($trim, 'not a working copy') === false) {
			if (svn_diff_is_fatal($out)) {
				svn_diff_json(array('ok' => false, 'error' => $trim));
			}
			svn_diff_json(array('ok' => true, 'diff' => $out));
		}
	}
}

// --- 4) last-resort remote gateway (legacy; usually has no diff endpoint) ---
$params = array('username' => $svn_login, 'password' => $svn_password, 'repository' => $repository, 'file' => $file);
$urls = array(
	rtrim($svn_path, '/') . '/web1_filediff.php?' . http_build_query($params, '', '&'),
	rtrim($svn_path, '/') . '/index.php?' . http_build_query(array_merge($params, array('action' => 'filediff')), '', '&'),
);
function svn_diff_normalize_gateway($res) {
	$n = preg_replace('/<br\s*\/?>/i', "\n", $res);
	return trim(strip_tags(html_entity_decode($n, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}
$plain = '';
foreach ($urls as $url) {
	$res = get_page($url);
	$plain = svn_diff_normalize_gateway($res);
	if ($plain === '') continue;
	if (preg_match('/^--- |^\+\+\+ |^Index: |^diff --git |^Binary files /mi', $plain) || strlen($plain) > 120) break;
}
if ($plain !== '' && !preg_match('/^(ERROR:|error:)/i', $plain)) {
	svn_diff_json(array('ok' => true, 'diff' => $plain));
}

svn_diff_json(array(
	'ok' => false,
	'error' => 'Could not produce a diff for ' . $file . ' in ' . $repository . '. No readable SVN working copy or local repository was found, and the remote gateway has no diff endpoint.',
));
