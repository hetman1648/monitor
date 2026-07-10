<?php
/*
	Client File API — POST /client_file_api/place
	================================================
	Lets an external caller (Sayu Copilot) publish static assets (images / js / styles / csv / …)
	into a client website's web root, on whichever server actually serves that site. Copilot can't
	resolve "domain -> web root -> which physical server", but Monitor can (svn/svn_hosts.php), so
	that resolution is the job of this endpoint.

	Contract (what Copilot sends and depends on):
	    POST {CLIENT_FILE_API_URL}/place
	    Authorization: Bearer {CLIENT_FILE_API_TOKEN}
	    {
	      "domain":  "puregusto.co.uk",
	      "db_name": "puregusto",                 // accepted, never used (files only)
	      "kind":    "content-assets",             // or "microsite" — selects the validation ruleset
	      "subpath": "content-assets/1722",       // relative to the client web root
	      "mode":    "pull",                       // only "pull" is supported
	      "purge":   true,                         // wipe the subpath first (idempotent re-publish)
	      "files":   [ { "name": "x.js", "url": "https://copilot.sayu.co.uk/media/imported/1722/x.js" }, … ]
	    }

	Microsite mode (kind:"microsite") — standalone micro-pages at a web-root path
	(e.g. https://www.puregusto.co.uk/coffee-report/). Differs from the default only in:
	  - subpath is a SINGLE web-root slug (^[a-z0-9][a-z0-9-]{0,63}$), depth 1, no slashes/dots;
	    reserved names (admin, images, includes, … — anything platform-critical) are rejected.
	  - .html/.htm/.webmanifest are additionally allowed (still forbidden in content-assets mode).
	  - a .sayu-microsite marker file is dropped in the directory on create; a target directory
	    that already exists WITHOUT that marker is never touched (422) — so purge/re-publish is
	    idempotent for our own dirs but can't clobber a pre-existing web-root folder.
	Contract doc on the Copilot side: docs/client-file-placement-api.md → "Microsite mode".
	Returns:
	    { "ok": true,
	      "base_url": "https://www.puregusto.co.uk/content-assets/1722/",   // the REAL public URL (REQUIRED)
	      "placed": [ {name,bytes,url}… ], "failed": [ {name,error}… ] }

	Placement:
	  - web1-hosted sites (the default)  -> written locally as the site's unix user via monitor_runas.
	  - sites mapped in svn/svn_hosts.php -> written on their own server over SSH (monitor's key, tema+sudo).

	Safety (see validation below): bearer token (constant-time) + optional IP allowlist; subpath
	allowlist content-assets/<digits> (or, in microsite mode, a single reserved-name-checked slug);
	basename + extension allowlist; fetch host pinned to copilot.sayu.co.uk with no redirects (SSRF
	guard); per-file and total size caps; purge only ever deletes inside the validated subpath (and,
	for microsites, only marker-bearing dirs); the client database is never touched.
*/

// -------- config + shared helpers/auth --------
require_once dirname(__FILE__) . '/cfa_common.php';

$CFA_FETCH_HOST   = 'copilot.sayu.co.uk';   // the ONLY host we will fetch from
$CFA_MAX_FILE     = 30 * 1024 * 1024;       // 30 MB per file
$CFA_MAX_TOTAL    = 300 * 1024 * 1024;      // 300 MB per request
$CFA_MAX_FILES    = 300;
$CFA_EXT_ALLOW    = array(
	'css','js','mjs','map','json','geojson','csv','txt','xml',
	'svg','png','jpg','jpeg','gif','webp','avif','ico','bmp',
	'woff','woff2','ttf','otf','eot','pdf',
);
// Markup types a standalone micro-page needs — allowed in microsite mode ONLY.
$CFA_MS_EXT_EXTRA = array('html','htm','webmanifest');
// Web-root slugs a microsite must never shadow: platform/app dirs (ViArt layout: admin, images,
// includes, js, payments, templates, …) plus the generic web-critical names from the contract.
// Defence in depth — a pre-existing directory is refused anyway via the .sayu-microsite marker.
$CFA_MS_RESERVED  = array(
	'content-assets','admin','administrator','cgi-bin','includes','system','var','vendor',
	'wp-admin','wp-content','wp-includes','api','assets','images','img','css','js','fonts',
	'media','uploads','files','attachments','downloads','download','blocks','classes','db',
	'dist','editor','messages','payments','previews','shipping','styles','swf','templates',
	'ckeditor','tinymce','fancybox','widgets','scripts','secure','stats','cache','temp','tmp',
	'kbase','manual','install','forum','blog','help','search','login','logout','error',
);

