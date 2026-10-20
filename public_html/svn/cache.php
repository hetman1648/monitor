<?php
/*
	AJAX (JSON): a site's nginx proxy cache — state, and a flush through the server's own
	flush-cache.sh (the same thing done by hand with `sudo flush-cache.sh`).

	Only off-web1 sites (svn_hosts.php) are covered; nginx caching lives on those boxes (web2 today:
	richdiamonds.com, tressoro.com). The cache zones are read from the server's nginx config — every
	proxy_cache_path / fastcgi_cache_path whose directory is named after the site — so a zone added to
	nginx.conf shows up here with nothing to change.

	flush-cache.sh is not per-site: on web2 it stops nginx, deletes /var/cache/nginx/dynamic/<site> for
	every site it lists and starts nginx again (~5s of downtime for all of the box's sites). The script
	is read and parsed so the popup can say exactly that, and a flush is refused for a site the script
	does not clear.

	@param repository   the domain (repo name)
	@param action       status (default) | flush

	-> { ok, host, nginx:'active', zones:[{path,zone,max_size,inactive,bytes,files,oldest,newest}],
	     script:{path, stops_nginx, sites:[...], covers}, flushed:{rc, output} }
*/

$root_inc_path = "../";
include ("../includes/common.php");
include ("./auth.php");
include ("./svn_hosts.php");

header('Content-Type: application/json; charset=utf-8');
function nc_json($a) { echo json_encode($a); exit; }

$repository = trim((string) GetParam("repository"));
if ($repository === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $repository) || strpos($repository, '..') !== false) {
	nc_json(array('ok' => false, 'error' => 'Invalid repository.'));
}
$action = (GetParam("action") === 'flush') ? 'flush' : 'status';
session_write_close();

$host = svn_host_for($repository);
if (!$host) {
	nc_json(array('ok' => true, 'host' => 'web1', 'zones' => array(), 'script' => null,
		'note' => $repository . ' is served from web1, which has no nginx cache.'));
}

// Runs as root on the site's box ($1 = mode, $2 = site). Output is one tab-separated record per line:
//   NGINX <state> | ZONE <path> <zone> <max_size> <inactive> <bytes> <files> <oldest> <newest>
//   SCRIPT <path> <base64 of the script> | RC <exit code> | OUT <line of flush output>
$remote = <<<'SH'
mode="$1"; r="$2"
case "$r" in ''|*[!A-Za-z0-9.-]*) echo "bad site" >&2; exit 2 ;; esac
s="$(PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin command -v flush-cache.sh)"
if [ "$mode" = flush ]; then
  [ -n "$s" ] || { echo "RC	127"; exit 0; }
  grep -qE "(^|[[:space:]/])${r//./\\.}([[:space:]/]|\$)" "$s" || { echo "RC	-2"; exit 0; }
  out="$(timeout 120 "$s" 2>&1)"; rc=$?
  echo "RC	$rc"
  printf '%s\n' "$out" | tail -n 50 | sed 's/^/OUT	/'
fi
echo "NGINX	$(systemctl is-active nginx 2>/dev/null)"
grep -rhE '^[[:space:]]*(proxy|fastcgi)_cache_path[[:space:]]' /etc/nginx 2>/dev/null |
awk -v r="$r" '{ p=$2; if (substr(p, length(p)-length(r)) != "/" r) next; z=""; m=""; i="";
  for (k=3; k<=NF; k++) { n=$k; sub(/=.*/, "", n); v=$k; sub(/^[^=]*=/, "", v); sub(/;$/, "", v);
    if (n=="keys_zone") z=v; else if (n=="max_size") m=v; else if (n=="inactive") i=v }
  print p "\t" z "\t" m "\t" i }' | sort -u |
while IFS=$'\t' read -r p z m i; do
  if [ -d "$p" ]; then
    b="$(timeout 20 du -sb "$p" 2>/dev/null | cut -f1)"
    st="$(timeout 20 find "$p" -type f -printf '%T@\n' 2>/dev/null | awk 'NR==1{mn=$1; mx=$1} {if($1<mn)mn=$1; if($1>mx)mx=$1; c++} END{printf "%d\t%d\t%d", c, mn, mx}')"
  else b=-1; st="0	0	0"; fi
  echo "ZONE	$p	$z	$m	$i	${b:--1}	$st"
done
[ -n "$s" ] && echo "SCRIPT	$s	$(base64 -w0 "$s")"
exit 0
SH;

$cmd = 'sudo -n bash -c ' . escapeshellarg($remote) . ' _ ' . escapeshellarg($action) . ' ' . escapeshellarg($repository);
$out = array(); $rc = 0;
exec(svn_host_ssh($host) . ' ' . escapeshellarg($cmd) . ' 2>&1', $out, $rc);

