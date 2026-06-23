<?php
/*
	AJAX (JSON): return the source of a deployed site file so it can be shown with a line highlighted.
	Sandboxed: only files under the vhost code trees, only known code extensions, size-capped.

	@param file   absolute path (as seen in error.log, e.g. /mnt/drive2/vhosts/<site>/public_html/header.php)
	@param line   optional 1-based line to centre on (used to window very large files)
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function vs_json($a) { echo json_encode($a); exit; }

$file = trim((string) GetParam("file"));
$line = (int) GetParam("line");
if ($file === '' || strpos($file, "\0") !== false) {
	vs_json(array('ok' => false, 'error' => 'No file specified.'));
}

$allowed_ext = array('php','phtml','inc','tpl','twig','html','htm','js','jsx','ts','vue','css','scss','less','xml','json','txt','sql','ini','conf','md','sh','yml','yaml');
$MAXFULL = 4000;
$WINDOW  = 400;

// Off-web1 sites: the file lives on the site's own server — read it there over SSH.
$hp = svn_host_for_path($file);
if ($hp) {
	$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
	if (!in_array($ext, $allowed_ext, true)) {
		vs_json(array('ok' => false, 'error' => 'This file type cannot be viewed (' . $ext . ').'));
	}
	$ssh = svn_host_ssh($hp['host']);
	$fq  = escapeshellarg($file);
	// size + line count in one round-trip
	$meta = array();
	exec($ssh . ' ' . escapeshellarg('sudo stat -c %s ' . $fq . ' 2>/dev/null; echo @@@; sudo wc -l < ' . $fq . ' 2>/dev/null'), $meta);
	$blob = implode("\n", $meta);
	$mp = explode('@@@', $blob);
	$size  = isset($mp[0]) && ctype_digit(trim($mp[0])) ? (int) trim($mp[0]) : -1;
	$total = isset($mp[1]) && ctype_digit(trim($mp[1])) ? (int) trim($mp[1]) + 1 : 0; // wc -l counts newlines
	if ($size < 0) {
		vs_json(array('ok' => false, 'error' => 'File not found on ' . $hp['host']['ssh_host'] . '.'));
	}
	if ($size > 5 * 1024 * 1024) {
		vs_json(array('ok' => false, 'error' => 'File is too large to view (' . round($size / 1048576, 1) . ' MB).'));
	}
	$start = 1; $from = 1; $to = ($total > 0 ? $total : $MAXFULL);
	if ($total > $MAXFULL && $line > 0) {
		$from = max(1, $line - $WINDOW);
		$to   = min($total, $line + $WINDOW);
		$start = $from;
	} else {
		$to = min($total > 0 ? $total : $MAXFULL, $MAXFULL);
	}
	$body = array();
	exec($ssh . ' ' . escapeshellarg('sudo sed -n ' . escapeshellarg($from . ',' . $to . 'p') . ' ' . $fq), $body);
	$content = implode("\n", $body);
	if (function_exists('ensure_utf8')) $content = ensure_utf8($content);
	vs_json(array('ok' => true, 'file' => $file, 'line' => $line, 'start' => $start, 'total' => $total, 'content' => $content));
}

$real = realpath($file);
if ($real === false || !is_file($real)) {
	vs_json(array('ok' => false, 'error' => 'File not found.'));
}

$allowed_bases = array('/mnt/drive2/vhosts', '/home/vhosts', '/mnt/drive2/webclients');
$ok_base = false;
foreach ($allowed_bases as $b) {
	$br = realpath($b);
	if ($br !== false && strpos($real, $br . '/') === 0) { $ok_base = true; break; }
}
if (!$ok_base) {
	vs_json(array('ok' => false, 'error' => 'File is outside the allowed directories.'));
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext, true)) {
	vs_json(array('ok' => false, 'error' => 'This file type cannot be viewed (' . $ext . ').'));
}

$size = @filesize($real);
if ($size !== false && $size > 5 * 1024 * 1024) {
	vs_json(array('ok' => false, 'error' => 'File is too large to view (' . round($size / 1048576, 1) . ' MB).'));
}

$lines = @file($real, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
	vs_json(array('ok' => false, 'error' => 'Could not read the file.'));
}
$total = count($lines);

// Window very large files around the target line.
$start = 1;
if ($total > $MAXFULL && $line > 0) {
	$from = max(1, $line - $WINDOW);
	$to   = min($total, $line + $WINDOW);
	$lines = array_slice($lines, $from - 1, $to - $from + 1);
	$start = $from;
}

$content = implode("\n", $lines);
if (function_exists('ensure_utf8')) {
	$content = ensure_utf8($content);
}

vs_json(array('ok' => true, 'file' => $real, 'line' => $line, 'start' => $start, 'total' => $total, 'content' => $content));
