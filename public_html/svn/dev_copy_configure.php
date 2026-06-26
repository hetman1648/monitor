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

echo $done ? ('   configured: ' . implode(', ', $done) . "\n") : "   (no framework config needed)\n";
