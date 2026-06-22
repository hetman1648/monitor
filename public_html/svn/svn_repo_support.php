<?php
/**
 * Shared helpers for SVN monitor: working copy path, HEAD revision + log line, DB column detection.
 */

function svn_repo_wc_path($repository) {
	$repository = trim((string) $repository);
	if ($repository === '' || strpos($repository, '..') !== false || preg_match('#[/\\\\\x00]#', $repository)) {
		return '';
	}
	$bases = array('/home/vhosts', '/mnt/drive2/webclients');
	foreach ($bases as $base) {
		$base = rtrim($base, '/');
		$candidate = $base . '/' . $repository;
		$wc = realpath($candidate);
		$base_real = realpath($base);
		if ($wc === false || $base_real === false || !is_dir($wc)) {
			continue;
		}
		if (strpos($wc, $base_real) !== 0) {
			continue;
		}
		return $wc;
	}
	return '';
}

/**
 * Extract working copy revision from web1 checkout/update HTTP response body.
 */
function svn_parse_revision_from_gateway_response($res) {
	if (!is_string($res) || $res === '') {
		return '';
	}
	$body = $res;
	$patterns = array(
		'/Updated to revision\s+(\d+)/i',
		'/At revision\s+(\d+)/i',
		'/Checked out revision\s+(\d+)/i',
		'/Last merged revision.*?\b(\d+)\b/is',
	);
	$best = '';
	foreach ($patterns as $p) {
		if (preg_match_all($p, $body, $mm)) {
			$cand = end($mm[1]);
			if (is_string($cand) && ctype_digit($cand)) {
				$best = $cand;
			}
		}
	}
	return $best;
}

function svn_wc_run($wc_path, $cmd) {
	if ($wc_path === '' || !is_dir($wc_path)) {
		return null;
	}
	$real = realpath($wc_path);
	if ($real === false) {
		return null;
	}
	putenv('LANG=C.UTF-8');
	$old = getcwd();
	if (!@chdir($real)) {
		return null;
	}
	$out = shell_exec($cmd . ' 2>/dev/null');
	if ($old !== false) {
		@chdir($old);
	}
	return $out;
}

/**
 * Log message for a single revision (working copy must be on web1 or monitor where WC exists).
 */
function svn_wc_log_message_for_revision($wc_path, $rev) {
	$rev = trim((string) $rev);
	if ($rev === '' || !ctype_digit($rev)) {
		return '';
	}
	$log = svn_wc_run($wc_path, 'svn log --non-interactive -r ' . escapeshellarg($rev) . ' -l 1');
	if (!is_string($log) || $log === '') {
		return '';
	}
	if (preg_match('/^-{10,}\nr\d+\s+\|[^\n]+\n\n(.+?)(?:\n-{10,}|\z)/s', $log, $lm)) {
		return trim($lm[1]);
	}
	return '';
}

/**
 * Map revision => log message from recent history (one svn log --xml call).
 */
function svn_wc_log_messages_map($wc_path, $limit = 400) {
	$limit = max(1, min(500, (int) $limit));
	if ($wc_path === '' || !is_dir($wc_path)) {
		return array();
	}
	$xml = svn_wc_run($wc_path, 'svn log --non-interactive -l ' . $limit . ' --xml');
	if (!is_string($xml) || strpos($xml, '<logentry') === false) {
		return array();
	}
	libxml_use_internal_errors(true);
	$sx = @simplexml_load_string($xml);
	if (!$sx) {
		return array();
	}
	$map = array();
	$entries = $sx->xpath('//*[local-name()="logentry"]');
	if (!is_array($entries)) {
		return array();
	}
	foreach ($entries as $e) {
		$rev = (string) $e['revision'];
		$msg = '';
		$mn = $e->xpath('*[local-name()="msg"]');
		if ($mn && isset($mn[0])) {
			$msg = trim((string) $mn[0]);
		}
		if ($rev !== '' && ctype_digit($rev)) {
			$map[$rev] = $msg;
		}
	}
	return $map;
}

/**
 * Locate the local FSFS repository directory for a repository (readable via file://, no svnserve auth).
 * Tries the repo root from the working copy's "svn info", then the /mnt/drive2/webclients convention.
 */
function svn_repo_fs_path($repository) {
	$repository = trim((string) $repository);
	if ($repository === '' || preg_match('#[/\\\\\x00]#', $repository) || strpos($repository, '..') !== false) {
		return '';
	}
	$cands = array();
	$wc = svn_repo_wc_path($repository);
	if ($wc !== '') {
		$info = svn_wc_run($wc, 'svn info --non-interactive');
		if (is_string($info) && preg_match('/^Repository Root:\s*(\S+)/m', $info, $m)) {
			if (preg_match('#^[a-z][a-z0-9+\-.]*://[^/]*(/.+)$#i', $m[1], $mm)) {
				$cands[] = $mm[1];
			}
		}
	}
	$cands[] = '/mnt/drive2/webclients/' . $repository;
	foreach ($cands as $p) {
		$p = rtrim($p, '/');
		if ($p !== '' && strpos($p, '..') === false && is_file($p . '/format') && is_dir($p . '/db')) {
			return $p;
		}
	}
	return '';
}