// -------- 1) method + auth --------
if (cfa_g($_SERVER,'REQUEST_METHOD','GET') !== 'POST') cfa_fail('POST only.', 405);
cfa_require_auth();

// -------- 2) parse + validate the request --------
$raw = file_get_contents('php://input');
if (strlen($raw) > 2 * 1024 * 1024) cfa_fail('Request too large.', 413); // the JSON itself, not the files
$req = json_decode($raw, true);
if (!is_array($req)) cfa_fail('Invalid JSON body.');

$domain  = strtolower(trim((string)cfa_g($req,'domain','')));
$kind    = strtolower(trim((string)cfa_g($req,'kind','content-assets')));
$subpath = trim((string)cfa_g($req,'subpath',''));
$mode    = strtolower(trim((string)cfa_g($req,'mode','pull')));
$purge   = !empty($req['purge']);
$files   = cfa_g($req,'files',array());

if ($mode !== 'pull') cfa_fail('Unsupported mode (only "pull").');
if ($kind !== 'content-assets' && $kind !== 'microsite') cfa_fail('Unsupported kind (only "content-assets" or "microsite").');
$microsite = ($kind === 'microsite');

// domain: a bare hostname, no path/scheme/traversal.
if ($domain === '' || strlen($domain) > 253 || !preg_match('/^[a-z0-9.-]+$/', $domain)
	|| strpos($domain, '..') !== false || $domain[0] === '.' || $domain[0] === '-') {
	cfa_fail('Invalid domain.');
}

if ($microsite) {
	// microsite subpath: ONE safe web-root slug — no slash, dot, .., backslash or NUL possible by
	// construction of the pattern, so the target is a depth-1 child of the web root.
	if (!preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $subpath)) {
		cfa_fail('Invalid subpath (microsite: a single lower-case slug, e.g. "coffee-report").');
	}
	if (in_array($subpath, $GLOBALS['CFA_MS_RESERVED'], true)) {
		cfa_fail('Reserved subpath: ' . $subpath, 422);
	}
} else {
	// subpath: content-assets/<digits> ONLY. No leading slash, no .., no backslashes.
	if (!preg_match('#^content-assets/[0-9]+$#', $subpath)) {
		cfa_fail('Invalid subpath (allowed: content-assets/<number>).');
	}
}

if (!is_array($files) || !count($files)) cfa_fail('No files given.');
if (count($files) > $GLOBALS['CFA_MAX_FILES']) cfa_fail('Too many files (max ' . $GLOBALS['CFA_MAX_FILES'] . ').');

// Validate every file entry up front (basename, extension, fetch-host) before touching anything.
$ext_allow = $microsite
	? array_merge($GLOBALS['CFA_EXT_ALLOW'], $GLOBALS['CFA_MS_EXT_EXTRA'])
	: $GLOBALS['CFA_EXT_ALLOW'];
