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

header('Content-Type: application/json; charset=utf-8');
function vs_json($a) { echo json_encode($a); exit; }

$file = trim((string) GetParam("file"));
$line = (int) GetParam("line");
if ($file === '' || strpos($file, "\0") !== false) {
	vs_json(array('ok' => false, 'error' => 'No file specified.'));
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
$allowed_ext = array('php','phtml','inc','tpl','twig','html','htm','js','jsx','ts','vue','css','scss','less','xml','json','txt','sql','ini','conf','md','sh','yml','yaml');
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
$MAXFULL = 4000;
$WINDOW  = 400;
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
