<?php
/*
	Remote dev-copy configuration — runs ON slayer as the developer (piped in by dev_copy.php
	with the __PLACEHOLDERS__ substituted). Adapts a freshly checked-out site to run under the
	dev URL. Idempotent and self-guarding: every step checks the file/marker exists first, so it
	simply no-ops on older ViArt sites that don't have these files. Originals are backed up to
	*.devbak before the first change.

	Steps:
	  A) config/database/{staging,development}.php  -> the per-dev DB on slayer  (newer framework)
	  A2) includes/var_definition.php               -> the per-dev DB on slayer  (older ViArt)
	  B) includes/common.php dev site_url override  -> real request host over https (fixes assets)
	  C) public_html/.htaccess                      -> enable the DEV friendly-URL block
	  D) public_html/.htaccess                      -> basic-auth the dev copy (/etc/.htpasswd, "sayu-dev")
	  E) public_html/robots.txt                     -> Disallow: / (keep dev copies out of search engines)
*/

$proj   = '__PROJ__';
$login  = '__LOGIN__';
$dbname = '__DBNAME__';

$done = array();

// A) newer-framework DB config -> the dev DB (hosting-db = slayer's local MySQL).
if ($dbname !== '') {
	$dbcfg = "<?php defined('CONFIGURATION_LOADED') or die('Forbidden');\n\n"
		. "defined('DATABASE_CONFIGURATION_LOADED') or die('Forbidden');\n\n"
		. "return [\n    'default' => [\n        'hostname' => 'hosting-db',\n"
		. "        'username' => '" . $login . "',\n        'password' => '',\n"
		. "        'schema' => '" . $dbname . "',\n        'port' => '3306',\n    ],\n];\n";
	foreach (array('staging', 'development') as $env) {
		$df = $proj . '/config/database/' . $env . '.php';
		if (is_file($df)) {
			if (!is_file($df . '.devbak')) @copy($df, $df . '.devbak');
			@file_put_contents($df, $dbcfg);
			$done[] = "db:$env";
		}
	}
}

// A2) older-ViArt DB config (includes/var_definition.php). These sites keep $db_user / $db_password /
//     $db_host / $db_name as plain vars and only switch $db_name on the dev server (the $is_sayu_dev_server
//     flag). Append a guarded override that runs LAST (so it wins over the file's own dev-branch
//     assignment) and points all four at the per-dev DB on slayer: user = the dev login with an empty
//     password (how slayer's local MySQL grants dev DBs). No-ops on the newer framework (no such file /
//     no $is_sayu_dev_server) and on any site where we didn't import a DB.
if ($dbname !== '') {
	$vf = $proj . '/public_html/includes/var_definition.php';
	if (is_file($vf)) {
		$v = file_get_contents($vf);
		if (strpos($v, '$is_sayu_dev_server') !== false && strpos($v, 'dev copy: per-dev DB') === false) {
			if (!is_file($vf . '.devbak')) @copy($vf, $vf . '.devbak');
			$ovr = "\n/* dev copy: per-dev DB */\n"
				. "if (!empty(\$is_sayu_dev_server)) {\n"
				. "    \$db_user = " . var_export($login, true) . ";\n"
				. "    \$db_password = '';\n"
				. "    \$db_host = 'hosting-db';\n"
				. "    \$db_name = " . var_export($dbname, true) . ";\n"
				. "}\n";
			if (preg_match('/\?>\s*$/', $v)) { $v = preg_replace('/\?>\s*$/', $ovr . "?>\n", $v); }
			else { $v = rtrim($v, "\n") . "\n" . $ovr; }
			@file_put_contents($vf, $v);
			$done[] = 'var_definition:db';
		}
	}
}

// B) common.php dev site_url override -> use the real request host over https
//    (otherwise the framework falls back to a hardcoded http://<dev>.sayuconnect.com/<proj>/).
$cf = $proj . '/public_html/includes/common.php';
if (is_file($cf)) {
	$c = file_get_contents($cf);
	$old = '\'http://\' . $sayu_developer_name[1] . \'.sayuconnect.com/\' . $sayu_project_name[1] . \'/\'';
	$new = '\'https://\' . $_SERVER[\'HTTP_HOST\'] . \'/\' . explode(\'/\', ltrim($_SERVER[\'REQUEST_URI\'], \'/\'))[0] . \'/\'';
	if (strpos($c, $old) !== false) {
		if (!is_file($cf . '.devbak')) @copy($cf, $cf . '.devbak');
		@file_put_contents($cf, str_replace($old, $new, $c));
		$done[] = 'common.php:site_url';
	}
}

