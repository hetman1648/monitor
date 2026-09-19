<?php
/*
	AJAX (JSON): status of an existing dev copy on slayer for the current developer.

	One SSH round-trip (as the dev's slayer login) gathers, best-effort:
	  - files   : svn revision + last-changed date, and the working-copy dir mtime (last checkout/update)
	  - images  : whether the images tree exists, how many files and bytes it holds, when it was
	              last written, and whether that amounts to a real copy of the live tree - or,
	              when it is a bind mount of the shared image store, that it is a mount (which
	              is left unmeasured: walking one takes minutes, see svn_images_probe_sh)
	  - database: the dev DB name (read back from the checked-out site's own config), its size,
	              CREATE_TIME (import) and UPDATE_TIME (last write), and table count

	Everything is guarded — a missing dev copy just yields exists=0; missing pieces come back empty.

	@param repository
	Returns { ok, exists, dev_url, rev, changed, files_mtime, img_present, img_mount, img_count,
	          img_bytes, img_src_bytes, img_copied, img_mtime, db_name, db_bytes, db_create,
	          db_update, db_tables }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include_once ("./svn_backups_support.php");
include_once ("./svn_repo_support.php");
include_once ("./svn_hosts.php");

// Nothing below writes to the session, and everything below is slow (ssh round-trips to slayer
// and to the site's own host). PHP's file session handler holds an exclusive lock for the life
// of the request, so holding it here serialises this endpoint against every other AJAX call the
// popup makes - dev_copy_images.php would sit in exec() while this one sat in session_start(),
// each waiting out the other's ssh. Let go of the lock now; $_SESSION stays readable.
session_write_close();

header("Content-Type: application/json");
function dcinfo_json($a) { echo json_encode($a); exit; }

$SSH_KEY     = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/id_ed25519";
$SSH_KNOWN   = "/mnt/drive2/vhosts/monitor.sayu.co.uk/.ssh/known_hosts";
$SLAYER_HOST = "slayer.sayu.co.uk";
$SLAYER_PORT = "2222";

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $repository) || strpos($repository, '..') !== false) {
	dcinfo_json(array("ok" => false, "error" => "Invalid repository."));
}

$uid = (int) GetSessionParam("UserID");
$login = ''; $subdomain = '';
$db->query("SELECT svn_login, svn_subdomain FROM users WHERE user_id=" . $uid);
if ($db->next_record()) { $login = trim($db->f("svn_login")); $subdomain = trim((string) $db->f("svn_subdomain")); }
if ($login === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $login)) {
	dcinfo_json(array("ok" => false, "error" => "Your Developer Settings are incomplete."));
}

// The admin folder + auto-login query for this site (from clients_sites, matching svn_site_status.php's
// host-matching). Returned so the popup can build a dev-site admin link: the folder path is the same in
// the dev checkout as live, and the imported DB carries the same admin credentials.
function dcinfo_admin_parts(&$db, $repository) {
	$d = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $repository));
	if ($d === '') return array('', '');
	$db->query("SELECT web_address,admin_web_address,admin_web_site_login,admin_web_site_password FROM clients_sites WHERE web_address LIKE " . ToSQL('%' . $d . '%', "text") . " ORDER BY client_id LIMIT 50");
	$rows = array();
	while ($db->next_record()) {
		$rows[] = array('wa' => (string) $db->f("web_address"), 'awa' => (string) $db->f("admin_web_address"),
			'login' => (string) $db->f("admin_web_site_login"), 'pass' => (string) $db->f("admin_web_site_password"));
	}
	$tail = '.' . $d; $matched = null;
	foreach ($rows as $r) {
		$wa = strtolower(trim($r['wa']));
		$host = parse_url($wa, PHP_URL_HOST); if (!$host) { $host = parse_url('http://' . ltrim($wa, '/'), PHP_URL_HOST); }
		$host = strtolower((string) $host);
		$m = ($host === $d || $host === 'www.' . $d || (strlen($host) > strlen($tail) && substr($host, -strlen($tail)) === $tail));
		if (!$m && preg_match('#[/~]' . preg_quote($d, '#') . '(?:/|$)#', $wa)) { $m = true; }
		if ($m) { $matched = $r; break; }
	}
	if (!$matched || $matched['awa'] === '' || $matched['login'] === '') return array('', '');
	// Reduce admin_web_address to the folder path under the site root (drop any leading host).
	$adm = trim(str_ireplace(array('http://', 'https://'), '', $matched['awa']), '/');
	$fs = strpos($adm, '/');
	if ($fs !== false && strpos(substr($adm, 0, $fs), '.') !== false) { $adm = substr($adm, $fs + 1); }
	else if ($fs === false && strpos($adm, '.') !== false) { $adm = ''; }  // bare host, no folder
	$adm = trim($adm, '/');
	if ($adm === '') return array('', '');
	$query = '?operation=login&login=' . urlencode($matched['login']) . '&password=' . urlencode((string) $matched['pass']);
	return array($adm, $query);
}
list($admin_path, $admin_query) = dcinfo_admin_parts($db, $repository);

$proj = "/home/staff/" . $login . "/projects/" . $repository;

// Images: mount-aware and time-bounded (see svn_images_probe_sh) - the tree is often a bind
// mount of the shared image store, where a plain find/du runs for minutes and kills the request.
$img_probe = svn_images_probe_sh(escapeshellarg($proj . "/public_html/images"));

// Remote status script — emits key=value lines. $proj is built from a validated login + repository.
// Base64-encoded and decoded on the far side so the nested sed/mysql quoting survives ssh intact.
$script = <<<SH
P="{$proj}"
if [ -d "\$P/public_html" ]; then echo "exists=1"; else echo "exists=0"; fi
if [ -d "\$P/.svn" ]; then svn info "\$P" 2>/dev/null | sed -n "s/^Revision: /rev=/p; s/^Last Changed Date: /changed=/p"; fi
echo "files_mtime=\$(stat -c %Y "\$P" 2>/dev/null)"
{$img_probe}
DBN=""
for f in "\$P/config/database/development.php" "\$P/config/database/staging.php"; do
  if [ -f "\$f" ]; then DBN=\$(sed -n "s/.*'schema' *=> *'\\([^']*\\)'.*/\\1/p" "\$f" | head -1); [ -n "\$DBN" ] && break; fi