$clean = array();
foreach ($files as $f) {
	$name = trim((string)cfa_g($f,'name',''));
	$url  = trim((string)cfa_g($f,'url',''));
	if ($name === '' || $url === '') cfa_fail('Each file needs a name and a url.');
	// name = basename only, conservative charset, must have an allowed extension.
	if ($name !== basename($name) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) || strpos($name, '..') !== false) {
		cfa_fail('Bad file name: ' . $name);
	}
	$dot = strrpos($name, '.');
	$ext = $dot === false ? '' : strtolower(substr($name, $dot + 1));
	if (!in_array($ext, $ext_allow, true)) cfa_fail('Disallowed file type: ' . $name);
	// url: https on the pinned host only (SSRF guard). We also forbid redirects when fetching.
	$pu = parse_url($url);
	if (!$pu || strtolower(cfa_g($pu,'scheme','')) !== 'https'
		|| strcasecmp(cfa_g($pu,'host',''), $GLOBALS['CFA_FETCH_HOST']) !== 0) {
		cfa_fail('File url must be https on ' . $GLOBALS['CFA_FETCH_HOST'] . ': ' . $name);
	}
	$clean[] = array('name' => $name, 'url' => $url);
}

// -------- 3) resolve where this site lives --------
$host = svn_host_for($domain);   // null => default (web1, local); else a remote server config
if ($host) {
	$webroot = rtrim($host['wc_base'], '/') . '/' . $domain . '/public_html';
} else {
	$webroot = '/home/vhosts/' . $domain . '/public_html';
	if (!is_dir($webroot)) cfa_fail('Unknown site (no web root on web1, not in the host map): ' . $domain, 404);
}

// -------- 4) fetch all files into a local, world-readable staging dir --------
$stage = sys_get_temp_dir() . '/cfa-' . bin2hex(openssl_random_pseudo_bytes(8));
if (!@mkdir($stage, 0755, true)) cfa_fail('Could not create staging directory.', 500);
@chmod($stage, 0755);

$placed = array(); $failed = array(); $total = 0;
foreach ($clean as $f) {
	$dest = $stage . '/' . $f['name'];
	$fh = @fopen($dest, 'wb');
	if (!$fh) { $failed[] = array('name' => $f['name'], 'error' => 'staging open failed'); continue; }
	$ch = curl_init($f['url']);
	curl_setopt_array($ch, array(
		CURLOPT_FILE           => $fh,
		CURLOPT_FOLLOWLOCATION => false,            // no redirects -> can't be bounced to an internal host
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_MAXFILESIZE    => $GLOBALS['CFA_MAX_FILE'],
		CURLOPT_FAILONERROR    => true,             // treat 4xx/5xx as an error
		CURLOPT_USERAGENT      => 'monitor-client-file-api/1.0',
	));
	$ok   = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$err  = curl_error($ch);
	curl_close($ch);
	fclose($fh);

	$size = @filesize($dest);
	if ($ok === false) { @unlink($dest); $failed[] = array('name' => $f['name'], 'error' => 'fetch failed (' . ($err ?: ('HTTP ' . $code)) . ')'); continue; }
	if ($size === false || $size === 0) { @unlink($dest); $failed[] = array('name' => $f['name'], 'error' => 'empty file'); continue; }
	if ($size > $GLOBALS['CFA_MAX_FILE']) { @unlink($dest); $failed[] = array('name' => $f['name'], 'error' => 'over per-file size cap'); continue; }
	if ($total + $size > $GLOBALS['CFA_MAX_TOTAL']) { @unlink($dest); $failed[] = array('name' => $f['name'], 'error' => 'over total size cap'); continue; }
	@chmod($dest, 0644);
	$total += $size;
	$placed[] = array('name' => $f['name'], 'bytes' => $size, 'url' => $f['url']);
}

// Don't purge a live folder if we have nothing to put back.
if (!count($placed)) {
	cfa_rmtree($stage);
	cfa_fail('No files could be fetched; nothing placed (left existing content untouched).', 502,
		array('failed' => $failed));
}