/**
 * Recent commits from a local FSFS repo (file://), with author, date, message and changed paths.
 * @return array of array{revision,author,date,msg,files:[{action,kind,path}]}
 */
function svn_repo_recent_commits($repo_fs, $limit = 50) {
	$limit = max(1, min(100, (int) $limit));
	if ($repo_fs === '' || !is_dir($repo_fs)) {
		return array();
	}
	$url = 'file://' . $repo_fs;
	putenv('LANG=C.UTF-8');
	$xml = shell_exec('svn log -v --xml -l ' . $limit . ' --non-interactive ' . escapeshellarg($url) . ' 2>/dev/null');
	if (!is_string($xml) || strpos($xml, '<logentry') === false) {
		return array();
	}
	libxml_use_internal_errors(true);
	$sx = @simplexml_load_string($xml);
	if (!$sx) {
		return array();
	}
	$out = array();
	$entries = $sx->xpath('//*[local-name()="logentry"]');
	if (!is_array($entries)) {
		return array();
	}
	foreach ($entries as $e) {
		$rev = (string) $e['revision'];
		$author = ''; $date = ''; $msg = ''; $files = array();
		$an = $e->xpath('*[local-name()="author"]'); if ($an && isset($an[0])) $author = (string) $an[0];
		$dn = $e->xpath('*[local-name()="date"]');   if ($dn && isset($dn[0])) $date = (string) $dn[0];
		$mn = $e->xpath('*[local-name()="msg"]');    if ($mn && isset($mn[0])) $msg = trim((string) $mn[0]);
		$pn = $e->xpath('*[local-name()="paths"]/*[local-name()="path"]');
		if (is_array($pn)) {
			foreach ($pn as $p) {
				$files[] = array(
					'action' => (string) $p['action'],
					'kind'   => (string) $p['kind'],
					'path'   => ltrim((string) $p, '/'),
				);
			}
		}
		$out[] = array('revision' => $rev, 'author' => $author, 'date' => $date, 'msg' => $msg, 'files' => $files);
	}
	return $out;
}

/**
 * @return array{0:string,1:string} revision (digits only), commit message body
 */
function svn_wc_head_revision_and_message($wc_path) {
	if ($wc_path === '' || !is_dir($wc_path)) {
		return array('', '');
	}
	$info = svn_wc_run($wc_path, 'svn info --non-interactive --show-item revision');
	$rev = '';
	if (is_string($info)) {
		$rev = trim($info);
	}
	if ($rev === '' || !ctype_digit($rev)) {
		$info = svn_wc_run($wc_path, 'svn info --non-interactive');
		if (is_string($info) && preg_match('/^Revision:\s*(\d+)/m', $info, $m)) {
			$rev = $m[1];
		}
	}
	if ($rev === '') {
		return array('', '');
	}
	$msg = svn_wc_log_message_for_revision($wc_path, $rev);
	return array($rev, $msg);
}

function svn_updates_has_revision_columns(&$db) {
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}
	$cached = false;
	if (!isset($db) || !is_object($db) || !method_exists($db, 'query')) {
		return false;
	}
	$db->query('SHOW COLUMNS FROM svn_updates');
	$cols = array();
	while ($db->next_record()) {
		$cols[] = strtolower((string) $db->f('Field'));
	}
	$cached = in_array('revision', $cols, true) && in_array('commit_message', $cols, true);
	return $cached;
}

/**
 * Human-readable labels + stable CSS slug for each SVN "show updates" change code.
 */
function svn_status_kind_maps() {
	return array(
		// "svn status --show-updates" runs on the live working copy, so the letter codes
		// describe the LIVE SITE's state and "*" means out of date (an incoming change).
		'labels' => array(
			"*" => "Out of date",
			"A" => "Added on live",
			"D" => "Removed on live",
			"M" => "Edited on live",
			"C" => "Conflict",
			"?" => "Not in SVN",
			"!" => "Missing on live",
			"L" => "Locked",
			"R" => "Replaced",
			"~" => "Type changed",
		),
		'tips' => array(
			"*" => "A newer version exists in SVN — updating will bring it to the live site.",
			"A" => "Scheduled for addition in the live working copy (not committed yet).",
			"D" => "Scheduled for deletion in the live working copy (not committed yet).",
			"M" => "Changed directly on the live site, so it differs from SVN.",
			"C" => "Merge conflict — needs manual resolution.",
			"?" => "Exists on the live site but is not tracked in SVN.",
			"!" => "Tracked in SVN but missing from the live site — updating restores it.",
			"L" => "The working copy item is locked.",
			"R" => "The item was replaced (deleted and re-added).",
			"~" => "The item changed type (e.g. file \xE2\x86\x94 directory).",
		),
		'slugs' => array(
			"*" => "not-on-server",
			"A" => "to-add",
			"D" => "to-delete",
			"M" => "modified",
			"C" => "conflict",
			"?" => "not-in-svn",
			"!" => "missing",
			"L" => "locked",
			"R" => "replaced",
			"~" => "type-change",
		),
	);
}

