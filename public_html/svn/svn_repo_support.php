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
	// "svn status -u" prefixes each path with up to a few metadata tokens (after whitespace
	// is collapsed): an optional 1-char status letter, the "*" out-of-date marker, and the
	// working revision. Crucially, a file that was *added in the repo* but isn't in the live
	// working copy yet shows as just "* path" — no status letter and no revision (2 tokens).
	// Consume the leading metadata tokens generically; everything after is the path.
	$parts = explode(' ', $row);
	$n = count($parts);
	if ($n < 2) {
		return null;
	}
	$meta_letters = 'ACDIMRXL?!~'; // svn status column codes
	$status_letter = '';
	$out_of_date = false;
	$rev = '-';
	$i = 0;
	for (; $i < $n; $i++) {
		$tok = $parts[$i];
		if ($tok === '*') { $out_of_date = true; continue; }
		if (ctype_digit($tok)) { $rev = $tok; continue; }
		if (strlen($tok) === 1 && strpos($meta_letters, strtoupper($tok)) !== false) { $status_letter = strtoupper($tok); continue; }
		break; // first non-metadata token starts the path
	}
	if ($i >= $n) {
		return null; // no path
	}
	$fullpath = implode(' ', array_slice($parts, $i));
	// Only keep things that look like files (have a slash or an extension); this drops the
	// bare directory / "." lines svn also emits for out-of-date containers.
	$path_ok = (strpos($fullpath, '/') !== false) || preg_match('/\.[a-z0-9]{1,8}$/i', $fullpath);
	if (!$path_ok) {
		return null;
	}
	$status = $status_letter !== '' ? $status_letter : ($out_of_date ? '*' : '');
	if ($status === '') {
		return null; // nothing actionable on this line
	}
	// A line can be BOTH — e.g. "M * 618": edited on the live site AND a newer revision waiting in
	// SVN. The local letter wins for $status (it describes the file on disk), but the "*" is the
	// thing the updater exists to surface: there is a deploy to do. Carry it separately so it isn't
	// lost, otherwise an M-and-out-of-date file reads as a mere live edit and its update goes unseen.
	return array($status, $rev, $fullpath, $out_of_date);
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
		$out_of_date = !empty($parsed[3]);
		$fullpath = str_replace('\\', '/', $fullpath);
		$file_name = basename($fullpath);
		$dir = dirname($fullpath);
		$file_path = ($dir === '.' || $dir === '') ? '' : $dir . '/';
		$label = isset($maps['labels'][$code]) ? $maps['labels'][$code] : $code;
		$tip   = isset($maps['tips'][$code]) ? $maps['tips'][$code] : '';
		$badge = isset($maps['slugs'][$code]) ? $maps['slugs'][$code] : 'default';
		// out-of-date AND a local change ("M *"): it's deployable, so present it as out of date
		// (the actionable state) while still naming the live edit, and badge it as an incoming
		// change so the "Edited on live" filter never hides a pending deploy.
		if ($out_of_date && $code !== '*') {
			$label = 'Out of date · ' . strtolower($label);
			$tip   = 'A newer version is waiting in SVN — updating deploys it. The live file also has local changes, which the update will merge in.';
			$badge = 'not-on-server';
		}
		$files[] = array(
			'kind'         => $code,
			'incoming'     => ($out_of_date || $code === '*'),
			'status'       => $label,
			'status_tip'   => $tip,
			'status_badge' => $badge,
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

/**
 * Shared LIBRARY repositories (Common8 / Common) — plain PHP libraries, not servable sites.
 * Their repo root is just tags/ + trunk/: there is no public_html, no database and no images,
 * and no client record. Sites pull them in as a SIBLING of the site directory, e.g.
 *   <site>/public_html/includes/../../../Common8/tags/1.1.0/vendor/autoload.php
 * which resolves to /mnt/drive2/vhosts/Common8 live, and to ~/projects/Common8 on slayer
 * (next to the dev site copies). So a "dev copy" of one is ONLY an svn checkout/update into
 * ~/projects/<repo> — no DB import, no images, no vhost alias, no site config rewrite.
 */
function svn_library_repos() {
	return array('Common8', 'Common');
}
function svn_is_library_repo($repo) {
	return in_array((string) $repo, svn_library_repos(), true);
}

// Build a one-click admin auto-login URL for a site (mirrors create_client.php's logic).
// Shared by svn_site_status.php and site_admin.php.
function svn_build_admin_url($web_address, $admin_web_address, $login, $password) {
	$web = str_ireplace(array('http://', 'https://'), '', (string) $web_address);
	$adm = str_ireplace(array('http://', 'https://'), '', (string) $admin_web_address);
	$login = (string) $login;
	if ($adm === '' || $login === '') return '';
	$base = $adm;
	if (stripos($base, 'http') === 0) {
		$base = preg_replace('#^http://#i', 'https://', $base);
	} else {
		$fs = strpos($base, '/');
		if ($fs !== false && strpos(substr($base, 0, $fs), '.') !== false) {
			$base = 'https://' . $base;
		} else {
			$base = 'https://' . rtrim($web, '/') . '/' . ltrim($base, '/');
		}
	}
	return rtrim($base, '/') . '/admin_login.php?operation=login&login=' . urlencode($login) . '&password=' . urlencode((string) $password);
}

// Resolve the Monitor client + admin-login URL for a domain (host matched precisely).
// Returns array('clientId'=>int, 'adminUrl'=>string).
function svn_site_admin(&$db, $repository) {
	$d = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $repository));
	if ($d === '') return array('clientId' => 0, 'adminUrl' => '');
	$db->query("SELECT client_id,web_address,admin_web_address,admin_web_site_login,admin_web_site_password FROM clients_sites WHERE web_address LIKE " . ToSQL('%' . $d . '%', "text") . " ORDER BY client_id LIMIT 50");
	$rows = array();
	while ($db->next_record()) {
		$rows[] = array(
			'cid'   => (int) $db->f("client_id"),
			'wa'    => (string) $db->f("web_address"),
			'awa'   => (string) $db->f("admin_web_address"),
			'login' => (string) $db->f("admin_web_site_login"),
			'pass'  => (string) $db->f("admin_web_site_password"),
		);
	}
	$tail = '.' . $d; $matched = null;
	foreach ($rows as $r) {
		$wa = strtolower(trim($r['wa']));
		$host = parse_url($wa, PHP_URL_HOST);
		if (!$host) { $host = parse_url('http://' . ltrim($wa, '/'), PHP_URL_HOST); }
		$host = strtolower((string) $host);
		$m = ($host === $d || $host === 'www.' . $d || (strlen($host) > strlen($tail) && substr($host, -strlen($tail)) === $tail));
		if (!$m && preg_match('#[/~]' . preg_quote($d, '#') . '(?:/|$)#', $wa)) { $m = true; }
		if ($m) { $matched = $r; break; }
	}
	if (!$matched) return array('clientId' => 0, 'adminUrl' => '');
	$cid = $matched['cid'];
	$url = svn_build_admin_url($matched['wa'], $matched['awa'], $matched['login'], $matched['pass']);
	if ($url === '') {
		// fall back to another site of the same client that has admin credentials
		$db->query("SELECT web_address,admin_web_address,admin_web_site_login,admin_web_site_password FROM clients_sites WHERE client_id=" . $cid . " AND admin_web_address<>'' AND admin_web_site_login<>'' LIMIT 5");
		while ($db->next_record()) {
			$u = svn_build_admin_url($db->f("web_address"), $db->f("admin_web_address"), $db->f("admin_web_site_login"), $db->f("admin_web_site_password"));
			if ($u !== '') { $url = $u; break; }
		}
	}
	return array('clientId' => $cid, 'adminUrl' => $url);
}