// -------- 5) place the staged files into the web root (local or remote) --------
$placeErr = '';
if ($host) {
	$placeErr = cfa_place_remote($host, $webroot, $subpath, $stage, $placed, $purge, $microsite);
} else {
	$placeErr = cfa_place_local($webroot, $subpath, $stage, $placed, $purge, $microsite);
}
cfa_rmtree($stage);
if ($placeErr !== '') {
	// The placement script refused a web-root dir that exists but wasn't created by us.
	if (strpos($placeErr, 'CFA_NOT_MICROSITE') !== false) {
		cfa_fail('target exists and is not a Sayu microsite: ' . $subpath, 422, array('failed' => $failed));
	}
	cfa_fail('Placement failed: ' . $placeErr, 500, array('failed' => $failed));
}
error_log('client_file_api/place: ' . $kind . ' ' . $domain . '/' . $subpath
	. ' placed=' . count($placed) . ' failed=' . count($failed)
	. ' purge=' . ($purge ? '1' : '0') . ' from ' . cfa_g($_SERVER, 'REMOTE_ADDR', '?'));

// -------- 6) the public base URL (the one field Copilot truly depends on) --------
// If the site's server declares a public_base (it's served via something other than its own domain,
// e.g. web2's userdir), build the URL from that pattern. Otherwise follow the domain's live redirect.
if ($host && !empty($host['public_base'])) {
	$base_url = rtrim(str_replace('{repo}', $domain, $host['public_base']), '/') . '/' . $subpath . '/';
} else {
	$pubhost = cfa_public_host($domain);
	$base_url = 'https://' . $pubhost . '/' . $subpath . '/';
}

cfa_out(array(
	'ok'       => true,
	'base_url' => $base_url,
	'placed'   => $placed,
	'failed'   => $failed,
));

// ============================ placement backends ============================

// Build a bash snippet that purges (optional) then copies the named files from $stage into
// $webroot/$subpath. Shared by both backends; every value is single-quote escaped for the shell.
// $microsite: the target is a web-root dir — refuse one that exists without our .sayu-microsite
// marker (a symlink counts as foreign too), and (re)create the marker after placing.
function cfa_place_script($webroot, $subpath, $stage, $placed, $purge, $do_chown, $microsite = false) {
	$q = function ($s) { return "'" . str_replace("'", "'\\''", $s) . "'"; };
	$s  = "set -e\n";
	$s .= "WR=" . $q($webroot) . "\n";
	$s .= "T=\"\$WR/" . $subpath . "\"\n";                 // $subpath is already validated (content-assets/<digits> or a microsite slug)
	$s .= "STAGE=" . $q($stage) . "\n";
	// Re-assert the target stays under the web root (defence in depth; PHP already validated).
	$s .= "case \"\$T\" in \"\$WR/\"*) : ;; *) echo 'path escape'; exit 9;; esac\n";
	if ($microsite) {
		$s .= "if [ -L \"\$T\" ]; then echo CFA_NOT_MICROSITE; exit 8; fi\n";
		$s .= "if [ -e \"\$T\" ] && [ ! -f \"\$T/.sayu-microsite\" ]; then echo CFA_NOT_MICROSITE; exit 8; fi\n";
	}
	if ($purge) $s .= "rm -rf -- \"\$T\"\n";
	$s .= "mkdir -p -- \"\$T\"\n";
	foreach ($placed as $p) {
		$n = $q($p['name']);
		$s .= "cp -f -- \"\$STAGE\"/" . $n . " \"\$T\"/" . $n . "\n";
		$s .= "chmod 644 \"\$T\"/" . $n . "\n";
	}
	if ($microsite) {
		$s .= "touch \"\$T\"/.sayu-microsite\n";
		$s .= "chmod 644 \"\$T\"/.sayu-microsite\n";
	}
	$s .= "chmod 755 \"\$T\"\n";
	if ($do_chown) {
		// Remote backend runs as root via sudo, so hand the tree back to the site's own user.
		$s .= "OWN=\$(stat -c %U \"\$WR\")\n";
		$s .= "chown -R \"\$OWN\":\"\$OWN\" \"\$T\"\n";
	}
	$s .= "echo CFA_OK\n";
	return $s;
}