$res = array('ok' => true, 'host' => $host['ssh_host'], 'nginx' => '', 'zones' => array(), 'script' => null);
$flush = null; $junk = array();
foreach ($out as $line) {
	$p = explode("\t", $line);
	switch ($p[0]) {
		case 'NGINX': $res['nginx'] = isset($p[1]) ? $p[1] : ''; break;
		case 'ZONE':
			if (count($p) < 9) break;
			$res['zones'][] = array('path' => $p[1], 'zone' => $p[2], 'max_size' => $p[3], 'inactive' => $p[4],
				'bytes' => (int) $p[5], 'files' => (int) $p[6], 'oldest' => (int) $p[7], 'newest' => (int) $p[8]);
			break;
		case 'SCRIPT':
			$res['script'] = nc_parse_script(isset($p[1]) ? $p[1] : '', base64_decode(isset($p[2]) ? $p[2] : ''), $repository);
			break;
		case 'RC':  $flush = array('rc' => (int) $p[1], 'output' => ''); break;
		case 'OUT': if ($flush) $flush['output'] .= substr($line, 4) . "\n"; break;
		default: $junk[] = $line;
	}
}
if ($flush && $flush['rc'] === 127) nc_json(array('ok' => false, 'error' => 'There is no flush-cache.sh on ' . $host['ssh_host'] . '.'));
if ($flush && $flush['rc'] === -2)  nc_json(array('ok' => false, 'error' => 'flush-cache.sh on ' . $host['ssh_host'] . ' does not clear ' . $repository . ' — nothing was run.'));
if ($rc !== 0 || $res['nginx'] === '') {
	nc_json(array('ok' => false, 'error' => 'Could not read the cache on ' . $host['ssh_host'] . '.',
		'detail' => ensure_utf8(trim(implode("\n", array_slice($junk, -5))))));
}
if ($action === 'flush') {
	if (!$flush) nc_json(array('ok' => false, 'error' => 'The flush did not run.'));
	$flush['output'] = ensure_utf8(trim($flush['output']));
	$res['flushed'] = $flush;
	error_log('svn cache.php: ' . $host['ssh_host'] . ' flush-cache.sh for ' . $repository . ' by user ' . GetSessionParam("UserID") . ' rc=' . $flush['rc']);
	nc_mail_flushed($repository, $host['ssh_host'], $res, $current_user_first_name);
}
nc_json($res);

// Tell Artem and Vlad whenever a flush has been run from here — it takes nginx down for the whole box.
function nc_mail_flushed($repository, $host, $res, $who) {
	$f = $res['flushed']; $sc = $res['script'];
	$others = $sc ? array_values(array_diff($sc['sites'], array($repository))) : array();
	$txt  = ($who !== '' ? $who : 'User ' . GetSessionParam("UserID")) . ' flushed the nginx cache for ' . $repository
		. ' on ' . $host . ' at ' . date('Y-m-d H:i:s') . " from the SVN updater.\n\n";
	$txt .= 'flush-cache.sh exit code: ' . $f['rc'] . ($f['rc'] === 0 ? ' (ok)' : ' (FAILED)') . "\n";
	$txt .= 'nginx after the flush: ' . $res['nginx'] . "\n";
	if ($sc && $sc['stops_nginx']) $txt .= 'The script stops nginx, so every site on ' . $host . " was down for a few seconds.\n";
	if ($others) $txt .= 'Also cleared: ' . implode(', ', $others) . "\n";
	if ($f['output'] !== '') $txt .= "\nOutput:\n" . $f['output'] . "\n";
	$headers  = "From: noreply@sayu.co.uk\r\n";
	$headers .= "Reply-To: noreply@sayu.co.uk\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
	@mail('artem.birzul@gmail.com, vlad@sayu.co.uk',
		'nginx cache flushed: ' . $repository . ' (' . $host . ')' . ($f['rc'] === 0 ? '' : ' - FAILED'), $txt, $headers);
}

// What flush-cache.sh actually clears: the "cd <dir>" it runs in plus each "rm -rf <name>" after it
// (full paths are taken as they are). Whether it stops nginx to do it.
function nc_parse_script($path, $src, $repository) {
	$dir = ''; $paths = array(); $sites = array();
	foreach (preg_split('/\r?\n/', (string) $src) as $l) {
		$l = trim(preg_replace('/(^|\s)#.*$/', '', $l));
		if (preg_match('/^cd\s+(\S+)/', $l, $m)) $dir = rtrim($m[1], '/');
		elseif (preg_match('/^rm\s+(?:-\w+\s+)*(.+)$/', $l, $m)) {
			foreach (preg_split('/\s+/', $m[1]) as $t) {
				$t = trim($t, "\"'");
				if ($t === '' || $t[0] === '-') continue;
				$full = ($t[0] === '/') ? $t : $dir . '/' . $t;
				$paths[] = $full;
				$sites[] = basename(rtrim($full, '/*'));
			}
		}
	}
	$sites = array_values(array_unique($sites));
	return array(
		'path'        => $path,
		'stops_nginx' => (bool) preg_match('/\b(systemctl|service)\s+(stop\s+nginx|nginx\s+stop|restart\s+nginx)/', $src),
		'sites'       => $sites,
		'paths'       => $paths,
		'covers'      => in_array($repository, $sites, true),
	);
}