/**
 * Which PHP version the LIVE site actually runs on, e.g. "8.1" (or '' if it can't be told).
 *
 * The authoritative answer is which FPM pool the webserver hands .php files to. The mere
 * EXISTENCE of a socket proves nothing — most sites have pools provisioned for both 5.6 and
 * 8.1 — and neither does the presence of a version string in the vhost: watches.co.uk carries
 * its old 5.6 SetHandler commented out above the live 8.1 one. So only lines that are actually
 * in force count, hence the "no # before the directive" match.
 *
 * web1 serves with Apache (SetHandler "proxy:unix:/run/php/php8.1-<site>-fpm.sock|…"); the
 * dedicated boxes serve with nginx (fastcgi_pass unix:/run/php/phpX.Y-…) — except rubberduck,
 * where nginx proxies to a local Apache, so no PHP version appears in its nginx conf at all.
 * There the per-box CLI version is the honest answer (these boxes run a single PHP).
 *
 * Best-effort by design: anything unreadable or ambiguous yields '' and callers carry on.
 */
function svn_live_php_version($repo) {
	if ($repo === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $repo) || strpos($repo, '..') !== false) return '';
	// Uncommented handler lines only: optional leading whitespace, then the directive itself.
	$re_apache = '^[[:space:]]*SetHandler[^#]*php[0-9]+\\.[0-9]+';
	$re_nginx  = '^[[:space:]]*fastcgi_pass[^#]*php[0-9]+\\.[0-9]+';
	$host = svn_host_for($repo);   // non-null => off-web1
	$out  = array();
	if ($host) {
		$rq = escapeshellarg($repo);
		$remote = 'sudo grep -rhE ' . escapeshellarg($re_nginx) . ' /etc/nginx/sites-enabled/ 2>/dev/null;'
			. ' sudo grep -rhE ' . escapeshellarg($re_apache) . ' /etc/apache2/sites-enabled/' . $rq . '* 2>/dev/null';
		@exec(svn_host_ssh($host) . ' ' . escapeshellarg($remote) . ' 2>/dev/null', $out);
		if (!svn_php_version_from_lines($out)) {
			// nginx→Apache proxy (rubberduck): nothing names a version. Ask the box itself.
			$out = array();
			@exec(svn_host_ssh($host) . ' ' . escapeshellarg('php -v 2>/dev/null | head -1') . ' 2>/dev/null', $out);
			if ($out && preg_match('/PHP\s+([0-9]+\.[0-9]+)/', implode(' ', $out), $m)) return $m[1];
			return '';
		}
	} else {
		// The site's own vhosts only — never the whole tree, or a neighbour would answer for it.
		@exec('grep -rhE ' . escapeshellarg($re_apache) . ' /etc/apache2/sites-enabled/' . escapeshellarg($repo) . '-*.conf 2>/dev/null', $out);
	}
	return svn_php_version_from_lines($out);
}

/** Highest phpX.Y named across already-filtered handler lines, or '' if none. */
function svn_php_version_from_lines($lines) {
	$best = '';
	foreach ((array) $lines as $line) {
		if (!preg_match_all('/php([0-9]+\.[0-9]+)/', $line, $mm)) continue;
		foreach ($mm[1] as $v) { if ($best === '' || version_compare($v, $best, '>')) $best = $v; }
	}
	return $best;
}
