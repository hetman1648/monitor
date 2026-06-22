<?php
/*
	AJAX (JSON): the diff of a single SVN revision (svn diff -c REV) read from the local FSFS repo via file://.
	@param repository
	@param rev   revision number
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_repo_support.php");

header('Content-Type: application/json; charset=utf-8');
function rd_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
$rev = trim((string) GetParam("rev"));
if ($repository === '' || $rev === '' || !ctype_digit($rev)) {
	rd_json(array('ok' => false, 'error' => 'Repository and revision are required.'));
}

$repo_fs = svn_repo_fs_path($repository);
if ($repo_fs === '') {
	rd_json(array('ok' => false, 'error' => 'No local repository found for ' . $repository . '.'));
}

putenv('LANG=C.UTF-8');
$url = 'file://' . $repo_fs;
$out = shell_exec('svn diff -c ' . escapeshellarg($rev) . ' --non-interactive ' . escapeshellarg($url) . ' 2>&1');
if (!is_string($out)) {
	rd_json(array('ok' => false, 'error' => 'Could not run svn diff.'));
}
$trim = trim($out);
if ($trim === '') {
	rd_json(array('ok' => true, 'diff' => "No textual changes in r" . $rev . " (property-only or empty revision).\n"));
}
if (preg_match('/\bsvn:\s*E\d+/', $trim) && !preg_match('/^(Index: |--- |\+\+\+ |@@ )/m', $trim)) {
	rd_json(array('ok' => false, 'error' => $trim));
}
// cap very large revision diffs
$max = 600000;
if (strlen($out) > $max) {
	$out = substr($out, 0, $max) . "\n\n… diff truncated (revision is very large).\n";
}
if (function_exists('ensure_utf8')) {
	$out = ensure_utf8($out);
}
rd_json(array('ok' => true, 'diff' => $out));