/**
 * Parse one line from SVN "show updates" output into [status_code, revision, full_path] or null to skip.
 * Mirrors svn_parse_update_line() in get_recent_files.php.
 */
function svn_status_parse_line($row) {
	$row = trim(preg_replace('/\s+/', ' ', $row));
	if ($row === '') {
		return null;
	}
	$skip_res = array(
		'/status against revision/i', '/^summary of conflicts/i', '/^text conflicts:/i',
		'/^tree conflicts:/i', '/^merged conflicts:/i', '/^resolved conflicts:/i',
		'/^conflict details/i', '/^subversion is /i', '/^-+$/',
		'/^\d+\s+text conflicts/i', '/^\d+\s+tree conflicts/i',
	);
	foreach ($skip_res as $re) {
		if (preg_match($re, $row)) {
			return null;
		}
	}
	$parts = explode(' ', $row);
	$n = count($parts);
	if ($n < 3) {
		return null;
	}
	$path_start = false;
	for ($i = 0; $i < $n; $i++) {
		if (strpos($parts[$i], '/') !== false) { $path_start = $i; break; }
	}
	if ($path_start === false) {
		$last = $parts[$n - 1];
		if (preg_match('/\.[a-z0-9]{1,8}$/i', $last)) { $path_start = $n - 1; }
	}
	if ($path_start === false || $path_start < 2) {
		return null;
	}
	$fullpath = implode(' ', array_slice($parts, $path_start));
	$path_ok = (strpos($fullpath, '/') !== false) || preg_match('/\.[a-z0-9]{1,8}$/i', $fullpath);
	if (!$path_ok) {
		return null;
	}
	$before = array_slice($parts, 0, $path_start);
	$svn_first_letters = 'ACDMR?!L*~';
	$status_raw = $before[0];
	$c0 = strtoupper(substr(trim($status_raw), 0, 1));
	if (strpos($svn_first_letters, $c0) === false && isset($before[1])) {
		$status_raw = $before[1];
	}
	$status = strlen($status_raw) > 1 ? substr(trim($status_raw), 0, 1) : $status_raw;
	$rev = '-';
	for ($j = count($before) - 1; $j >= 1; $j--) {
		if ($before[$j] === '*' || ctype_digit($before[$j])) { $rev = $before[$j]; break; }
	}
	return array($status, $rev, $fullpath);
}

/**
 * Turn a raw gateway "show updates" response into a normalised list of changed files.
 * @return array of array{kind,status,status_badge,version,file_name,file_path,rel_path}
 */
function svn_status_parse_files($res) {
	$maps = svn_status_kind_maps();
	$files = array();
	if (strpos($res, 'Server response is: +OK') === false) {
		return $files;
	}
	$lines = explode("Server response is: +OK Updates:", $res);
	if (count($lines) < 2) {
		return $files;
	}
	$rows = explode("\n", $lines[1]);
	foreach ($rows as $row) {
		if (strpos($row, 'Status against revision') !== false) {
			continue;
		}
		$parsed = svn_status_parse_line($row);
		if ($parsed === null) {
			continue;
		}
		list($code, $version, $fullpath) = $parsed;
		$fullpath = str_replace('\\', '/', $fullpath);
		$file_name = basename($fullpath);
		$dir = dirname($fullpath);
		$file_path = ($dir === '.' || $dir === '') ? '' : $dir . '/';
		$files[] = array(
			'kind'         => $code,
			'status'       => isset($maps['labels'][$code]) ? $maps['labels'][$code] : $code,
			'status_tip'   => isset($maps['tips'][$code]) ? $maps['tips'][$code] : '',
			'status_badge' => isset($maps['slugs'][$code]) ? $maps['slugs'][$code] : 'default',
			'version'      => $version,
			'file_name'    => $file_name,
			'file_path'    => $file_path,
			'rel_path'     => $fullpath,
		);
	}
	return $files;
}

/**
 * Short "2d ago" style relative time for a DB datetime string.
 */
function svn_relative_time($raw) {
	$raw = trim((string) $raw);
	if ($raw === '') {
		return '';
	}
	$t = strtotime($raw);
	if ($t === false) {
		return '';
	}
	$diff = time() - $t;
	if ($diff < 0) {
		$diff = 0;
	}
	if ($diff < 60)        return 'just now';
	if ($diff < 3600)      return floor($diff / 60) . 'm ago';
	if ($diff < 86400)     return floor($diff / 3600) . 'h ago';
	if ($diff < 86400 * 7) return floor($diff / 86400) . 'd ago';
	if ($diff < 86400 * 30) return floor($diff / (86400 * 7)) . 'w ago';
	return date('j M Y', $t);
}

function svn_history_truncate_message($s, $max = 200) {
	$s = trim((string) $s);
	if ($s === '') {
		return '';
	}
	if (function_exists('mb_strlen') && function_exists('mb_substr')) {
		if (mb_strlen($s) > $max) {
			return rtrim(mb_substr($s, 0, $max - 1)) . '…';
		}
		return $s;
	}
	if (strlen($s) > $max) {
		return rtrim(substr($s, 0, $max - 3)) . '...';
	}
	return $s;
}