done
if [ -z "\$DBN" ] && [ -f "\$P/public_html/includes/var_definition.php" ]; then
  DBN=\$(sed -n "s/.*db_name *= *'\\([^']*\\)'.*/\\1/p" "\$P/public_html/includes/var_definition.php" | tail -1)
fi
if echo "\$DBN" | grep -qE '^[A-Za-z0-9_]+\$'; then
  echo "db_name=\$DBN"
  sudo mysql -N -e "SELECT CONCAT('db_bytes=',COALESCE(SUM(data_length+index_length),0)),CONCAT('db_tables=',COUNT(*)),CONCAT('db_create=',IFNULL(MAX(create_time),'')),CONCAT('db_update=',IFNULL(MAX(update_time),'')) FROM information_schema.tables WHERE table_schema='\$DBN'" 2>/dev/null | tr '\\t' '\\n'
fi
SH;

$remote = "echo " . base64_encode($script) . " | base64 -d | bash";

$SLAYER = "ssh -i " . escapeshellarg($SSH_KEY) . " -p " . (int) $SLAYER_PORT
	. " -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=yes -o UserKnownHostsFile=" . escapeshellarg($SSH_KNOWN)
	. " " . escapeshellarg($login . "@" . $SLAYER_HOST);

$out = array();
@exec($SLAYER . " " . escapeshellarg($remote) . " 2>/dev/null", $out);

$kv = array();
foreach ($out as $line) {
	$p = strpos($line, '=');
	if ($p === false) continue;
	$kv[substr($line, 0, $p)] = trim(substr($line, $p + 1));
}

$g = function ($k) use ($kv) { return isset($kv[$k]) ? $kv[$k] : ''; };
$exists = ($g('exists') === '1');

// Whether the images were really copied, which a file count on its own cannot answer: SVN
// versions a handful of files under public_html/images (a .htaccess, a placeholder), so a
// checkout with no copy at all still reports a non-empty folder. Weighing the tree against
// the live one is what separates "the copy ran" from "this is just what the checkout brought".
// Stays null when the live size is unknown, and the popup then makes no claim either way.
// A bind mount of the shared store answers the question outright - the dev copy IS the live
// tree - and is deliberately left unmeasured, so neither size is available to compare.
$img_mount = ($g('img_mount') === '1');
$img_bytes = (ctype_digit($g('img_bytes')) ? (int) $g('img_bytes') : -1);
$img_src_bytes = $img_mount ? -1 : svn_site_images_bytes($repository);
$img_copied = $img_mount ? true : null;
if (!$img_mount && $img_bytes >= 0 && $img_src_bytes > 0) { $img_copied = ($img_bytes >= (int) ($img_src_bytes / 2)); }

$dev_url = ($subdomain !== '') ? ('https://' . $subdomain . '.sayuconnect.com/' . $repository . '/') : '';

// Which PHP the LIVE site runs on, so the popup can pre-tick "PHP 8 site" instead of making the
// developer know (and remember) it. '' = couldn't tell, and the box is left as the user set it.
$live_php = svn_live_php_version($repository);

dcinfo_json(array(
	"ok"          => true,
	"exists"      => $exists,
	"dev_url"     => $dev_url,
	"live_php"    => $live_php,
	"live_php8"   => ($live_php !== '' && version_compare($live_php, '8.0', '>=')),
	"admin_path"  => $admin_path,
	"admin_query" => $admin_query,
	"rev"         => $g('rev'),
	"changed"     => $g('changed'),
	"files_mtime" => ($g('files_mtime') !== '' ? (int) $g('files_mtime') : 0),
	"img_present"   => ($g('img_present') === '1'),
	"img_mount"     => $img_mount,
	"img_count"     => ($g('img_count') !== '' ? (int) $g('img_count') : -1),
	"img_bytes"     => $img_bytes,
	"img_src_bytes" => $img_src_bytes,
	"img_copied"    => $img_copied,
	"img_mtime"     => ($g('img_mtime') !== '' ? (int) $g('img_mtime') : 0),
	"db_name"     => $g('db_name'),
	"db_bytes"    => ($g('db_bytes') !== '' ? (float) $g('db_bytes') : -1),
	"db_create"   => $g('db_create'),
	"db_update"   => $g('db_update'),
	"db_tables"   => ($g('db_tables') !== '' ? (int) $g('db_tables') : -1),
));