// web1-local placement: run the script as the site's unix user via the monitor_runas sudo wrapper
// (the same wrapper the SVN cron tool uses). Files end up owned by the site user automatically.
function cfa_place_local($webroot, $subpath, $stage, $placed, $purge, $microsite = false) {
	if (!is_dir($webroot)) return 'web root not found: ' . $webroot;
	$owner = '';
	if (function_exists('posix_getpwuid')) {
		$pw = @posix_getpwuid(@fileowner($webroot));
		$owner = $pw ? $pw['name'] : '';
	}
	if ($owner === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $owner)) return 'could not determine site user for ' . $webroot;

	$script = cfa_place_script($webroot, $subpath, $stage, $placed, $purge, false, $microsite);
	$cmd = 'sudo -n /usr/local/bin/monitor_runas ' . escapeshellarg($owner);
	$descr = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$p = proc_open($cmd, $descr, $pipes);
	if (!is_resource($p)) return 'could not start monitor_runas';
	fwrite($pipes[0], $script); fclose($pipes[0]);
	$out = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$er  = stream_get_contents($pipes[2]); fclose($pipes[2]);
	$rc  = proc_close($p);
	if ($rc !== 0 || strpos($out, 'CFA_OK') === false) {
		return 'runas rc=' . $rc . ' ' . trim($out . ' ' . $er);
	}
	return '';
}

// Off-web1 placement: rsync the staged files to a temp dir on the site's server, then run the
// placement script there as root (tema has passwordless sudo) and chown the result to the site user.
function cfa_place_remote($host, $webroot, $subpath, $stage, $placed, $purge, $microsite = false) {
	$key   = '/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/id_ed25519';
	$known = '/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/known_hosts';
	$sshopts = '-i ' . escapeshellarg($key)
		. ' -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes'
		. ' -o UserKnownHostsFile=' . escapeshellarg($known);
	$target = escapeshellarg($host['ssh_user'] . '@' . $host['ssh_host']);
	$rtmp   = '/tmp/cfa-' . bin2hex(openssl_random_pseudo_bytes(8));

	// 1) push the staged files (only the ones we fetched) to a fresh remote temp dir.
	$rc = 0; $o = array();
	$rsync = 'rsync -rt --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r --delete'
		. ' -e ' . escapeshellarg('ssh ' . $sshopts)
		. ' ' . escapeshellarg(rtrim($stage, '/') . '/')
		. ' ' . escapeshellarg($host['ssh_user'] . '@' . $host['ssh_host'] . ':' . $rtmp . '/');
	@exec($rsync . ' 2>&1', $o, $rc);
	if ($rc !== 0) return 'rsync rc=' . $rc . ' ' . trim(implode(' ', $o));

	// 2) place on the remote server as root (STAGE = the remote temp dir), chown to the site user,
	//    then clean the temp up.
	$script  = cfa_place_script($webroot, $subpath, $rtmp, $placed, $purge, true, $microsite);
	$remote  = 'sudo bash -c ' . escapeshellarg($script) . '; rc=$?; rm -rf -- ' . escapeshellarg($rtmp) . '; exit $rc';

	$o2 = array(); $rc2 = 0;
	@exec('ssh ' . $sshopts . ' ' . $target . ' ' . escapeshellarg($remote) . ' 2>&1', $o2, $rc2);
	$out = implode("\n", $o2);
	if ($rc2 !== 0 || strpos($out, 'CFA_OK') === false) return 'remote rc=' . $rc2 . ' ' . trim($out);
	return '';
}

// The real public host for the site: follow the live redirect from https://<domain>/ and use the
// final host (handles apex<->www canonicalisation per the site's own config). Falls back to the
// domain as given if the probe fails.
function cfa_public_host($domain) {
	$ch = curl_init('https://' . $domain . '/');
	curl_setopt_array($ch, array(
		CURLOPT_NOBODY         => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 5,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_TIMEOUT        => 12,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_USERAGENT      => 'monitor-client-file-api/1.0',
	));
	curl_exec($ch);
	$eff = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	$h = $eff !== '' ? parse_url($eff, PHP_URL_HOST) : '';
	return ($h && preg_match('/^[A-Za-z0-9.-]+$/', $h)) ? strtolower($h) : $domain;
}