// C) .htaccess: uncomment the "DEV server friendly URLs" block, comment the live one.
$hf = $proj . '/public_html/.htaccess';
if (is_file($hf)) {
	$lines = file($hf, FILE_IGNORE_NEW_LINES);
	if (is_array($lines)) {
		$dev = false; $changed = false; $out = array();
		foreach ($lines as $ln) {
			if (strpos($ln, '# DEV server friendly URLs') !== false) { $dev = true; $out[] = $ln; continue; }
			if ($dev && trim($ln) === '') { $dev = false; $out[] = $ln; continue; }
			if ($dev && preg_match('/^#(RewriteCond|RewriteRule)/', $ln)) { $out[] = preg_replace('/^#/', '', $ln); $changed = true; continue; }
			if (preg_match('#^RewriteRule \. /friendly_url\.php \[L\]#', $ln)) { $out[] = '#' . $ln; $changed = true; continue; }
			$out[] = $ln;
		}
		if ($changed) {
			if (!is_file($hf . '.devbak')) @copy($hf, $hf . '.devbak');
			@file_put_contents($hf, implode("\n", $out) . "\n");
			$done[] = '.htaccess:friendly-urls';
		}
	}
}

// D) Basic-auth the dev copy, the way the other dev sites do it: a block at the top of .htaccess
//    pointing at /etc/.htpasswd with the global "sayu-dev" login. Prepend it (guarded by a marker)
//    unless an active AuthUserFile is already present, and create .htaccess if the site has none.
$hf = $proj . '/public_html/.htaccess';
$ht = is_file($hf) ? (string) file_get_contents($hf) : '';
$has_active_auth = (bool) preg_match('/^[ \t]*AuthUserFile/mi', $ht);
if (!$has_active_auth && strpos($ht, 'dev copy: basic auth') === false) {
	if (is_file($hf) && !is_file($hf . '.devbak')) @copy($hf, $hf . '.devbak');
	$auth = "# dev copy: basic auth\n"
		. "AuthName \"Restricted Area\"\n"
		. "AuthType Basic\n"
		. "AuthUserFile /etc/.htpasswd\n"
		. "AuthGroupFile /dev/null\n"
		. "require user sayu-dev\n\n";
	@file_put_contents($hf, $auth . $ht);
	$done[] = '.htaccess:basic-auth';
}

// E) robots.txt: disallow everything on the dev copy (belt-and-braces with the basic auth above).
$rf = $proj . '/public_html/robots.txt';
$disallow = "User-agent: *\nDisallow: /\n";
if (is_dir(dirname($rf)) && (!is_file($rf) || trim((string) file_get_contents($rf)) !== trim($disallow))) {
	if (is_file($rf) && !is_file($rf . '.devbak')) @copy($rf, $rf . '.devbak');
	@file_put_contents($rf, $disallow);
	$done[] = 'robots.txt:disallow-all';
}

// F) admin folder(s): make the ViArt admin reachable on the dev copy. The admin .htaccess blocks by
//    GeoIP country (RewriteRule [F=403] guarded by GEOIP_COUNTRY_CODE, which is UNSET on slayer -> every
//    request 403s) and IP-allowlists some sensitive pages (Deny from all / Allow from <office IPs>).
//    Neutralise both here — the dev copy is already gated by sayu-dev basic auth + robots noindex, so the
//    live geo/IP protection isn't needed. Idempotent via a marker; original kept as .devbak.
foreach ((array) glob($proj . '/public_html/*/admin_login.php') as $al) {
	$af = dirname($al) . '/.htaccess';
	if (!is_file($af)) continue;
	$h = (string) file_get_contents($af);
	if (strpos($h, 'dev copy: admin access relaxed') !== false) continue; // already relaxed
	$lines = preg_split('/\r\n|\r|\n/', $h);
	$out = array(); $changed = false;
	foreach ($lines as $ln) {
		$bare = ltrim($ln);
		if ($bare !== '' && $bare[0] === '#') { $out[] = $ln; continue; }               // leave comments
		if (preg_match('/GEOIP_COUNTRY_CODE/i', $ln)                                     // geo RewriteCond
			|| preg_match('/^\s*RewriteRule\b.*\[[^\]]*F(?:=\d+)?[,\]]/i', $ln)          // any [F]/[F=403] forbid rule
			|| preg_match('/^\s*Deny\s+from\s+all\b/i', $ln)) {                          // IP-allowlist deny
			$out[] = '#' . $ln; $changed = true; continue;
		}
		$out[] = $ln;
	}
	if ($changed) {
		if (!is_file($af . '.devbak')) @copy($af, $af . '.devbak');
		@file_put_contents($af, "# dev copy: admin access relaxed\n" . implode("\n", $out) . "\n");
		$done[] = 'admin:' . basename(dirname($al));
	}
}

echo $done ? ('   configured: ' . implode(', ', $done) . "\n") : "   (no framework config needed)\n";
